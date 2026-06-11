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
        $optimizeAction = Action::custom(<<<'CS'
optimizePhase = "running";
optimizeStatus = "Optimizing...";
optimizeHeroVisible = false;
optimizeRunningVisible = true;
optimizeDoneVisible = false;
optimizeReport = "";
optimizeProgress = 0;
UpdateUI();
await App.Stream.StartAsync("/api/optimize/execute",
    (evt, data) => Dispatcher.Invoke(() => {
        if (evt == "progress" && data.ValueKind == System.Text.Json.JsonValueKind.Number)
            optimizeProgress = data.GetDouble();
        else if (data.TryGetProperty("text", out var t)) optimizeReport += t.GetString() + "\n";
        UpdateUI();
    }),
    () => Dispatcher.Invoke(() => { optimizePhase = "done"; optimizeStatus = "Complete"; optimizeHeroVisible = false; optimizeRunningVisible = false; optimizeDoneVisible = true; optimizeProgress = 100; UpdateUI(); }),
    (ex) => Dispatcher.Invoke(() => { optimizeStatus = "Error: " + ex.Message; UpdateUI(); })
);
CS);

        $previewAction = Action::custom(<<<'CS'
optimizePhase = "running";
optimizeStatus = "Previewing...";
optimizeHeroVisible = false;
optimizeRunningVisible = true;
optimizeDoneVisible = false;
optimizeReport = "";
optimizeProgress = 0;
UpdateUI();
await App.Stream.StartAsync("/api/optimize/preview",
    (evt, data) => Dispatcher.Invoke(() => {
        if (evt == "progress" && data.ValueKind == System.Text.Json.JsonValueKind.Number)
            optimizeProgress = data.GetDouble();
        else if (data.TryGetProperty("text", out var t)) optimizeReport += t.GetString() + "\n";
        UpdateUI();
    }),
    () => Dispatcher.Invoke(() => { optimizePhase = "idle"; optimizeStatus = "Preview complete"; optimizeHeroVisible = true; optimizeRunningVisible = false; optimizeDoneVisible = false; optimizeProgress = 100; UpdateUI(); }),
    (ex) => Dispatcher.Invoke(() => { optimizeStatus = "Error: " + ex.Message; UpdateUI(); })
);
CS);

        // ── 空闲态: ToolHero ──
        $statusText = (new Text($bindings['optimizeStatus']))
            ->style(Style::make()
                ->fontSize(11)
                ->fontFamily('Cascadia Code')
                ->foregroundColor(Theme::TEXT_SECONDARY)
                ->set(\Perry\UI\Styling\StyleProperty::Margin, '0,16,0,4'));
        $reportText = (new Text('Waiting to start...'))
            ->style(Style::make()
                ->fontSize(12)
                ->fontFamily(Theme::FONT_MONO)
                ->foregroundColor(Theme::TEXT_PRIMARY));

        $hero = ToolHero::build($tool, [
            PillButton::primary('Optimize Now', $optimizeAction),
            PillButton::secondary('Preview', $previewAction),
        ], [$statusText, $reportText]);
        $hero->name('panel_optimizeHero');
        $hero->visible($bindings['optimizeHeroVisible']);

        // ── 运行态: 进度条 ──
        $progress  = (new Progress($bindings['optimizeProgress']))
            ->style(Style::make()
                ->height(3)
                ->backgroundColor(Theme::HAIRLINE));

        // ── 完成横幅 ──
        $doneDetail = new Binding('optimizeDoneDetail', '');
        $doneBanner = TaskReport::doneBanner('Optimized', $doneDetail, $tool->accent());

        // ── 命名容器 + visible() 绑定 ──
        $runningSection = (new VStack($progress))
            ->name('panel_optimizeRunning')
            ->visible($bindings['optimizeRunningVisible']);
        $doneSection = (new VStack($doneBanner))
            ->name('panel_optimizeDone')
            ->visible($bindings['optimizeDoneVisible']);

        return new VStack(
            new Spacer(),
            $hero,
            $runningSection,
            $doneSection,
        );
    }
}
