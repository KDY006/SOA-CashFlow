package com.cashflow.auth.repository;

import com.cashflow.auth.config.DatabaseConfig;
import com.cashflow.auth.model.User;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class UserRepository {

    // =========================================================
    // CHECK USERNAME EXISTS
    // =========================================================
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

    // =========================================================
    // CHECK EMAIL EXISTS
    // =========================================================
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

    // =========================================================
    // FIND USER BY USERNAME
    // =========================================================
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

    // =========================================================
    // FIND USER BY EMAIL
    // =========================================================
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

    // =========================================================
    // FIND USER BY ID
    // =========================================================
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

    // =========================================================
    // CREATE USER
    // =========================================================
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

            throw new SQLException(
                    "Creating user failed, no ID obtained."
            );
        }
    }

    // =========================================================
    // READ ALL USERS
    // =========================================================
    public List<User> findAll() throws SQLException {
        String sql = "SELECT * FROM users ORDER BY id ASC";

        List<User> users = new ArrayList<>();

        try (
                Connection connection = DatabaseConfig.getConnection();
                PreparedStatement statement = connection.prepareStatement(sql);
                ResultSet resultSet = statement.executeQuery()
        ) {
            while (resultSet.next()) {
                users.add(mapUser(resultSet));
            }
        }

        return users;
    }

    // =========================================================
    // UPDATE USER
    // Used by Admin / profile update
    // Password is NOT changed here
    // =========================================================
    public boolean update(User user) throws SQLException {
        String sql = """
                UPDATE users
                SET username = ?,
                    email = ?,
                    full_name = ?,
                    phone_number = ?,
                    role = ?,
                    status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                """;

        try (
                Connection connection = DatabaseConfig.getConnection();
                PreparedStatement statement = connection.prepareStatement(sql)
        ) {
            statement.setString(1, user.getUsername());
            statement.setString(2, user.getEmail());
            statement.setString(3, user.getFullName());
            statement.setString(4, user.getPhoneNumber());
            statement.setString(5, user.getRole());
            statement.setString(6, user.getStatus());
            statement.setLong(7, user.getId());

            return statement.executeUpdate() > 0;
        }
    }

    // =========================================================
    // UPDATE PASSWORD
    // Only updates password_hash of the specified user.
    // =========================================================
    public boolean updatePassword(
            long userId,
            String passwordHash
    ) throws SQLException {

        String sql = """
                UPDATE users
                SET password_hash = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                """;

        try (
                Connection connection = DatabaseConfig.getConnection();
                PreparedStatement statement = connection.prepareStatement(sql)
        ) {
            statement.setString(1, passwordHash);
            statement.setLong(2, userId);

            return statement.executeUpdate() > 0;
        }
    }

    // =========================================================
    // DELETE USER
    // =========================================================
    public boolean deleteById(long id) throws SQLException {
        String sql = "DELETE FROM users WHERE id = ?";

        try (
                Connection connection = DatabaseConfig.getConnection();
                PreparedStatement statement = connection.prepareStatement(sql)
        ) {
            statement.setLong(1, id);

            return statement.executeUpdate() > 0;
        }
    }

    // =========================================================
    // MAP RESULTSET -> USER OBJECT
    // =========================================================
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