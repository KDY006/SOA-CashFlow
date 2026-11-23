package com.cashflow.auth.security;

import com.cashflow.auth.entity.User;
import com.cashflow.auth.exception.ApiErrors.Failure;
import com.cashflow.auth.repository.UserRepository;
import com.fasterxml.jackson.databind.ObjectMapper;
import jakarta.servlet.FilterChain;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.springframework.dao.DataAccessException;
import org.springframework.http.HttpHeaders;
import org.springframework.http.MediaType;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.oauth2.jwt.Jwt;
import org.springframework.security.oauth2.jwt.JwtDecoder;
import org.springframework.security.oauth2.jwt.JwtException;
import org.springframework.security.web.util.matcher.RequestMatcher;
import org.springframework.transaction.CannotCreateTransactionException;
import org.springframework.web.filter.OncePerRequestFilter;

import java.io.IOException;
import java.util.List;
import java.util.regex.Pattern;

public class JwtAuthenticationFilter extends OncePerRequestFilter {
    private static final Pattern BEARER = Pattern.compile("^Bearer +([^ ]+)$", Pattern.CASE_INSENSITIVE);
    private final JwtDecoder decoder;
    private final UserRepository users;
    private final ObjectMapper json;
    private final RequestMatcher publicRoutes;

    public JwtAuthenticationFilter(JwtDecoder decoder, UserRepository users, ObjectMapper json,
                                   RequestMatcher publicRoutes) {
        this.decoder = decoder;
        this.users = users;
        this.json = json;
        this.publicRoutes = publicRoutes;
    }

    @Override
    protected boolean shouldNotFilter(HttpServletRequest request) {
        return publicRoutes.matches(request);
    }

    @Override
    protected void doFilterInternal(HttpServletRequest request, HttpServletResponse response, FilterChain chain)
            throws ServletException, IOException {
        String header = request.getHeader(HttpHeaders.AUTHORIZATION);
        if (header == null) {
            chain.doFilter(request, response);
            return;
        }
        var match = BEARER.matcher(header.trim());
        if (!match.matches()) {
            writeFailure(response, HttpServletResponse.SC_UNAUTHORIZED, "Authorization token is required");
            return;
        }

        Jwt jwt;
        try {
            jwt = decoder.decode(match.group(1));
        } catch (JwtException ex) {
            writeFailure(response, HttpServletResponse.SC_UNAUTHORIZED, "Invalid or expired token");
            return;
        }

        User user;
        try {
            user = users.findById(((Number) jwt.getClaims().get("userId")).longValue()).orElse(null);
        } catch (DataAccessException | CannotCreateTransactionException ex) {
            writeFailure(response, HttpServletResponse.SC_SERVICE_UNAVAILABLE, "Database temporarily unavailable");
            return;
        }
        if (user == null || !user.isActive() || !user.getRole().equals(jwt.getClaimAsString("role"))) {
            writeFailure(response, HttpServletResponse.SC_UNAUTHORIZED, "Invalid or expired token");
            return;
        }

        var principal = new AccountPrincipal(user.getId(), user.getUsername(), user.getRole(), user.getStatus());
        var authentication = UsernamePasswordAuthenticationToken.authenticated(
                principal, null, List.of(new SimpleGrantedAuthority("ROLE_" + principal.role())));
        var context = SecurityContextHolder.createEmptyContext();
        context.setAuthentication(authentication);
        SecurityContextHolder.setContext(context);
        chain.doFilter(request, response);
    }

    public void writeFailure(HttpServletResponse response, int status, String message) throws IOException {
        response.setStatus(status);
        response.setContentType(MediaType.APPLICATION_JSON_VALUE);
        if (status == HttpServletResponse.SC_UNAUTHORIZED) {
            response.setHeader(HttpHeaders.WWW_AUTHENTICATE, "Bearer");
        }
        json.writeValue(response.getOutputStream(), new Failure(message));
    }
}
