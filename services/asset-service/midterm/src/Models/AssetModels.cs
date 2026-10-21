namespace AssetService.Midterm.Models;

public sealed record AssetInput(
    long UserId, 
    string AssetName, 
    string AssetType, 
    decimal PurchasePrice, 
    decimal CurrentValue, 
    decimal SalvageValue, 
    int UsefulLifeYears, 
    DateTime? PurchaseDate, 
    string? Currency, 
    string? Note
);

public sealed record DepreciationInput(
    decimal PurchasePrice, 
    decimal SalvageValue, 
    int UsefulLifeYears
);