using System;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;

namespace Wurrow
{
    /// <summary>
    /// HTTP 客户端封装，用于与 PHP 后端通信。
    /// 基础 URL: http://localhost:7891
    /// </summary>
    public class ApiClient
    {
        private static readonly HttpClient client = new HttpClient();
        private readonly string baseUrl;

        public ApiClient(int port = 7891)
        {
            baseUrl = $"http://localhost:{port}";
            client.Timeout = TimeSpan.FromSeconds(30);
        }

        public async Task<T?> GetAsync<T>(string endpoint) where T : class
        {
            try
            {
                var response = await client.GetAsync($"{baseUrl}{endpoint}");
                response.EnsureSuccessStatusCode();
                var json = await response.Content.ReadAsStringAsync();
                return JsonSerializer.Deserialize<T>(json);
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"API GET error: {ex.Message}");
                return null;
            }
        }

        public async Task<T?> PostAsync<T>(string endpoint, object? data = null) where T : class
        {
            try
            {
                var content = data != null
                    ? new StringContent(JsonSerializer.Serialize(data), Encoding.UTF8, "application/json")
                    : new StringContent("{}", Encoding.UTF8, "application/json");

                var response = await client.PostAsync($"{baseUrl}{endpoint}", content);
                response.EnsureSuccessStatusCode();
                var json = await response.Content.ReadAsStringAsync();
                return JsonSerializer.Deserialize<T>(json);
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"API POST error: {ex.Message}");
                return null;
            }
        }

        public async Task<HttpResponseMessage?> PostStreamAsync(string endpoint, object? data = null)
        {
            try
            {
                var request = new HttpRequestMessage(HttpMethod.Post, $"{baseUrl}{endpoint}");
                if (data != null)
                {
                    request.Content = new StringContent(
                        JsonSerializer.Serialize(data),
                        Encoding.UTF8,
                        "application/json"
                    );
                }

                var response = await client.SendAsync(request, HttpCompletionOption.ResponseHeadersRead);
                response.EnsureSuccessStatusCode();
                return response;
            }
            catch (Exception ex)
            {
                Console.Error.WriteLine($"API Stream error: {ex.Message}");
                return null;
            }
        }
    }
}
