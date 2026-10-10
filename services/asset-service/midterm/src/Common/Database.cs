using System.Globalization;
using MySql.Data.MySqlClient;

namespace AssetService.Midterm.Common;

public static class Database
{
    private static readonly string ConnectionString = Environment.GetEnvironmentVariable("DB_CONNECTION")
        ?? $"Server={Environment.GetEnvironmentVariable("DB_HOST") ?? "localhost"};Port=3306;Database=asset_db;Uid=root;Pwd={Environment.GetEnvironmentVariable("DB_PASSWORD") ?? "root_password"};";

    public static MySqlConnection GetConnection()
    {
        var conn = new MySqlConnection(ConnectionString);
        conn.Open();
        return conn;
    }

    public static List<Dictionary<string, object?>> Query(string sql, long userId)
    {
        using var connection = GetConnection();
        using var command = new MySqlCommand(sql, connection);
        command.Parameters.AddWithValue("@user", userId);
        using var reader = command.ExecuteReader();
        var rows = new List<Dictionary<string, object?>>();
        while (reader.Read())
        {
            var row = new Dictionary<string, object?>();
            for (var i = 0; i < reader.FieldCount; i++) 
                row[reader.GetName(i)] = reader.IsDBNull(i) ? null : reader.GetValue(i);
            rows.Add(row);
        }
        return rows;
    }

    public static Dictionary<string, object?> GetById(string table, long id, long userId)
    {
        using var connection = GetConnection();
        using var command = new MySqlCommand($"SELECT * FROM {table} WHERE id=@id AND user_id=@user", connection);
        command.Parameters.AddWithValue("@id", id);
        command.Parameters.AddWithValue("@user", userId);
        using var reader = command.ExecuteReader();
        if (!reader.Read()) throw new KeyNotFoundException("Record not found");
        
        var row = new Dictionary<string, object?>();
        for (var i = 0; i < reader.FieldCount; i++) 
            row[reader.GetName(i)] = reader.IsDBNull(i) ? null : reader.GetValue(i);
        return row;
    }
}