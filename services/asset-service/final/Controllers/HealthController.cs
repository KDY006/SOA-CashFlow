using AssetService.Final.Data;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
namespace AssetService.Final.Controllers;
[ApiController, AllowAnonymous]
public sealed class HealthController(AssetDbContext db) : ControllerBase
{
    [HttpGet("/"), HttpGet("/health")]
    public IActionResult Health() => Ok(new { service="asset-service",status="UP",version="2.0.0-final" });
    [HttpGet("/health/ready")]
    public async Task<IActionResult> Ready(CancellationToken ct) => await db.Database.CanConnectAsync(ct)
        ? Ok(new {status="UP",database="UP"}) : StatusCode(503,new {status="DOWN",database="DOWN"});
}
