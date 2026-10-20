using System.Globalization;
using AssetService.Midterm.Common;
using AssetService.Midterm.Models;
using MySql.Data.MySqlClient;

namespace AssetService.Midterm.Handlers;

public static class InvestmentHandler
{
    public static object GetInvestments(long userId)
    {
        return Database.Query("SELECT id,user_id,symbol,investment_type,quantity,buy_price,current_price,created_at FROM investments WHERE user_id=@user ORDER BY id", userId);
    }

    public static object CreateInvestment(InvestmentInput x)
    {
        ValidateInvestment(x);

        using var c = Database.GetConnection();
        using var q = new MySqlCommand("INSERT INTO investments(user_id,symbol,investment_type,quantity,buy_price,current_price) VALUES(@user,@symbol,@type,@quantity,@buy,@current)", c);
        q.Parameters.AddWithValue("@user", x.UserId);
        q.Parameters.AddWithValue("@symbol", x.Symbol);
        q.Parameters.AddWithValue("@type", x.InvestmentType);
        q.Parameters.AddWithValue("@quantity", x.Quantity);
        q.Parameters.AddWithValue("@buy", x.BuyPrice);
        q.Parameters.AddWithValue("@current", x.CurrentPrice);
        q.ExecuteNonQuery();

        var id = Convert.ToInt64(new MySqlCommand("SELECT LAST_INSERT_ID()", c).ExecuteScalar(), CultureInfo.InvariantCulture);
        return Database.GetById("investments", id, x.UserId);
    }

    public static object UpdateInvestment(long id, long userId, InvestmentInput x)
    {
        if (x.UserId != userId) throw new ArgumentException("user_id must match the requested user");
        ValidateInvestment(x);

        // Kiểm tra sự tồn tại của khoản đầu tư
        _ = Database.GetById("investments", id, userId);

        using var c = Database.GetConnection();
        using var q = new MySqlCommand("UPDATE investments SET symbol=@symbol, investment_type=@type, quantity=@quantity, buy_price=@buy, current_price=@current WHERE id=@id AND user_id=@user", c);
        q.Parameters.AddWithValue("@symbol", x.Symbol);
        q.Parameters.AddWithValue("@type", x.InvestmentType);
        q.Parameters.AddWithValue("@quantity", x.Quantity);
        q.Parameters.AddWithValue("@buy", x.BuyPrice);
        q.Parameters.AddWithValue("@current", x.CurrentPrice);
        q.Parameters.AddWithValue("@id", id);
        q.Parameters.AddWithValue("@user", userId);
        q.ExecuteNonQuery();

        return Database.GetById("investments", id, userId);
    }

    public static object DeleteInvestment(long id, long userId)
    {
        using var c = Database.GetConnection();
        using var q = new MySqlCommand("DELETE FROM investments WHERE id=@id AND user_id=@user", c);
        q.Parameters.AddWithValue("@id", id);
        q.Parameters.AddWithValue("@user", userId);
        var rowsAffected = q.ExecuteNonQuery();
        
        if (rowsAffected == 0) throw new KeyNotFoundException("Investment not found or unauthorized");
        return new { message = "Investment deleted successfully", id };
    }

    private static void ValidateInvestment(InvestmentInput x)
    {
        if (x.UserId <= 0 
            || string.IsNullOrWhiteSpace(x.Symbol) || x.Symbol.Length > 20 
            || string.IsNullOrWhiteSpace(x.InvestmentType) || x.InvestmentType.Length > 50 
            || x.Quantity <= 0 || x.BuyPrice < 0 || x.CurrentPrice < 0)
            throw new ArgumentException("Invalid investment values");
    }
}