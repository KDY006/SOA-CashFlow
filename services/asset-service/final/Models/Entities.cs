using System.Text.Json.Serialization;
namespace AssetService.Final.Models;

public sealed class Asset
{
    public long Id { get; set; }
    public long UserId { get; set; }
    public string AssetName { get; set; } = "";
    public string AssetType { get; set; } = "";
    public decimal PurchasePrice { get; set; }
    public decimal CurrentValue { get; set; }
    public decimal SalvageValue { get; set; }
    public int UsefulLifeYears { get; set; }
    public DateTime? PurchaseDate { get; set; }
    public string? Currency { get; set; } = "VND";
    public string? Note { get; set; }
    public DateTime? CreatedAt { get; set; }
    public DateTime? UpdatedAt { get; set; }
    [JsonIgnore] public List<AssetHistory> History { get; set; } = new();
}
public sealed class AssetHistory
{
    public long Id { get; set; }
    public long AssetId { get; set; }
    public decimal Value { get; set; }
    public DateTime? RecordedAt { get; set; }
    [JsonIgnore] public Asset Asset { get; set; } = null!;
}
public sealed class Loan
{
    public long Id { get; set; }
    public long UserId { get; set; }
    public string LoanTitle { get; set; } = "";
    public decimal PrincipalAmount { get; set; }
    public decimal InterestRate { get; set; }
    public int TermMonths { get; set; }
    public DateTime StartDate { get; set; }
    public string Status { get; set; } = "ACTIVE";
    public DateTime? CreatedAt { get; set; }
    public DateTime? UpdatedAt { get; set; }
}
public sealed class Investment
{
    public long Id { get; set; }
    public long UserId { get; set; }
    public string Symbol { get; set; } = "";
    public string InvestmentType { get; set; } = "";
    public decimal Quantity { get; set; }
    public decimal BuyPrice { get; set; }
    public decimal CurrentPrice { get; set; }
    public DateTime? CreatedAt { get; set; }
    public DateTime? UpdatedAt { get; set; }
}
