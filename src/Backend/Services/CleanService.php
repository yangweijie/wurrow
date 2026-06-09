<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Backend\Services;

/**
 * 系统清理服务 — 对应 Burrow 的 `mo clean` 功能。
 *
 * 清理目标:
 * - Windows Temp 目录 (%TEMP%, C:\Windows\Temp)
 * - 浏览器缓存 (Chrome, Edge, Firefox)
 * - 缩略图缓存
 * - Windows Update 缓存（需管理员）
 * - 回收站
 * - 预取文件
 *
 * 输出格式兼容 Burrow TaskReport:
 *   ➤ Category
 *   → action item, size
 *   ✓ completed item
 *   ✗ error item
 */
final class CleanService
{
    private PowerShellRunner $ps;
    private bool $cancelled = false;

    /** 清理目标定义 */
    private const TARGETS = [
        'Windows Temp' => [
            'paths'   => ['%TEMP%', 'C:\\Windows\\Temp'],
            'admin'   => false,
        ],
        'Browser Caches' => [
            'paths'   => [
                '%LOCALAPPDATA%\\Google\\Chrome\\User Data\\Default\\Cache',
                '%LOCALAPPDATA%\\Microsoft\\Edge\\User Data\\Default\\Cache',
                '%LOCALAPPDATA%\\Mozilla\\Firefox\\Profiles\\*\\cache2',
            ],
            'admin' => false,
        ],
        'Thumbnail Cache' => [
            'paths'   => ['%LOCALAPPDATA%\\Microsoft\\Windows\\Explorer\\thumbcache_*'],
            'admin'   => false,
        ],
        'Prefetch Files' => [
            'paths'   => ['C:\\Windows\\Prefetch\\*.pf'],
            'admin'   => true,
        ],
        'Windows Update Cache' => [
            'paths'   => ['C:\\Windows\\SoftwareDistribution\\Download'],
            'admin'   => true,
        ],
    ];

    public function __construct()
    {
        $this->ps = new PowerShellRunner();
    }

