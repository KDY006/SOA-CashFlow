package com.cashflow.auth.repository;

import com.cashflow.auth.config.DatabaseConfig;
import com.cashflow.auth.model.User;

import java.sql.*;

public class UserRepository {

    public boolean existsByUsername(String username) throws SQLException {
        String sql = "SELECT 1 FROM users WHERE username = ? LIMIT 1";

        try (
                Connection connection = DatabaseConfig.getConnection();
                PreparedStatement statement = connection.prepareStatement(sql)
        ) {
            statement.setString(1, username);

            try (ResultSet resultSet = statement.executeQuery()) {
                return resultSet.next();
            }
        }
    }

    public boolean existsByEmail(String email) throws SQLException {
        String sql = "SELECT 1 FROM users WHERE email = ? LIMIT 1";

        try (
                Connection connection = DatabaseConfig.getConnection();
                PreparedStatement statement = connection.prepareStatement(sql)
        ) {
            statement.setString(1, email);

            try (ResultSet resultSet = statement.executeQuery()) {
                return resultSet.next();
            }
        }
    }

    public User findByUsername(String username) throws SQLException {
        String sql = "SELECT * FROM users WHERE username = ?";

        try (
                Connection connection = DatabaseConfig.getConnection();
                PreparedStatement statement = connection.prepareStatement(sql)
        ) {
            statement.setString(1, username);

            try (ResultSet resultSet = statement.executeQuery()) {
                if (resultSet.next()) {
                    return mapUser(resultSet);
                }
            }
        }

        return null;
    }

    public User findByEmail(String email) throws SQLException {
        String sql = "SELECT * FROM users WHERE email = ?";

        try (
                Connection connection = DatabaseConfig.getConnection();
                PreparedStatement statement = connection.prepareStatement(sql)
        ) {
            statement.setString(1, email);

            try (ResultSet resultSet = statement.executeQuery()) {
                if (resultSet.next()) {
                    return mapUser(resultSet);
                }
            }
        }

        return null;
    }

    public User findById(long id) throws SQLException {
        String sql = "SELECT * FROM users WHERE id = ?";

        try (
                Connection connection = DatabaseConfig.getConnection();
                PreparedStatement statement = connection.prepareStatement(sql)
        ) {
            statement.setLong(1, id);

            try (ResultSet resultSet = statement.executeQuery()) {
                if (resultSet.next()) {
                    return mapUser(resultSet);
                }
            }
        }

        return null;
    }

    public long create(User user) throws SQLException {
        String sql = """
                INSERT INTO users
                (username, email, password_hash, full_name, phone_number, role, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                """;

        try (
                Connection connection = DatabaseConfig.getConnection();
                PreparedStatement statement = connection.prepareStatement(
                        sql,
                        Statement.RETURN_GENERATED_KEYS
                )
        ) {
            statement.setString(1, user.getUsername());
            statement.setString(2, user.getEmail());
            statement.setString(3, user.getPasswordHash());
            statement.setString(4, user.getFullName());
            statement.setString(5, user.getPhoneNumber());
            statement.setString(6, user.getRole());
            statement.setString(7, user.getStatus());

            int affectedRows = statement.executeUpdate();

            if (affectedRows == 0) {
                throw new SQLException("Creating user failed.");
            }

            try (ResultSet generatedKeys = statement.getGeneratedKeys()) {
                if (generatedKeys.next()) {
                    return generatedKeys.getLong(1);
                }
            }

            throw new SQLException("Creating user failed, no ID obtained.");
        }
    }

    private User mapUser(ResultSet resultSet) throws SQLException {
        return new User(
                resultSet.getLong("id"),
                resultSet.getString("username"),
                resultSet.getString("email"),
                resultSet.getString("password_hash"),
                resultSet.getString("full_name"),
                resultSet.getString("phone_number"),
                resultSet.getString("role"),
                resultSet.getString("status"),
                resultSet.getTimestamp("created_at"),
                resultSet.getTimestamp("updated_at")
        );
    }
}