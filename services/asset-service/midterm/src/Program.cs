using System.Globalization;
using System.Net;
using System.Text.Json;
using MySql.Data.MySqlClient;

namespace AssetService.Midterm;

internal static class Program
{
    private static readonly string ConnectionString = Environment.GetEnvironmentVariable("DB_CONNECTION")
        ?? $"Server={Environment.GetEnvironmentVariable("DB_HOST") ?? "localhost"};Port=3306;Database=asset_db;Uid=root;Pwd={Environment.GetEnvironmentVariable("DB_PASSWORD") ?? "root_password"};";
    private static readonly JsonSerializerOptions JsonOptions = new() { PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower };

    private static async Task Main()
    {
        var port = Environment.GetEnvironmentVariable("PORT") ?? "8083";
        using var listener = new HttpListener();
        var host = Environment.GetEnvironmentVariable("LISTEN_HOST") ?? "localhost";
        listener.Prefixes.Add($"http://{host}:{port}/");
        listener.Start();
        Console.WriteLine($"[Asset Service Native C#] Running on port {port}...");

        while (listener.IsListening)
        {
            HttpListenerContext context;
            try { context = await listener.GetContextAsync(); }
            catch (HttpListenerException) when (!listener.IsListening) { break; }
            _ = Task.Run(() => ProcessRequestAsync(context));
        }
    }

    private static async Task ProcessRequestAsync(HttpListenerContext context)
    {
        var response = context.Response;
        response.AddHeader("Access-Control-Allow-Origin", "*");
        response.AddHeader("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS");
        response.AddHeader("Access-Control-Allow-Headers", "Content-Type");
        if (context.Request.HttpMethod == "OPTIONS") { response.StatusCode = 204; response.Close(); return; }

        try
        {
            var path = context.Request.Url?.AbsolutePath.TrimEnd('/').ToLowerInvariant() ?? "";
            var method = context.Request.HttpMethod;
            object? result;
            int status = 200;

            var userId = ParseUserId(context.Request.QueryString["user_id"]);
            if (path == "/api/assets/net-worth" && method == "GET") result = GetNetWorth(userId);
            else if (path == "/api/assets/depreciation" && method == "POST") result = CalculateDepreciation(await ReadBodyAsync<DepreciationInput>(context));
            else if (path == "/api/loans/calculate" && method == "POST") result = CalculateLoan(await ReadBodyAsync<LoanCalculationInput>(context));
            else if (path == "/api/assets" && method == "GET") result = Query("SELECT id,user_id,asset_name,asset_type,purchase_price,current_value,salvage_value,useful_life_years,purchase_date,currency,note FROM assets WHERE user_id=@user ORDER BY id", userId);
            else if (path == "/api/assets" && method == "POST") { result = CreateAsset(await ReadBodyAsync<AssetInput>(context)); status = 201; }
            else if (TryId(path, "/api/assets/", out var assetId) && method == "GET") result = GetById("assets", assetId, userId);
            else if (TryId(path, "/api/assets/", out assetId) && method == "PUT") result = UpdateAsset(assetId, userId, await ReadBodyAsync<AssetInput>(context));
            else if (path == "/api/loans" && method == "GET") result = Query("SELECT id,user_id,loan_title,principal_amount,interest_rate,term_months,start_date,status FROM loans WHERE user_id=@user ORDER BY id", userId);
            else if (path == "/api/loans" && method == "POST") { result = CreateLoan(await ReadBodyAsync<LoanInput>(context)); status = 201; }
            else if (TryId(path, "/api/loans/", out var loanId) && method == "GET") result = GetById("loans", loanId, userId);
            else if (path == "/api/investments" && method == "GET") result = Query("SELECT id,user_id,symbol,investment_type,quantity,buy_price,current_price,created_at FROM investments WHERE user_id=@user ORDER BY id", userId);
            else if (path == "/api/investments" && method == "POST") { result = CreateInvestment(await ReadBodyAsync<InvestmentInput>(context)); status = 201; }
            else { status = 404; result = new { error = "Endpoint not found" }; }

            await WriteJsonAsync(response, status, result);
        }
        catch (JsonException) { await WriteJsonAsync(response, 400, new { error = "Invalid JSON request body" }); }
        catch (ArgumentException ex) { await WriteJsonAsync(response, 400, new { error = ex.Message }); }
        catch (KeyNotFoundException ex) { await WriteJsonAsync(response, 404, new { error = ex.Message }); }
        catch (Exception ex)
        {
            Console.Error.WriteLine(ex);
            await WriteJsonAsync(response, 500, new { error = "Database or server error" });
        }
    }

