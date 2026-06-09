using System;
using System.Diagnostics;
using System.Text.Json;
using System.Threading.Tasks;
using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.Wpf;

namespace Wurrow
{
    /// <summary>
    /// WebView2 ↔ C# 桥接，用于 Treemap 可视化交互。
    ///
    /// 处理 JS → C# 消息（下钻、回收站、刷新）
    /// 提供 C# → JS 调用（更新 treemap 数据）
    ///
    /// 对应 Burrow AnalyzeView 中的 AnalyzeModel 管理逻辑。
    /// </summary>
    public class TreemapBridge
    {
        private readonly WebView2 webView;
        private readonly ApiClient apiClient;
        private bool isReady;

        /// <summary>
        /// 当用户点击 treemap 块要求下钻时触发。
        /// 参数: 目录路径
        /// </summary>
        public event Action<string>? OnDrillDown;

        /// <summary>
        /// 当用户右键选择"移到回收站"时触发。
        /// 参数: 文件/目录路径
        /// </summary>
        public event Action<string>? OnTrashRequest;

        /// <summary>
        /// 当 WebView2 初始化完成时触发。
        /// </summary>
        public event Action? OnReady;

        public TreemapBridge(WebView2 webView, ApiClient apiClient)
        {
            this.webView = webView;
            this.apiClient = apiClient;
            SetupMessageHandler();
        }

        /// <summary>
        /// 注册 WebView2 消息处理器。
        /// JS 端通过 window.chrome.webview.postMessage({ type: 'drill', path: '...' }) 发送消息。
        /// </summary>
        private void SetupMessageHandler()
        {
            webView.CoreWebView2InitializationCompleted += (s, e) =>
            {
                if (e.IsSuccess)
                {
                    webView.CoreWebView2.WebMessageReceived += HandleWebMessage;
                    isReady = true;
                    OnReady?.Invoke();
                    Debug.WriteLine("[TreemapBridge] WebView2 ready.");
                }
                else
                {
                    Debug.WriteLine($"[TreemapBridge] WebView2 init failed: {e.InitializationException?.Message}");
                }
            };
        }

        /// <summary>
        /// 处理来自 JS 的消息。
        /// 支持的消息类型: drill, trash, refresh
        /// </summary>
        private void HandleWebMessage(object? sender, CoreWebView2WebMessageReceivedEventArgs e)
        {
            try
            {
                var json = e.WebMessageAsJson;
                var msg = JsonSerializer.Deserialize<JsonElement>(json);

                if (!msg.TryGetProperty("type", out var typeEl)) return;
                var type = typeEl.GetString();

                switch (type)
                {
                    case "drill":
                        if (msg.TryGetProperty("path", out var drillPath))
                        {
                            var path = drillPath.GetString();
                            if (!string.IsNullOrEmpty(path))
                            {
                                Debug.WriteLine($"[TreemapBridge] Drill down: {path}");
                                OnDrillDown?.Invoke(path);
                            }
                        }
                        break;

                    case "trash":
                        if (msg.TryGetProperty("path", out var trashPath))
                        {
                            var path = trashPath.GetString();
                            if (!string.IsNullOrEmpty(path))
                            {
                                Debug.WriteLine($"[TreemapBridge] Trash request: {path}");
                                OnTrashRequest?.Invoke(path);
                            }
                        }
                        break;

                    case "refresh":
                        Debug.WriteLine("[TreemapBridge] Refresh requested.");
                        OnDrillDown?.Invoke(""); // empty = re-scan current
                        break;

                    default:
                        Debug.WriteLine($"[TreemapBridge] Unknown message type: {type}");
                        break;
                }
            }
            catch (Exception ex)
            {
                Debug.WriteLine($"[TreemapBridge] Message parse error: {ex.Message}");
            }
        }

        /// <summary>
        /// 将扫描结果数据发送给 JS 端，更新 treemap 可视化。
        /// 调用 JS 全局函数 updateTreemap(jsonData)。
        /// </summary>
        public async Task UpdateTreemapAsync(JsonElement data)
        {
            if (!isReady) return;

            try
            {
                var json = data.GetRawText();
                // 转义 JS 字符串
                var escaped = json.Replace("\\", "\\\\").Replace("`", "\\`");
                await webView.CoreWebView2.ExecuteScriptAsync($"updateTreemap(`{escaped}`)");
            }
            catch (Exception ex)
            {
                Debug.WriteLine($"[TreemapBridge] UpdateTreemap error: {ex.Message}");
            }
        }

        /// <summary>
        /// 扫描指定路径并将结果推送到 treemap。
        /// </summary>
        public async Task ScanAndUpdateAsync(string path)
        {
            try
            {
                var encodedPath = Uri.EscapeDataString(path);
                var result = await apiClient.GetAsync<JsonElement>($"/api/analyze?path={encodedPath}");
                if (result != null)
                {
                    await UpdateTreemapAsync(result.Value);
                }
            }
            catch (Exception ex)
            {
                Debug.WriteLine($"[TreemapBridge] ScanAndUpdate error: {ex.Message}");
            }
        }
    }
}
