using System.Globalization;
using System.Net;
using System.Text.Json;

namespace AssetService.Midterm.Common;

public static class HttpHelper
{
    public static readonly JsonSerializerOptions JsonOptions = new() { PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower };

    public static async Task<T> ReadBodyAsync<T>(HttpListenerContext context)
    {
        var result = await JsonSerializer.DeserializeAsync<T>(
            context.Request.InputStream, 
            new JsonSerializerOptions { PropertyNameCaseInsensitive = true, PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower }
        );
        return result ?? throw new ArgumentException("Request body is required");
    }

    public static async Task WriteJsonAsync(HttpListenerResponse response, int status, object value)
    {
        if (response.OutputStream.CanWrite)
        {
            var bytes = JsonSerializer.SerializeToUtf8Bytes(value, JsonOptions);
            response.StatusCode = status;
            response.ContentType = "application/json; charset=utf-8";
            response.ContentLength64 = bytes.Length;
            try { await response.OutputStream.WriteAsync(bytes); } catch (HttpListenerException) { }
            response.Close();
        }
    }

    public static long AuthUserId(HttpListenerRequest request) =>
        long.TryParse(request.Headers["X-User-Id"], NumberStyles.None, CultureInfo.InvariantCulture, out var id) && id > 0 ? id : 0;

    public static bool IsAdmin(HttpListenerRequest request) =>
        string.Equals(request.Headers["X-User-Role"], "ADMIN", StringComparison.OrdinalIgnoreCase);

    public static long ResolveUserId(HttpListenerRequest request)
    {
        var auth = AuthUserId(request);
        var query = request.QueryString["user_id"];
        if (auth > 0) return IsAdmin(request) && query is not null ? ParseUserId(query) : auth;
        return ParseUserId(query);
    }

    public static long BodyUserId(HttpListenerRequest request, long bodyUserId, long fallbackUserId)
    {
        var auth = AuthUserId(request);
        if (auth == 0) return bodyUserId > 0 ? bodyUserId : fallbackUserId;
        return IsAdmin(request) && bodyUserId > 0 ? bodyUserId : auth;
    }

    public static long ParseUserId(string? value)
    {
        if (value is null) return 1;
        if (long.TryParse(value, NumberStyles.None, CultureInfo.InvariantCulture, out var id) && id > 0) return id;
        throw new ArgumentException("user_id must be a positive integer");
    }

    public static bool TryParseId(string path, string prefix, out long id)
    {
        id = 0;
        return path.StartsWith(prefix, StringComparison.Ordinal) 
            && long.TryParse(path[prefix.Length..], NumberStyles.None, CultureInfo.InvariantCulture, out id) 
            && id > 0;
    }
}
