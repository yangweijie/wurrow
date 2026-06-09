using System;
using System.Diagnostics;
using System.Text.Json;
using System.Threading.Tasks;
using System.Windows.Controls;
using Microsoft.Web.WebView2.Core;

namespace Wurrow
{
    /// <summary>
    /// Hand-written partial class for MainWindow.
    /// Wires up TreemapBridge for WebView2 ↔ C# communication
    /// and handles TabControl SelectionChanged for lazy loading.
    /// </summary>
    public partial class MainWindow
    {
        private TreemapBridge? _treemapBridge;
        private bool _softwareLoaded = false;
        private bool _settingsLoaded = false;

        /// <summary>
        /// Called after WebView2 is initialized (hook from generated code).
        /// </summary>
        partial void OnAfterWebViewInit()
        {
            // ── TreemapBridge ──
            _treemapBridge = new TreemapBridge(webView, App.Api);

            _treemapBridge.OnDrillDown += async (path) =>
            {
                if (string.IsNullOrEmpty(path))
                    await AnalyzeAndPushAsync(analyzePath);
                else
                {
                    analyzePath = path;
                    await AnalyzeAndPushAsync(path);
                }
            };

            _treemapBridge.OnTrashRequest += async (path) =>
            {
                try
                {
                    await App.Api.PostAsync<object>("/api/analyze/trash",
                        new { path });
                    await AnalyzeAndPushAsync(analyzePath);
                }
                catch (Exception ex)
                {
                    Debug.WriteLine($"[Wurrow] Trash error: {ex.Message}");
                }
            };

            // ── Tab selection for lazy loading ──
            tabControl_main.SelectionChanged += OnTabSelectionChanged;

            Debug.WriteLine("[Wurrow] TreemapBridge + Tab listeners initialized.");
        }

        /// <summary>
        /// Handles tab changes to auto-load data on first visit.
        /// </summary>
        private async void OnTabSelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (e.Source is not TabControl) return;

            var index = tabControl_main.SelectedIndex;
            Debug.WriteLine($"[Wurrow] Tab selected: {index}");

            // Settings tab (index 6): auto-load on first visit
            if (index == 6 && !_settingsLoaded)
            {
                _settingsLoaded = true;
                await LoadSettingsAsync();
            }
        }

        /// <summary>
        /// Loads settings from the PHP API and populates the Settings tab.
        /// </summary>
        private async Task LoadSettingsAsync()
        {
            try
            {
                var result = await App.Api.GetAsync<JsonElement>("/api/settings");
                if (result != null)
                {
                    var config = result.Value;
                    if (config.TryGetProperty("serverPort", out var port))
                        serverPort = port.GetInt32();
                    if (config.TryGetProperty("cleanTemp", out var ct))
                        cleanTemp = ct.GetBoolean();
                    if (config.TryGetProperty("cleanBrowser", out var cb))
                        cleanBrowser = cb.GetBoolean();
                    if (config.TryGetProperty("cleanThumbs", out var th))
                        cleanThumbs = th.GetBoolean();
                    if (config.TryGetProperty("cleanPrefetch", out var pf))
                        cleanPrefetch = pf.GetBoolean();
                    if (config.TryGetProperty("cleanRecycle", out var rc))
                        cleanRecycle = rc.GetBoolean();
                    Dispatcher.Invoke(UpdateUI);
                    Debug.WriteLine("[Wurrow] Settings loaded from backend.");
                }
            }
            catch (Exception ex)
            {
                Debug.WriteLine($"[Wurrow] Settings load error: {ex.Message}");
            }
        }

        /// <summary>
        /// Loads the software list from the PHP API and populates panel_softwareList.
        /// Shared with the sort/search button handlers generated from SoftwareView.
        /// </summary>
        private async Task LoadSoftwareListAsync(string sortBy)
        {
            try
            {
                softwareLoading = true;
                Dispatcher.Invoke(UpdateUI);

                var result = await App.Api.GetAsync<JsonElement>(
                    $"/api/software/list?sort={Uri.EscapeDataString(sortBy)}");

                if (result != null)
                {
                    RenderSoftwareList(result.Value);
                }
            }
            catch (Exception ex)
            {
                Debug.WriteLine($"[Wurrow] Software list error: {ex.Message}");
            }
            finally
            {
                softwareLoading = false;
                Dispatcher.Invoke(UpdateUI);
            }
        }

        /// <summary>
        /// Renders software list entries into panel_softwareList.
        /// </summary>
        private void RenderSoftwareList(JsonElement result)
        {
            panel_softwareList.Children.Clear();
            var apps = result.GetProperty("apps");
            foreach (var app in apps.EnumerateArray())
            {
                var row = new StackPanel
                {
                    Orientation = Orientation.Horizontal,
                    Margin = new System.Windows.Thickness(0, 2)
                };
                var nameTb = new System.Windows.Controls.TextBlock
                {
                    Text = app.GetProperty("name").GetString(),
                    Foreground = System.Windows.Media.Brushes.White,
                    FontSize = 12,
                    Width = 200
                };
                var verTb = new System.Windows.Controls.TextBlock
                {
                    Text = app.GetProperty("version").GetString(),
                    Foreground = System.Windows.Media.Brushes.Gray,
                    FontSize = 11,
                    Width = 80,
                    FontFamily = new System.Windows.Media.FontFamily("Cascadia Code")
                };
                var sizeTb = new System.Windows.Controls.TextBlock
                {
                    Text = app.GetProperty("sizeStr").GetString(),
                    Foreground = System.Windows.Media.Brushes.Gray,
                    FontSize = 11,
                    Width = 80,
                    FontFamily = new System.Windows.Media.FontFamily("Cascadia Code")
                };
                row.Children.Add(nameTb);
                row.Children.Add(verTb);
                row.Children.Add(sizeTb);
                panel_softwareList.Children.Add(row);
            }
        }

        /// <summary>
        /// Scan a path via the PHP API and push results to the WebView2 treemap.
        /// </summary>
        private async Task AnalyzeAndPushAsync(string path)
        {
            if (string.IsNullOrEmpty(path)) return;

            try
            {
                analyzeSummary = "Scanning...";
                Dispatcher.Invoke(UpdateUI);

                var result = await App.Api.GetAsync<JsonElement>(
                    "/api/analyze?path=" + Uri.EscapeDataString(path));

                if (result != null)
                {
                    var entries = result.Value.GetProperty("entries");
                    analyzeSummary = "Found " + entries.GetArrayLength() + " items";
                    webView.CoreWebView2.PostWebMessageAsJson(
                        JsonSerializer.Serialize(new { entries }));
                    Dispatcher.Invoke(UpdateUI);
                }
            }
            catch (Exception ex)
            {
                analyzeSummary = "Scan error: " + ex.Message;
                Dispatcher.Invoke(UpdateUI);
            }
        }
    }
}
