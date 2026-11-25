using AssetService.Final.DTOs;
using AssetService.Final.Services;
using AssetService.Final.Security;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
namespace AssetService.Final.Controllers;

[ApiController, Authorize, Route("api/assets")]
[Produces("application/json")]
[ProducesResponseType(400), ProducesResponseType(401), ProducesResponseType(403), ProducesResponseType(404), ProducesResponseType(409), ProducesResponseType(500)]
public sealed class AssetsController(AssetManager service, CurrentUser user, NetWorthService netWorth) : ControllerBase
{
    [HttpGet]
    public async Task<ActionResult<IReadOnlyList<AssetResponse>>> List([FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.List(user.Resolve(userId),ct));
    [HttpGet("{id:long:min(1)}")]
    public async Task<ActionResult<AssetResponse>> Get(long id,[FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.Get(user.Resolve(userId),id,ct));
    [HttpPost, ProducesResponseType(201)]
    public async Task<ActionResult<AssetResponse>> Create(AssetInput input,[FromQuery(Name="user_id")] long? userId,CancellationToken ct)
    {
        var result=await service.Create(user.Resolve(userId,input.UserId),input,ct);
        return CreatedAtAction(nameof(Get),new {id=result.Id,user_id=result.UserId},result);
    }
    [HttpPut("{id:long:min(1)}")]
    public async Task<ActionResult<AssetResponse>> Update(long id,AssetInput input,[FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.Update(user.Resolve(userId,input.UserId),id,input,ct));
    [HttpDelete("{id:long:min(1)}")]
    public async Task<ActionResult<DeleteResponse>> Delete(long id,[FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.Delete(user.Resolve(userId),id,ct));
    [HttpPost("depreciation")]
    public ActionResult<DepreciationResult> Depreciation(DepreciationInput input) => Ok(FinanceMath.Depreciation(input));
    [HttpGet("net-worth")]
    public async Task<ActionResult<NetWorthResponse>> NetWorth([FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await netWorth.Get(user.Resolve(userId),ct));
    [HttpGet("{id:long:min(1)}/history")]
    public async Task<ActionResult<IReadOnlyList<HistoryResponse>>> History(long id,[FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.History(user.Resolve(userId),id,ct));
    [HttpGet("{id:long:min(1)}/valuation")]
    public async Task<ActionResult<ValuationResponse>> Valuation(long id,[FromQuery(Name="as_of")] DateTime? asOf,[FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.Valuation(user.Resolve(userId),id,asOf,ct));
}
