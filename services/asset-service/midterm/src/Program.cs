using System.Net;

namespace AssetService.Midterm;

internal static class Program
{
    private static async Task Main()
    {
        var port = Environment.GetEnvironmentVariable("PORT") ?? "8083";
        // Sử dụng LISTEN_HOST="+" hoặc mặc định "+" để chấp nhận kết nối từ mọi giao diện mạng (kể cả Docker Bridge)
        var host = Environment.GetEnvironmentVariable("LISTEN_HOST");
        if (string.IsNullOrWhiteSpace(host) || host == "0.0.0.0") host = "+";
        
        using var listener = new HttpListener();
        listener.Prefixes.Add($"http://{host}:{port}/");
        
        try
        {
            listener.Start();
            Console.WriteLine($"[Asset Service Native C#] Running on http://{host}:{port}/...");
        }
        catch (Exception ex)
        {
            Console.Error.WriteLine($"[Asset Service] Failed to start HttpListener: {ex.Message}");
            return;
        }

        while (listener.IsListening)
        {
            HttpListenerContext context;
            try { context = await listener.GetContextAsync(); }
            catch (HttpListenerException) when (!listener.IsListening) { break; }
            
            _ = Task.Run(() => Router.DispatchAsync(context));
        }
    }
}
