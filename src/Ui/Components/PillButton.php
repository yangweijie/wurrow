<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Ui\Components;

use Perry\UI\Action;
use Perry\UI\Binding;
use Perry\UI\Styling\Style;
use Perry\UI\Widget\Button;
use Yangweijie\Wurrow\Ui\Theme;

/**
 * 胶囊按钮组件 — 主操作和次要操作的通用按钮样式。
 *
 * 对应 Burrow TaskReport.swift L457-472 的 PillButton。
 */
final class PillButton
{
    /**
     * 创建主按钮（白底黑字）。
     */
    public static function primary(string $label, Action $action): Button
    {
        return (new Button($label, $action))
            ->style(Theme::primaryButton());
    }

    /**
     * 创建次要按钮（透明底 + 描边）。
     */
    public static function secondary(string $label, Action $action): Button
    {
        return (new Button($label, $action))
            ->style(Theme::secondaryButton());
    }

    /**
     * 创建带主题色的按钮。
     */
    public static function accent(string $label, Action $action, string $accentColor): Button
    {
        return (new Button($label, $action))
            ->style(Style::make()
                ->backgroundColor($accentColor)
                ->foregroundColor('#FFFFFF')
                ->fontSize(13)
                ->fontWeight('semibold')
                ->cornerRadius(20)
                ->padding(10));
    }
}
