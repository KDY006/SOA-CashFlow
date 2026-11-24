using AssetService.Final.Models;
using Microsoft.EntityFrameworkCore;
namespace AssetService.Final.Data;

public sealed class AssetDbContext(DbContextOptions<AssetDbContext> options) : DbContext(options)
{
    public DbSet<Asset> Assets => Set<Asset>();
    public DbSet<Loan> Loans => Set<Loan>();
    public DbSet<Investment> Investments => Set<Investment>();
    public DbSet<AssetHistory> AssetHistory => Set<AssetHistory>();
    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        modelBuilder.Entity<Asset>(e => {
            e.ToTable("assets");
            e.HasKey(x => x.Id);
            e.Property(x => x.Id).HasColumnName("id").ValueGeneratedOnAdd();
            e.Property(x => x.UserId).HasColumnName("user_id");
            e.Property(x => x.AssetName).HasColumnName("asset_name").HasMaxLength(100);
            e.Property(x => x.AssetType).HasColumnName("asset_type").HasMaxLength(50);
            e.Property(x => x.PurchasePrice).HasColumnName("purchase_price").HasPrecision(18, 2);
            e.Property(x => x.CurrentValue).HasColumnName("current_value").HasPrecision(18, 2).IsConcurrencyToken();
            e.Property(x => x.SalvageValue).HasColumnName("salvage_value").HasPrecision(18, 2);
            e.Property(x => x.UsefulLifeYears).HasColumnName("useful_life_years");
            e.Property(x => x.PurchaseDate).HasColumnName("purchase_date").HasColumnType("date");
            e.Property(x => x.Currency).HasColumnName("currency").HasMaxLength(10);
            e.Property(x => x.Note).HasColumnName("note").HasColumnType("text");
            e.Property(x => x.CreatedAt).HasColumnName("created_at").HasColumnType("timestamp");
            e.Property(x => x.UpdatedAt).HasColumnName("updated_at").HasColumnType("timestamp");
            e.HasIndex(x => x.UserId);
        });
        modelBuilder.Entity<Loan>(e => {
            e.ToTable("loans");
            e.HasKey(x => x.Id);
            e.Property(x => x.Id).HasColumnName("id").ValueGeneratedOnAdd();
            e.Property(x => x.UserId).HasColumnName("user_id");
            e.Property(x => x.LoanTitle).HasColumnName("loan_title").HasMaxLength(150);
            e.Property(x => x.PrincipalAmount).HasColumnName("principal_amount").HasPrecision(18, 2);
            e.Property(x => x.InterestRate).HasColumnName("interest_rate").HasPrecision(5, 2);
            e.Property(x => x.TermMonths).HasColumnName("term_months");
            e.Property(x => x.StartDate).HasColumnName("start_date").HasColumnType("date");
            e.Property(x => x.Status).HasColumnName("status").HasMaxLength(20);
            e.Property(x => x.CreatedAt).HasColumnName("created_at").HasColumnType("timestamp");
            e.Property(x => x.UpdatedAt).HasColumnName("updated_at").HasColumnType("timestamp");
            e.HasIndex(x => x.UserId);
        });
        modelBuilder.Entity<Investment>(e => {
            e.ToTable("investments");
            e.HasKey(x => x.Id);
            e.Property(x => x.Id).HasColumnName("id").ValueGeneratedOnAdd();
            e.Property(x => x.UserId).HasColumnName("user_id");
            e.Property(x => x.Symbol).HasColumnName("symbol").HasMaxLength(20);
            e.Property(x => x.InvestmentType).HasColumnName("investment_type").HasMaxLength(50);
            e.Property(x => x.Quantity).HasColumnName("quantity").HasPrecision(18, 4);
            e.Property(x => x.BuyPrice).HasColumnName("buy_price").HasPrecision(18, 2);
            e.Property(x => x.CurrentPrice).HasColumnName("current_price").HasPrecision(18, 2);
            e.Property(x => x.CreatedAt).HasColumnName("created_at").HasColumnType("timestamp");
            e.Property(x => x.UpdatedAt).HasColumnName("updated_at").HasColumnType("timestamp");
            e.HasIndex(x => x.UserId);
        });
        modelBuilder.Entity<AssetHistory>(e => {
            e.ToTable("asset_history");
            e.HasKey(x => x.Id);
            e.Property(x => x.Id).HasColumnName("id").ValueGeneratedOnAdd();
            e.Property(x => x.AssetId).HasColumnName("asset_id");
            e.Property(x => x.Value).HasColumnName("value").HasPrecision(18, 2);
            e.Property(x => x.RecordedAt).HasColumnName("recorded_at").HasColumnType("timestamp");
            e.HasOne(x => x.Asset).WithMany(x => x.History).HasForeignKey(x => x.AssetId).OnDelete(DeleteBehavior.Cascade);
        });
    }
}
