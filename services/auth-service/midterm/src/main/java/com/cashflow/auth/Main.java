package com.cashflow.auth;

import com.cashflow.auth.handler.AdminUserHandler;
import com.cashflow.auth.handler.LoginHandler;
import com.cashflow.auth.handler.ProfileHandler;
import com.cashflow.auth.handler.RefreshHandler;
import com.cashflow.auth.handler.RegisterHandler;
import com.cashflow.auth.handler.ValidateHandler;
import com.sun.net.httpserver.HttpServer;

import java.net.InetSocketAddress;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.Executors;

public class Main {
    private static final int PORT = 8081;

    public static void main(String[] args) throws Exception {
        HttpServer server = HttpServer.create(new InetSocketAddress(PORT), 0);

        server.createContext("/health", exchange -> {
            byte[] body = "{\"service\":\"auth-service\",\"status\":\"UP\",\"version\":\"1.0.0-midterm\"}"
                    .getBytes(StandardCharsets.UTF_8);
            exchange.getResponseHeaders().add("Content-Type", "application/json; charset=utf-8");
            exchange.sendResponseHeaders(200, body.length);
            try (var os = exchange.getResponseBody()) {
                os.write(body);
            }
        });

        server.createContext("/api/auth/register", new RegisterHandler());
        server.createContext("/api/auth/login", new LoginHandler());
        server.createContext("/api/auth/validate", new ValidateHandler());
        server.createContext("/api/auth/refresh", new RefreshHandler());
        server.createContext("/api/profile", new ProfileHandler());
        server.createContext("/api/admin/users", new AdminUserHandler());

        server.setExecutor(Executors.newFixedThreadPool(10));
        server.start();

        System.out.println("Auth Service (Native Java 17) running on http://localhost:" + PORT);
    }
}
