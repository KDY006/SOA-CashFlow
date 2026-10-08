package com.cashflow.auth;

import com.cashflow.auth.handler.LoginHandler;
import com.cashflow.auth.handler.RegisterHandler;
import com.sun.net.httpserver.HttpServer;
import com.cashflow.auth.handler.ValidateHandler;

import java.net.InetSocketAddress;
import java.util.concurrent.Executors;
import com.cashflow.auth.handler.RefreshHandler;
import com.cashflow.auth.handler.ProfileHandler;


public class Main {

    private static final int PORT = 8081;

    public static void main(String[] args) throws Exception {

        HttpServer server =
                HttpServer.create(
                        new InetSocketAddress(PORT),
                        0
                );

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

        server.createContext(
                "/api/profile",
                new ProfileHandler()
        );

        server.setExecutor(
                Executors.newFixedThreadPool(10)
        );

        server.start();

        System.out.println(
                "Auth Service running on http://localhost:" + PORT
        );

        System.out.println(
                "POST /api/auth/register"
        );

        System.out.println(
                "POST /api/auth/login"
        );

        System.out.println("GET /api/auth/validate");
        System.out.println("GET /api/profile");
        System.out.println("POST /api/auth/refresh");
    }
}