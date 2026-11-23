package com.cashflow.auth.service;

import com.cashflow.auth.dto.Requests;
import com.cashflow.auth.dto.UserView;
import com.cashflow.auth.entity.User;
import com.cashflow.auth.exception.ApiException;
import com.cashflow.auth.repository.RefreshTokenRepository;
import com.cashflow.auth.repository.UserRepository;
import org.springframework.data.domain.Sort;
import org.springframework.http.HttpStatus;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;
import java.util.Locale;
import java.util.Optional;

@Service
public class UserService {
    private final UserRepository users;
    private final RefreshTokenRepository tokens;
    private final PasswordEncoder passwords;

    public UserService(UserRepository users, RefreshTokenRepository tokens, PasswordEncoder passwords) {
        this.users = users;
        this.tokens = tokens;
        this.passwords = passwords;
    }

    @Transactional(readOnly = true)
    public List<UserView.Detailed> list() {
        return users.findAll(Sort.by("id")).stream().map(UserView.Detailed::from).toList();
    }

    @Transactional(readOnly = true)
    public UserView.Detailed get(long id) {
        requireValidId(id);
        return users.findById(id).map(UserView.Detailed::from).orElseThrow(UserService::notFound);
    }

    @Transactional
    public UserView.Detailed create(Requests.AdminCreate request) {
        String role = role(request.role());
        String status = status(request.status());
        String email = request.email().toLowerCase(Locale.ROOT);
        requireUnique(users.findByUsername(request.username()), users.findByEmail(email), null);
        User user = new User(request.username(), email, passwords.encode(request.password()),
                emptyToNull(request.fullName()), emptyToNull(request.phoneNumber()), role, status);
        return UserView.Detailed.from(users.saveAndFlush(user));
    }

    @Transactional
    public UserView.Detailed update(long id, Requests.AdminUpdate request) {
        User user = lockedUser(id);
        String role = role(request.role());
        String status = status(request.status());
        String email = request.email().toLowerCase(Locale.ROOT);
        requireUnique(users.findByUsername(request.username()), users.findByEmail(email), id);
        user.update(request.username(), email, emptyToNull(request.fullName()),
                emptyToNull(request.phoneNumber()), role, status);
        if (!user.isActive()) {
            tokens.revokeForUser(id);
        }
        return UserView.Detailed.from(users.saveAndFlush(user));
    }

    @Transactional
    public void delete(long id) {
        users.delete(lockedUser(id));
        users.flush();
    }

    @Transactional
    public UserView.Detailed updateProfile(long id, Requests.Profile request) {
        User user = lockedActiveUser(id);
        user.updateProfile(emptyToNull(request.fullName()), emptyToNull(request.phoneNumber()));
        return UserView.Detailed.from(users.saveAndFlush(user));
    }

    @Transactional
    public void changePassword(long id, Requests.PasswordChange request) {
        User user = lockedActiveUser(id);
        if (!passwords.matches(request.currentPassword(), user.getPasswordHash())) {
            throw new ApiException(HttpStatus.BAD_REQUEST, "Current password is incorrect");
        }
        if (passwords.matches(request.newPassword(), user.getPasswordHash())) {
            throw new ApiException(HttpStatus.BAD_REQUEST, "New password must be different from current password");
        }
        user.changePassword(passwords.encode(request.newPassword()));
        tokens.revokeForUser(id);
    }

    private User lockedUser(long id) {
        requireValidId(id);
        return users.findForUpdate(id).orElseThrow(UserService::notFound);
    }

    private User lockedActiveUser(long id) {
        User user = lockedUser(id);
        if (!user.isActive()) {
            throw new ApiException(HttpStatus.UNAUTHORIZED, "Account is not active");
        }
        return user;
    }

    static void requireUnique(Optional<User> usernameOwner, Optional<User> emailOwner, Long currentId) {
        if (usernameOwner.filter(user -> !user.getId().equals(currentId)).isPresent()) {
            throw new ApiException(HttpStatus.CONFLICT, "Username already exists");
        }
        if (emailOwner.filter(user -> !user.getId().equals(currentId)).isPresent()) {
            throw new ApiException(HttpStatus.CONFLICT, "Email already exists");
        }
    }

    static String emptyToNull(String value) {
        return value == null || value.isBlank() ? null : value;
    }

    private static void requireValidId(long id) {
        if (id <= 0) {
            throw new ApiException(HttpStatus.BAD_REQUEST, "Invalid user id");
        }
    }

    private static ApiException notFound() {
        return new ApiException(HttpStatus.BAD_REQUEST, "User not found");
    }

    private static String role(String value) {
        String role = emptyToNull(value) == null ? "USER" : value.toUpperCase(Locale.ROOT);
        if (!"USER".equals(role) && !"ADMIN".equals(role)) {
            throw new ApiException(HttpStatus.BAD_REQUEST, "Role must be USER or ADMIN");
        }
        return role;
    }

    private static String status(String value) {
        String status = emptyToNull(value) == null ? "ACTIVE" : value.toUpperCase(Locale.ROOT);
        if (!"ACTIVE".equals(status) && !"BLOCKED".equals(status)) {
            throw new ApiException(HttpStatus.BAD_REQUEST, "Status must be ACTIVE or BLOCKED");
        }
        return status;
    }
}
