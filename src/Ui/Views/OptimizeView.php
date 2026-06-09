<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Ui\Views;

use Perry\UI\Action;
use Perry\UI\Binding;
use Perry\UI\Styling\Style;
use Perry\UI\Widget\HStack;
use Perry\UI\Widget\Progress;
use Perry\UI\Widget\Spacer;
use Perry\UI\Widget\Text;
use Perry\UI\Widget\VStack;
use Yangweijie\Wurrow\Ui\Components\PillButton;
use Yangweijie\Wurrow\Ui\Components\TaskReport;
use Yangweijie\Wurrow\Ui\Components\ToolHero;
use Yangweijie\Wurrow\Ui\Theme;
use Yangweijie\Wurrow\Ui\Tool;

/**
 * 优化视图 — 对应 Burrow OptimizeView.swift。
 *
 * 与 CleanView 相同模式，但操作目标为系统优化（DNS 刷新、磁盘整理等）。
 */
final class OptimizeView
{
    /**
     * @param array<string, Binding> $bindings
     */
    public static function build(array $bindings): VStack
    {
        $tool = Tool::Optimize;

        // ── 按钮动作 ──
        $previewAction  = Action::custom('// TODO: Call POST /api/optimize/preview via StreamingClient');
        $optimizeAction = Action::custom('// TODO: Call POST /api/optimize/execute via StreamingClient');

        // ── 空闲态: ToolHero ──
        $hero = ToolHero::build($tool, [
            PillButton::primary('Optimize Now', $optimizeAction),
            PillButton::secondary('Preview', $previewAction),
        ]);

        // ── 运行态 ──
        $statusBar = TaskReport::statusBar($bindings['optimizeStatus'], $tool->accent());
        $progress  = (new Progress())
            ->style(Style::make()
                ->height(3)
                ->backgroundColor(Theme::HAIRLINE));

        // ── 报告区域 ──
        $reportBinding = new Binding('optimizeReport', 'Waiting to start...');
        $report = TaskReport::build($reportBinding, $tool->accent());

        // ── 完成横幅 ──
        $doneDetail = new Binding('optimizeDoneDetail', '');
        $doneBanner = TaskReport::doneBanner('Optimized', $doneDetail, $tool->accent());

        return new VStack(
            $hero,
            $statusBar,
            $progress,
            $doneBanner,
            $report,
        );
    }
}
