using AssetService.Final.Data;
using AssetService.Final.DTOs;
using AssetService.Final.Models;
using Microsoft.EntityFrameworkCore;
namespace AssetService.Final.Services;

public sealed class InvestmentService(AssetDbContext db)
{
    private IQueryable<Investment> Owned(long user)=>db.Investments.Where(a=>a.UserId==user);
    private async Task<Investment> Find(long user,long id,CancellationToken ct)=>await Owned(user).SingleOrDefaultAsync(a=>a.Id==id,ct)??throw new KeyNotFoundException("Investment not found");
    public async Task<IReadOnlyList<InvestmentResponse>> List(long user,CancellationToken ct)=>(await Owned(user).AsNoTracking().OrderBy(a=>a.Id).ToListAsync(ct)).Select(InvestmentResponse.From).ToList();
    public async Task<InvestmentResponse> Get(long user,long id,CancellationToken ct)=>InvestmentResponse.From(await Find(user,id,ct));
    public async Task<InvestmentResponse> Create(long user,InvestmentInput x,CancellationToken ct)
    {
        var a=new Investment{UserId=user,CreatedAt=DateTime.UtcNow}; Apply(a,x); db.Investments.Add(a); await db.SaveChangesAsync(ct); return InvestmentResponse.From(a);
    }
    public async Task<InvestmentResponse> Update(long user,long id,InvestmentInput x,CancellationToken ct)
    {
        var a=await Find(user,id,ct); Apply(a,x); await db.SaveChangesAsync(ct); return InvestmentResponse.From(a);
    }
    public async Task<DeleteResponse> Delete(long user,long id,CancellationToken ct)
    {
        db.Investments.Remove(await Find(user,id,ct)); await db.SaveChangesAsync(ct); return new("Investment deleted successfully",id);
    }
    public async Task<PortfolioResponse> Summary(long user,CancellationToken ct)
    {
        var list=await Owned(user).AsNoTracking().ToListAsync(ct);
        var cost=list.Sum(a=>a.Quantity*a.BuyPrice); var value=list.Sum(a=>a.Quantity*a.CurrentPrice);
        return new(user,decimal.Round(cost,2),decimal.Round(value,2),decimal.Round(value-cost,2),cost==0?null:decimal.Round((value-cost)/cost*100,2),"VND");
    }
    private static void Apply(Investment a,InvestmentInput x)
    {
        FinanceMath.Money(x.Quantity,"quantity",true,4,99999999999999.9999m);
        FinanceMath.Money(x.BuyPrice,"buy_price"); FinanceMath.Money(x.CurrentPrice,"current_price");
        if (Math.Max(x.BuyPrice,x.CurrentPrice)>0 && x.Quantity>FinanceMath.MaxMoney/Math.Max(x.BuyPrice,x.CurrentPrice))
            throw new ArgumentException("Investment position exceeds supported value range");
        a.Symbol=x.Symbol.Trim().ToUpperInvariant(); a.InvestmentType=x.InvestmentType.Trim().ToUpperInvariant();
        a.Quantity=x.Quantity; a.BuyPrice=x.BuyPrice; a.CurrentPrice=x.CurrentPrice; a.UpdatedAt=DateTime.UtcNow;
    }
}
