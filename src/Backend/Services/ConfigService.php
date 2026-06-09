<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Backend\Services;

/**
 * 配置服务 — 持久化应用设置到 JSON 文件。
 *
 * 配置文件存储在用户目录下: %APPDATA%/Wurrow/config.json
 * 开发模式下存储在 %TEMP%/Wurrow/config.json
 */
final class ConfigService
{
    private string $configPath;

    /** 默认配置 */
    private const DEFAULTS = [
        'serverPort'   => 7891,
        'cleanTemp'    => true,
        'cleanBrowser' => true,
        'cleanThumbs'  => true,
        'cleanPrefetch' => false,
        'cleanRecycle' => false,
    ];

    public function __construct(?string $configDir = null)
    {
        if ($configDir !== null) {
            $this->configPath = $configDir . '/config.json';
        } else {
            $appData = getenv('APPDATA') ?: (getenv('TEMP') ?: sys_get_temp_dir());
            $dir = rtrim($appData, '/\\') . '/Wurrow';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $this->configPath = $dir . '/config.json';
        }
    }

    /**
     * 加载配置，不存在则返回默认值。
     *
     * @return array<string, mixed>
     */
    public function load(): array
    {
        if (!file_exists($this->configPath)) {
            return self::DEFAULTS;
        }

        $json = @file_get_contents($this->configPath);
        if ($json === false) {
            return self::DEFAULTS;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return self::DEFAULTS;
        }

        // 合并默认值（补全缺失字段）
        return array_merge(self::DEFAULTS, $data);
    }

    /**
     * 保存配置到文件。
     *
     * @param array<string, mixed> $config
     */
    public function save(array $config): void
    {
        // 只保存已知的配置键
        $filtered = array_intersect_key($config, self::DEFAULTS);
        $json = json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $dir = dirname($this->configPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @file_put_contents($this->configPath, $json);
    }

    /**
     * 获取清理目标开关状态（用于传递给 CleanService）。
     *
     * @return array<string, bool>
     */
    public function getCleanTargets(): array
    {
        $config = $this->load();
        return [
            'temp'    => (bool) ($config['cleanTemp'] ?? true),
            'browser' => (bool) ($config['cleanBrowser'] ?? true),
            'thumbs'  => (bool) ($config['cleanThumbs'] ?? true),
            'prefetch' => (bool) ($config['cleanPrefetch'] ?? false),
            'recycle' => (bool) ($config['cleanRecycle'] ?? false),
        ];
    }
}
