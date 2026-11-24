using AssetService.Final.Security;
using Microsoft.EntityFrameworkCore;

namespace AssetService.Final.Middleware;

public sealed class ExceptionMiddleware(RequestDelegate next, ILogger<ExceptionMiddleware> logger)
{
    public async Task InvokeAsync(HttpContext context)
    {
        try { await next(context); }
        catch (OperationCanceledException) when (context.RequestAborted.IsCancellationRequested) { }
        catch (Exception ex) when (!context.Response.HasStarted)
        {
            var (status, message) = ex switch {
                ArgumentException => (400, ex.Message),
                KeyNotFoundException => (404, ex.Message),
                UnauthorizedAccessException => (401, "Authentication required"),
                ForbiddenException => (403, ex.Message),
                DbUpdateConcurrencyException => (409, "Record changed concurrently; reload and retry"),
                _ => (500, "Database or server error")
            };
            if (status == 500) logger.LogError(ex, "Unhandled request error {TraceId}", context.TraceIdentifier);
            context.Response.Clear();
            context.Response.StatusCode = status;
            await context.Response.WriteAsJsonAsync(new { error = message, trace_id = context.TraceIdentifier });
        }
    }
}
