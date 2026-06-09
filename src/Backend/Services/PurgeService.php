<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Backend\Services;

/**
 * 开发文件清理服务 — 对应 Tool::Purge。
 *
 * 清理开发过程中产生的缓存和临时文件:
 * - node_modules 目录
 * - vendor 目录 (Composer)
 * - Python __pycache__ / .pyc
 * - build / dist 目录
 * - IDE 配置 (.idea, .vscode)
 * - 日志文件 (*.log)
 * - 缓存目录 (npm, pip, composer)
 * - .DS_Store / Thumbs.db 等系统文件
 */
final class PurgeService
{
    private PowerShellRunner $ps;
    private bool $cancelled = false;

    /** 清理目标定义 */
    private const TARGETS = [
        'Node Modules' => [
            'patterns' => ['**/node_modules'],
            'admin'    => false,
            'desc'     => 'npm/yarn dependency directories',
        ],
        'PHP Vendor' => [
            'patterns' => ['**/vendor'],
            'admin'    => false,
            'desc'     => 'Composer dependency directories',
        ],
        'Python Cache' => [
            'patterns' => ['**/__pycache__', '**/*.pyc', '**/*.pyo'],
            'admin'    => false,
            'desc'     => 'Python bytecode cache files',
        ],
        'Build Output' => [
            'patterns' => ['**/build', '**/dist', '**/.next', '**/out'],
            'admin'    => false,
            'desc'     => 'Build/distribution output directories',
        ],
        'IDE Config' => [
            'patterns' => ['**/.idea', '**/.vscode', '**/.vs'],
            'admin'    => false,
            'desc'     => 'IDE project configuration directories',
        ],
        'Log Files' => [
            'patterns' => ['**/*.log', '**/logs'],
            'admin'    => false,
            'desc'     => 'Application log files',
        ],
        'System Junk' => [
            'patterns' => ['**/.DS_Store', '**/Thumbs.db', '**/desktop.ini'],
            'admin'    => false,
            'desc'     => 'OS-generated junk files',
        ],
    ];

    /** 要搜索的根目录列表 */
    private const SEARCH_ROOTS = [
        '%USERPROFILE%',
        '%HOMEDRIVE%%HOMEPATH%',
    ];

    public function __construct()
    {
        $this->ps = new PowerShellRunner();
    }

