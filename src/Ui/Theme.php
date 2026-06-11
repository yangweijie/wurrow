<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Ui;

use Perry\UI\Styling\Style;
use Perry\UI\Styling\StyleProperty;

/**
 * Wurrow 视觉主题常量。
 *
 * 对应 Burrow 的 Brand 枚举 — 深色仪表板风格，每个工具携带自己的主题色。
 * 所有颜色值为 6 位十六进制（不含 #），供 perry-php Style 使用。
 */
final class Theme
{
    // ─── 全局色板 ───────────────────────────────────────────────
    public const NEAR_BLACK     = '#141414';
    public const SURFACE        = '#1E1E1E';
    public const CARD_FILL      = '#262626';
    public const CARD_FILL_HOVER = '#2E2E2E';
    public const HAIRLINE       = '#333333';

    // ─── 文字层级 ───────────────────────────────────────────────
    public const TEXT_PRIMARY   = '#F5F5F5';
    public const TEXT_SECONDARY = '#A3A3A3';
    public const TEXT_TERTIARY  = '#6B6B6B';

    // ─── 语义色 ─────────────────────────────────────────────────
    public const GREEN  = '#4ADE80';
    public const RED    = '#F87171';
    public const GOLD   = '#E6A93C';
    public const ORANGE = '#FB923C';

    // ─── 工具主题色（对应 Tool.swift L54-64）────────────────────
    public const ACCENT_CLEAN     = '#35C2A5'; // teal
    public const ACCENT_PURGE     = '#6FB06A'; // moss
    public const ACCENT_INSTALLER = '#D98C5F'; // ginger
    public const ACCENT_APPS      = '#F0714E'; // coral
    public const ACCENT_OPTIMIZE  = '#8E84F0'; // violet
    public const ACCENT_ANALYZE   = '#4FA3E3'; // azure

    // ─── Treemap 调色板（对应 AnalyzeView L196-200）────────────
    public const TREEMAP_PALETTE = [
        '#4FA3E3', '#57C2A5', '#E6A93C', '#F0884E',
        '#8E84F0', '#5AA8F0', '#E0667E', '#6FB06A',
    ];

    // ─── 字体 ───────────────────────────────────────────────────
    public const FONT_SANS = 'Segoe UI';
    public const FONT_MONO = 'Cascadia Code';

    // ─── 样式工厂 ───────────────────────────────────────────────

    /** 标题样式（28px, semibold） */
    public static function title(string $color = self::TEXT_PRIMARY): Style
    {
        return Style::make()
            ->fontSize(28)
            ->fontWeight('semibold')
            ->fontFamily(self::FONT_SANS)
            ->foregroundColor($color);
    }

    /** 正文样式（13px） */
    public static function body(string $color = self::TEXT_PRIMARY): Style
    {
        return Style::make()
            ->fontSize(13)
            ->fontFamily(self::FONT_SANS)
            ->foregroundColor($color);
    }

    /** 等宽标签样式（11px, mono） */
    public static function mono(string $color = self::TEXT_SECONDARY): Style
    {
        return Style::make()
            ->fontSize(11)
            ->fontFamily(self::FONT_MONO)
            ->foregroundColor($color);
    }

    /** 卡片容器样式 */
    public static function card(): Style
    {
        return Style::make()
            ->backgroundColor(self::CARD_FILL)
            ->cornerRadius(12)
            ->padding(14)
            ->border(1, self::HAIRLINE);
    }

    /** 主按钮样式（白底黑字） */
    public static function primaryButton(): Style
    {
        return Style::make()
            ->backgroundColor('#FFFFFF')
            ->foregroundColor('#000000')
            ->fontSize(16)
            ->fontWeight('semibold')
            ->fontFamily(self::FONT_SANS)
            ->cornerRadius(24)
            ->padding(20)
            ->set(StyleProperty::Margin, '0,0,14,0');
    }

    /** 次按钮样式（透明底 + 描边） */
    public static function secondaryButton(): Style
    {
        return Style::make()
            ->backgroundColor(self::CARD_FILL)
            ->foregroundColor(self::TEXT_PRIMARY)
            ->fontSize(16)
            ->fontWeight('semibold')
            ->fontFamily(self::FONT_SANS)
            ->cornerRadius(24)
            ->padding(20)
            ->border(1, self::HAIRLINE);
    }

    /** 窗口背景样式 */
    public static function windowBackground(): Style
    {
        return Style::make()
            ->backgroundColor(self::NEAR_BLACK);
    }

    /** 导航栏样式 */
    public static function navBar(): Style
    {
        return Style::make()
            ->backgroundColor(self::SURFACE)
            ->cornerRadius(22)
            ->padding(4)
            ->border(1, self::HAIRLINE);
    }

    /** 分隔线样式 */
    public static function divider(): Style
    {
        return Style::make()
            ->backgroundColor(self::HAIRLINE)
            ->height(1);
    }
}
