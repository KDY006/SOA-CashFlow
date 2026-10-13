using System.Globalization;
using AssetService.Midterm.Common;
using AssetService.Midterm.Models;
using MySql.Data.MySqlClient;

namespace AssetService.Midterm.Handlers;

public static class LoanHandler
{
    public static object GetLoans(long userId)
    {
        return Database.Query("SELECT id,user_id,loan_title,principal_amount,interest_rate,term_months,start_date,status FROM loans WHERE user_id=@user ORDER BY id", userId);
    }

    public static object CreateLoan(LoanInput x)
    {
        ValidateLoan(x);
        using var c = Database.GetConnection();
        using var q = new MySqlCommand("INSERT INTO loans(user_id,loan_title,principal_amount,interest_rate,term_months,start_date) VALUES(@user,@title,@principal,@rate,@term,@date)", c);
        q.Parameters.AddWithValue("@user", x.UserId);
        q.Parameters.AddWithValue("@title", x.LoanTitle);
        q.Parameters.AddWithValue("@principal", x.PrincipalAmount);
        q.Parameters.AddWithValue("@rate", x.InterestRate);
        q.Parameters.AddWithValue("@term", x.TermMonths);
        q.Parameters.AddWithValue("@date", x.StartDate);
        q.ExecuteNonQuery();
        
        var id = Convert.ToInt64(new MySqlCommand("SELECT LAST_INSERT_ID()", c).ExecuteScalar(), CultureInfo.InvariantCulture);
        return Database.GetById("loans", id, x.UserId);
    }

    public static object CalculateLoan(LoanCalculationInput x)
    {
        if (x.PrincipalAmount <= 0 || x.InterestRate < 0 || x.InterestRate > 999.99m || x.TermMonths <= 0)
            throw new ArgumentException("Require principal_amount > 0, annual_interest_rate between 0 and 999.99, and term_months > 0");

        var r = (double)(x.InterestRate / 100m / 12m);
        var factor = Math.Pow(1 + r, x.TermMonths);
        var payment = r == 0 ? (double)x.PrincipalAmount / x.TermMonths : (double)x.PrincipalAmount * r * factor / (factor - 1);

        if (!double.IsFinite(payment) || payment > (double)decimal.MaxValue)
            throw new ArgumentException("Loan values exceed supported calculation range");

        return new { 
            principal_amount = x.PrincipalAmount, 
            annual_interest_rate = x.InterestRate, 
            term_months = x.TermMonths, 
            monthly_payment = decimal.Round((decimal)payment, 2) 
        };
    }

    private static void ValidateLoan(LoanInput x)
    {
        if (x.UserId <= 0 || string.IsNullOrWhiteSpace(x.LoanTitle) || x.PrincipalAmount <= 0 || x.InterestRate < 0 || x.TermMonths <= 0 || x.StartDate == default)
            throw new ArgumentException("Invalid loan values");
    }
}