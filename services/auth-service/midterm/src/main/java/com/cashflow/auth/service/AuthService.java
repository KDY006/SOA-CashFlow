package com.cashflow.auth.service;

import com.cashflow.auth.model.User;
import com.cashflow.auth.repository.UserRepository;
import com.cashflow.auth.util.PasswordUtil;

import java.sql.SQLIntegrityConstraintViolationException;
import java.util.regex.Pattern;

public class AuthService {

    // username chỉ cho chữ, số, dấu chấm, gạch dưới (3-50 ký tự, khớp VARCHAR(50))
    private static final Pattern USERNAME_PATTERN =
            Pattern.compile("^[A-Za-z0-9._]{3,50}$");

    private static final Pattern EMAIL_PATTERN =
            Pattern.compile("^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$");

    private static final Pattern PHONE_PATTERN =
            Pattern.compile("^\\+?[0-9]{9,15}$");

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

        if (!USERNAME_PATTERN.matcher(username).matches()) {
            throw new IllegalArgumentException(
                    "Username must be 3-50 characters (letters, digits, '.', '_')"
            );
        }

        if (email == null || email.isBlank()) {
            throw new IllegalArgumentException("Email is required");
        }

        // email lưu chữ thường để tránh 2 tài khoản A@x.com và a@x.com
        email = email.toLowerCase();

        if (email.length() > 100 || !EMAIL_PATTERN.matcher(email).matches()) {
            throw new IllegalArgumentException("Email is invalid");
        }

        if (password == null || password.length() < 6) {
            throw new IllegalArgumentException(
                    "Password must contain at least 6 characters"
            );
        }

        if (password.length() > 100) {
            throw new IllegalArgumentException(
                    "Password must not exceed 100 characters"
            );
        }

        if (fullName != null && fullName.isBlank()) {
            fullName = null;
        }

        if (fullName != null && fullName.length() > 100) {
            throw new IllegalArgumentException(
                    "Full name must not exceed 100 characters"
            );
        }

        if (phoneNumber != null && phoneNumber.isBlank()) {
            phoneNumber = null;
        }

        if (phoneNumber != null && !PHONE_PATTERN.matcher(phoneNumber).matches()) {
            throw new IllegalArgumentException("Phone number is invalid");
        }

        if (userRepository.existsByUsername(username)) {
            throw new IllegalStateException(
                    "Username already exists"
            );
        }

        if (userRepository.existsByEmail(email)) {
            throw new IllegalStateException(
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

        long id;
        try {
            id = userRepository.create(user);
        } catch (SQLIntegrityConstraintViolationException e) {
            // 2 request đăng ký cùng lúc đều qua được bước kiểm tra ở trên,
            // request sau sẽ dính unique key của mysql -> báo trùng chứ không phải lỗi 500
            throw new IllegalStateException(
                    "Username or email already exists"
            );
        }

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
        user = userRepository.findByEmail(username.toLowerCase());
    }

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