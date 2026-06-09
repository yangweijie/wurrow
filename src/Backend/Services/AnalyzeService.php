<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Backend\Services;

/**
 * 磁盘分析服务 — 对应 Burrow DiskScanner.swift。
 *
 * 扫描指定目录的一级子目录，返回每个子目录的递归聚合大小。
 * 按需下钻：每次只扫描一级，用户点击目录后再扫描其子目录。
 *
 * 返回结构对应 Burrow DiskScanResult:
 *   {path, total_size, total_files, entries: [{name, path, size, is_dir}]}
 */
final class AnalyzeService
{
    private PowerShellRunner $ps;

    public function __construct()
    {
        $this->ps = new PowerShellRunner();
    }

    /**
     * 扫描指定目录的直接子项。
     *
     * @param string $path 要扫描的目录路径
     * @return array{path: string, total_size: int, total_files: int, entries: array}
     */
    public function scan(string $path): array
    {
        if (!PowerShellRunner::isWindows()) {
            return $this->scanNative($path);
        }
        return $this->scanPowerShell($path);
    }

    /**
     * 将文件或目录移动到回收站。
     */
    public function trash(string $path): void
    {
        if (!PowerShellRunner::isWindows()) {
            // 模拟模式：不做任何操作
            return;
        }

        $isDir = is_dir($path);
        $deleteMethod = $isDir ? 'DeleteDirectory' : 'DeleteFile';

        // 路径通过环境变量传递，避免字符串拼接注入
        $script = 'Add-Type -AssemblyName Microsoft.VisualBasic; ' .
            sprintf('[Microsoft.VisualBasic.FileIO.FileSystem]::%s($env:WURROW_PATH, "OnlyErrorDialogs", "SendToRecycleBin")', $deleteMethod);

        $result = $this->ps->runSync($script, ['WURROW_PATH' => $path]);
        if ($result['exitCode'] !== 0) {
            // 回退到直接删除
            $script2 = 'if (Test-Path $env:WURROW_PATH) { Remove-Item -Path $env:WURROW_PATH -Recurse -Force -ErrorAction Stop }';
            $this->ps->runSync($script2, ['WURROW_PATH' => $path]);
        }
    }

    /**
     * Windows 上使用 PowerShell 扫描（更快，利用 .NET API）。
     */
    private function scanPowerShell(string $path): array
    {
        // 路径通过环境变量传递，避免字符串拼接注入
        $script = <<<'PS'
$path = $env:WURROW_SCAN_PATH;
$items = Get-ChildItem -Path $path -Force -ErrorAction SilentlyContinue;
$results = @();
foreach ($item in $items) {
  if ($item.PSIsContainer) {
    $size = (Get-ChildItem -Path $item.FullName -Recurse -Force -ErrorAction SilentlyContinue |
            Measure-Object -Property Length -Sum -ErrorAction SilentlyContinue).Sum;
    $files = (Get-ChildItem -Path $item.FullName -Recurse -Force -ErrorAction SilentlyContinue |
              Where-Object { -not $_.PSIsContainer }).Count;
  } else {
    $size = $item.Length; $files = 1;
  };
  $results += @{ name=$item.Name; path=$item.FullName; size=[int64]$size; is_dir=$item.PSIsContainer; files=$files };
};
$totalSize = ($results | Measure-Object -Property size -Sum).Sum;
$totalFiles = ($results | Measure-Object -Property files -Sum).Sum;
$results | Sort-Object -Property size -Descending | ConvertTo-Json -Depth 2;
Write-Output "TOTAL|$totalSize|$totalFiles"
PS;

        $result = $this->ps->runSync($script, ['WURROW_SCAN_PATH' => $path]);
        $output = trim(implode("\n", $result['output']));

        return $this->parsePowerShellOutput($path, $output);
    }

    /**
     * 原生 PHP 扫描（跨平台，开发调试用）。
     */
    private function scanNative(string $path): array
    {
        $entries = [];
        $totalSize = 0;
        $totalFiles = 0;

        if (!is_dir($path)) {
            return [
                'path'       => $path,
                'total_size' => 0,
                'total_files' => 0,
                'entries'    => [],
            ];
        }

        $items = @scandir($path);
        if ($items === false) {
            return [
                'path'       => $path,
                'total_size' => 0,
                'total_files' => 0,
                'entries'    => [],
            ];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            $isDir = is_dir($fullPath);

            if ($isDir) {
                $info = $this->getDirectorySize($fullPath);
                $size = $info['size'];
                $files = $info['files'];
            } else {
                $size = @filesize($fullPath) ?: 0;
                $files = 1;
            }

            $entries[] = [
                'name'   => $item,
                'path'   => $fullPath,
                'size'   => $size,
                'is_dir' => $isDir,
            ];

            $totalSize += $size;
            $totalFiles += $files;
        }

        // 按大小降序排列
        usort($entries, fn($a, $b) => $b['size'] <=> $a['size']);

        return [
            'path'        => $path,
            'total_size'  => $totalSize,
            'total_files' => $totalFiles,
            'entries'     => $entries,
        ];
    }

    /**
     * 递归计算目录大小。
     */
    private function getDirectorySize(string $path): array
    {
        $size = 0;
        $files = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                    $files++;
                }
            }
        } catch (\Throwable) {
            // 权限不足等错误静默处理
        }

        return ['size' => $size, 'files' => $files];
    }

    /**
     * 解析 PowerShell JSON 输出。
     */
    private function parsePowerShellOutput(string $path, string $output): array
    {
        $lines = explode("\n", $output);
        $totalSize = 0;
        $totalFiles = 0;
        $entries = [];

        // 最后一行可能是 TOTAL|size|files
        $lastLine = trim(end($lines));
        if (str_starts_with($lastLine, 'TOTAL|')) {
            $parts = explode('|', $lastLine);
            $totalSize = (int) ($parts[1] ?? 0);
            $totalFiles = (int) ($parts[2] ?? 0);
            array_pop($lines);
        }

        // 其余行是 JSON
        $jsonStr = trim(implode("\n", $lines));
        if (!empty($jsonStr)) {
            $parsed = json_decode($jsonStr, true);
            if (is_array($parsed)) {
                // 单个对象时转为数组
                if (isset($parsed['name'])) {
                    $parsed = [$parsed];
                }
                foreach ($parsed as $item) {
                    $entries[] = [
                        'name'   => $item['name'] ?? '',
                        'path'   => $item['path'] ?? '',
                        'size'   => (int) ($item['size'] ?? 0),
                        'is_dir' => (bool) ($item['is_dir'] ?? false),
                    ];
                }
            }
        }

        // 如果没有从 TOTAL 行获取到，从 entries 计算
        if ($totalSize === 0) {
            $totalSize = array_sum(array_column($entries, 'size'));
        }

        return [
            'path'        => $path,
            'total_size'  => $totalSize,
            'total_files' => $totalFiles,
            'entries'     => $entries,
        ];
    }
}
