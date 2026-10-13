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
        if (x.UserId <= 0 || string.IsNullOrWhiteSpace(x.Symbol) || string.IsNullOrWhiteSpace(x.InvestmentType) || x.Quantity <= 0 || x.BuyPrice < 0 || x.CurrentPrice < 0)
            throw new ArgumentException("Invalid investment values");

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
}