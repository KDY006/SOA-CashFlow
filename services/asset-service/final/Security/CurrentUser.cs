using System.Security.Claims;
using System.Globalization;

namespace AssetService.Final.Security;

public sealed class CurrentUser(IHttpContextAccessor accessor)
{
    public static bool TryReadId(ClaimsPrincipal? principal, out long id) =>
        long.TryParse(principal?.FindFirst("userId")?.Value ?? principal?.FindFirst("user_id")?.Value ?? principal?.FindFirst("id")?.Value,
            NumberStyles.None, CultureInfo.InvariantCulture, out id) && id > 0;

    // Only signed claims determine identity. Admin may explicitly target another user.
    public long Resolve(long? queryId = null, long? bodyId = null)
    {
        var principal = accessor.HttpContext?.User;
        if (!TryReadId(principal, out var id)) throw new UnauthorizedAccessException();
        if (queryId is <= 0 || bodyId is <= 0) throw new ArgumentException("user_id must be positive");
        if (queryId.HasValue && bodyId.HasValue && queryId != bodyId)
            throw new ArgumentException("Query and body user_id must match");
        var target = queryId ?? bodyId ?? id;
        if (target != id && !string.Equals(principal?.FindFirst("role")?.Value, "ADMIN", StringComparison.OrdinalIgnoreCase))
            throw new ForbiddenException("Cannot access another user's records");
        return target;
    }
}
public sealed class ForbiddenException(string message) : Exception(message) { }
