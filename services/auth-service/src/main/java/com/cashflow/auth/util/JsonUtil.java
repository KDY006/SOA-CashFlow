package com.cashflow.auth.util;

import com.fasterxml.jackson.databind.ObjectMapper;

public class JsonUtil {

    private static final ObjectMapper objectMapper =
            new ObjectMapper();

    private JsonUtil() {
    }

    public static ObjectMapper getObjectMapper() {
        return objectMapper;
    }

    public static String toJson(Object object) {
        try {
            return objectMapper.writeValueAsString(object);
        } catch (Exception e) {
            throw new RuntimeException("Cannot convert object to JSON", e);
        }
    }
}