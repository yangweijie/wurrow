using System;
using System.IO;
using System.Net.Http;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace Wurrow
{
    /// <summary>
    /// SSE (Server-Sent Events) 流式客户端。
    /// 对应 Burrow 的 CommandRunner — 逐行读取 PHP 后端的 SSE 输出，
    /// 通过回调函数将事件分发给 UI 层。
    /// </summary>
    public class StreamingClient
    {
        private readonly ApiClient apiClient;
        private CancellationTokenSource? cts;

        public StreamingClient(ApiClient apiClient)
        {
            this.apiClient = apiClient;
        }

        /// <summary>
        /// 开始流式请求。
        /// onEvent: (eventType, data) => void — 每个 SSE 事件回调。
        /// onComplete: () => void — 流结束回调。
        /// onError: (Exception) => void — 错误回调。
        /// </summary>
        public async Task StartAsync(
            string endpoint,
            Action<string, JsonElement> onEvent,
            Action? onComplete = null,
            Action<Exception>? onError = null,
            object? postData = null)
        {
            cts = new CancellationTokenSource();
            try
            {
                var response = await apiClient.PostStreamAsync(endpoint, postData);
                if (response == null)
                {
                    onError?.Invoke(new Exception("Failed to get streaming response"));
                    return;
                }

                using var stream = await response.Content.ReadAsStreamAsync(cts.Token);
                using var reader = new StreamReader(stream);

                string? currentEvent = "message";
                string dataBuffer = "";

                while (!reader.EndOfStream && !cts.Token.IsCancellationRequested)
                {
                    var line = await reader.ReadLineAsync(cts.Token);
                    if (line == null) break;

                    if (line.StartsWith("event: "))
                    {
                        currentEvent = line.Substring(7).Trim();
                    }
                    else if (line.StartsWith("data: "))
                    {
                        dataBuffer += line.Substring(6);
                    }
                    else if (string.IsNullOrWhiteSpace(line))
                    {
                        // Empty line = event dispatch
                        if (!string.IsNullOrEmpty(dataBuffer))
                        {
                            try
                            {
                                var jsonData = JsonSerializer.Deserialize<JsonElement>(dataBuffer);
                                onEvent(currentEvent ?? "message", jsonData);
                            }
                            catch (JsonException)
                            {
                                // Non-JSON data, skip
                            }
                            dataBuffer = "";
                            currentEvent = "message";
                        }
                    }
                }

                onComplete?.Invoke();
            }
            catch (OperationCanceledException)
            {
                // Normal cancellation
                onComplete?.Invoke();
            }
            catch (Exception ex)
            {
                onError?.Invoke(ex);
            }
        }

        /// <summary>
        /// 取消当前流式请求。
        /// </summary>
        public void Cancel()
        {
            cts?.Cancel();
        }
    }
}
