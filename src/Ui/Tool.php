<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Ui;

/**
 * Wurrow 工具枚举。
 *
 * 对应 Burrow 的 Tool.swift — 每个工具拥有独立的颜色、标签和标语。
 * `navOrder()` 定义顶部导航的排列顺序。
 */
enum Tool: string
{
    case Clean     = 'clean';
    case Purge     = 'purge';
    case Installer = 'installer';
    case Optimize  = 'optimize';
    case Apps      = 'apps';
    case Analyze   = 'analyze';

    /** 顶部导航的排列顺序（对应 Tool.swift L23 navOrder） */
    public static function navOrder(): array
    {
        return [
            self::Clean,
            self::Purge,
            self::Installer,
            self::Optimize,
            self::Apps,
            self::Analyze,
        ];
    }

    /** 标签文本（小写，导航用） */
    public function label(): string
    {
        return match ($this) {
            self::Clean     => 'clean',
            self::Purge     => 'purge',
            self::Installer => 'installers',
            self::Optimize  => 'optimize',
            self::Apps      => 'software',
            self::Analyze   => 'analyze',
        };
    }

    /** 标题文本（首字母大写，英雄区域用） */
    public function title(): string
    {
        return match ($this) {
            self::Clean     => 'Clean',
            self::Purge     => 'Purge',
            self::Installer => 'Installers',
            self::Optimize  => 'Optimize',
            self::Apps      => 'Software',
            self::Analyze   => 'Analyze',
        };
    }

    /** 主题色（十六进制，对应 Tool.swift accent） */
    public function accent(): string
    {
        return match ($this) {
            self::Clean     => Theme::ACCENT_CLEAN,
            self::Purge     => Theme::ACCENT_PURGE,
            self::Installer => Theme::ACCENT_INSTALLER,
            self::Optimize  => Theme::ACCENT_OPTIMIZE,
            self::Apps      => Theme::ACCENT_APPS,
            self::Analyze   => Theme::ACCENT_ANALYZE,
        };
    }

    /** 标语（对应 Tool.swift tagline） */
    public function tagline(): string
    {
        return match ($this) {
            self::Clean     => 'Fresh air through old tunnels.',
            self::Purge     => 'Clear the diggings dev work leaves behind.',
            self::Installer => 'Sweep out the crates you unpacked.',
            self::Optimize  => 'Small turns, a smoother run.',
            self::Apps      => "Shed what you've outgrown.",
            self::Analyze   => 'Map every chamber below.',
        };
    }

    /** 图标符号（Segoe MDL2 / Unicode，对应 Tool.swift glyph） */
    public function glyph(): string
    {
        return match ($this) {
            self::Clean     => "\u{2728}",       // ✨ sparkles
            self::Purge     => "\u{1F4C2}",      // 📂 folder
            self::Installer => "\u{2B07}",        // ⬇ arrow down
            self::Optimize  => "\u{2728}",       // ✨ wand
            self::Apps      => "\u{1F4E6}",      // 📦 package
            self::Analyze   => "\u{25A6}",       // ▦ grid
        };
    }

    /** TabView 索引位置 */
    public function tabIndex(): int
    {
        return array_search($this, self::navOrder(), true) ?: 0;
    }
}
