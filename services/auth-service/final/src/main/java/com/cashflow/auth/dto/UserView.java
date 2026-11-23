package com.cashflow.auth.dto;

import com.cashflow.auth.entity.User;
import com.fasterxml.jackson.annotation.JsonUnwrapped;

public record UserView(long id, String username, String email, String fullName,
                       String phoneNumber, String role, String status) {
    public static UserView from(User user) {
        return new UserView(user.getId(), user.getUsername(), user.getEmail(), user.getFullName(),
                user.getPhoneNumber(), user.getRole(), user.getStatus());
    }

    public record Detailed(@JsonUnwrapped UserView user, Long createdAt, Long updatedAt) {
        public static Detailed from(User user) {
            return new Detailed(UserView.from(user),
                    user.getCreatedAt() == null ? null : user.getCreatedAt().getTime(),
                    user.getUpdatedAt() == null ? null : user.getUpdatedAt().getTime());
        }
    }
}
