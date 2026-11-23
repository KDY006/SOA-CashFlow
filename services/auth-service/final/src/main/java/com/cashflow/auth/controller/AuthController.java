package com.cashflow.auth.controller;

import com.cashflow.auth.dto.Requests;
import com.cashflow.auth.security.AccessTokens;
import com.cashflow.auth.security.AccountPrincipal;
import com.cashflow.auth.service.AuthService;
import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.web.bind.annotation.*;

import java.util.Map;

@RestController
@RequestMapping("/api/auth")
public class AuthController {
    private final AuthService auth;

    public AuthController(AuthService auth) {
        this.auth = auth;
    }

    @PostMapping("/register")
    ResponseEntity<Map<String, Object>> register(@Valid @RequestBody Requests.Register request) {
        return ResponseEntity.status(HttpStatus.CREATED).body(Map.of(
                "success", true, "message", "User registered successfully", "user", auth.register(request)));
    }

    @PostMapping("/login")
    Map<String, Object> login(@RequestBody Requests.Login request) {
        AuthService.LoginResult result = auth.login(request);
        return Map.of("success", true, "message", "Login successful", "accessToken", result.accessToken(),
                "refreshToken", result.refreshToken(), "tokenType", "Bearer", "expiresIn", AccessTokens.EXPIRES_IN,
                "user", result.user());
    }

    @GetMapping("/validate")
    Map<String, Object> validate(@AuthenticationPrincipal AccountPrincipal principal) {
        return Map.of("success", true, "valid", true, "userId", principal.id(), "username", principal.username(),
                "role", principal.role(), "status", principal.status());
    }

    @PostMapping("/refresh")
    Map<String, Object> refresh(@Valid @RequestBody Requests.Refresh request) {
        return Map.of("success", true, "message", "Access token refreshed successfully",
                "accessToken", auth.refresh(request.refreshToken()), "tokenType", "Bearer",
                "expiresIn", AccessTokens.EXPIRES_IN);
    }
}
