package com.cashflow.auth.util;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;

public class MessageDigestUtil {

    private MessageDigestUtil() {
    }

    public static boolean constantTimeEquals(
            String first,
            String second
    ) {
        return MessageDigest.isEqual(
                first.getBytes(StandardCharsets.UTF_8),
                second.getBytes(StandardCharsets.UTF_8)
        );
    }
}