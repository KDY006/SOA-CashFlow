package com.cashflow.auth.handler;

import com.cashflow.auth.model.User;
import com.cashflow.auth.repository.RefreshTokenRepository;
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

public class RefreshHandler implements HttpHandler {

    private final RefreshTokenRepository refreshTokenRepository;
    private final UserRepository userRepository;

    public RefreshHandler() {
        this.refreshTokenRepository =
                new RefreshTokenRepository();

        this.userRepository =
                new UserRepository();
    }

    @Override
    public void handle(HttpExchange exchange)
            throws IOException {

        if (!"POST".equalsIgnoreCase(
                exchange.getRequestMethod()
        )) {
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
            Map<?, ?> request =
                    JsonUtil.getObjectMapper().readValue(
                            exchange.getRequestBody(),
                            Map.class
                    );

            Object value =
                    request.get("refreshToken");

            if (value == null ||
                    value.toString().isBlank()) {

                sendResponse(
                        exchange,
                        400,
                        Map.of(
                                "success", false,
                                "message",
                                "Refresh token is required"
                        )
                );
                return;
            }

            String refreshToken =
                    value.toString().trim();

            Long userId =
                    refreshTokenRepository
                            .findUserIdByToken(
                                    refreshToken
                            );

            if (userId == null) {

                sendResponse(
                        exchange,
                        401,
                        Map.of(
                                "success", false,
                                "message",
                                "Invalid or expired refresh token"
                        )
                );
                return;
            }

            User user =
                    userRepository.findById(userId);

            if (user == null ||
                    !"ACTIVE".equals(user.getStatus())) {

                sendResponse(
                        exchange,
                        401,
                        Map.of(
                                "success", false,
                                "message",
                                "User is not active"
                        )
                );
                return;
            }

            String newAccessToken =
                    JwtUtil.generateToken(
                            user.getId(),
                            user.getUsername(),
                            user.getRole()
                    );

            Map<String, Object> response =
                    new LinkedHashMap<>();

            response.put("success", true);
            response.put(
                    "message",
                    "Access token refreshed successfully"
            );
            response.put(
                    "accessToken",
                    newAccessToken
            );
            response.put(
                    "tokenType",
                    "Bearer"
            );
            response.put(
                    "expiresIn",
                    3600
            );

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
                            "message",
                            "Internal server error"
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
                json.getBytes(
                        StandardCharsets.UTF_8
                );

        exchange.getResponseHeaders().set(
                "Content-Type",
                "application/json; charset=UTF-8"
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