package com.cashflow.auth.config;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;

public class DatabaseConfig {

    private static String getEnv(String primary, String fallback, String defaultValue) {
        String val = System.getenv(primary);
        if (val != null && !val.isBlank()) return val;
        val = System.getenv(fallback);
        if (val != null && !val.isBlank()) return val;
        return defaultValue;
    }

    private static final String DB_HOST =
            getEnv("DB_HOST", "AUTH_DB_HOST", "localhost");

    private static final String DB_PORT =
            getEnv("DB_PORT", "AUTH_DB_PORT", "3307");

    private static final String DB_NAME =
            getEnv("DB_NAME", "AUTH_DB_NAME", "auth_db");

    private static final String DB_USER =
            getEnv("DB_USER", "AUTH_DB_USER", "root");

    private static final String DB_PASSWORD =
            getEnv("DB_PASSWORD", "AUTH_DB_PASSWORD", "root_password");

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