    /**
     * 预览模式 — 扫描开发缓存文件。
     *
     * @param callable $emit SSE 发送回调: function(string $event, mixed $data)
     */
    public function preview(callable $emit): void
    {
        $this->cancelled = false;
        $totalSize = 0;
        $totalItems = 0;
        $categories = 0;
        $totalCategories = count(self::TARGETS);
        $processed = 0;

        foreach (self::TARGETS as $category => $config) {
            if ($this->cancelled) break;

            $emit('line', ['marker' => 'group', 'text' => $category]);

            if (!PowerShellRunner::isWindows()) {
                $emit('line', ['marker' => 'info', 'text' => "{$config['desc']} (simulated)"]);
                $categories++;
                $processed++;
                $emit('progress', (int) ($processed / max(1, $totalCategories) * 100));
                continue;
            }

            $results = $this->scanCategory($config['patterns']);
            if (!empty($results)) {
                foreach ($results as $result) {
                    if ($this->cancelled) break;
                    $sizeStr = $this->formatBytes($result['size']);
                    $emit('line', [
                        'marker' => 'action',
                        'text'   => "{$result['path']} — {$sizeStr}",
                        'path'   => $result['path'],
                    ]);
                    $totalSize += $result['size'];
                    $totalItems++;
                }
                $categories++;
            } else {
                $emit('line', ['marker' => 'ok', 'text' => 'Nothing found']);
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
     * 执行清理 — 删除找到的开发缓存文件。
     *
     * @param callable $emit SSE 发送回调
     */
    public function execute(callable $emit): void
    {
        $this->cancelled = false;
        $totalSize = 0;
        $totalItems = 0;
        $categories = 0;
        $totalCategories = count(self::TARGETS);
        $processed = 0;

        foreach (self::TARGETS as $category => $config) {
            if ($this->cancelled) break;

            $emit('line', ['marker' => 'group', 'text' => $category]);

            if (!PowerShellRunner::isWindows()) {
                $emit('line', ['marker' => 'info', 'text' => "{$config['desc']} (simulated)"]);
                $categories++;
                $processed++;
                $emit('progress', (int) ($processed / max(1, $totalCategories) * 100));
                continue;
            }

            $results = $this->scanCategory($config['patterns']);
            if (!empty($results)) {
                foreach ($results as $result) {
                    if ($this->cancelled) break;
                    $deleted = $this->deleteItem($result['path']);
                    if ($deleted) {
                        $sizeStr = $this->formatBytes($result['size']);
                        $emit('line', [
                            'marker' => 'ok',
                            'text'   => "Removed {$result['path']} — freed {$sizeStr}",
                            'path'   => $result['path'],
                        ]);
                        $totalSize += $result['size'];
                        $totalItems++;
                    } else {
                        $emit('line', [
                            'marker' => 'error',
                            'text'   => "Failed to remove {$result['path']}",
                            'path'   => $result['path'],
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
        $emit('done', ['status' => 'purge_complete']);
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
     * 在用户目录中扫描匹配给定模式的目录/文件。
     *
     * @param array $patterns 通配符模式列表
     * @return array 找到的项 [{path: string, size: int}]
     */
    private function scanCategory(array $patterns): array
    {
        $results = [];

        foreach (self::SEARCH_ROOTS as $root) {
            $resolvedRoot = $this->resolvePath($root);
            if (!is_dir($resolvedRoot)) continue;

            foreach ($patterns as $pattern) {
                if ($this->cancelled) break;

                // 将 **/ 模式转换为 PowerShell 递归搜索
                $psPattern = str_replace('**/', '*\\', $pattern);
                $searchRoot = str_replace('\\', '\\\\', $resolvedRoot);

                $script = <<<'PS'
$root = $env:WURROW_PURGE_ROOT;
$pattern = $env:WURROW_PURGE_PATTERN;
Get-ChildItem -Path $root -Filter $pattern -Recurse -Force -Depth 5 -ErrorAction SilentlyContinue |
  ForEach-Object {
    if ($_.PSIsContainer) {
      $size = (Get-ChildItem -Path $_.FullName -Recurse -Force -ErrorAction SilentlyContinue |
               Measure-Object -Property Length -Sum -ErrorAction SilentlyContinue).Sum;
      Write-Output ("DIR|" + $_.FullName + "|" + [int64]$size)
    } else {
      Write-Output ("FILE|" + $_.FullName + "|" + $_.Length)
    }
  }
PS;

                $env = [
                    'WURROW_PURGE_ROOT'    => $resolvedRoot,
                    'WURROW_PURGE_PATTERN' => $psPattern,
                ];

                $result = $this->ps->runSync($script, $env);
                $dirsFound = [];

                foreach ($result['output'] as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, 'DIR|') || str_starts_with($line, 'FILE|')) {
                        $parts = explode('|', $line);
                        $path = $parts[1] ?? '';
                        $size = (int) ($parts[2] ?? 0);

                        // 去重：同路径只保留一个条目
                        $dirKey = is_dir($path) ? rtrim($path, '/\\') : $path;
                        if (!isset($dirsFound[$dirKey])) {
                            $dirsFound[$dirKey] = true;
                            $results[] = ['path' => $path, 'size' => $size];
                        }
                    }
                }
            }
        }

        return $results;
    }

    /**
     * 删除文件或目录。
     */
    private function deleteItem(string $path): bool
    {
        if (!file_exists($path)) return true;

        $script = <<<'PS'
$path = $env:WURROW_PURGE_DELETE;
if (Test-Path $path) {
  Remove-Item -Path $path -Recurse -Force -ErrorAction SilentlyContinue;
  if (Test-Path $path) { Write-Output "FAIL" } else { Write-Output "OK" }
} else { Write-Output "OK" }
PS;

        $result = $this->ps->runSync($script, ['WURROW_PURGE_DELETE' => $path]);
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
     * 格式化字节大小为可读字符串。
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1_048_576) return number_format($bytes / 1024, 1) . ' KB';
        if ($bytes < 1_073_741_824) return number_format($bytes / 1_048_576, 1) . ' MB';
        return number_format($bytes / 1_073_741_824, 2) . ' GB';
    }
}
