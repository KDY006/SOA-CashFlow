package com.cashflow.auth.util;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.time.Instant;
import java.util.Base64;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class JwtUtil {

private static final String SECRET = loadSecret();

    private static String loadSecret() {
        String secret = System.getenv("JWT_SECRET");

        if (secret == null || secret.isBlank()) {
            secret = System.getenv("AUTH_JWT_SECRET");
        }

        if (secret == null || secret.isBlank()) {
            return "cashflow-auth-midterm-development-secret-key";
        }

        if (secret.length() < 32) {
            throw new IllegalStateException(
                    "JWT_SECRET must contain at least 32 characters"
            );
        }

        return secret;
    }

    private static final long EXPIRATION_SECONDS = 3600;

    private JwtUtil() {
    }

    public static String generateToken(
            long userId,
            String username,
            String role
    ) {
        long issuedAt = Instant.now().getEpochSecond();
        long expiration = issuedAt + EXPIRATION_SECONDS;

        String header =
                "{\"alg\":\"HS256\",\"typ\":\"JWT\"}";

        String payload =
                "{\"userId\":" + userId +
                ",\"username\":\"" + escape(username) + "\"" +
                ",\"role\":\"" + escape(role) + "\"" +
                ",\"iat\":" + issuedAt +
                ",\"exp\":" + expiration +
                "}";

        String encodedHeader = base64Url(header);
        String encodedPayload = base64Url(payload);

        String data =
                encodedHeader + "." + encodedPayload;

        String signature = sign(data);

        return data + "." + signature;
    }

    public static boolean validateToken(String token) {
        try {
            String[] parts = token.split("\\.");

            if (parts.length != 3) {
                return false;
            }

            String data = parts[0] + "." + parts[1];
            String expectedSignature = sign(data);

            if (!MessageDigestUtil.constantTimeEquals(
                    expectedSignature,
                    parts[2]
            )) {
                return false;
            }

            String payload = new String(
                    Base64.getUrlDecoder().decode(parts[1]),
                    StandardCharsets.UTF_8
            );

            long expiration = getLongClaim(payload, "exp");

            return Instant.now().getEpochSecond() < expiration;

        } catch (Exception e) {
            return false;
        }
    }

    public static long getUserId(String token) {
        String payload = getPayload(token);
        return getLongClaim(payload, "userId");
    }

    public static String getUsername(String token) {
        return getStringClaim(getPayload(token), "username");
    }

    public static String getRole(String token) {
        return getStringClaim(getPayload(token), "role");
    }

    private static String getPayload(String token) {
        String[] parts = token.split("\\.");

        if (parts.length != 3) {
            throw new IllegalArgumentException("Invalid token");
        }

        return new String(
                Base64.getUrlDecoder().decode(parts[1]),
                StandardCharsets.UTF_8
        );
    }

    private static String base64Url(String value) {
        return Base64.getUrlEncoder()
                .withoutPadding()
                .encodeToString(
                        value.getBytes(StandardCharsets.UTF_8)
                );
    }

    private static String sign(String data) {
        try {
            Mac mac = Mac.getInstance("HmacSHA256");

            SecretKeySpec key =
                    new SecretKeySpec(
                            SECRET.getBytes(StandardCharsets.UTF_8),
                            "HmacSHA256"
                    );

            mac.init(key);

            byte[] signature =
                    mac.doFinal(
                            data.getBytes(StandardCharsets.UTF_8)
                    );

            return Base64.getUrlEncoder()
                    .withoutPadding()
                    .encodeToString(signature);

        } catch (Exception e) {
            throw new RuntimeException(
                    "Cannot create JWT signature",
                    e
            );
        }
    }

    private static long getLongClaim(
            String payload,
            String name
    ) {
        Pattern pattern = Pattern.compile(
                "\"" + name + "\"\\s*:\\s*(\\d+)"
        );

        Matcher matcher = pattern.matcher(payload);

        if (!matcher.find()) {
            throw new IllegalArgumentException(
                    "Claim not found: " + name
            );
        }

        return Long.parseLong(matcher.group(1));
    }

    private static String getStringClaim(
            String payload,
            String name
    ) {
        Pattern pattern = Pattern.compile(
                "\"" + name + "\"\\s*:\\s*\"([^\"]*)\""
        );

        Matcher matcher = pattern.matcher(payload);

        if (!matcher.find()) {
            throw new IllegalArgumentException(
                    "Claim not found: " + name
            );
        }

        return matcher.group(1);
    }

    private static String escape(String value) {
        return value
                .replace("\\", "\\\\")
                .replace("\"", "\\\"");
    }
}