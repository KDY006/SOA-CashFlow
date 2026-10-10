package com.cashflow.auth.handler;

import com.cashflow.auth.model.User;
import com.cashflow.auth.repository.RefreshTokenRepository;
import com.cashflow.auth.service.AuthService;
import com.cashflow.auth.util.JsonUtil;
import com.cashflow.auth.util.JwtUtil;
import com.cashflow.auth.util.RefreshTokenUtil;
import com.fasterxml.jackson.core.JsonProcessingException;
import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpHandler;

import java.io.IOException;
import java.io.OutputStream;
import java.nio.charset.StandardCharsets;
import java.sql.Timestamp;
import java.time.Instant;
import java.util.LinkedHashMap;
import java.util.Map;

public class LoginHandler implements HttpHandler {

    private final AuthService authService;
    private final RefreshTokenRepository refreshTokenRepository;

    private static final long REFRESH_TOKEN_EXPIRATION =
            7 * 24 * 60 * 60;

    public LoginHandler() {
        this.authService = new AuthService();
        this.refreshTokenRepository =
                new RefreshTokenRepository();
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
            Map<?, ?> request;
            try {
                Object body = JsonUtil.getObjectMapper().readValue(
                        exchange.getRequestBody(),
                        Object.class
                );
                if (!(body instanceof Map)) {
                    throw new IllegalStateException();
                }
                request = (Map<?, ?>) body;
            } catch (JsonProcessingException | IllegalStateException e) {
                // body rỗng / sai json thì là lỗi phía client, không phải 500
                Map<String, Object> error = new LinkedHashMap<>();
                error.put("success", false);
                error.put("message", "Request body must be a valid JSON object");
                sendResponse(exchange, 400, error);
                return;
            }

            String username =
                    getString(request, "username");

            String password =
                    getString(request, "password");

            User user =
                    authService.login(
                            username,
                            password
                    );

            String accessToken =
                    JwtUtil.generateToken(
                            user.getId(),
                            user.getUsername(),
                            user.getRole()
                    );

            String refreshToken =
                    RefreshTokenUtil.generateToken();

            Timestamp expiryDate =
                    Timestamp.from(
                            Instant.now().plusSeconds(
                                    REFRESH_TOKEN_EXPIRATION
                            )
                    );

            refreshTokenRepository.deleteByUserId(
                    user.getId()
            );

            refreshTokenRepository.save(
                    user.getId(),
                    refreshToken,
                    expiryDate
            );

            Map<String, Object> userData =
                    new LinkedHashMap<>();

            userData.put(
                    "id",
                    user.getId()
            );

            userData.put(
                    "username",
                    user.getUsername()
            );

            userData.put(
                    "email",
                    user.getEmail()
            );

            userData.put(
                    "fullName",
                    user.getFullName()
            );

            userData.put(
                    "phoneNumber",
                    user.getPhoneNumber()
            );

            userData.put(
                    "role",
                    user.getRole()
            );

            userData.put(
                    "status",
                    user.getStatus()
            );

            Map<String, Object> response =
                    new LinkedHashMap<>();

            response.put(
                    "success",
                    true
            );

            response.put(
                    "message",
                    "Login successful"
            );

            response.put(
                    "accessToken",
                    accessToken
            );

            response.put(
                    "refreshToken",
                    refreshToken
            );

            response.put(
                    "tokenType",
                    "Bearer"
            );

            response.put(
                    "expiresIn",
                    3600
            );

            response.put(
                    "user",
                    userData
            );

            sendResponse(
                    exchange,
                    200,
                    response
            );

        } catch (IllegalArgumentException e) {

            sendResponse(
                    exchange,
                    401,
                    Map.of(
                            "success", false,
                            "message", e.getMessage()
                    )
            );

        } catch (Exception e) {

            e.printStackTrace();

            sendResponse(
                    exchange,
                    500,
                    Map.of(
                            "success", false,
                            "message", "Internal server error"
                    )
            );
        }
    }

    private String getString(
            Map<?, ?> map,
            String key
    ) {

        Object value =
                map.get(key);

        return value == null
                ? null
                : value.toString().trim();
    }

    private void sendResponse(
            HttpExchange exchange,
            int statusCode,
            Object response
    ) throws IOException {

        String json =
                JsonUtil.toJson(response);

        byte[] bytes =
                json.getBytes(
                        StandardCharsets.UTF_8
                );

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

        try (
                OutputStream output =
                        exchange.getResponseBody()
        ) {

            output.write(bytes);
        }
    }
}