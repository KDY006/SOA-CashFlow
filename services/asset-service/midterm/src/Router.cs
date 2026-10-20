using System.Net;
using System.Text.Json;
using AssetService.Midterm.Common;
using AssetService.Midterm.Handlers;
using AssetService.Midterm.Models;

namespace AssetService.Midterm;

public static class Router
{
    public static async Task DispatchAsync(HttpListenerContext context)
    {
        var response = context.Response;
        response.AddHeader("Access-Control-Allow-Origin", "*");
        response.AddHeader("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS");
        response.AddHeader("Access-Control-Allow-Headers", "Content-Type");

        if (context.Request.HttpMethod == "OPTIONS")
        {
            response.StatusCode = 204;
            response.Close();
            return;
        }

        try
        {
            var path = context.Request.Url?.AbsolutePath.TrimEnd('/').ToLowerInvariant() ?? "";
            var method = context.Request.HttpMethod;
            object? result;
            int status = 200;

            var userId = HttpHelper.ParseUserId(context.Request.QueryString["user_id"]);

            // --- ANALYTICS & THUẬT TOÁN ---
            if (path == "/api/assets/net-worth" && method == "GET") 
                result = AnalyticsHandler.GetNetWorth(userId);
            else if (path == "/api/assets/depreciation" && method == "POST") 
                result = AssetHandler.CalculateDepreciation(await HttpHelper.ReadBodyAsync<DepreciationInput>(context));
            else if (path == "/api/loans/calculate" && method == "POST") 
                result = LoanHandler.CalculateLoan(await HttpHelper.ReadBodyAsync<LoanCalculationInput>(context));
            
            // --- ASSETS (TÀI SẢN) ---
            else if (path == "/api/assets" && method == "GET") 
                result = AssetHandler.GetAssets(userId);
            else if (path == "/api/assets" && method == "POST") 
                { result = AssetHandler.CreateAsset(await HttpHelper.ReadBodyAsync<AssetInput>(context)); status = 201; }
            else if (HttpHelper.TryParseId(path, "/api/assets/", out var assetId) && method == "GET") 
                result = Database.GetById("assets", assetId, userId);
            else if (HttpHelper.TryParseId(path, "/api/assets/", out assetId) && method == "PUT") 
                result = AssetHandler.UpdateAsset(assetId, userId, await HttpHelper.ReadBodyAsync<AssetInput>(context));
            else if (HttpHelper.TryParseId(path, "/api/assets/", out assetId) && method == "DELETE") 
                result = AssetHandler.DeleteAsset(assetId, userId);

            // --- LOANS (KHOẢN VAY) ---
            else if (path == "/api/loans" && method == "GET") 
                result = LoanHandler.GetLoans(userId);
            else if (path == "/api/loans" && method == "POST") 
                { result = LoanHandler.CreateLoan(await HttpHelper.ReadBodyAsync<LoanInput>(context)); status = 201; }
            else if (HttpHelper.TryParseId(path, "/api/loans/", out var loanId) && method == "GET") 
                result = Database.GetById("loans", loanId, userId);
            else if (HttpHelper.TryParseId(path, "/api/loans/", out loanId) && method == "PUT") 
                result = LoanHandler.UpdateLoan(loanId, userId, await HttpHelper.ReadBodyAsync<LoanInput>(context));
            else if (HttpHelper.TryParseId(path, "/api/loans/", out loanId) && method == "DELETE") 
                result = LoanHandler.DeleteLoan(loanId, userId);

            // --- INVESTMENTS (ĐẦU TƯ) ---
            else if (path == "/api/investments" && method == "GET") 
                result = InvestmentHandler.GetInvestments(userId);
            else if (path == "/api/investments" && method == "POST") 
                { result = InvestmentHandler.CreateInvestment(await HttpHelper.ReadBodyAsync<InvestmentInput>(context)); status = 201; }
            else if (HttpHelper.TryParseId(path, "/api/investments/", out var invId) && method == "GET") 
                result = Database.GetById("investments", invId, userId);
            else if (HttpHelper.TryParseId(path, "/api/investments/", out invId) && method == "PUT") 
                result = InvestmentHandler.UpdateInvestment(invId, userId, await HttpHelper.ReadBodyAsync<InvestmentInput>(context));
            else if (HttpHelper.TryParseId(path, "/api/investments/", out invId) && method == "DELETE") 
                result = InvestmentHandler.DeleteInvestment(invId, userId);

            // --- 404 NOT FOUND ---
            else 
                { status = 404; result = new { error = "Endpoint not found" }; }

            await HttpHelper.WriteJsonAsync(response, status, result);
        }
        catch (JsonException) { await HttpHelper.WriteJsonAsync(response, 400, new { error = "Invalid JSON request body" }); }
        catch (ArgumentException ex) { await HttpHelper.WriteJsonAsync(response, 400, new { error = ex.Message }); }
        catch (KeyNotFoundException ex) { await HttpHelper.WriteJsonAsync(response, 404, new { error = ex.Message }); }
        catch (Exception ex)
        {
            Console.Error.WriteLine(ex);
            await HttpHelper.WriteJsonAsync(response, 500, new { error = "Database or server error" });
        }
    }
}