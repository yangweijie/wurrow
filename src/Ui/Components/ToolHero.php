<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Ui\Components;

use Perry\UI\Action;
use Perry\UI\Binding;
use Perry\UI\Styling\Style;
use Perry\UI\Widget\Button;
use Perry\UI\Widget\HStack;
use Perry\UI\Widget\Spacer;
use Perry\UI\Widget\Text;
use Perry\UI\Widget\VStack;
use Yangweijie\Wurrow\Ui\Theme;
use Yangweijie\Wurrow\Ui\Tool;

/**
 * 工具英雄组件 — 空闲状态下展示的大圆形 Orb + 标题 + 标语 + 按钮组。
 *
 * 对应 Burrow TaskReport.swift L474-492 的 ToolHero。
 */
final class ToolHero
{
    /**
     * 构建 ToolHero 组件。
     *
     * @param Tool    $tool    当前工具（决定颜色和文案）
     * @param array   $buttons 按钮 Widget 数组
     * @return VStack
     */
    public static function build(Tool $tool, array $buttons): VStack
    {
        $accent = $tool->accent();

        // Orb — 用大圆形 Text 模拟（perry-php 没有 Circle widget，用文字+背景色近似）
        $orb = (new Text($tool->glyph()))
            ->style(Style::make()
                ->fontSize(48)
                ->textAlignment('center')
                ->width(120)
                ->height(120)
                ->backgroundColor($accent)
                ->cornerRadius(60)
                ->foregroundColor('#FFFFFF')
                ->opacity(0.85));

        // 标题
        $title = (new Text($tool->title()))
            ->style(Theme::title($accent));

        // 标语
        $subtitle = (new Text($tool->tagline()))
            ->style(Style::make()
                ->fontSize(15)
                ->fontFamily('Segoe UI')
                ->foregroundColor(Theme::TEXT_SECONDARY)
                ->textAlignment('center'));

        // 按钮组
        $buttonRow = new HStack(...$buttons);

        return new VStack(
            new Spacer(),
            $orb,
            (new VStack($title, $subtitle))
                ->style(Style::make()->padding(8)),
            $buttonRow,
            new Spacer(),
        );
    }
}
