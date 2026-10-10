package com.cashflow.auth.handler;

import com.cashflow.auth.model.User;
import com.cashflow.auth.repository.UserRepository;
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

    private final UserRepository userRepository;

    public ProfileHandler() {
        this.userRepository = new UserRepository();
    }

    @Override
    public void handle(HttpExchange exchange) throws IOException {

        if (!"GET".equalsIgnoreCase(exchange.getRequestMethod())) {
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
                                "message", "Authorization token is required"
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
                                "message", "Invalid or expired token"
                        )
                );
                return;
            }

            long userId =
                    JwtUtil.getUserId(token);

            User user =
                    userRepository.findById(userId);

            if (user == null) {

                sendResponse(
                        exchange,
                        404,
                        Map.of(
                                "success", false,
                                "message", "User not found"
                        )
                );
                return;
            }

            if (!"ACTIVE".equals(user.getStatus())) {

                sendResponse(
                        exchange,
                        403,
                        Map.of(
                                "success", false,
                                "message", "Account is not active"
                        )
                );
                return;
            }

            Map<String, Object> profile =
                    new LinkedHashMap<>();

            profile.put("id", user.getId());
            profile.put("username", user.getUsername());
            profile.put("email", user.getEmail());
            profile.put("fullName", user.getFullName());
            profile.put("phoneNumber", user.getPhoneNumber());
            profile.put("role", user.getRole());
            profile.put("status", user.getStatus());
            profile.put("createdAt", user.getCreatedAt());
            profile.put("updatedAt", user.getUpdatedAt());

            Map<String, Object> response =
                    new LinkedHashMap<>();

            response.put("success", true);
            response.put("profile", profile);

            sendResponse(
                    exchange,
                    200,
                    response
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

    private void sendResponse(
            HttpExchange exchange,
            int statusCode,
            Object response
    ) throws IOException {

        String json =
                JsonUtil.toJson(response);

        byte[] bytes =
                json.getBytes(StandardCharsets.UTF_8);

        exchange.getResponseHeaders().set(
                "Content-Type",
                "application/json; charset=UTF-8"
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