    /**
     * 预览模式（dry-run）— 扫描可清理的文件并报告大小。
     *
     * @param callable $emit    SSE 发送回调: function(string $event, mixed $data)
     * @param array<string, bool> $targets 清理目标开关: ['temp' => true, 'browser' => true, ...]
     */
    public function preview(callable $emit, array $targets = []): void
    {
        $this->cancelled = false;
        $totalSize = 0;
        $totalItems = 0;
        $categories = 0;
        $activeTargets = $this->filterTargets($targets);
        $totalCategories = count($activeTargets);
        $processed = 0;

        foreach ($activeTargets as $category => $config) {
            if ($this->cancelled) break;

            $emit('line', ['marker' => 'group', 'text' => $category]);
            $categories++;

            foreach ($config['paths'] as $pathPattern) {
                if ($this->cancelled) break;

                $resolved = $this->resolvePath($pathPattern);
                $info = $this->scanDirectory($resolved);

                if ($info['size'] > 0) {
                    $sizeStr = $this->formatBytes($info['size']);
                    $emit('line', [
                        'marker' => 'action',
                        'text'   => "{$info['count']} items, {$sizeStr}",
                        'path'   => $resolved,
                    ]);
                    $totalSize += $info['size'];
                    $totalItems += $info['count'];
                } else {
                    $emit('line', [
                        'marker' => 'ok',
                        'text'   => 'Nothing to clean',
                        'path'   => $resolved,
                    ]);
                }
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
     * 执行清理 — 实际删除文件。
     *
     * @param callable $emit    SSE 发送回调
     * @param array<string, bool> $targets 清理目标开关
     */
    public function execute(callable $emit, array $targets = []): void
    {
        $this->cancelled = false;
        $totalSize = 0;
        $totalItems = 0;
        $categories = 0;
        $activeTargets = $this->filterTargets($targets);
        $totalCategories = count($activeTargets);
        $processed = 0;

        foreach ($activeTargets as $category => $config) {
            if ($this->cancelled) break;

            $emit('line', ['marker' => 'group', 'text' => $category]);
            $categories++;

            foreach ($config['paths'] as $pathPattern) {
                if ($this->cancelled) break;

                $resolved = $this->resolvePath($pathPattern);
                $info = $this->scanDirectory($resolved);

                if ($info['size'] > 0) {
                    $deleted = $this->cleanDirectory($resolved);
                    $sizeStr = $this->formatBytes($deleted['size']);
                    $emit('line', [
                        'marker' => $deleted['errors'] > 0 ? 'review' : 'action',
                        'text'   => "Cleaned {$deleted['count']} items, freed {$sizeStr}",
                        'path'   => $resolved,
                    ]);
                    $totalSize += $deleted['size'];
                    $totalItems += $deleted['count'];
                } else {
                    $emit('line', ['marker' => 'ok', 'text' => 'Already clean']);
                }
            }

            $processed++;
            $emit('progress', (int) ($processed / max(1, $totalCategories) * 100));
        }

        $emit('summary', [
            'space'      => $this->formatBytes($totalSize),
            'items'      => (string) $totalItems,
            'categories' => (string) $categories,
        ]);
        $emit('done', ['status' => 'clean_complete']);
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
     * 解析路径中的环境变量。
     */
    private function resolvePath(string $path): string
    {
        return preg_replace_callback('/%(\w+)%/', function ($m) {
            return getenv($m[1]) ?: $m[0];
        }, $path) ?? $path;
    }

    /**
     * 扫描目录获取文件大小和数量。
     */
    private function scanDirectory(string $path): array
    {
        if (!PowerShellRunner::isWindows()) {
            // 模拟模式
            return ['size' => 0, 'count' => 0];
        }

        // 路径通过环境变量传递，避免字符串拼接注入
        $script = <<<'PS'
$items = Get-ChildItem -Path $env:WURROW_CLEAN_PATH -Recurse -Force -ErrorAction SilentlyContinue;
$size = ($items | Measure-Object -Property Length -Sum -ErrorAction SilentlyContinue).Sum;
$count = ($items | Where-Object { -not $_.PSIsContainer }).Count;
Write-Output "$count|$size"
PS;

        $result = $this->ps->runSync($script, ['WURROW_CLEAN_PATH' => $path]);
        $output = trim(implode('', $result['output']));

        if (preg_match('/(\d+)\|(\d+)/', $output, $m)) {
            return ['count' => (int) $m[1], 'size' => (int) $m[2]];
        }
        return ['size' => 0, 'count' => 0];
    }

    /**
     * 清理目录中的文件。
     */
    private function cleanDirectory(string $path): array
    {
        if (!PowerShellRunner::isWindows()) {
            $info = $this->scanDirectory($path);
            return ['size' => $info['size'], 'count' => $info['count'], 'errors' => 0];
        }

        // 路径通过环境变量传递，避免字符串拼接注入
        $script = <<<'PS'
$before = (Get-ChildItem -Path $env:WURROW_CLEAN_PATH -Recurse -Force -ErrorAction SilentlyContinue |
           Measure-Object -Property Length -Sum -ErrorAction SilentlyContinue).Sum;
$count = (Get-ChildItem -Path $env:WURROW_CLEAN_PATH -Recurse -Force -ErrorAction SilentlyContinue |
          Where-Object { -not $_.PSIsContainer }).Count;
Remove-Item -Path "$env:WURROW_CLEAN_PATH\*" -Recurse -Force -ErrorAction SilentlyContinue;
$errors = $Error.Count;
Write-Output "$count|$before|$errors"
PS;

        $result = $this->ps->runSync($script, ['WURROW_CLEAN_PATH' => $path]);
        $output = trim(implode('', $result['output']));

        if (preg_match('/(\d+)\|(\d+)\|(\d+)/', $output, $m)) {
            return ['count' => (int) $m[1], 'size' => (int) $m[2], 'errors' => (int) $m[3]];
        }
        return ['size' => 0, 'count' => 0, 'errors' => 0];
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

    /**
     * 根据用户设置过滤清理目标。
     *
     * @param array<string, bool> $targets
     * @return array<string, array>
     */
    private function filterTargets(array $targets): array
    {
        // 无过滤时返回全部目标（向后兼容）
        if (empty($targets)) {
            return self::TARGETS;
        }

        // 映射设置键到 TARGETS 分类
        $mapping = [
            'temp'    => 'Windows Temp',
            'browser' => 'Browser Caches',
            'thumbs'  => 'Thumbnail Cache',
            'prefetch' => 'Prefetch Files',
            'recycle' => 'Windows Update Cache',
        ];

        $filtered = [];
        foreach ($mapping as $key => $category) {
            if (!empty($targets[$key]) && isset(self::TARGETS[$category])) {
                $filtered[$category] = self::TARGETS[$category];
            }
        }

        return $filtered ?: self::TARGETS;
    }
}
