using System.Text;
using System.Text.Json;
using AssetService.Final.Data;
using AssetService.Final.Middleware;
using AssetService.Final.Security;
using AssetService.Final.Services;
using Microsoft.AspNetCore.Authentication.JwtBearer;
using Microsoft.EntityFrameworkCore;
using Microsoft.IdentityModel.Tokens;
using Microsoft.OpenApi.Models;
using Microsoft.AspNetCore.Mvc;

var builder = WebApplication.CreateBuilder(args);
var cfg = builder.Configuration;
var connection = cfg["DB_CONNECTION"] ?? cfg.GetConnectionString("AssetDb")
    ?? "Server=localhost;Port=3306;Database=asset_db;Uid=root;Pwd=root_password;";
var secret = cfg["JWT_SECRET"] ?? cfg["AUTH_JWT_SECRET"]
    ?? throw new InvalidOperationException("Set JWT_SECRET to the same secret as Auth Service.");
if (Encoding.UTF8.GetByteCount(secret) < 32)
    throw new InvalidOperationException("JWT_SECRET must contain at least 32 UTF-8 bytes for HS256.");
builder.WebHost.UseUrls(cfg["ASPNETCORE_URLS"] ?? $"http://0.0.0.0:{cfg["PORT"] ?? "8083"}");
builder.Services.AddDbContext<AssetDbContext>(o => o.UseMySql(connection, new MySqlServerVersion(new Version(8, 0, 0))));
builder.Services.AddControllers().AddJsonOptions(o => o.JsonSerializerOptions.PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower);
builder.Services.Configure<ApiBehaviorOptions>(o => o.InvalidModelStateResponseFactory = ctx => new BadRequestObjectResult(new {
    error = "Validation failed", details = ctx.ModelState.Where(x => x.Value?.Errors.Count > 0)
        .ToDictionary(x => x.Key, x => x.Value!.Errors.Select(e => string.IsNullOrEmpty(e.ErrorMessage) ? "Invalid value" : e.ErrorMessage).ToArray())
}));
builder.Services.AddAuthentication(JwtBearerDefaults.AuthenticationScheme).AddJwtBearer(o => {
    o.MapInboundClaims = false;
    o.TokenValidationParameters = new TokenValidationParameters {
        ValidateIssuerSigningKey = true, IssuerSigningKey = new SymmetricSecurityKey(Encoding.UTF8.GetBytes(secret)),
        ValidAlgorithms = new[] { SecurityAlgorithms.HmacSha256 }, ValidateIssuer = false, ValidateAudience = false,
        ValidateLifetime = true, RequireExpirationTime = true, RequireSignedTokens = true,
        ClockSkew = TimeSpan.Zero, NameClaimType = "username", RoleClaimType = "role"
    };
    o.Events = new JwtBearerEvents { OnTokenValidated = ctx => {
        if (!CurrentUser.TryReadId(ctx.Principal, out _)) ctx.Fail("Invalid user identity");
        return Task.CompletedTask;
    }, OnChallenge = async ctx => {
        ctx.HandleResponse();
        ctx.Response.StatusCode = 401;
        ctx.Response.Headers["WWW-Authenticate"] = "Bearer";
        await ctx.Response.WriteAsJsonAsync(new { error = "Valid Bearer token required" });
    }, OnForbidden = async ctx => {
        ctx.Response.StatusCode = 403;
        await ctx.Response.WriteAsJsonAsync(new { error = "Access forbidden" });
    }};
});
builder.Services.AddAuthorization();
builder.Services.AddHttpContextAccessor();
builder.Services.AddScoped<CurrentUser>();
builder.Services.AddScoped<AssetManager>();
builder.Services.AddScoped<LoanService>();
builder.Services.AddScoped<InvestmentService>();
builder.Services.AddScoped<NetWorthService>();
builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen(o => {
    o.SwaggerDoc("v1", new OpenApiInfo { Title = "Asset & Investment Service", Version = "2.0.0-final" });
    o.AddSecurityDefinition("Bearer", new OpenApiSecurityScheme { Type = SecuritySchemeType.Http, Scheme = "bearer", BearerFormat = "JWT", Description = "Token issued by CashFlow Auth Service" });
    o.AddSecurityRequirement(new OpenApiSecurityRequirement { { new OpenApiSecurityScheme { Reference = new OpenApiReference { Type = ReferenceType.SecurityScheme, Id = "Bearer" } }, Array.Empty<string>() } });
});
var app = builder.Build();
app.UseMiddleware<ExceptionMiddleware>();
if (cfg.GetValue("ENABLE_SWAGGER", true)) { app.UseSwagger(); app.UseSwaggerUI(); }
app.UseAuthentication();
app.UseAuthorization();
app.MapControllers();
app.Run();
public partial class Program { }
