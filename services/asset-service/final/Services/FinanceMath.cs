using AssetService.Final.DTOs;
namespace AssetService.Final.Services;

public static class FinanceMath
{
    public const decimal MaxMoney = 9999999999999999.99m;
    public const int MaxTerm = 1200;
    public static void Money(decimal value, string name, bool positive = false, int scale = 2, decimal max = MaxMoney)
    {
        if (value < 0 || (positive && value == 0) || value > max || decimal.Round(value, scale) != value)
            throw new ArgumentException($"{name} is outside supported range or precision");
    }
    public static DepreciationResult Depreciation(DepreciationInput x)
    {
        Money(x.PurchasePrice, "purchase_price"); Money(x.SalvageValue, "salvage_value");
        if (x.SalvageValue > x.PurchasePrice || x.UsefulLifeYears is <= 0 or > 1000)
            throw new ArgumentException("Require purchase_price >= salvage_value and useful_life_years between 1 and 1000");
        var annual = (x.PurchasePrice-x.SalvageValue)/x.UsefulLifeYears;
        return new(x.PurchasePrice,x.SalvageValue,x.UsefulLifeYears,decimal.Round(annual,2),decimal.Round(annual/12,2));
    }
    public static void ValidateLoan(decimal principal, decimal rate, int months)
    {
        Money(principal,"principal_amount",true); Money(rate,"interest_rate",max:999.99m);
        if (months is <= 0 or > MaxTerm) throw new ArgumentException($"term_months must be between 1 and {MaxTerm}");
    }
    public static decimal ExactPayment(decimal principal, decimal rate, int months)
    {
        ValidateLoan(principal,rate,months);
        if (rate == 0) return principal / months;
        double r = (double)(rate/1200m);
        // Negative exponent avoids overflow for high rates / long terms.
        return (decimal)((double)principal*r/(1-Math.Pow(1+r,-months)));
    }
    public static LoanCalculationResult CalculateLoan(LoanCalculationInput x)
    {
        var payment = ExactPayment(x.PrincipalAmount,x.InterestRate,x.TermMonths);
        var total = decimal.Round(payment*x.TermMonths,2);
        return new(x.PrincipalAmount,x.InterestRate,x.TermMonths,decimal.Round(payment,2),total,total-x.PrincipalAmount);
    }
    public static IReadOnlyList<LoanInstallment> Schedule(decimal principal, decimal rate, int months, DateTime start)
    {
        var payment = decimal.Round(ExactPayment(principal,rate,months),2);
        var balance = principal; var rows = new List<LoanInstallment>();
        for (var i=1;i<=months;i++) {
            var interest = decimal.Round(balance*rate/1200m,2);
            var principalPart = i==months ? balance : Math.Min(balance, Math.Max(0,payment-interest));
            balance -= principalPart;
            rows.Add(new(i,start.AddMonths(i),principalPart+interest,principalPart,interest,balance));
        }
        return rows;
    }
    public static decimal BookValue(decimal purchase, decimal salvage, int life, decimal elapsedYears)
    {
        Depreciation(new(purchase,salvage,life));
        return decimal.Round(purchase-(purchase-salvage)*Math.Clamp(elapsedYears/life,0m,1m),2);
    }
}
