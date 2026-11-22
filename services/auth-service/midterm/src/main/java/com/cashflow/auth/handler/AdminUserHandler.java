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
import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

public class AdminUserHandler implements HttpHandler {

    private final AuthService authService;

    public AdminUserHandler() {
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
                    "GET, POST, PUT, DELETE, OPTIONS"
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
            // AUTHENTICATION + ADMIN AUTHORIZATION
            // =================================================
            String authorization =
                    exchange.getRequestHeaders()
                            .getFirst("Authorization");

            if (authorization == null ||
                    !authorization.startsWith("Bearer ")) {

                sendResponse(
                        exchange,
                        401,
                        Map.of(
                                "success", false,
                                "message",
                                "Authorization token is required"
                        )
                );

                return;
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

                return;
            }

            String role = JwtUtil.getRole(token);

            if (!"ADMIN".equalsIgnoreCase(role)) {

                sendResponse(
                        exchange,
                        403,
                        Map.of(
                                "success", false,
                                "message",
                                "Admin permission is required"
                        )
                );

                return;
            }

            // =================================================
            // ROUTING
            // =================================================
            String method =
                    exchange.getRequestMethod().toUpperCase();

            String path =
                    exchange.getRequestURI().getPath();

            // Context:
            // /api/admin/users
            //
            // Supported:
            // /api/admin/users
            // /api/admin/users/{id}

            String basePath = "/api/admin/users";

            String remaining = "";

            if (path.length() > basePath.length()) {
                remaining =
                        path.substring(basePath.length());
            }

            // Remove leading /
            if (remaining.startsWith("/")) {
                remaining = remaining.substring(1);
            }

            // Remove trailing /
            while (remaining.endsWith("/") &&
                    !remaining.isEmpty()) {

                remaining =
                        remaining.substring(
                                0,
                                remaining.length() - 1
                        );
            }

            // =================================================
            // /api/admin/users
            // =================================================
            if (remaining.isEmpty()) {

                switch (method) {

                    case "GET":
                        getAllUsers(exchange);
                        return;

                    case "POST":
                        createUser(exchange);
                        return;

                    default:
                        sendMethodNotAllowed(exchange);
                        return;
                }
            }

            // =================================================
            // /api/admin/users/{id}
            // =================================================
            long userId;

            try {
                userId = Long.parseLong(remaining);
            } catch (NumberFormatException e) {

                sendResponse(
                        exchange,
                        400,
                        Map.of(
                                "success", false,
                                "message", "Invalid user id"
                        )
                );

                return;
            }

            switch (method) {

                case "GET":
                    getUserById(exchange, userId);
                    break;

                case "PUT":
                    updateUser(exchange, userId);
                    break;

                case "DELETE":
                    deleteUser(exchange, userId);
                    break;

                default:
                    sendMethodNotAllowed(exchange);
            }

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
                            "message",
                            "Internal server error"
                    )
            );
        }
    }

    // =========================================================
    // READ ALL
    // GET /api/admin/users
    // =========================================================
    private void getAllUsers(
            HttpExchange exchange
    ) throws Exception {

        List<User> users =
                authService.getAllUsers();

        List<Map<String, Object>> result =
                new ArrayList<>();

        for (User user : users) {
            result.add(userToMap(user));
        }

        Map<String, Object> response =
                new LinkedHashMap<>();

        response.put("success", true);
        response.put("count", result.size());
        response.put("users", result);

        sendResponse(
                exchange,
                200,
                response
        );
    }

    // =========================================================
    // READ ONE
    // GET /api/admin/users/{id}
    // =========================================================
    private void getUserById(
            HttpExchange exchange,
            long userId
    ) throws Exception {

        User user =
                authService.getUserById(userId);

        Map<String, Object> response =
                new LinkedHashMap<>();

        response.put("success", true);
        response.put("user", userToMap(user));

        sendResponse(
                exchange,
                200,
                response
        );
    }

    // =========================================================
    // CREATE
    // POST /api/admin/users
    // =========================================================
    private void createUser(
            HttpExchange exchange
    ) throws Exception {

        String body = new String(
                exchange.getRequestBody().readAllBytes(),
                StandardCharsets.UTF_8
        );

Map<String, Object> data =
        JsonUtil.getObjectMapper().readValue(
                body,
                Map.class
        );

        String username =
                getString(data, "username");

        String email =
                getString(data, "email");

        String password =
                getString(data, "password");

        String fullName =
                getString(data, "fullName");

        String phoneNumber =
                getString(data, "phoneNumber");

        String role =
                getString(data, "role");

        String status =
                getString(data, "status");

        User user =
                authService.createUserByAdmin(
                        username,
                        email,
                        password,
                        fullName,
                        phoneNumber,
                        role,
                        status
                );

        Map<String, Object> response =
                new LinkedHashMap<>();

        response.put("success", true);
        response.put(
                "message",
                "User created successfully"
        );
        response.put("user", userToMap(user));

        sendResponse(
                exchange,
                201,
                response
        );
    }

    // =========================================================
    // UPDATE
    // PUT /api/admin/users/{id}
    // =========================================================
    private void updateUser(
            HttpExchange exchange,
            long userId
    ) throws Exception {

        String body = new String(
                exchange.getRequestBody().readAllBytes(),
                StandardCharsets.UTF_8
        );

Map<String, Object> data =
        JsonUtil.getObjectMapper().readValue(
                body,
                Map.class
        );

        String username =
                getString(data, "username");

        String email =
                getString(data, "email");

        String fullName =
                getString(data, "fullName");

        String phoneNumber =
                getString(data, "phoneNumber");

        String role =
                getString(data, "role");

        String status =
                getString(data, "status");

        User user =
                authService.updateUserByAdmin(
                        userId,
                        username,
                        email,
                        fullName,
                        phoneNumber,
                        role,
                        status
                );

        Map<String, Object> response =
                new LinkedHashMap<>();

        response.put("success", true);
        response.put(
                "message",
                "User updated successfully"
        );
        response.put("user", userToMap(user));

        sendResponse(
                exchange,
                200,
                response
        );
    }

    // =========================================================
    // DELETE
    // DELETE /api/admin/users/{id}
    // =========================================================
    private void deleteUser(
            HttpExchange exchange,
            long userId
    ) throws Exception {

        authService.deleteUserByAdmin(userId);

        sendResponse(
                exchange,
                200,
                Map.of(
                        "success", true,
                        "message",
                        "User deleted successfully"
                )
        );
    }

    // =========================================================
    // USER -> SAFE JSON
    // KHONG TRA password_hash
    // =========================================================
    private Map<String, Object> userToMap(
            User user
    ) {

        Map<String, Object> result =
                new LinkedHashMap<>();

        result.put("id", user.getId());
        result.put("username", user.getUsername());
        result.put("email", user.getEmail());
        result.put("fullName", user.getFullName());
        result.put(
                "phoneNumber",
                user.getPhoneNumber()
        );
        result.put("role", user.getRole());
        result.put("status", user.getStatus());
        result.put(
                "createdAt",
                user.getCreatedAt()
        );
        result.put(
                "updatedAt",
                user.getUpdatedAt()
        );

        return result;
    }

    // =========================================================
    // JSON STRING HELPER
    // =========================================================
    private String getString(
            Map<String, Object> data,
            String key
    ) {

        Object value = data.get(key);

        if (value == null) {
            return null;
        }

        return String.valueOf(value);
    }

    // =========================================================
    // 405
    // =========================================================
    private void sendMethodNotAllowed(
            HttpExchange exchange
    ) throws IOException {

        sendResponse(
                exchange,
                405,
                Map.of(
                        "success", false,
                        "message",
                        "Method not allowed"
                )
        );
    }

    // =========================================================
    // JSON RESPONSE
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