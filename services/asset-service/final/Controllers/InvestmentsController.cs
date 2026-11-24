using AssetService.Final.DTOs;
using AssetService.Final.Services;
using AssetService.Final.Security;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
namespace AssetService.Final.Controllers;

[ApiController, Authorize, Route("api/investments")]
[Produces("application/json")]
[ProducesResponseType(400), ProducesResponseType(401), ProducesResponseType(403), ProducesResponseType(404), ProducesResponseType(409), ProducesResponseType(500)]
public sealed class InvestmentsController(InvestmentService service, CurrentUser user) : ControllerBase
{
    [HttpGet]
    public async Task<ActionResult<IReadOnlyList<InvestmentResponse>>> List([FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.List(user.Resolve(userId),ct));
    [HttpGet("{id:long:min(1)}")]
    public async Task<ActionResult<InvestmentResponse>> Get(long id,[FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.Get(user.Resolve(userId),id,ct));
    [HttpPost, ProducesResponseType(201)]
    public async Task<ActionResult<InvestmentResponse>> Create(InvestmentInput input,[FromQuery(Name="user_id")] long? userId,CancellationToken ct)
    {
        var result=await service.Create(user.Resolve(userId,input.UserId),input,ct);
        return CreatedAtAction(nameof(Get),new {id=result.Id,user_id=result.UserId},result);
    }
    [HttpPut("{id:long:min(1)}")]
    public async Task<ActionResult<InvestmentResponse>> Update(long id,InvestmentInput input,[FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.Update(user.Resolve(userId,input.UserId),id,input,ct));
    [HttpDelete("{id:long:min(1)}")]
    public async Task<ActionResult<DeleteResponse>> Delete(long id,[FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.Delete(user.Resolve(userId),id,ct));
    [HttpGet("summary")]
    public async Task<ActionResult<PortfolioResponse>> Summary([FromQuery(Name="user_id")] long? userId,CancellationToken ct) => Ok(await service.Summary(user.Resolve(userId),ct));
}
