package com.cashflow.auth.config;

import com.nimbusds.jose.jwk.source.ImmutableSecret;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.core.env.Environment;
import org.springframework.core.env.Profiles;
import org.springframework.security.oauth2.core.OAuth2Error;
import org.springframework.security.oauth2.core.OAuth2TokenValidatorResult;
import org.springframework.security.oauth2.jwt.JwtDecoder;
import org.springframework.security.oauth2.jwt.JwtEncoder;
import org.springframework.security.oauth2.jwt.JwtTimestampValidator;
import org.springframework.security.oauth2.jwt.NimbusJwtDecoder;
import org.springframework.security.oauth2.jwt.NimbusJwtEncoder;

import javax.crypto.SecretKey;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.time.Duration;
import java.time.Instant;

@Configuration
public class JwtConfig {
    @Bean
    SecretKey jwtKey(Environment environment) {
        String secret = environment.getProperty("JWT_SECRET", "");
        if (secret.isBlank()) {
            secret = environment.getProperty("AUTH_JWT_SECRET", "");
        }
        byte[] bytes = secret.getBytes(StandardCharsets.UTF_8);
        if (secret.isBlank() || bytes.length < 32) {
            throw new IllegalStateException("JWT_SECRET (or AUTH_JWT_SECRET) requires at least 32 UTF-8 bytes");
        }
        if (environment.acceptsProfiles(Profiles.of("prod"))
                && "cashflow-auth-midterm-development-secret-key".equals(secret)) {
            throw new IllegalStateException("Configure a dedicated JWT secret for the prod profile");
        }
        return new SecretKeySpec(bytes, "HmacSHA256");
    }

    @Bean
    JwtEncoder jwtEncoder(SecretKey key) {
        return new NimbusJwtEncoder(new ImmutableSecret<>(key));
    }

    @Bean
    JwtDecoder jwtDecoder(SecretKey key) {
        NimbusJwtDecoder decoder = NimbusJwtDecoder.withSecretKey(key).build();
        JwtTimestampValidator timestamps = new JwtTimestampValidator(Duration.ZERO);
        decoder.setJwtValidator(jwt -> {
            Object id = jwt.getClaims().get("userId");
            Object username = jwt.getClaims().get("username");
            Object role = jwt.getClaims().get("role");
            if ((id instanceof Long || id instanceof Integer) && ((Number) id).longValue() > 0
                    && username instanceof String name && !name.isBlank()
                    && ("USER".equals(role) || "ADMIN".equals(role))
                    && jwt.getIssuedAt() != null && jwt.getExpiresAt() != null
                    && jwt.getExpiresAt().isAfter(Instant.now())) {
                return timestamps.validate(jwt);
            }
            return OAuth2TokenValidatorResult.failure(new OAuth2Error("invalid_token"));
        });
        return decoder;
    }
}
