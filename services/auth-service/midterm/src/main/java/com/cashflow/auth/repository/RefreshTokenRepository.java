package com.cashflow.auth.repository;

import com.cashflow.auth.config.DatabaseConfig;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Timestamp;

public class RefreshTokenRepository {

    public void save(
            long userId,
            String token,
            Timestamp expiryDate
    ) throws SQLException {

        String sql = """
                INSERT INTO refresh_tokens
                (user_id, token, expiry_date)
                VALUES (?, ?, ?)
                """;

        try (
                Connection connection = DatabaseConfig.getConnection();
                PreparedStatement statement =
                        connection.prepareStatement(sql)
        ) {
            statement.setLong(1, userId);
            statement.setString(2, token);
            statement.setTimestamp(3, expiryDate);

            statement.executeUpdate();
        }
    }

    public Long findUserIdByToken(String token)
            throws SQLException {

        String sql = """
                SELECT user_id
                FROM refresh_tokens
                WHERE token = ?
                AND expiry_date > CURRENT_TIMESTAMP
                """;

        try (
                Connection connection = DatabaseConfig.getConnection();
                PreparedStatement statement =
                        connection.prepareStatement(sql)
        ) {
            statement.setString(1, token);

            try (ResultSet resultSet = statement.executeQuery()) {

                if (resultSet.next()) {
                    return resultSet.getLong("user_id");
                }
            }
        }

        return null;
    }

    public void deleteByToken(String token)
            throws SQLException {

        String sql =
                "DELETE FROM refresh_tokens WHERE token = ?";

        try (
                Connection connection = DatabaseConfig.getConnection();
                PreparedStatement statement =
                        connection.prepareStatement(sql)
        ) {
            statement.setString(1, token);
            statement.executeUpdate();
        }
    }

    public void deleteByUserId(long userId)
            throws SQLException {

        String sql =
                "DELETE FROM refresh_tokens WHERE user_id = ?";

        try (
                Connection connection = DatabaseConfig.getConnection();
                PreparedStatement statement =
                        connection.prepareStatement(sql)
        ) {
            statement.setLong(1, userId);
            statement.executeUpdate();
        }
    }
}