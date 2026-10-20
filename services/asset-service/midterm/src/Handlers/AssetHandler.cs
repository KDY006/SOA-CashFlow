using System.Globalization;
using AssetService.Midterm.Common;
using AssetService.Midterm.Models;
using MySql.Data.MySqlClient;

namespace AssetService.Midterm.Handlers;

public static class AssetHandler
{
    public static object GetAssets(long userId)
    {
        return Database.Query("SELECT id,user_id,asset_name,asset_type,purchase_price,current_value,salvage_value,useful_life_years,purchase_date,currency,note FROM assets WHERE user_id=@user ORDER BY id", userId);
    }

    public static object CreateAsset(AssetInput x)
    {
        ValidateAsset(x);
        using var c = Database.GetConnection();
        using var q = new MySqlCommand("INSERT INTO assets(user_id,asset_name,asset_type,purchase_price,current_value,salvage_value,useful_life_years,purchase_date,currency,note) VALUES(@user,@name,@type,@purchase,@current,@salvage,@life,@date,@currency,@note)", c);
        AddAssetParameters(q, x);
        q.ExecuteNonQuery();
        var id = Convert.ToInt64(new MySqlCommand("SELECT LAST_INSERT_ID()", c).ExecuteScalar(), CultureInfo.InvariantCulture);
        return Database.GetById("assets", id, x.UserId);
    }

    public static object UpdateAsset(long id, long userId, AssetInput x)
    {
        ValidateAsset(x);
        if (x.UserId != userId) throw new ArgumentException("user_id must match the requested user");
        
        // Kiểm tra xem bản ghi có tồn tại trước hay không
        _ = Database.GetById("assets", id, userId);

        using var c = Database.GetConnection();
        using var q = new MySqlCommand("UPDATE assets SET asset_name=@name,asset_type=@type,purchase_price=@purchase,current_value=@current,salvage_value=@salvage,useful_life_years=@life,purchase_date=@date,currency=@currency,note=@note WHERE id=@id AND user_id=@user", c);
        AddAssetParameters(q, x);
        q.Parameters.AddWithValue("@id", id);
        q.ExecuteNonQuery(); // Không check rowsAffected vì nếu dữ liệu không đổi, MySQL trả 0 nhưng bản ghi vẫn hợp lệ

        return Database.GetById("assets", id, userId);
    }

    public static object DeleteAsset(long id, long userId)
    {
        using var c = Database.GetConnection();
        using var q = new MySqlCommand("DELETE FROM assets WHERE id=@id AND user_id=@user", c);
        q.Parameters.AddWithValue("@id", id);
        q.Parameters.AddWithValue("@user", userId);
        var rowsAffected = q.ExecuteNonQuery();
        
        if (rowsAffected == 0) throw new KeyNotFoundException("Asset not found or unauthorized");
        return new { message = "Asset deleted successfully", id };
    }

    public static object CalculateDepreciation(DepreciationInput x)
    {
        if (x.PurchasePrice < 0 || x.SalvageValue < 0 || x.SalvageValue > x.PurchasePrice || x.UsefulLifeYears <= 0)
            throw new ArgumentException("Require purchase_price >= salvage_value >= 0 and useful_life_years > 0");

        var annual = (x.PurchasePrice - x.SalvageValue) / x.UsefulLifeYears;
        return new { 
            purchase_price = x.PurchasePrice, 
            salvage_value = x.SalvageValue, 
            useful_life_years = x.UsefulLifeYears, 
            annual_depreciation = decimal.Round(annual, 2), 
            monthly_depreciation = decimal.Round(annual / 12, 2) 
        };
    }

    private static void ValidateAsset(AssetInput x)
    {
        if (x.UserId <= 0 
            || string.IsNullOrWhiteSpace(x.AssetName) || x.AssetName.Length > 100 
            || string.IsNullOrWhiteSpace(x.AssetType) || x.AssetType.Length > 50 
            || x.PurchasePrice < 0 || x.CurrentValue < 0 || x.SalvageValue < 0 || x.UsefulLifeYears < 0 
            || x.Currency?.Length > 10)
            throw new ArgumentException("Invalid asset values or length exceeds limit");
    }

    private static void AddAssetParameters(MySqlCommand q, AssetInput x)
    {
        q.Parameters.AddWithValue("@user", x.UserId);
        q.Parameters.AddWithValue("@name", x.AssetName);
        q.Parameters.AddWithValue("@type", x.AssetType);
        q.Parameters.AddWithValue("@purchase", x.PurchasePrice);
        q.Parameters.AddWithValue("@current", x.CurrentValue);
        q.Parameters.AddWithValue("@salvage", x.SalvageValue);
        q.Parameters.AddWithValue("@life", x.UsefulLifeYears);
        q.Parameters.AddWithValue("@date", x.PurchaseDate.HasValue ? x.PurchaseDate.Value : DBNull.Value);
        q.Parameters.AddWithValue("@currency", x.Currency ?? "VND");
        q.Parameters.AddWithValue("@note", (object?)x.Note ?? DBNull.Value);
    }
}