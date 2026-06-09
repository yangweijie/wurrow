<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Backend\Services;

/**
 * 安装包管理服务 — 对应 Tool::Installer。
 *
 * 扫描并管理系统中残留的安装包文件:
 * - 下载目录中的 .exe / .msi / .dmg 安装程序
 * - Windows Temp 中的临时安装文件
 * - 孤立的安装包提取目录
 */
final class InstallerService
{
    private PowerShellRunner $ps;
    private bool $cancelled = false;

    /** 安装包扩展名 */
    private const INSTALLER_EXTS = ['.exe', '.msi', '.msp', '.appx', '.appxbundle'];

    /** 搜索位置 */
    private const SEARCH_LOCATIONS = [
        'Downloads' => [
            'path'    => '%USERPROFILE%\\Downloads',
            'admin'   => false,
        ],
        'Desktop' => [
            'path'    => '%USERPROFILE%\\Desktop',
            'admin'   => false,
        ],
        'Temp Installers' => [
            'path'    => '%TEMP%',
            'admin'   => false,
        ],
    ];

    public function __construct()
    {
        $this->ps = new PowerShellRunner();
    }

    /**
     * 预览模式 — 扫描安装包文件。
     *
     * @param callable $emit SSE 发送回调
     */
    public function preview(callable $emit): void
    {
        $this->cancelled = false;
        $totalSize = 0;
        $totalItems = 0;
        $categories = 0;
        $totalCategories = count(self::SEARCH_LOCATIONS);
        $processed = 0;

        foreach (self::SEARCH_LOCATIONS as $category => $config) {
            if ($this->cancelled) break;

            $emit('line', ['marker' => 'group', 'text' => $category]);

            if (!PowerShellRunner::isWindows()) {
                $emit('line', ['marker' => 'info', 'text' => "Scanning {$config['path']} (simulated)"]);
                $categories++;
                $processed++;
                $emit('progress', (int) ($processed / max(1, $totalCategories) * 100));
                continue;
            }

            $items = $this->scanLocation($config['path']);
            if (!empty($items)) {
                foreach ($items as $item) {
                    if ($this->cancelled) break;
                    $sizeStr = $this->formatBytes($item['size']);
                    $emit('line', [
                        'marker' => 'action',
                        'text'   => "{$item['name']} — {$sizeStr}",
                        'path'   => $item['path'],
                    ]);
                    $totalSize += $item['size'];
                    $totalItems++;
                }
                $categories++;
            } else {
                $emit('line', ['marker' => 'ok', 'text' => 'No installers found']);
            }

            $processed++;
            $emit('progress', (int) ($processed / max(1, $totalCategories) * 100));
        }

        $emit('summary', [
            'space'      => $this->formatBytes($totalSize),
            'items'      => (string) $totalItems,
            'categories' => (string) $categories,
        ]);
        $emit('done', ['status' => 'preview_complete']);
    }

    /**
     * 执行清理 — 删除扫描到的安装包。
     *
     * @param callable $emit SSE 发送回调
     */
    public function execute(callable $emit): void
    {
        $this->cancelled = false;
        $totalSize = 0;
        $totalItems = 0;
        $categories = 0;
        $totalCategories = count(self::SEARCH_LOCATIONS);
        $processed = 0;

        foreach (self::SEARCH_LOCATIONS as $category => $config) {
            if ($this->cancelled) break;

            $emit('line', ['marker' => 'group', 'text' => $category]);

            if (!PowerShellRunner::isWindows()) {
                $emit('line', ['marker' => 'info', 'text' => "Cleaning {$config['path']} (simulated)"]);
                $categories++;
                $processed++;
                $emit('progress', (int) ($processed / max(1, $totalCategories) * 100));
                continue;
            }

            $items = $this->scanLocation($config['path']);
            if (!empty($items)) {
                foreach ($items as $item) {
                    if ($this->cancelled) break;
                    if ($this->deleteFile($item['path'])) {
                        $sizeStr = $this->formatBytes($item['size']);
                        $emit('line', [
                            'marker' => 'ok',
                            'text'   => "Removed {$item['name']} — freed {$sizeStr}",
                            'path'   => $item['path'],
                        ]);
                        $totalSize += $item['size'];
                        $totalItems++;
                    } else {
                        $emit('line', [
                            'marker' => 'error',
                            'text'   => "Failed to remove {$item['name']}",
                            'path'   => $item['path'],
                        ]);
                    }
                }
                $categories++;
            } else {
                $emit('line', ['marker' => 'ok', 'text' => 'Already clean']);
            }

            $processed++;
            $emit('progress', (int) ($processed / max(1, $totalCategories) * 100));
        }

        $emit('summary', [
            'space'      => $this->formatBytes($totalSize),
            'items'      => (string) $totalItems,
            'categories' => (string) $categories,
        ]);
        $emit('done', ['status' => 'installer_clean_complete']);
    }

    /**
     * 取消当前操作。
     */
    public function cancel(): void
    {
        $this->cancelled = true;
        $this->ps->cancel();
    }

    /**
     * 扫描指定位置的安装包文件。
     */
    private function scanLocation(string $locationPattern): array
    {
        $resolved = $this->resolvePath($locationPattern);
        if (!is_dir($resolved)) return [];

        $extensions = implode("', '", self::INSTALLER_EXTS);

        $script = <<<PS
\$location = \$env:WURROW_INSTALLER_PATH;
\$exts = @('{$extensions}');
Get-ChildItem -Path \$location -Force -ErrorAction SilentlyContinue |
  Where-Object { (-not \$_.PSIsContainer) -and (\$exts -contains \$_.Extension) } |
  Sort-Object Length -Descending |
  ForEach-Object {
    Write-Output ("FILE|" + \$_.FullName + "|" + \$_.Name + "|" + \$_.Length)
  }
PS;

        $result = $this->ps->runSync($script, ['WURROW_INSTALLER_PATH' => $resolved]);
        $items = [];

        foreach ($result['output'] as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'FILE|')) {
                $parts = explode('|', $line);
                $items[] = [
                    'path' => $parts[1] ?? '',
                    'name' => $parts[2] ?? '',
                    'size' => (int) ($parts[3] ?? 0),
                ];
            }
        }

        return $items;
    }

    /**
     * 删除单个文件。
     */
    private function deleteFile(string $path): bool
    {
        if (!file_exists($path)) return true;

        $script = <<<'PS'
$path = $env:WURROW_INSTALLER_DELETE;
if (Test-Path $path) {
  Remove-Item -Path $path -Force -ErrorAction SilentlyContinue;
  if (Test-Path $path) { Write-Output "FAIL" } else { Write-Output "OK" }
} else { Write-Output "OK" }
PS;

        $result = $this->ps->runSync($script, ['WURROW_INSTALLER_DELETE' => $path]);
        $output = trim(implode('', $result['output']));
        return str_contains($output, 'OK');
    }

    /**
     * 解析路径中的环境变量。
     */
    private function resolvePath(string $path): string
    {
        return preg_replace_callback('/%(\w+)%/', function ($m) {
            return getenv($m[1]) ?: $m[0];
        }, $path) ?? $path;
    }

    /**
     * 格式化字节大小。
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1_048_576) return number_format($bytes / 1024, 1) . ' KB';
        if ($bytes < 1_073_741_824) return number_format($bytes / 1_048_576, 1) . ' MB';
        return number_format($bytes / 1_073_741_824, 2) . ' GB';
    }
}
