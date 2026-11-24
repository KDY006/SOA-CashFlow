using AssetService.Final.Models;
namespace AssetService.Final.DTOs;

public sealed record AssetResponse(long Id, long UserId, string AssetName, string AssetType, decimal PurchasePrice,
    decimal CurrentValue, decimal SalvageValue, int UsefulLifeYears, DateTime? PurchaseDate, string? Currency, string? Note,
    DateTime? CreatedAt, DateTime? UpdatedAt)
{
    public static AssetResponse From(Asset a) => new(a.Id,a.UserId,a.AssetName,a.AssetType,a.PurchasePrice,a.CurrentValue,a.SalvageValue,a.UsefulLifeYears,a.PurchaseDate,a.Currency,a.Note,a.CreatedAt,a.UpdatedAt);
}
public sealed record LoanResponse(long Id, long UserId, string LoanTitle, decimal PrincipalAmount, decimal InterestRate,
    int TermMonths, DateTime StartDate, string Status, DateTime? CreatedAt, DateTime? UpdatedAt)
{
    public static LoanResponse From(Loan a) => new(a.Id,a.UserId,a.LoanTitle,a.PrincipalAmount,a.InterestRate,a.TermMonths,a.StartDate,a.Status,a.CreatedAt,a.UpdatedAt);
}
public sealed record InvestmentResponse(long Id, long UserId, string Symbol, string InvestmentType, decimal Quantity, decimal BuyPrice,
    decimal CurrentPrice, DateTime? CreatedAt, DateTime? UpdatedAt, decimal CostBasis, decimal MarketValue, decimal ProfitLoss, decimal? Roi)
{
    public static InvestmentResponse From(Investment a) {
        var cost = a.Quantity * a.BuyPrice; var value = a.Quantity * a.CurrentPrice;
        return new(a.Id,a.UserId,a.Symbol,a.InvestmentType,a.Quantity,a.BuyPrice,a.CurrentPrice,a.CreatedAt,a.UpdatedAt,
            decimal.Round(cost,2),decimal.Round(value,2),decimal.Round(value-cost,2),cost == 0 ? null : decimal.Round((value-cost)/cost*100,2));
    }
}
public sealed record DepreciationResult(decimal PurchasePrice, decimal SalvageValue, int UsefulLifeYears, decimal AnnualDepreciation, decimal MonthlyDepreciation);
public sealed record LoanCalculationResult(decimal PrincipalAmount, decimal AnnualInterestRate, int TermMonths, decimal MonthlyPayment, decimal TotalPayment, decimal TotalInterest);
public sealed record LoanInstallment(int Period, DateTime DueDate, decimal Payment, decimal Principal, decimal Interest, decimal RemainingBalance);
public sealed record HistoryResponse(long Id, long AssetId, decimal Value, DateTime? RecordedAt);
public sealed record ValuationResponse(long AssetId, DateTime AsOf, decimal ElapsedYears, decimal EstimatedBookValue, string? Currency, string Method);
public sealed record PortfolioResponse(long UserId, decimal TotalCost, decimal TotalValue, decimal ProfitLoss, decimal? Roi, string Currency);
public sealed record NetWorthResponse(long UserId, decimal TotalNetWorth, string Currency, decimal TotalAssets, decimal TotalDebt, decimal InvestmentValue, int ExcludedNonVndAssets, string Basis);
public sealed record DeleteResponse(string Message, long Id);