    private static object Query(string sql, long userId)
    {
        using var connection = new MySqlConnection(ConnectionString);
        connection.Open();
        using var command = new MySqlCommand(sql, connection);
        command.Parameters.AddWithValue("@user", userId);
        using var reader = command.ExecuteReader();
        var rows = new List<Dictionary<string, object?>>();
        while (reader.Read())
        {
            var row = new Dictionary<string, object?>();
            for (var i = 0; i < reader.FieldCount; i++) row[reader.GetName(i)] = reader.IsDBNull(i) ? null : reader.GetValue(i);
            rows.Add(row);
        }
        return rows;
    }

    private static object GetById(string table, long id, long userId)
    {
        using var connection = new MySqlConnection(ConnectionString); connection.Open();
        using var command = new MySqlCommand($"SELECT * FROM {table} WHERE id=@id AND user_id=@user", connection);
        command.Parameters.AddWithValue("@id", id);
        command.Parameters.AddWithValue("@user", userId);
        using var reader = command.ExecuteReader();
        if (!reader.Read()) throw new KeyNotFoundException("Record not found");
        var row = new Dictionary<string, object?>();
        for (var i = 0; i < reader.FieldCount; i++) row[reader.GetName(i)] = reader.IsDBNull(i) ? null : reader.GetValue(i);
        return row;
    }

    private static object CreateAsset(AssetInput x)
    {
        ValidateAsset(x);
        using var c = new MySqlConnection(ConnectionString); c.Open();
        using var q = new MySqlCommand("INSERT INTO assets(user_id,asset_name,asset_type,purchase_price,current_value,salvage_value,useful_life_years,purchase_date,currency,note) VALUES(@user,@name,@type,@purchase,@current,@salvage,@life,@date,@currency,@note)", c);
        AddAsset(q, x); q.ExecuteNonQuery();
        var id = Convert.ToInt64(new MySqlCommand("SELECT LAST_INSERT_ID()", c).ExecuteScalar(), CultureInfo.InvariantCulture);
        return GetById("assets", id, x.UserId);
    }

    private static object UpdateAsset(long id, long userId, AssetInput x)
    {
        ValidateAsset(x);
        if (x.UserId != userId) throw new ArgumentException("user_id must match the requested user");
        using var c = new MySqlConnection(ConnectionString); c.Open();
        using var q = new MySqlCommand("UPDATE assets SET asset_name=@name,asset_type=@type,purchase_price=@purchase,current_value=@current,salvage_value=@salvage,useful_life_years=@life,purchase_date=@date,currency=@currency,note=@note WHERE id=@id AND user_id=@user", c);
        AddAsset(q, x); q.Parameters.AddWithValue("@id", id);
        q.ExecuteNonQuery();
        return GetById("assets", id, userId);
    }

    private static object CreateLoan(LoanInput x)
    {
        ValidateLoan(x);
        using var c = new MySqlConnection(ConnectionString); c.Open();
        using var q = new MySqlCommand("INSERT INTO loans(user_id,loan_title,principal_amount,interest_rate,term_months,start_date) VALUES(@user,@title,@principal,@rate,@term,@date)", c);
        AddLoan(q, x); q.ExecuteNonQuery();
        return GetById("loans", Convert.ToInt64(new MySqlCommand("SELECT LAST_INSERT_ID()", c).ExecuteScalar(), CultureInfo.InvariantCulture), x.UserId);
    }

