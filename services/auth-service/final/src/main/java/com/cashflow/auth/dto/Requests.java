package com.cashflow.auth.dto;

import com.fasterxml.jackson.annotation.JsonAlias;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Pattern;
import jakarta.validation.constraints.Size;

public final class Requests {
    private static final String USERNAME = "[A-Za-z0-9._]{3,50}";
    private static final String EMAIL = "[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}";
    private static final String PHONE = "\\s*|\\+?[0-9]{9,15}";

    private Requests() {}

    public record Register(
            @NotBlank @Pattern(regexp = USERNAME) String username,
            @NotBlank @Size(max = 100) @Pattern(regexp = EMAIL) String email,
            @NotBlank @Size(min = 6, max = 100) String password,
            @Size(max = 100) @JsonAlias("full_name") String fullName,
            @Pattern(regexp = PHONE) @JsonAlias("phone_number") String phoneNumber) {
        public Register {
            username = trim(username);
            email = trim(email);
            password = trim(password);
            fullName = trim(fullName);
            phoneNumber = trim(phoneNumber);
        }
    }

    public record Login(String username, String password) {
        public Login {
            username = trim(username);
        }
    }

    public record Refresh(@NotBlank(message = "Refresh token is required") @Size(max = 255)
                          @JsonAlias("refresh_token") String refreshToken) {
        public Refresh {
            refreshToken = trim(refreshToken);
        }
    }

    public record Profile(@Size(max = 100) String fullName, @Pattern(regexp = PHONE) String phoneNumber) {}

    public record PasswordChange(@NotBlank String currentPassword,
                                 @NotBlank @Size(min = 6, max = 100) String newPassword) {}

    public record AdminCreate(
            @NotBlank @Pattern(regexp = USERNAME) String username,
            @NotBlank @Size(max = 100) @Pattern(regexp = EMAIL) String email,
            @NotBlank @Size(min = 6, max = 100) String password,
            @Size(max = 100) String fullName,
            @Pattern(regexp = PHONE) String phoneNumber,
            String role, String status) {}

    public record AdminUpdate(
            @NotBlank @Pattern(regexp = USERNAME) String username,
            @NotBlank @Size(max = 100) @Pattern(regexp = EMAIL) String email,
            @Size(max = 100) String fullName,
            @Pattern(regexp = PHONE) String phoneNumber,
            String role, String status) {}

    private static String trim(String value) {
        return value == null ? null : value.trim();
    }
}
