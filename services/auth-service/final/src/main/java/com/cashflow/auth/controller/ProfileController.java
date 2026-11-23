package com.cashflow.auth.controller;

import com.cashflow.auth.dto.Requests;
import com.cashflow.auth.security.AccountPrincipal;
import com.cashflow.auth.service.UserService;
import jakarta.validation.Valid;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.web.bind.annotation.*;

import java.util.Map;

@RestController
@RequestMapping("/api/profile")
public class ProfileController {
    private final UserService users;

    public ProfileController(UserService users) {
        this.users = users;
    }

    @GetMapping
    Map<String, Object> get(@AuthenticationPrincipal AccountPrincipal principal) {
        return Map.of("success", true, "profile", users.get(principal.id()));
    }

    @PutMapping
    Map<String, Object> update(@AuthenticationPrincipal AccountPrincipal principal,
                               @Valid @RequestBody Requests.Profile request) {
        return Map.of("success", true, "message", "Profile updated successfully",
                "profile", users.updateProfile(principal.id(), request));
    }

    @PutMapping("/password")
    Map<String, Object> changePassword(@AuthenticationPrincipal AccountPrincipal principal,
                                       @Valid @RequestBody Requests.PasswordChange request) {
        users.changePassword(principal.id(), request);
        return Map.of("success", true, "message", "Password changed successfully");
    }
}
