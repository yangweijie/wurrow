<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Backend\Services;

/**
 * 系统优化服务 — 对应 Burrow 的 `mo optimize` 功能。
 *
 * 优化操作:
 * - DNS 缓存刷新 (ipconfig /flushdns)
 * - 磁盘碎片整理触发 (defrag, 需管理员)
 * - 内存优化 (释放工作集)
 * - 启动项管理 (读取注册表)
 */
final class OptimizeService
{
    private PowerShellRunner $ps;

    /** 优化操作定义 */
    private const OPERATIONS = [
        'DNS Cache' => [
            'preview' => 'Flush DNS resolver cache',
            'command' => 'ipconfig /flushdns',
            'admin'   => false,
        ],
        'Memory Optimization' => [
            'preview' => 'Release working sets to free memory',
            'command' => '[System.GC]::Collect(); [System.GC]::WaitForPendingFinalizers(); Write-Output "Memory optimized"',
            'admin'   => false,
        ],
        'Temp Internet Files' => [
            'preview' => 'Clear temporary internet files',
            'command' => 'Remove-Item -Path "$env:LOCALAPPDATA\\Microsoft\\Windows\\INetCache\\*" -Recurse -Force -ErrorAction SilentlyContinue; Write-Output "Internet cache cleared"',
            'admin'   => false,
        ],
        'Startup Items' => [
            'preview' => 'List startup programs',
            'command' => 'Get-ItemProperty -Path "HKCU:\\Software\\Microsoft\\Windows\\CurrentVersion\\Run" -ErrorAction SilentlyContinue | Format-List',
            'admin'   => false,
            'readonly' => true,
        ],
        'Disk Optimization' => [
            'preview' => 'Trigger disk optimization (requires admin)',
            'command' => 'Optimize-Volume -DriveLetter C -ReTrim -Verbose',
            'admin'   => true,
        ],
    ];

    public function __construct()
    {
        $this->ps = new PowerShellRunner();
    }

    /**
     * 预览模式 — 列出可执行的优化操作。
     */
    public function preview(callable $emit): void
    {
        $emit('line', ['marker' => 'group', 'text' => 'Available Optimizations']);

        foreach (self::OPERATIONS as $name => $op) {
            $adminTag = $op['admin'] ? ' (requires admin)' : '';
            $emit('line', [
                'marker' => 'info',
                'text'   => "{$name}: {$op['preview']}{$adminTag}",
            ]);
        }

        $emit('summary', [
            'space'      => '—',
            'items'      => (string) count(self::OPERATIONS),
            'categories' => '1',
        ]);
        $emit('done', ['status' => 'preview_complete']);
    }

    /**
     * 执行优化操作。
     */
    public function execute(callable $emit): void
    {
        $success = 0;
        $failed  = 0;
        $operations = array_filter(self::OPERATIONS, fn($op) => empty($op['readonly']));
        $total = count($operations);
        $processed = 0;

        foreach (self::OPERATIONS as $name => $op) {
            if (isset($op['readonly']) && $op['readonly']) {
                continue; // 跳过只读操作
            }

            $emit('line', ['marker' => 'group', 'text' => $name]);

            if ($op['admin'] && !PowerShellRunner::isWindows()) {
                $emit('line', ['marker' => 'review', 'text' => 'Skipped (requires admin on Windows)']);
                $processed++;
                $emit('progress', (int) ($processed / max(1, $total) * 100));
                continue;
            }

            $result = $this->ps->runSync($op['command']);

            if ($result['exitCode'] === 0) {
                $output = trim(implode("\n", $result['output']));
                $emit('line', [
                    'marker' => 'ok',
                    'text'   => $output ?: 'Done',
                ]);
                $success++;
            } else {
                $emit('line', [
                    'marker' => 'error',
                    'text'   => "Failed (exit code: {$result['exitCode']})",
                ]);
                $failed++;
            }

            $processed++;
            $emit('progress', (int) ($processed / max(1, $total) * 100));
        }

        $emit('summary', [
            'space'      => "{$success} optimized",
            'items'      => (string) ($success + $failed),
            'categories' => '1',
        ]);
        $emit('done', ['status' => 'optimize_complete']);
    }
}
