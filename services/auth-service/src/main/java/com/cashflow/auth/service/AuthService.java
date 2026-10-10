package com.cashflow.auth.service;

import com.cashflow.auth.model.User;
import com.cashflow.auth.repository.UserRepository;
import com.cashflow.auth.util.PasswordUtil;

public class AuthService {

    private final UserRepository userRepository;

    public AuthService() {
        this.userRepository = new UserRepository();
    }

    public User register(
            String username,
            String email,
            String password,
            String fullName,
            String phoneNumber
    ) throws Exception {

        if (username == null || username.isBlank()) {
            throw new IllegalArgumentException("Username is required");
        }

        if (email == null || email.isBlank()) {
            throw new IllegalArgumentException("Email is required");
        }

        if (password == null || password.length() < 6) {
            throw new IllegalArgumentException(
                    "Password must contain at least 6 characters"
            );
        }

        if (userRepository.existsByUsername(username)) {
            throw new IllegalArgumentException(
                    "Username already exists"
            );
        }

        if (userRepository.existsByEmail(email)) {
            throw new IllegalArgumentException(
                    "Email already exists"
            );
        }

        User user = new User();

        user.setUsername(username);
        user.setEmail(email);
        user.setPasswordHash(
                PasswordUtil.hashPassword(password)
        );
        user.setFullName(fullName);
        user.setPhoneNumber(phoneNumber);
        user.setRole("USER");
        user.setStatus("ACTIVE");

        long id = userRepository.create(user);

        user.setId(id);

        return user;
    }

    public User login(
        String username,
        String password
) throws Exception {

    if (username == null || username.isBlank()) {
        throw new IllegalArgumentException(
                "Username is required"
        );
    }

    if (password == null || password.isBlank()) {
        throw new IllegalArgumentException(
                "Password is required"
        );
    }

    User user =
            userRepository.findByUsername(username);

    if (user == null) {
        throw new IllegalArgumentException(
                "Invalid username or password"
        );
    }

    if (!PasswordUtil.verifyPassword(
            password,
            user.getPasswordHash()
    )) {
        throw new IllegalArgumentException(
                "Invalid username or password"
        );
    }

    if (!"ACTIVE".equals(user.getStatus())) {
        throw new IllegalArgumentException(
                "Account is not active"
        );
    }

    return user;
}
}