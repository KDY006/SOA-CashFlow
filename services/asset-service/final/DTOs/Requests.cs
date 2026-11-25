using System.ComponentModel.DataAnnotations;
namespace AssetService.Final.DTOs;

public sealed class AssetInput
{
    public long? UserId { get; set; }
    [Required, StringLength(100)] public string AssetName { get; set; } = "";
    [Required, StringLength(50)] public string AssetType { get; set; } = "";
    public decimal PurchasePrice { get; set; }
    public decimal CurrentValue { get; set; }
    public decimal SalvageValue { get; set; }
    public int UsefulLifeYears { get; set; }
    public DateTime? PurchaseDate { get; set; }
    [StringLength(10)] public string? Currency { get; set; }
    [StringLength(16000)] public string? Note { get; set; }
}
public sealed class LoanInput
{
    public long? UserId { get; set; }
    [Required, StringLength(150)] public string LoanTitle { get; set; } = "";
    public decimal PrincipalAmount { get; set; }
    public decimal InterestRate { get; set; }
    public int TermMonths { get; set; }
    public DateTime StartDate { get; set; }
    [StringLength(20)] public string? Status { get; set; }
}
public sealed class InvestmentInput
{
    public long? UserId { get; set; }
    [Required, StringLength(20)] public string Symbol { get; set; } = "";
    [Required, StringLength(50)] public string InvestmentType { get; set; } = "";
    public decimal Quantity { get; set; }
    public decimal BuyPrice { get; set; }
    public decimal CurrentPrice { get; set; }
}
public sealed record DepreciationInput(decimal PurchasePrice, decimal SalvageValue, int UsefulLifeYears);
public sealed record LoanCalculationInput(decimal PrincipalAmount, decimal InterestRate, int TermMonths);
public sealed record LoanStatusInput([property: Required] string Status);
