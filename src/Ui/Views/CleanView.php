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
cleanHeroVisible = false;
cleanRunningVisible = true;
cleanDoneVisible = false;
cleanReport = "";
cleanProgress = 0;
UpdateUI();
await App.Stream.StartAsync("/api/clean/execute",
    (evt, data) => Dispatcher.Invoke(() => {
        if (evt == "progress" && data.ValueKind == System.Text.Json.JsonValueKind.Number)
            cleanProgress = data.GetDouble();
        else if (data.TryGetProperty("text", out var t)) cleanReport += t.GetString() + "\n";
        UpdateUI();
    }),
    () => Dispatcher.Invoke(() => { cleanPhase = "done"; cleanStatus = "Complete"; cleanHeroVisible = false; cleanRunningVisible = false; cleanDoneVisible = true; cleanProgress = 100; UpdateUI(); }),
    (ex) => Dispatcher.Invoke(() => { cleanStatus = "Error: " + ex.Message; UpdateUI(); })
);
CS);

        $previewAction = Action::custom(<<<'CS'
cleanPhase = "running";
cleanStatus = "Previewing...";
cleanHeroVisible = false;
cleanRunningVisible = true;
cleanDoneVisible = false;
cleanReport = "";
cleanProgress = 0;
UpdateUI();
await App.Stream.StartAsync("/api/clean/preview",
    (evt, data) => Dispatcher.Invoke(() => {
        if (evt == "progress" && data.ValueKind == System.Text.Json.JsonValueKind.Number)
            cleanProgress = data.GetDouble();
        else if (data.TryGetProperty("text", out var t)) cleanReport += t.GetString() + "\n";
        UpdateUI();
    }),
    () => Dispatcher.Invoke(() => { cleanPhase = "idle"; cleanStatus = "Preview complete"; cleanHeroVisible = true; cleanRunningVisible = false; cleanDoneVisible = false; cleanProgress = 100; UpdateUI(); }),
    (ex) => Dispatcher.Invoke(() => { cleanStatus = "Error: " + ex.Message; UpdateUI(); })
);
CS);

        // ── 空闲态: ToolHero ──
        $statusText = (new Text($bindings['cleanStatus']))
            ->style(Style::make()
                ->fontSize(11)
                ->fontFamily('Cascadia Code')
                ->foregroundColor(Theme::TEXT_SECONDARY)
                ->set(\Perry\UI\Styling\StyleProperty::Margin, '0,16,0,4'));
        $reportText = (new Text('Waiting to start...'))
            ->style(Style::make()
                ->fontSize(12)
                ->fontFamily('Cascadia Code')
                ->foregroundColor(Theme::TEXT_PRIMARY));

        $hero = ToolHero::build($tool, [
            PillButton::primary('Clean Now', $cleanAction),
            PillButton::secondary('Preview', $previewAction),
        ], [$statusText, $reportText]);
        $hero->name('panel_cleanHero');
        $hero->visible($bindings['cleanHeroVisible']);

        // ── 运行态: 进度条 ──
        $progress  = (new Progress($bindings['cleanProgress']))
            ->style(Style::make()
                ->height(3)
                ->backgroundColor(Theme::HAIRLINE));

        // ── 完成横幅 ──
        $doneDetail = new Binding('cleanDoneDetail', '');
        $doneBanner = TaskReport::doneBanner('Cleaned', $doneDetail, $tool->accent());

        // ── 命名容器 + visible() 绑定 ──
        $runningSection = (new VStack($progress))
            ->name('panel_cleanRunning')
            ->visible($bindings['cleanRunningVisible']);
        $doneSection = (new VStack($doneBanner))
            ->name('panel_cleanDone')
            ->visible($bindings['cleanDoneVisible']);

        // ── 组装 ──
        return new VStack(
            new Spacer(),
            $hero,
            $runningSection,
            $doneSection,
        );
    }
}
