package com.cashflow.auth.exception;

import com.cashflow.auth.repository.UserRepository;
import com.cashflow.auth.security.JwtAuthenticationFilter;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.junit.jupiter.api.Test;
import org.springframework.dao.DataIntegrityViolationException;
import org.springframework.dao.DataAccessResourceFailureException;
import org.springframework.http.HttpStatus;
import org.springframework.test.web.servlet.setup.MockMvcBuilders;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RestController;

import java.sql.SQLException;

import org.springframework.mock.web.MockFilterChain;
import org.springframework.mock.web.MockHttpServletRequest;
import org.springframework.mock.web.MockHttpServletResponse;
import org.springframework.security.oauth2.jwt.Jwt;
import org.springframework.security.oauth2.jwt.JwtDecoder;
import org.springframework.transaction.CannotCreateTransactionException;

import java.util.Map;

import static org.assertj.core.api.Assertions.assertThat;
import static org.mockito.Mockito.*;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

class ApiErrorsTest {
    @Test
    void databaseErrorsDoNotExposeSqlDetails() throws Exception {
        Runnable query = mock(Runnable.class);
        doThrow(new DataIntegrityViolationException("private details", new SQLException("duplicate", "23000", 1062)))
                .doThrow(new DataIntegrityViolationException("private details", new SQLException("foreign key", "23000", 1452)))
                .doThrow(new DataAccessResourceFailureException("private connection details"))
                .when(query).run();
        var mvc = MockMvcBuilders.standaloneSetup(new TestController(query))
                .setControllerAdvice(new ApiErrors()).build();

        mvc.perform(get("/query")).andExpect(status().is(HttpStatus.CONFLICT.value()))
                .andExpect(jsonPath("$.message").value("Username or email already exists"));
        mvc.perform(get("/query")).andExpect(status().isConflict())
                .andExpect(jsonPath("$.message").value("Database constraint violation"));
        mvc.perform(get("/query")).andExpect(status().isServiceUnavailable())
                .andExpect(jsonPath("$.message").value("Database temporarily unavailable"));
    }

    @Test
    void databaseUnavailableDuringBearerAuthenticationReturns503() throws Exception {
        JwtDecoder decoder = mock(JwtDecoder.class);
        UserRepository users = mock(UserRepository.class);
        Jwt jwt = mock(Jwt.class);
        when(decoder.decode("test-token")).thenReturn(jwt);
        when(jwt.getClaims()).thenReturn(Map.of("userId", 42L));
        when(users.findById(42L)).thenThrow(new CannotCreateTransactionException("private connection details"));

        var filter = new JwtAuthenticationFilter(decoder, users, new ObjectMapper(), request -> false);
        var request = new MockHttpServletRequest("GET", "/api/profile");
        request.addHeader("Authorization", "Bearer test-token");
        var response = new MockHttpServletResponse();
        filter.doFilter(request, response, new MockFilterChain());

        assertThat(response.getStatus()).isEqualTo(503);
        assertThat(response.getContentAsString()).contains("Database temporarily unavailable")
                .doesNotContain("private connection details");
    }

    @RestController
    static class TestController {
        private final Runnable query;

        TestController(Runnable query) { this.query = query; }

        @GetMapping("/query")
        void run() { query.run(); }
    }
}
