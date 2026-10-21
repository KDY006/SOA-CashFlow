using System.Globalization;
using AssetService.Midterm.Common;
using MySql.Data.MySqlClient;

namespace AssetService.Midterm.Handlers;

public static class AnalyticsHandler
{
    public static object GetNetWorth(long userId)
    {
        using var c = Database.GetConnection();
        using var q = new MySqlCommand("SELECT COALESCE((SELECT SUM(current_value) FROM assets WHERE user_id=@user),0)-COALESCE((SELECT SUM(principal_amount) FROM loans WHERE user_id=@user AND status='ACTIVE'),0)", c);
        q.Parameters.AddWithValue("@user", userId);
        
        return new { 
            user_id = userId, 
            total_net_worth = Convert.ToDecimal(q.ExecuteScalar(), CultureInfo.InvariantCulture), 
            currency = "VND" 
        };
    }
}