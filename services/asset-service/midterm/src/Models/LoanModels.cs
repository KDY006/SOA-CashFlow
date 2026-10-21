namespace AssetService.Midterm.Models;

public sealed record LoanInput(
    long UserId, 
    string LoanTitle, 
    decimal PrincipalAmount, 
    decimal InterestRate, 
    int TermMonths, 
    DateTime StartDate
);

public sealed record LoanCalculationInput(
    decimal PrincipalAmount, 
    decimal InterestRate, 
    int TermMonths
);