using AssetService.Final.DTOs;
using AssetService.Final.Services;
using AssetService.Final.Security;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
namespace AssetService.Final.Controllers;

[ApiController, Authorize, Route("api/loans")]
[Produces("application/json")]
[ProducesResponseType(400), ProducesResponseType(401), ProducesResponseType(403), ProducesResponseType(404), ProducesResponseType(409), ProducesResponseType(500)]
public sealed class LoansController(LoanService service, CurrentUser user) : ControllerBase
{
    [HttpGet]
    public async Task<ActionResult<IReadOnlyList<LoanResponse>>> List([FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.List(user.Resolve(userId),ct));
    [HttpGet("{id:long:min(1)}")]
    public async Task<ActionResult<LoanResponse>> Get(long id,[FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.Get(user.Resolve(userId),id,ct));
    [HttpPost, ProducesResponseType(201)]
    public async Task<ActionResult<LoanResponse>> Create(LoanInput input,[FromQuery(Name="user_id")] long? userId,CancellationToken ct)
    {
        var result=await service.Create(user.Resolve(userId,input.UserId),input,ct);
        return CreatedAtAction(nameof(Get),new {id=result.Id,user_id=result.UserId},result);
    }
    [HttpPut("{id:long:min(1)}")]
    public async Task<ActionResult<LoanResponse>> Update(long id,LoanInput input,[FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.Update(user.Resolve(userId,input.UserId),id,input,ct));
    [HttpDelete("{id:long:min(1)}")]
    public async Task<ActionResult<DeleteResponse>> Delete(long id,[FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.Delete(user.Resolve(userId),id,ct));
    [HttpPost("calculate")]
    public ActionResult<LoanCalculationResult> Calculate(LoanCalculationInput input) => Ok(FinanceMath.CalculateLoan(input));
    [HttpGet("{id:long:min(1)}/schedule")]
    public async Task<ActionResult<IReadOnlyList<LoanInstallment>>> Schedule(long id,[FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.Schedule(user.Resolve(userId),id,ct));
    [HttpPatch("{id:long:min(1)}/status")]
    public async Task<ActionResult<LoanResponse>> SetStatus(long id,LoanStatusInput input,[FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.SetStatus(user.Resolve(userId),id,input.Status,ct));
}
