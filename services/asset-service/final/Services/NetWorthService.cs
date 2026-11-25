using AssetService.Final.Data;
using AssetService.Final.DTOs;
using Microsoft.EntityFrameworkCore;
namespace AssetService.Final.Services;

public sealed class NetWorthService(AssetDbContext db)
{
    public async Task<NetWorthResponse> Get(long user,CancellationToken ct)
    {
        // A consistent read across tables; keep the original net-worth contract and expose investments separately.
        await using var tx=await db.Database.BeginTransactionAsync(System.Data.IsolationLevel.RepeatableRead,ct);
        var assets=await db.Assets.AsNoTracking().Where(a=>a.UserId==user).Select(a=>new {a.Currency,a.CurrentValue}).ToListAsync(ct);
        var vnd=assets.Where(a=>string.IsNullOrEmpty(a.Currency)||string.Equals(a.Currency,"VND",StringComparison.OrdinalIgnoreCase)).ToList();
        var total=vnd.Sum(a=>a.CurrentValue);
        var debt=await db.Loans.Where(a=>a.UserId==user && a.Status=="ACTIVE").SumAsync(a=>(decimal?)a.PrincipalAmount,ct)??0m;
        var inv=await db.Investments.AsNoTracking().Where(a=>a.UserId==user).Select(a=>new {a.Quantity,a.CurrentPrice}).ToListAsync(ct);
        await tx.CommitAsync(ct);
        return new(user,total-debt,"VND",total,debt,decimal.Round(inv.Sum(a=>a.Quantity*a.CurrentPrice),2),assets.Count-vnd.Count,
            "VND assets minus active loan principal; investment value reported separately to avoid double counting; no FX or payment ledger");
    }
}
