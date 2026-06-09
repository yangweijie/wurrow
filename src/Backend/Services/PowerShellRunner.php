<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Backend\Services;

/**
 * PowerShell 进程执行器。
 *
 * 封装 Windows PowerShell 命令的执行，支持流式输出和进程取消。
 * 在非 Windows 系统上回退到模拟模式（开发调试用）。
 */
final class PowerShellRunner
{
    private ?array $process = null;
    private bool $cancelled = false;

    /**
     * 检测是否在 Windows 系统上运行。
     */
    public static function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /**
     * 执行 PowerShell 脚本并流式返回输出。
     *
     * 安全说明：不应将用户输入直接拼接在 $script 中。
     * 应通过 $envVars 传递用户控制的值，脚本内用 $env:VAR_NAME 读取。
     *
     * @param string   $script  PowerShell 脚本内容（不含用户输入拼接）
     * @param callable $onLine  每行输出回调: function(string $line): void
     * @param array    $envVars 环境变量键值对，供脚本通过 $env:KEY 安全读取
     * @return int 退出码
     */
    public function run(string $script, callable $onLine, array $envVars = []): int
    {
        $this->cancelled = false;

        if (!self::isWindows()) {
            return $this->simulate($script, $onLine);
        }

        $cmd = sprintf(
            'powershell -NoProfile -NonInteractive -Command %s',
            escapeshellarg($script)
        );

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        // 将环境变量合并传入子进程，避免字符串拼接注入
        $env = array_merge(getenv(), $envVars);
        $process = proc_open($cmd, $descriptors, $pipes, null, $env);
        if (!is_resource($process)) {
            $onLine("✗ Failed to start PowerShell process");
            return 1;
        }

        $this->process = $pipes;

        // 关闭 stdin
        fclose($pipes[0]);

        // 设置 stdout 为非阻塞
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer = '';
        $exitCode = -1;

        while (true) {
            if ($this->cancelled) {
                proc_terminate($process);
                $onLine("✗ Operation cancelled");
                break;
            }

            $stdout = fread($pipes[1], 8192);
            $stderr = fread($pipes[2], 8192);

            if ($stdout !== false && $stdout !== '') {
                $buffer .= $stdout;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $onLine(rtrim($line, "\r"));
                }
            }

            if ($stderr !== false && $stderr !== '') {
                $onLine("✗ " . rtrim($stderr, "\r\n"));
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                // 刷新剩余缓冲
                while (($remaining = fread($pipes[1], 8192)) !== false && $remaining !== '') {
                    $buffer .= $remaining;
                }
                if ($buffer !== '') {
                    $onLine(rtrim($buffer, "\r\n"));
                }
                $exitCode = $status['exitcode'];
                break;
            }

            usleep(50_000); // 50ms 轮询间隔
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $this->process = null;

        return $exitCode;
    }

    /**
     * 取消当前执行的操作。
     */
    public function cancel(): void
    {
        $this->cancelled = true;
    }

    /**
     * 执行 PowerShell 并返回完整输出（非流式）。
     *
     * @param string $script  PowerShell 脚本内容
     * @param array  $envVars 环境变量键值对，供脚本通过 $env:KEY 安全读取
     * @return array{exitCode: int, output: string[]}
     */
    public function runSync(string $script, array $envVars = []): array
    {
        $lines = [];
        $exitCode = $this->run($script, function (string $line) use (&$lines) {
            $lines[] = $line;
        }, $envVars);
        return ['exitCode' => $exitCode, 'output' => $lines];
    }

    /**
     * 非 Windows 系统上的模拟模式（开发调试用）。
     */
    private function simulate(string $script, callable $onLine): int
    {
        $onLine("ℹ PowerShell simulation mode (not on Windows)");
        $onLine("ℹ Script: " . substr($script, 0, 80) . '...');
        $onLine("✓ Simulation complete");
        return 0;
    }
}
