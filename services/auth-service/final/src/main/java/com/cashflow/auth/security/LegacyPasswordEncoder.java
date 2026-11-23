package com.cashflow.auth.security;

import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Component;

import javax.crypto.SecretKeyFactory;
import javax.crypto.spec.PBEKeySpec;
import java.security.GeneralSecurityException;
import java.security.MessageDigest;
import java.security.SecureRandom;
import java.util.Base64;

@Component
public class LegacyPasswordEncoder implements PasswordEncoder {
    private static final int ITERATIONS = 210_000;
    private static final int HASH_BYTES = 32;
    private final SecureRandom random = new SecureRandom();

    @Override
    public String encode(CharSequence password) {
        byte[] salt = new byte[16];
        random.nextBytes(salt);
        byte[] hash = derive(password, salt, ITERATIONS, HASH_BYTES);
        return ITERATIONS + ":" + Base64.getEncoder().encodeToString(salt)
                + ":" + Base64.getEncoder().encodeToString(hash);
    }

    @Override
    public boolean matches(CharSequence password, String encoded) {
        if (password == null || encoded == null) {
            return false;
        }
        String[] parts = encoded.split(":", -1);
        if (parts.length != 3) {
            return false;
        }
        try {
            int iterations = Integer.parseInt(parts[0]);
            byte[] salt = Base64.getDecoder().decode(parts[1]);
            byte[] hash = Base64.getDecoder().decode(parts[2]);
            return iterations > 0 && iterations <= 2_000_000 && salt.length > 0 && hash.length == HASH_BYTES
                    && MessageDigest.isEqual(hash, derive(password, salt, iterations, hash.length));
        } catch (IllegalArgumentException ex) {
            return false;
        }
    }

    private byte[] derive(CharSequence password, byte[] salt, int iterations, int length) {
        PBEKeySpec spec = new PBEKeySpec(password.toString().toCharArray(), salt, iterations, length * 8);
        try {
            return SecretKeyFactory.getInstance("PBKDF2WithHmacSHA256").generateSecret(spec).getEncoded();
        } catch (GeneralSecurityException ex) {
            throw new IllegalStateException("PBKDF2WithHmacSHA256 is unavailable", ex);
        } finally {
            spec.clearPassword();
        }
    }
}
