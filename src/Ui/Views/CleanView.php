<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Ui\Views;

use Perry\UI\Action;
use Perry\UI\Binding;
use Perry\UI\Styling\Style;
use Perry\UI\Widget\Button;
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
 * 清理视图 — 对应 Burrow CleanView.swift。
 *
 * 空闲态: ToolHero + "Clean Now" / "Preview" 按钮
 * 运行态: 状态栏 + 进度条 + 报告流
 * 完成态: DoneBanner + 报告视图
 */
final class CleanView
{
    /**
     * @param array<string, Binding> $bindings
     */
    public static function build(array $bindings): VStack
    {
        $tool = Tool::Clean;

        // ── 按钮动作（生成实际 C# 代码调用 PHP API）──
        $cleanAction = Action::custom(<<<'CS'
cleanPhase = "running";
cleanStatus = "Scanning system...";
cleanReport = "";
UpdateUI();
await App.Stream.StartAsync("/api/clean/execute",
    (evt, data) => Dispatcher.Invoke(() => {
        if (data.TryGetProperty("text", out var t)) cleanReport += t.GetString() + "\n";
        UpdateUI();
    }),
    () => Dispatcher.Invoke(() => { cleanPhase = "done"; cleanStatus = "Complete"; UpdateUI(); }),
    (ex) => Dispatcher.Invoke(() => { cleanStatus = "Error: " + ex.Message; UpdateUI(); })
);
CS);

        $previewAction = Action::custom(<<<'CS'
cleanPhase = "running";
cleanStatus = "Previewing...";
cleanReport = "";
UpdateUI();
await App.Stream.StartAsync("/api/clean/preview",
    (evt, data) => Dispatcher.Invoke(() => {
        if (data.TryGetProperty("text", out var t)) cleanReport += t.GetString() + "\n";
        UpdateUI();
    }),
    () => Dispatcher.Invoke(() => { cleanPhase = "idle"; cleanStatus = "Preview complete"; UpdateUI(); }),
    (ex) => Dispatcher.Invoke(() => { cleanStatus = "Error: " + ex.Message; UpdateUI(); })
);
CS);

        $cancelAction = Action::custom('await App.Api.PostAsync<object>("/api/clean/cancel");');
        $backAction   = Action::custom('cleanPhase = "idle"; cleanReport = "Waiting to start...";');

        // ── 空闲态: ToolHero ──
        $hero = ToolHero::build($tool, [
            PillButton::primary('Clean Now', $cleanAction),
            PillButton::secondary('Preview', $previewAction),
        ]);

        // ── 运行态: 状态栏 + 进度 ──
        $statusBar = TaskReport::statusBar($bindings['cleanStatus'], $tool->accent());
        $progress  = (new Progress())
            ->style(Style::make()
                ->height(3)
                ->backgroundColor(Theme::HAIRLINE));

        // ── 报告区域 ──
        $reportBinding = new Binding('cleanReport', 'Waiting to start...');
        $report = TaskReport::build($reportBinding, $tool->accent());

        // ── 完成横幅 ──
        $doneDetail = new Binding('cleanDoneDetail', '');
        $doneBanner = TaskReport::doneBanner('Cleaned', $doneDetail, $tool->accent());

        // ── 组装: 空闲英雄 → 运行时报告 → 完成横幅 ──
        return new VStack(
            $hero,
            $statusBar,
            $progress,
            $doneBanner,
            $report,
        );
    }
}