    private static object CreateInvestment(InvestmentInput x)
    {
        if (x.UserId <= 0 || string.IsNullOrWhiteSpace(x.Symbol) || string.IsNullOrWhiteSpace(x.InvestmentType) || x.Quantity <= 0 || x.BuyPrice < 0 || x.CurrentPrice < 0) throw new ArgumentException("Invalid investment values");
        using var c = new MySqlConnection(ConnectionString); c.Open();
        using var q = new MySqlCommand("INSERT INTO investments(user_id,symbol,investment_type,quantity,buy_price,current_price) VALUES(@user,@symbol,@type,@quantity,@buy,@current)", c);
        q.Parameters.AddWithValue("@user", x.UserId); q.Parameters.AddWithValue("@symbol", x.Symbol); q.Parameters.AddWithValue("@type", x.InvestmentType); q.Parameters.AddWithValue("@quantity", x.Quantity); q.Parameters.AddWithValue("@buy", x.BuyPrice); q.Parameters.AddWithValue("@current", x.CurrentPrice);
        q.ExecuteNonQuery();
        return GetById("investments", Convert.ToInt64(new MySqlCommand("SELECT LAST_INSERT_ID()", c).ExecuteScalar(), CultureInfo.InvariantCulture), x.UserId);
    }

    private static object GetNetWorth(long userId)
    {
        using var c = new MySqlConnection(ConnectionString); c.Open();
        using var q = new MySqlCommand("SELECT COALESCE((SELECT SUM(current_value) FROM assets WHERE user_id=@user),0)-COALESCE((SELECT SUM(principal_amount) FROM loans WHERE user_id=@user AND status='ACTIVE'),0)", c);
        q.Parameters.AddWithValue("@user", userId);
        return new { user_id = userId, total_net_worth = Convert.ToDecimal(q.ExecuteScalar(), CultureInfo.InvariantCulture), currency = "VND" };
    }

    private static object CalculateDepreciation(DepreciationInput x)
    {
        if (x.PurchasePrice < 0 || x.SalvageValue < 0 || x.SalvageValue > x.PurchasePrice || x.UsefulLifeYears <= 0) throw new ArgumentException("Require purchase_price >= salvage_value >= 0 and useful_life_years > 0");
        var annual = (x.PurchasePrice - x.SalvageValue) / x.UsefulLifeYears;
        return new { purchase_price = x.PurchasePrice, salvage_value = x.SalvageValue, useful_life_years = x.UsefulLifeYears, annual_depreciation = decimal.Round(annual, 2), monthly_depreciation = decimal.Round(annual / 12, 2) };
    }

    private static object CalculateLoan(LoanCalculationInput x)
    {
        if (x.PrincipalAmount <= 0 || x.InterestRate < 0 || x.InterestRate > 999.99m || x.TermMonths <= 0) throw new ArgumentException("Require principal_amount > 0, annual_interest_rate between 0 and 999.99, and term_months > 0");
        var r = (double)(x.InterestRate / 100m / 12m);
        var factor = Math.Pow(1 + r, x.TermMonths);
        var payment = r == 0 ? (double)x.PrincipalAmount / x.TermMonths : (double)x.PrincipalAmount * r * factor / (factor - 1);
        if (!double.IsFinite(payment) || payment > (double)decimal.MaxValue) throw new ArgumentException("Loan values exceed supported calculation range");
        return new { principal_amount = x.PrincipalAmount, annual_interest_rate = x.InterestRate, term_months = x.TermMonths, monthly_payment = decimal.Round((decimal)payment, 2) };
    }

