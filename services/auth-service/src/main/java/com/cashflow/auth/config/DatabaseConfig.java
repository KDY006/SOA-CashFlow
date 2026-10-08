package com.cashflow.auth.config;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;

public class DatabaseConfig {

    private static final String DB_HOST =
            System.getenv().getOrDefault("DB_HOST", "localhost");

    private static final String DB_PORT =
            System.getenv().getOrDefault("DB_PORT", "3307");

    private static final String DB_NAME =
            System.getenv().getOrDefault("DB_NAME", "auth_db");

    private static final String DB_USER =
            System.getenv().getOrDefault("DB_USER", "root");

    private static final String DB_PASSWORD =
            System.getenv().getOrDefault("DB_PASSWORD", "root_password");

    private static final String DB_URL =
            "jdbc:mysql://" + DB_HOST + ":" + DB_PORT + "/" + DB_NAME
                    + "?useSSL=false"
                    + "&allowPublicKeyRetrieval=true"
                    + "&serverTimezone=UTC";

    static {
        try {
            Class.forName("com.mysql.cj.jdbc.Driver");
        } catch (ClassNotFoundException e) {
            throw new RuntimeException(
                    "MySQL JDBC Driver not found",
                    e
            );
        }
    }

    private DatabaseConfig() {
    }

    public static Connection getConnection() throws SQLException {
        return DriverManager.getConnection(
                DB_URL,
                DB_USER,
                DB_PASSWORD
        );
    }
}