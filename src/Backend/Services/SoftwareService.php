<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Backend\Services;

/**
 * 软件管理服务 — 对应 Burrow SoftwareView.swift 的 SoftwareModel。
 *
 * 从 Windows 注册表枚举已安装软件，支持搜索和卸载。
 * 注册表路径:
 * - HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*
 * - HKLM\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*
 * - HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*
 */
final class SoftwareService
{
    private PowerShellRunner $ps;

    /** 注册表路径列表 */
    private const REG_PATHS = [
        'HKLM:\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\*',
        'HKLM:\\SOFTWARE\\WOW6432Node\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\*',
        'HKCU:\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\*',
    ];

    public function __construct()
    {
        $this->ps = new PowerShellRunner();
    }

    /**
     * 列出所有已安装软件。
     *
     * @return array 软件列表
     */
    public function listInstalled(): array
    {
        if (!PowerShellRunner::isWindows()) {
            return $this->mockSoftwareList();
        }

        $pathsStr = implode('", "', self::REG_PATHS);
        $script = sprintf(
            '$paths = @("%s"); ' .
            '$apps = foreach ($p in $paths) { ' .
            '  Get-ItemProperty -Path $p -ErrorAction SilentlyContinue | ' .
            '  Where-Object { $_.DisplayName -and $_.DisplayName.Trim() -ne "" } | ' .
            '  Select-Object DisplayName, DisplayVersion, Publisher, InstallLocation, ' .
            '    EstimatedSize, UninstallString, InstallDate ' .
            '}; ' .
            '$apps | Sort-Object -Property EstimatedSize -Descending | ConvertTo-Json -Depth 2',
            $pathsStr
        );

        $result = $this->ps->runSync($script);
        return $this->parseSoftwareJson(implode("\n", $result['output']));
    }

    /**
     * 搜索已安装软件。
     */
    public function search(string $query): array
    {
        $apps = $this->listInstalled();
        if (empty($query)) {
            return $apps;
        }

        $query = mb_strtolower($query);
        return array_values(array_filter($apps, function (array $app) use ($query) {
            return str_contains(mb_strtolower($app['name']), $query)
                || str_contains(mb_strtolower($app['publisher']), $query);
        }));
    }

    /**
     * 卸载指定的软件。
     *
     * @param array $ids 软件 ID（DisplayName 列表）
     * @return array 卸载结果
     */
    public function uninstall(array $ids): array
    {
        if (!PowerShellRunner::isWindows()) {
            return ['status' => 'simulated', 'uninstalled' => $ids];
        }

        $results = [];
        foreach ($ids as $name) {
            // DisplayName 通过环境变量传递，避免字符串拼接注入
            // 注册表路径为硬编码常量，安全可控
            $script = <<<'PS'
$targetName = $env:WURROW_UNINSTALL_NAME;
$paths = @(
  'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*',
  'HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*',
  'HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*'
);
$app = $null;
foreach ($p in $paths) {
  $found = Get-ItemProperty -Path $p -ErrorAction SilentlyContinue |
    Where-Object { $_.DisplayName -eq $targetName };
  if ($found) { $app = $found; break }
};
if ($app -and $app.UninstallString) {
  $uninstall = $app.UninstallString -replace "msiexec\.exe", "msiexec.exe /quiet";
  $uninstall = $uninstall -replace "$", " /S";
  Start-Process cmd.exe -ArgumentList "/c $uninstall" -Wait -NoNewWindow;
  Write-Output "OK"
} else { Write-Output "NOTFOUND" }
PS;

            $result = $this->ps->runSync($script, ['WURROW_UNINSTALL_NAME' => $name]);
            $output = trim(implode('', $result['output']));
            $results[$name] = str_contains($output, 'OK') ? 'uninstalled' : 'failed';
        }

        return ['status' => 'complete', 'results' => $results];
    }

    /**
     * 解析 PowerShell JSON 输出为软件列表。
     */
    private function parseSoftwareJson(string $json): array
    {
        $json = trim($json);
        if (empty($json)) return [];

        $parsed = json_decode($json, true);
        if (!is_array($parsed)) return [];

        // 单个对象时转为数组
        if (isset($parsed['DisplayName'])) {
            $parsed = [$parsed];
        }

        $apps = [];
        foreach ($parsed as $item) {
            $name = trim($item['DisplayName'] ?? '');
            if (empty($name)) continue;

            $sizeKb = (int) ($item['EstimatedSize'] ?? 0);
            $apps[] = [
                'id'              => $name,
                'name'            => $name,
                'version'         => $item['DisplayVersion'] ?? '',
                'publisher'       => $item['Publisher'] ?? '',
                'installLocation' => $item['InstallLocation'] ?? '',
                'sizeBytes'       => $sizeKb * 1024,
                'sizeStr'         => $this->formatBytes($sizeKb * 1024),
                'uninstallString' => $item['UninstallString'] ?? '',
                'installDate'     => $item['InstallDate'] ?? '',
                'source'          => 'Registry',
            ];
        }

        // 按大小降序排列
        usort($apps, fn($a, $b) => $b['sizeBytes'] <=> $a['sizeBytes']);
        return $apps;
    }

    /**
     * 模拟软件列表（非 Windows 开发调试用）。
     */
    private function mockSoftwareList(): array
    {
        return [
            ['id' => 'VS Code', 'name' => 'Visual Studio Code', 'version' => '1.95.0',
             'publisher' => 'Microsoft', 'installLocation' => 'C:\\Users\\jay\\AppData\\Local\\Programs\\Microsoft VS Code',
             'sizeBytes' => 524_288_000, 'sizeStr' => '500.0 MB', 'uninstallString' => '',
             'installDate' => '20240101', 'source' => 'Mock'],
            ['id' => 'Chrome', 'name' => 'Google Chrome', 'version' => '130.0',
             'publisher' => 'Google LLC', 'installLocation' => 'C:\\Program Files\\Google\\Chrome',
             'sizeBytes' => 1_073_741_824, 'sizeStr' => '1.00 GB', 'uninstallString' => '',
             'installDate' => '20240101', 'source' => 'Mock'],
            ['id' => 'Node.js', 'name' => 'Node.js', 'version' => '22.0.0',
             'publisher' => 'Node.js Foundation', 'installLocation' => 'C:\\Program Files\\nodejs',
             'sizeBytes' => 104_857_600, 'sizeStr' => '100.0 MB', 'uninstallString' => '',
             'installDate' => '20240101', 'source' => 'Mock'],
            ['id' => 'PHP', 'name' => 'PHP 8.3', 'version' => '8.3.0',
             'publisher' => 'PHP Group', 'installLocation' => 'C:\\php',
             'sizeBytes' => 52_428_800, 'sizeStr' => '50.0 MB', 'uninstallString' => '',
             'installDate' => '20240101', 'source' => 'Mock'],
        ];
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
