package com.cashflow.auth.service;

import com.cashflow.auth.dto.Requests;
import com.cashflow.auth.dto.UserView;
import com.cashflow.auth.entity.RefreshToken;
import com.cashflow.auth.entity.User;
import com.cashflow.auth.exception.ApiException;
import com.cashflow.auth.repository.RefreshTokenRepository;
import com.cashflow.auth.repository.UserRepository;
import com.cashflow.auth.security.AccessTokens;
import org.springframework.http.HttpStatus;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Isolation;
import org.springframework.transaction.annotation.Transactional;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.security.SecureRandom;
import java.sql.Timestamp;
import java.time.Duration;
import java.time.Instant;
import java.util.Base64;
import java.util.HexFormat;
import java.util.Locale;

@Service
public class AuthService {
    private static final Duration REFRESH_LIFETIME = Duration.ofDays(7);
    private static final String INVALID_REFRESH = "Invalid or expired refresh token";
    private final UserRepository users;
    private final RefreshTokenRepository tokens;
    private final PasswordEncoder passwords;
    private final AccessTokens accessTokens;
    private final SecureRandom random = new SecureRandom();
    private final String dummyHash;

    public AuthService(UserRepository users, RefreshTokenRepository tokens,
                       PasswordEncoder passwords, AccessTokens accessTokens) {
        this.users = users;
        this.tokens = tokens;
        this.passwords = passwords;
        this.accessTokens = accessTokens;
        this.dummyHash = passwords.encode(generateRefreshToken());
    }

    @Transactional
    public UserView register(Requests.Register request) {
        String email = request.email().toLowerCase(Locale.ROOT);
        UserService.requireUnique(users.findByUsername(request.username()), users.findByEmail(email), null);
        User user = new User(request.username(), email, passwords.encode(request.password()),
                UserService.emptyToNull(request.fullName()), UserService.emptyToNull(request.phoneNumber()),
                "USER", "ACTIVE");
        return UserView.from(users.saveAndFlush(user));
    }

    public record LoginResult(String accessToken, String refreshToken, UserView user) {}

    @Transactional
    public LoginResult login(Requests.Login request) {
        if (request.username() == null || request.username().isBlank()) {
            throw new ApiException(HttpStatus.UNAUTHORIZED, "Username is required");
        }
        if (request.password() == null || request.password().isBlank()) {
            throw new ApiException(HttpStatus.UNAUTHORIZED, "Password is required");
        }
        User user = users.findIdByUsername(request.username())
                .or(() -> users.findIdByEmail(request.username().toLowerCase(Locale.ROOT)))
                .flatMap(users::findForUpdate)
                .orElse(null);
        String password = request.password();
        String passwordHash = user == null ? dummyHash : user.getPasswordHash();
        boolean validPassword = passwords.matches(password, passwordHash)
                || (!password.equals(password.trim()) && passwords.matches(password.trim(), passwordHash));
        if (user == null || !validPassword) {
            throw new ApiException(HttpStatus.UNAUTHORIZED, "Invalid username or password");
        }
        if (!user.isActive()) {
            throw new ApiException(HttpStatus.UNAUTHORIZED, "Account is not active");
        }

        String refresh = generateRefreshToken();
        tokens.revokeForUser(user.getId());
        tokens.save(new RefreshToken(user, digest(refresh), Timestamp.from(Instant.now().plus(REFRESH_LIFETIME))));
        return new LoginResult(accessTokens.issue(user), refresh, UserView.from(user));
    }

    @Transactional(isolation = Isolation.READ_COMMITTED)
    public String refresh(String rawToken) {
        String storedValue = digest(rawToken);
        var ownerId = tokens.findOwnerId(storedValue);
        if (ownerId.isEmpty() && rawToken.matches("[A-Za-z0-9_-]{64}")) {
            // Midterm stored the 64-character opaque token directly.
            storedValue = rawToken;
            ownerId = tokens.findOwnerId(storedValue);
        }

        User user = ownerId.flatMap(users::findForUpdate)
                .orElseThrow(() -> new ApiException(HttpStatus.UNAUTHORIZED, INVALID_REFRESH));
        // Re-read after locking the user so a concurrent login or password change cannot revive a revoked token.
        RefreshToken token = tokens.findByToken(storedValue)
                .orElseThrow(() -> new ApiException(HttpStatus.UNAUTHORIZED, INVALID_REFRESH));
        if (!MessageDigest.isEqual(token.getToken().getBytes(StandardCharsets.UTF_8),
                storedValue.getBytes(StandardCharsets.UTF_8))
                || !user.getId().equals(token.getOwnerId())
                || !token.getExpiryDate().toInstant().isAfter(Instant.now())) {
            throw new ApiException(HttpStatus.UNAUTHORIZED, INVALID_REFRESH);
        }
        if (!user.isActive()) {
            throw new ApiException(HttpStatus.UNAUTHORIZED, "User is not active");
        }
        return accessTokens.issue(user);
    }

    private String generateRefreshToken() {
        byte[] bytes = new byte[48];
        random.nextBytes(bytes);
        return Base64.getUrlEncoder().withoutPadding().encodeToString(bytes);
    }

    private String digest(String rawToken) {
        try {
            byte[] hash = MessageDigest.getInstance("SHA-256").digest(rawToken.getBytes(StandardCharsets.UTF_8));
            return "sha256:" + HexFormat.of().formatHex(hash);
        } catch (NoSuchAlgorithmException ex) {
            throw new IllegalStateException("SHA-256 is unavailable", ex);
        }
    }
}
