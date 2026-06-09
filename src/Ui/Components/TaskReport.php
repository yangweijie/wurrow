<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Ui\Components;

use Perry\UI\Binding;
use Perry\UI\Styling\Style;
use Perry\UI\Widget\HStack;
use Perry\UI\Widget\ScrollView;
use Perry\UI\Widget\Spacer;
use Perry\UI\Widget\Text;
use Perry\UI\Widget\VStack;
use Yangweijie\Wurrow\Ui\Theme;

/**
 * 任务报告视图组件 — 渲染清理/优化操作的结构化输出。
 *
 * 对应 Burrow TaskReport.swift L381-438 的 TaskReportView。
 * 将后端返回的分组报告渲染为卡片列表。
 */
final class TaskReport
{
    /**
     * 构建任务报告视图（静态占位 — 实际数据由 C# code-behind 动态填充）。
     *
     * @param Binding $reportBinding 报告文本绑定
     * @param string  $accentColor   当前工具的主题色
     * @return ScrollView
     */
    public static function build(Binding $reportBinding, string $accentColor): ScrollView
    {
        // 报告内容区域 — C# code-behind 将解析 SSE 流并动态更新此 TextBlock
        $reportText = (new Text($reportBinding))
            ->style(Style::make()
                ->fontSize(12)
                ->fontFamily('Cascadia Code')
                ->foregroundColor(Theme::TEXT_PRIMARY));

        return new ScrollView(
            (new VStack($reportText))
                ->style(Style::make()
                    ->padding(18))
        );
    }

    /**
     * 构建完成横幅（DoneBanner）。
     *
     * 对应 Burrow TaskReport.swift L495-518。
     */
    public static function doneBanner(string $title, Binding $detailBinding, string $accentColor): HStack
    {
        $checkMark = (new Text("\u{2713}"))
            ->style(Style::make()
                ->fontSize(16)
                ->fontWeight('bold')
                ->foregroundColor($accentColor)
                ->width(38)
                ->height(38)
                ->textAlignment('center')
                ->backgroundColor($accentColor)
                ->cornerRadius(19)
                ->opacity(0.85));

        $titleText = (new Text($title))
            ->style(Style::make()
                ->fontSize(15)
                ->fontWeight('semibold')
                ->foregroundColor(Theme::TEXT_PRIMARY));

        $detailText = (new Text($detailBinding))
            ->style(Theme::mono(Theme::TEXT_SECONDARY));

        return new HStack(
            $checkMark,
            (new VStack($titleText, $detailText))
                ->style(Style::make()->padding(4)),
            new Spacer(),
        );
    }

    /**
     * 构建状态栏（运行时显示进度 + 取消按钮）。
     *
     * 对应 Burrow CleanView.swift L63-81 的 statusBar。
     */
    public static function statusBar(Binding $statusBinding, string $accentColor): HStack
    {
        $statusText = (new Text($statusBinding))
            ->style(Theme::mono(Theme::TEXT_SECONDARY));

        return new HStack(
            $statusText,
            new Spacer(),
        );
    }
}