    private static void AddAsset(MySqlCommand q, AssetInput x)
    {
        q.Parameters.AddWithValue("@user", x.UserId); q.Parameters.AddWithValue("@name", x.AssetName); q.Parameters.AddWithValue("@type", x.AssetType); q.Parameters.AddWithValue("@purchase", x.PurchasePrice); q.Parameters.AddWithValue("@current", x.CurrentValue); q.Parameters.AddWithValue("@salvage", x.SalvageValue); q.Parameters.AddWithValue("@life", x.UsefulLifeYears); q.Parameters.AddWithValue("@date", x.PurchaseDate.HasValue ? x.PurchaseDate.Value : DBNull.Value); q.Parameters.AddWithValue("@currency", x.Currency ?? "VND"); q.Parameters.AddWithValue("@note", (object?)x.Note ?? DBNull.Value);
    }
    private static void AddLoan(MySqlCommand q, LoanInput x)
    {
        q.Parameters.AddWithValue("@user", x.UserId); q.Parameters.AddWithValue("@title", x.LoanTitle); q.Parameters.AddWithValue("@principal", x.PrincipalAmount); q.Parameters.AddWithValue("@rate", x.InterestRate); q.Parameters.AddWithValue("@term", x.TermMonths); q.Parameters.AddWithValue("@date", x.StartDate);
    }
    private static void ValidateAsset(AssetInput x)
    {
        if (x.UserId <= 0 || string.IsNullOrWhiteSpace(x.AssetName) || string.IsNullOrWhiteSpace(x.AssetType) || x.PurchasePrice < 0 || x.CurrentValue < 0 || x.SalvageValue < 0 || x.UsefulLifeYears < 0 || x.Currency?.Length > 10) throw new ArgumentException("Invalid asset values");
    }
    private static void ValidateLoan(LoanInput x)
    {
        if (x.UserId <= 0 || string.IsNullOrWhiteSpace(x.LoanTitle) || x.PrincipalAmount <= 0 || x.InterestRate < 0 || x.TermMonths <= 0 || x.StartDate == default) throw new ArgumentException("Invalid loan values");
    }
    private static long ParseUserId(string? value)
    {
        if (value is null) return 1;
        if (long.TryParse(value, NumberStyles.None, CultureInfo.InvariantCulture, out var id) && id > 0) return id;
        throw new ArgumentException("user_id must be a positive integer");
    }
    private static bool TryId(string path, string prefix, out long id)
    {
        id = 0;
        return path.StartsWith(prefix, StringComparison.Ordinal) && long.TryParse(path[prefix.Length..], NumberStyles.None, CultureInfo.InvariantCulture, out id) && id > 0;
    }
    private static async Task<T> ReadBodyAsync<T>(HttpListenerContext context) => await JsonSerializer.DeserializeAsync<T>(context.Request.InputStream, new JsonSerializerOptions { PropertyNameCaseInsensitive = true, PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower }) ?? throw new ArgumentException("Request body is required");
    private static async Task WriteJsonAsync(HttpListenerResponse response, int status, object value)
    {
        if (response.OutputStream.CanWrite)
        {
            var bytes = JsonSerializer.SerializeToUtf8Bytes(value, JsonOptions);
            response.StatusCode = status; response.ContentType = "application/json; charset=utf-8"; response.ContentLength64 = bytes.Length;
            try { await response.OutputStream.WriteAsync(bytes); } catch (HttpListenerException) { }
            response.Close();
        }
    }

    private sealed record AssetInput(long UserId, string AssetName, string AssetType, decimal PurchasePrice, decimal CurrentValue, decimal SalvageValue, int UsefulLifeYears, DateTime? PurchaseDate, string? Currency, string? Note);
    private sealed record LoanInput(long UserId, string LoanTitle, decimal PrincipalAmount, decimal InterestRate, int TermMonths, DateTime StartDate);
    private sealed record InvestmentInput(long UserId, string Symbol, string InvestmentType, decimal Quantity, decimal BuyPrice, decimal CurrentPrice);
    private sealed record DepreciationInput(decimal PurchasePrice, decimal SalvageValue, int UsefulLifeYears);
    private sealed record LoanCalculationInput(decimal PrincipalAmount, decimal InterestRate, int TermMonths);
}
