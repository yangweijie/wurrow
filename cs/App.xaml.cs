using System;
using System.Diagnostics;
using System.IO;
using System.Net.Sockets;
using System.Text.Json;
using System.Threading.Tasks;
using System.Windows;

namespace Wurrow
{
    /// <summary>
    /// WPF 应用入口。启动时自动运行 PHP 后端服务器，关闭时终止。
    ///
    /// 对应 Burrow 的 @main App struct — 应用生命周期管理。
    /// PHP 服务器通过 php -S localhost:7891 src/Backend/Server.php 启动。
    /// </summary>
    public partial class App : Application
    {
        private Process? phpProcess;
        private readonly int serverPort = 7891;
        private readonly ApiClient apiClient;
        private readonly StreamingClient streamingClient;

        public App()
        {
            apiClient = new ApiClient(serverPort);
            streamingClient = new StreamingClient(apiClient);
        }

        /// <summary>
        /// 全局 ApiClient 实例，供所有视图使用。
        /// </summary>
        public static ApiClient Api => ((App)Current).apiClient;

        /// <summary>
        /// 全局 StreamingClient 实例，供所有视图使用。
        /// </summary>
        public static StreamingClient Stream => ((App)Current).streamingClient;

        protected override async void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);
            await StartPhpServerAsync();
        }

        protected override void OnExit(ExitEventArgs e)
        {
            StopPhpServer();
            base.OnExit(e);
        }

        /// <summary>
        /// 启动 PHP 内置服务器。
        /// 如果端口已被占用，假定已有实例在运行，跳过启动。
        /// </summary>
        private async Task StartPhpServerAsync()
        {
            if (IsPortInUse(serverPort))
            {
                Debug.WriteLine($"[Wurrow] Port {serverPort} already in use, assuming PHP server is running.");
                return;
            }

            try
            {
                // 查找项目根目录（从可执行文件位置向上查找）
                var projectRoot = FindProjectRoot();
                if (projectRoot == null)
                {
                    MessageBox.Show(
                        "Could not find Wurrow project root.\nEnsure the app is run from the build output directory.",
                        "Wurrow Error", MessageBoxButton.OK, MessageBoxImage.Error);
                    return;
                }

                var serverScript = Path.Combine(projectRoot, "src", "Backend", "Server.php");
                if (!File.Exists(serverScript))
                {
                    MessageBox.Show(
                        $"Server.php not found at:\n{serverScript}",
                        "Wurrow Error", MessageBoxButton.OK, MessageBoxImage.Error);
                    return;
                }

                var psi = new ProcessStartInfo
                {
                    FileName = "php",
                    Arguments = $"-S localhost:{serverPort} \"{serverScript}\"",
                    WorkingDirectory = projectRoot,
                    UseShellExecute = false,
                    CreateNoWindow = true,
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                };

                phpProcess = Process.Start(psi);
                if (phpProcess == null)
                {
                    MessageBox.Show("Failed to start PHP server process.", "Wurrow Error",
                        MessageBoxButton.OK, MessageBoxImage.Error);
                    return;
                }

                Debug.WriteLine($"[Wurrow] PHP server started (PID: {phpProcess.Id})");

                // 等待服务器就绪
                await WaitForServerAsync();
            }
            catch (Exception ex)
            {
                MessageBox.Show(
                    $"Failed to start PHP server:\n{ex.Message}\n\nEnsure PHP is installed and in PATH.",
                    "Wurrow Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        /// <summary>
        /// 等待 PHP 服务器就绪（最多 5 秒）。
        /// </summary>
        private async Task WaitForServerAsync()
        {
            for (int i = 0; i < 20; i++)
            {
                try
                {
                    var health = await apiClient.GetAsync<JsonElement>("/api/health");
                    if (health != null)
                    {
                        Debug.WriteLine("[Wurrow] PHP server is ready.");
                        return;
                    }
                }
                catch { }
                await Task.Delay(250);
            }
            Debug.WriteLine("[Wurrow] PHP server did not respond within timeout.");
        }

        /// <summary>
        /// 终止 PHP 服务器进程。
        /// </summary>
        private void StopPhpServer()
        {
            if (phpProcess != null && !phpProcess.HasExited)
            {
                try
                {
                    phpProcess.Kill(entireProcessTree: true);
                    phpProcess.Dispose();
                    Debug.WriteLine("[Wurrow] PHP server stopped.");
                }
                catch (Exception ex)
                {
                    Debug.WriteLine($"[Wurrow] Error stopping PHP server: {ex.Message}");
                }
            }
        }

        /// <summary>
        /// 检查端口是否被占用。
        /// </summary>
        private static bool IsPortInUse(int port)
        {
            try
            {
                using var client = new TcpClient();
                client.Connect("localhost", port);
                return true;
            }
            catch
            {
                return false;
            }
        }

        /// <summary>
        /// 查找项目根目录（包含 composer.json 的目录）。
        /// 从可执行文件位置向上遍历。
        /// </summary>
        private static string? FindProjectRoot()
        {
            // 优先使用环境变量（开发时设置）
            var envRoot = Environment.GetEnvironmentVariable("WURROW_ROOT");
            if (!string.IsNullOrEmpty(envRoot) && Directory.Exists(envRoot))
                return envRoot;

            // 从可执行文件位置向上查找
            var dir = AppDomain.CurrentDomain.BaseDirectory;
            while (dir != null)
            {
                if (File.Exists(Path.Combine(dir, "composer.json")))
                    return dir;
                dir = Directory.GetParent(dir)?.FullName;
            }

            return null;
        }
    }
}
