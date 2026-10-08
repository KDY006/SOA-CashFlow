package com.cashflow.auth.util;

import java.security.SecureRandom;
import java.util.Base64;

public class RefreshTokenUtil {

    private static final SecureRandom SECURE_RANDOM =
            new SecureRandom();

    private RefreshTokenUtil() {
    }

    public static String generateToken() {

        byte[] randomBytes = new byte[48];

        SECURE_RANDOM.nextBytes(randomBytes);

        return Base64.getUrlEncoder()
                .withoutPadding()
                .encodeToString(randomBytes);
    }
}