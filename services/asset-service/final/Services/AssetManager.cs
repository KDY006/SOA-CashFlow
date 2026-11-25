using AssetService.Final.Data;
using AssetService.Final.DTOs;
using AssetService.Final.Models;
using Microsoft.EntityFrameworkCore;
namespace AssetService.Final.Services;

public sealed class AssetManager(AssetDbContext db)
{
    private IQueryable<Asset> Owned(long user) => db.Assets.Where(a => a.UserId == user);
    private async Task<Asset> Find(long user, long id, CancellationToken ct) =>
        await Owned(user).SingleOrDefaultAsync(a => a.Id == id, ct) ?? throw new KeyNotFoundException("Asset not found");
    public async Task<IReadOnlyList<AssetResponse>> List(long user, CancellationToken ct) =>
        (await Owned(user).AsNoTracking().OrderBy(a=>a.Id).ToListAsync(ct)).Select(AssetResponse.From).ToList();
    public async Task<AssetResponse> Get(long user,long id,CancellationToken ct) => AssetResponse.From(await Find(user,id,ct));
    public async Task<AssetResponse> Create(long user, AssetInput x, CancellationToken ct)
    {
        var a = new Asset { UserId = user, CreatedAt = DateTime.UtcNow };
        Apply(a,x);
        a.History.Add(new AssetHistory { Value=a.CurrentValue, RecordedAt=DateTime.UtcNow });
        db.Assets.Add(a);
        // Both INSERTs are atomic in the transaction created by SaveChangesAsync.
        await db.SaveChangesAsync(ct);
        return AssetResponse.From(a);
    }
    public async Task<AssetResponse> Update(long user,long id,AssetInput x,CancellationToken ct)
    {
        var a=await Find(user,id,ct); var old=a.CurrentValue;
        Apply(a,x);
        if (old!=a.CurrentValue) a.History.Add(new AssetHistory { Value=a.CurrentValue, RecordedAt=DateTime.UtcNow });
        await db.SaveChangesAsync(ct);
        return AssetResponse.From(a);
    }
    public async Task<DeleteResponse> Delete(long user,long id,CancellationToken ct)
    {
        db.Assets.Remove(await Find(user,id,ct)); await db.SaveChangesAsync(ct);
        return new("Asset deleted successfully",id);
    }
    public async Task<IReadOnlyList<HistoryResponse>> History(long user,long id,CancellationToken ct)
    {
        await Find(user,id,ct);
        return await db.AssetHistory.AsNoTracking().Where(h=>h.AssetId==id && h.Asset.UserId==user)
            .OrderBy(h=>h.RecordedAt).ThenBy(h=>h.Id).Select(h=>new HistoryResponse(h.Id,h.AssetId,h.Value,h.RecordedAt)).ToListAsync(ct);
    }
    public async Task<ValuationResponse> Valuation(long user,long id,DateTime? asOf,CancellationToken ct)
    {
        var a=await Find(user,id,ct); var date=(asOf??DateTime.UtcNow).Date;
        if (!a.PurchaseDate.HasValue || a.UsefulLifeYears<=0) throw new ArgumentException("Valuation requires purchase_date and positive useful_life_years");
        if(date<a.PurchaseDate.Value.Date) throw new ArgumentException("as_of cannot precede purchase_date");
        // Actual days / 365.2425 approximates fractional calendar years; clamp at useful life anniversary.
        var start=a.PurchaseDate.Value.Date;
        decimal years=(decimal)(date-start).TotalDays/365.2425m;
        if (a.UsefulLifeYears<=9999-start.Year && date>=start.AddYears(a.UsefulLifeYears)) years=Math.Max(years,a.UsefulLifeYears);
        return new(id,date,decimal.Round(years,4),FinanceMath.BookValue(a.PurchasePrice,a.SalvageValue,a.UsefulLifeYears,years),a.Currency,"STRAIGHT_LINE_ACTUAL_DAYS");
    }
    private static void Apply(Asset a,AssetInput x)
    {
        FinanceMath.Money(x.PurchasePrice,"purchase_price"); FinanceMath.Money(x.CurrentValue,"current_value"); FinanceMath.Money(x.SalvageValue,"salvage_value");
        if (x.SalvageValue>x.PurchasePrice || x.UsefulLifeYears is <0 or >1000) throw new ArgumentException("Invalid salvage_value or useful_life_years");
        if (x.PurchaseDate.HasValue && x.PurchaseDate.Value.Year<1000) throw new ArgumentException("Invalid purchase_date");
        a.AssetName=x.AssetName.Trim(); a.AssetType=x.AssetType.Trim().ToUpperInvariant();
        a.PurchasePrice=x.PurchasePrice; a.CurrentValue=x.CurrentValue; a.SalvageValue=x.SalvageValue;
        a.UsefulLifeYears=x.UsefulLifeYears; a.PurchaseDate=x.PurchaseDate?.Date;
        a.Currency=string.IsNullOrWhiteSpace(x.Currency)?"VND":x.Currency.Trim().ToUpperInvariant(); a.Note=x.Note; a.UpdatedAt=DateTime.UtcNow;
    }
}
