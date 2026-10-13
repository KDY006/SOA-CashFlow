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

public class ValidateHandler implements HttpHandler {

    private final UserRepository userRepository;

    public ValidateHandler() {
        this.userRepository = new UserRepository();
    }

    @Override
    public void handle(HttpExchange exchange) throws IOException {

        if ("OPTIONS".equalsIgnoreCase(exchange.getRequestMethod())) {
            exchange.getResponseHeaders().set("Access-Control-Allow-Origin", "*");
            exchange.getResponseHeaders().set("Access-Control-Allow-Methods", "GET, OPTIONS");
            exchange.getResponseHeaders().set("Access-Control-Allow-Headers", "Content-Type, Authorization");
            exchange.sendResponseHeaders(204, -1);
            return;
        }

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
                                "valid", false,
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
                                "valid", false,
                                "message", "Invalid or expired token"
                        )
                );
                return;
            }

            long userId = JwtUtil.getUserId(token);
            String username = JwtUtil.getUsername(token);
            String role = JwtUtil.getRole(token);

            // Kiểm tra trạng thái tài khoản trong DB để đảm bảo còn ACTIVE
            User user = userRepository.findById(userId);
            if (user == null || !"ACTIVE".equals(user.getStatus())) {
                sendResponse(
                        exchange,
                        401,
                        Map.of(
                                "success", false,
                                "valid", false,
                                "message", user == null ? "User not found" : "Account is not active"
                        )
                );
                return;
            }

            Map<String, Object> response =
                    new LinkedHashMap<>();

            response.put("success", true);
            response.put("valid", true);
            response.put("userId", userId);
            response.put("username", username);
            response.put("role", role);
            response.put("status", user.getStatus());

            sendResponse(exchange, 200, response);

        } catch (Exception e) {

            e.printStackTrace();

            sendResponse(
                    exchange,
                    401,
                    Map.of(
                            "success", false,
                            "valid", false,
                            "message", "Invalid token"
                    )
            );
        }
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