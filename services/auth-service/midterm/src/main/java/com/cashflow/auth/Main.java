package com.cashflow.auth;

import com.cashflow.auth.handler.AdminUserHandler;
import com.cashflow.auth.handler.LoginHandler;
import com.cashflow.auth.handler.ProfileHandler;
import com.cashflow.auth.handler.RefreshHandler;
import com.cashflow.auth.handler.RegisterHandler;
import com.cashflow.auth.handler.ValidateHandler;
import com.sun.net.httpserver.HttpServer;

import java.net.InetSocketAddress;
import java.util.concurrent.Executors;

public class Main {

    private static final int PORT = 8081;

    public static void main(String[] args) throws Exception {

        HttpServer server =
                HttpServer.create(
                        new InetSocketAddress(PORT),
                        0
                );

        // =====================================================
        // AUTH
        // =====================================================

        server.createContext(
                "/api/auth/register",
                new RegisterHandler()
        );

        server.createContext(
                "/api/auth/login",
                new LoginHandler()
        );

        server.createContext(
                "/api/auth/validate",
                new ValidateHandler()
        );

        server.createContext(
                "/api/auth/refresh",
                new RefreshHandler()
        );

        // =====================================================
        // PROFILE
        // =====================================================

        server.createContext(
                "/api/profile",
                new ProfileHandler()
        );

        // =====================================================
        // ADMIN USER MANAGEMENT
        //
        // GET    /api/admin/users
        // GET    /api/admin/users/{id}
        // POST   /api/admin/users
        // PUT    /api/admin/users/{id}
        // DELETE /api/admin/users/{id}
        // =====================================================

        server.createContext(
                "/api/admin/users",
                new AdminUserHandler()
        );

        // =====================================================
        // THREAD POOL
        // =====================================================

        server.setExecutor(
                Executors.newFixedThreadPool(10)
        );

        server.start();

        // =====================================================
        // STARTUP LOG
        // =====================================================

        System.out.println(
                "Auth Service running on http://localhost:" + PORT
        );

        System.out.println("=== AUTH ENDPOINTS ===");
        System.out.println("POST   /api/auth/register");
        System.out.println("POST   /api/auth/login");
        System.out.println("GET    /api/auth/validate");
        System.out.println("POST   /api/auth/refresh");

        System.out.println("=== PROFILE ENDPOINTS ===");
        System.out.println("GET    /api/profile");

        System.out.println("=== ADMIN USER CRUD ===");
        System.out.println("GET    /api/admin/users");
        System.out.println("GET    /api/admin/users/{id}");
        System.out.println("POST   /api/admin/users");
        System.out.println("PUT    /api/admin/users/{id}");
        System.out.println("DELETE /api/admin/users/{id}");
    }
}