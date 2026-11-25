using AssetService.Final.Data;
using AssetService.Final.DTOs;
using AssetService.Final.Models;
using Microsoft.EntityFrameworkCore;
namespace AssetService.Final.Services;

public sealed class LoanService(AssetDbContext db)
{
    private IQueryable<Loan> Owned(long user)=>db.Loans.Where(a=>a.UserId==user);
    private async Task<Loan> Find(long user,long id,CancellationToken ct)=>await Owned(user).SingleOrDefaultAsync(a=>a.Id==id,ct)??throw new KeyNotFoundException("Loan not found");
    public async Task<IReadOnlyList<LoanResponse>> List(long user,CancellationToken ct)=>(await Owned(user).AsNoTracking().OrderBy(a=>a.Id).ToListAsync(ct)).Select(LoanResponse.From).ToList();
    public async Task<LoanResponse> Get(long user,long id,CancellationToken ct)=>LoanResponse.From(await Find(user,id,ct));
    public async Task<LoanResponse> Create(long user,LoanInput x,CancellationToken ct)
    {
        var a=new Loan{UserId=user,CreatedAt=DateTime.UtcNow}; Apply(a,x); db.Loans.Add(a); await db.SaveChangesAsync(ct); return LoanResponse.From(a);
    }
    public async Task<LoanResponse> Update(long user,long id,LoanInput x,CancellationToken ct)
    {
        var a=await Find(user,id,ct); Apply(a,x); await db.SaveChangesAsync(ct); return LoanResponse.From(a);
    }
    public async Task<DeleteResponse> Delete(long user,long id,CancellationToken ct)
    {
        db.Loans.Remove(await Find(user,id,ct)); await db.SaveChangesAsync(ct); return new("Loan deleted successfully",id);
    }
    public async Task<LoanResponse> SetStatus(long user,long id,string status,CancellationToken ct)
    {
        var a=await Find(user,id,ct); a.Status=ValidateStatus(status); a.UpdatedAt=DateTime.UtcNow; await db.SaveChangesAsync(ct); return LoanResponse.From(a);
    }
    public async Task<IReadOnlyList<LoanInstallment>> Schedule(long user,long id,CancellationToken ct)
    {
        var a=await Find(user,id,ct); ValidateDate(a.StartDate,a.TermMonths);
        return FinanceMath.Schedule(a.PrincipalAmount,a.InterestRate,a.TermMonths,a.StartDate);
    }
    private static string ValidateStatus(string status) => status.Trim().ToUpperInvariant() switch {
        "ACTIVE"=>"ACTIVE", "CLOSED"=>"CLOSED", _=>throw new ArgumentException("status must be ACTIVE or CLOSED")
    };
    private static void ValidateDate(DateTime date,int months)
    {
        if(date.Year<1000 || months> (9999-date.Year)*12+12-date.Month) throw new ArgumentException("Invalid start_date or schedule exceeds date range");
    }
    private static void Apply(Loan a,LoanInput x)
    {
        FinanceMath.ValidateLoan(x.PrincipalAmount,x.InterestRate,x.TermMonths); ValidateDate(x.StartDate,x.TermMonths);
        a.LoanTitle=x.LoanTitle.Trim(); a.PrincipalAmount=x.PrincipalAmount; a.InterestRate=x.InterestRate;
        a.TermMonths=x.TermMonths; a.StartDate=x.StartDate.Date; if(x.Status!=null) a.Status=ValidateStatus(x.Status); a.UpdatedAt=DateTime.UtcNow;
    }
}
