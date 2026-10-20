package com.cashflow.auth.handler;

import com.cashflow.auth.model.User;
import com.cashflow.auth.service.AuthService;
import com.cashflow.auth.util.JsonUtil;
import com.cashflow.auth.util.JwtUtil;
import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpHandler;

import java.io.IOException;
import java.io.OutputStream;
import java.nio.charset.StandardCharsets;
import java.util.LinkedHashMap;
import java.util.Map;

public class ProfileHandler implements HttpHandler {

    private final AuthService authService;

    public ProfileHandler() {
        this.authService = new AuthService();
    }

    @Override
    public void handle(HttpExchange exchange) throws IOException {

        // =====================================================
        // CORS
        // =====================================================
        if ("OPTIONS".equalsIgnoreCase(exchange.getRequestMethod())) {

            exchange.getResponseHeaders().set(
                    "Access-Control-Allow-Origin",
                    "*"
            );

            exchange.getResponseHeaders().set(
                    "Access-Control-Allow-Methods",
                    "GET, PUT, OPTIONS"
            );

            exchange.getResponseHeaders().set(
                    "Access-Control-Allow-Headers",
                    "Content-Type, Authorization"
            );

            exchange.sendResponseHeaders(204, -1);
            return;
        }

        try {

            // =================================================
            // AUTHENTICATION
            // =================================================
            long userId = authenticate(exchange);

            if (userId <= 0) {
                return;
            }

            String method =
                    exchange.getRequestMethod().toUpperCase();

            String path =
                    exchange.getRequestURI().getPath();

            // =================================================
            // GET /api/profile
            // =================================================
            if ("GET".equals(method)
                    && "/api/profile".equals(path)) {

                handleGetProfile(
                        exchange,
                        userId
                );

                return;
            }

            // =================================================
            // PUT /api/profile
            // =================================================
            if ("PUT".equals(method)
                    && "/api/profile".equals(path)) {

                handleUpdateProfile(
                        exchange,
                        userId
                );

                return;
            }

            // =================================================
            // PUT /api/profile/password
            // =================================================
            if ("PUT".equals(method)
                    && "/api/profile/password".equals(path)) {

                handleChangePassword(
                        exchange,
                        userId
                );

                return;
            }

            // =================================================
            // METHOD / ROUTE NOT ALLOWED
            // =================================================
            sendResponse(
                    exchange,
                    405,
                    Map.of(
                            "success", false,
                            "message", "Method not allowed"
                    )
            );

        } catch (IllegalArgumentException e) {

            sendResponse(
                    exchange,
                    400,
                    Map.of(
                            "success", false,
                            "message", e.getMessage()
                    )
            );

        } catch (IllegalStateException e) {

            sendResponse(
                    exchange,
                    409,
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

    // =========================================================
    // AUTHENTICATE USER USING JWT
    // =========================================================
    private long authenticate(
            HttpExchange exchange
    ) throws IOException {

        String authorization =
                exchange.getRequestHeaders()
                        .getFirst("Authorization");

        if (authorization == null
                || !authorization.startsWith("Bearer ")) {

            sendResponse(
                    exchange,
                    401,
                    Map.of(
                            "success", false,
                            "message",
                            "Authorization token is required"
                    )
            );

            return -1;
        }

        String token =
                authorization.substring(7).trim();

        if (!JwtUtil.validateToken(token)) {

            sendResponse(
                    exchange,
                    401,
                    Map.of(
                            "success", false,
                            "message",
                            "Invalid or expired token"
                    )
            );

            return -1;
        }

        long userId =
                JwtUtil.getUserId(token);

        if (userId <= 0) {

            sendResponse(
                    exchange,
                    401,
                    Map.of(
                            "success", false,
                            "message",
                            "Invalid token"
                    )
            );

            return -1;
        }

        return userId;
    }

    // =========================================================
    // GET OWN PROFILE
    // =========================================================
    private void handleGetProfile(
            HttpExchange exchange,
            long userId
    ) throws Exception {

        User user =
                authService.getUserById(userId);

        checkAccountActive(user);

        Map<String, Object> response =
                new LinkedHashMap<>();

        response.put(
                "success",
                true
        );

        response.put(
                "profile",
                userToMap(user)
        );

        sendResponse(
                exchange,
                200,
                response
        );
    }

    // =========================================================
    // UPDATE OWN PROFILE
    //
    // User can only update:
    // - fullName
    // - phoneNumber
    //
    // User CANNOT update:
    // - username
    // - email
    // - role
    // - status
    // =========================================================
    @SuppressWarnings("unchecked")
    private void handleUpdateProfile(
            HttpExchange exchange,
            long userId
    ) throws Exception {

        String body =
                new String(
                        exchange.getRequestBody().readAllBytes(),
                        StandardCharsets.UTF_8
                );

        Map<String, Object> data =
                JsonUtil.getObjectMapper()
                        .readValue(
                                body,
                                Map.class
                        );

        String fullName =
                getString(
                        data,
                        "fullName"
                );

        String phoneNumber =
                getString(
                        data,
                        "phoneNumber"
                );

        User user =
                authService.updateProfile(
                        userId,
                        fullName,
                        phoneNumber
                );

        Map<String, Object> response =
                new LinkedHashMap<>();

        response.put(
                "success",
                true
        );

        response.put(
                "message",
                "Profile updated successfully"
        );

        response.put(
                "profile",
                userToMap(user)
        );

        sendResponse(
                exchange,
                200,
                response
        );
    }

    // =========================================================
    // CHANGE OWN PASSWORD
    // =========================================================
    @SuppressWarnings("unchecked")
    private void handleChangePassword(
            HttpExchange exchange,
            long userId
    ) throws Exception {

        String body =
                new String(
                        exchange.getRequestBody().readAllBytes(),
                        StandardCharsets.UTF_8
                );

        Map<String, Object> data =
                JsonUtil.getObjectMapper()
                        .readValue(
                                body,
                                Map.class
                        );

        String currentPassword =
                getString(
                        data,
                        "currentPassword"
                );

        String newPassword =
                getString(
                        data,
                        "newPassword"
                );

        authService.changePassword(
                userId,
                currentPassword,
                newPassword
        );

        sendResponse(
                exchange,
                200,
                Map.of(
                        "success", true,
                        "message",
                        "Password changed successfully"
                )
        );
    }

    // =========================================================
    // CHECK ACCOUNT STATUS
    // =========================================================
    private void checkAccountActive(
            User user
    ) {

        if (!"ACTIVE".equalsIgnoreCase(
                user.getStatus()
        )) {

            throw new IllegalArgumentException(
                    "Account is not active"
            );
        }
    }

    // =========================================================
    // USER -> JSON SAFE MAP
    // Password hash is NEVER returned
    // =========================================================
    private Map<String, Object> userToMap(
            User user
    ) {

        Map<String, Object> profile =
                new LinkedHashMap<>();

        profile.put(
                "id",
                user.getId()
        );

        profile.put(
                "username",
                user.getUsername()
        );

        profile.put(
                "email",
                user.getEmail()
        );

        profile.put(
                "fullName",
                user.getFullName()
        );

        profile.put(
                "phoneNumber",
                user.getPhoneNumber()
        );

        profile.put(
                "role",
                user.getRole()
        );

        profile.put(
                "status",
                user.getStatus()
        );

        profile.put(
                "createdAt",
                user.getCreatedAt()
        );

        profile.put(
                "updatedAt",
                user.getUpdatedAt()
        );

        return profile;
    }

    // =========================================================
    // GET STRING FROM JSON
    // =========================================================
    private String getString(
            Map<String, Object> data,
            String key
    ) {

        Object value =
                data.get(key);

        if (value == null) {
            return null;
        }

        return String.valueOf(value);
    }

    // =========================================================
    // SEND JSON RESPONSE
    // =========================================================
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