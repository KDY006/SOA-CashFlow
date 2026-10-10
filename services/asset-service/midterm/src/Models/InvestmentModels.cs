namespace AssetService.Midterm.Models;

public sealed record InvestmentInput(
    long UserId, 
    string Symbol, 
    string InvestmentType, 
    decimal Quantity, 
    decimal BuyPrice, 
    decimal CurrentPrice
);