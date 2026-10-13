package com.cashflow.auth.handler;

import com.cashflow.auth.model.User;
import com.cashflow.auth.service.AuthService;
import com.cashflow.auth.util.JsonUtil;
import com.fasterxml.jackson.core.JsonProcessingException;
import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpHandler;

import java.io.IOException;
import java.io.OutputStream;
import java.nio.charset.StandardCharsets;
import java.util.LinkedHashMap;
import java.util.Map;

public class RegisterHandler implements HttpHandler {

    private final AuthService authService;

    public RegisterHandler() {
        this.authService = new AuthService();
    }

    @Override
    public void handle(HttpExchange exchange) throws IOException {

        if ("OPTIONS".equalsIgnoreCase(exchange.getRequestMethod())) {
            exchange.getResponseHeaders().set("Access-Control-Allow-Origin", "*");
            exchange.getResponseHeaders().set("Access-Control-Allow-Methods", "POST, OPTIONS");
            exchange.getResponseHeaders().set("Access-Control-Allow-Headers", "Content-Type, Authorization");
            exchange.sendResponseHeaders(204, -1);
            return;
        }

        if (!"POST".equalsIgnoreCase(exchange.getRequestMethod())) {
            sendResponse(
                    exchange,
                    405,
                    Map.of(
                            "success", false,
                            "message", "Method not allowed"
                    )
            );
            return;
        }

        try {
            Map<?, ?> request = readBody(exchange);

            String username = getString(request, "username");
            String email = getString(request, "email");
            String password = getString(request, "password");
            // frontend gửi camelCase, mấy service khác lại hay gửi snake_case -> nhận cả 2
            String fullName = getString(request, "fullName", "full_name");
            String phoneNumber = getString(request, "phoneNumber", "phone_number");

            User user = authService.register(
                    username,
                    email,
                    password,
                    fullName,
                    phoneNumber
            );

            Map<String, Object> userData =
                    new LinkedHashMap<>();

            userData.put("id", user.getId());
            userData.put("username", user.getUsername());
            userData.put("email", user.getEmail());
            userData.put("fullName", user.getFullName());
            userData.put("phoneNumber", user.getPhoneNumber());
            userData.put("role", user.getRole());
            userData.put("status", user.getStatus());

            Map<String, Object> response =
                    new LinkedHashMap<>();

            response.put("success", true);
            response.put(
                    "message",
                    "User registered successfully"
            );
            response.put("user", userData);

            sendResponse(exchange, 201, response);

        } catch (IllegalArgumentException e) {

            sendError(exchange, 400, e.getMessage());

        } catch (IllegalStateException e) {

            // trùng username / email
            sendError(exchange, 409, e.getMessage());

        } catch (Exception e) {

            e.printStackTrace();

            sendError(exchange, 500, "Internal server error");
        }
    }

    private Map<?, ?> readBody(HttpExchange exchange) throws IOException {
        Object body;

        try {
            body = JsonUtil.getObjectMapper().readValue(
                    exchange.getRequestBody(),
                    Object.class
            );
        } catch (JsonProcessingException e) {
            // body rỗng hoặc json sai cú pháp
            throw new IllegalArgumentException("Request body must be valid JSON");
        }

        if (!(body instanceof Map)) {
            throw new IllegalArgumentException("Request body must be a JSON object");
        }

        return (Map<?, ?>) body;
    }

    private String getString(
            Map<?, ?> map,
            String... keys
    ) {
        for (String key : keys) {
            Object value = map.get(key);

            if (value == null) {
                continue;
            }

            if (!(value instanceof String)) {
                throw new IllegalArgumentException(keys[0] + " must be a string");
            }

            return ((String) value).trim();
        }

        return null;
    }

    private void sendError(
            HttpExchange exchange,
            int statusCode,
            String message
    ) throws IOException {
        // không dùng Map.of vì nó không nhận giá trị null
        Map<String, Object> response = new LinkedHashMap<>();
        response.put("success", false);
        response.put("message", message == null ? "Bad request" : message);

        sendResponse(exchange, statusCode, response);
    }

    private void sendResponse(
            HttpExchange exchange,
            int statusCode,
            Object response
    ) throws IOException {

        String json = JsonUtil.toJson(response);

        byte[] bytes =
                json.getBytes(StandardCharsets.UTF_8);

        exchange.getResponseHeaders().set(
                "Content-Type",
                "application/json; charset=UTF-8"
        );
        exchange.getResponseHeaders().set(
                "Access-Control-Allow-Origin",
                "*"
        );

        exchange.sendResponseHeaders(
                statusCode,
                bytes.length
        );

        try (OutputStream output =
                     exchange.getResponseBody()) {

            output.write(bytes);
        }
    }
}