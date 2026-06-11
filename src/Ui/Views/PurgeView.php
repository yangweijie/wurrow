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
 * 开发文件清理视图 — 对应 Tool::Purge。
 *
 * 清理开发缓存和临时文件，释放磁盘空间。
 * 与 CleanView 相同模式。
 */
final class PurgeView
{
    /**
     * @param array<string, Binding> $bindings
     */
    public static function build(array $bindings): VStack
    {
        $tool = Tool::Purge;

        // ── 按钮动作 ──
        $purgeAction = Action::custom(<<<'CS'
purgePhase = "running";
purgeStatus = "Purging development files...";
purgeHeroVisible = false;
purgeRunningVisible = true;
purgeDoneVisible = false;
purgeReport = "";
purgeProgress = 0;
UpdateUI();
await App.Stream.StartAsync("/api/purge/execute",
    (evt, data) => Dispatcher.Invoke(() => {
        if (evt == "progress" && data.ValueKind == System.Text.Json.JsonValueKind.Number)
            purgeProgress = data.GetDouble();
        else if (data.TryGetProperty("text", out var t)) purgeReport += t.GetString() + "\n";
        UpdateUI();
    }),
    () => Dispatcher.Invoke(() => { purgePhase = "done"; purgeStatus = "Complete"; purgeHeroVisible = false; purgeRunningVisible = false; purgeDoneVisible = true; purgeProgress = 100; UpdateUI(); }),
    (ex) => Dispatcher.Invoke(() => { purgeStatus = "Error: " + ex.Message; UpdateUI(); })
);
CS);

        $previewAction = Action::custom(<<<'CS'
purgePhase = "running";
purgeStatus = "Previewing...";
purgeHeroVisible = false;
purgeRunningVisible = true;
purgeDoneVisible = false;
purgeReport = "";
purgeProgress = 0;
UpdateUI();
await App.Stream.StartAsync("/api/purge/preview",
    (evt, data) => Dispatcher.Invoke(() => {
        if (evt == "progress" && data.ValueKind == System.Text.Json.JsonValueKind.Number)
            purgeProgress = data.GetDouble();
        else if (data.TryGetProperty("text", out var t)) purgeReport += t.GetString() + "\n";
        UpdateUI();
    }),
    () => Dispatcher.Invoke(() => { purgePhase = "idle"; purgeStatus = "Preview complete"; purgeHeroVisible = true; purgeRunningVisible = false; purgeDoneVisible = false; purgeProgress = 100; UpdateUI(); }),
    (ex) => Dispatcher.Invoke(() => { purgeStatus = "Error: " + ex.Message; UpdateUI(); })
);
CS);

        // ── 空闲态: ToolHero ──
        $statusText = (new Text($bindings['purgeStatus']))
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
            PillButton::primary('Purge Now', $purgeAction),
            PillButton::secondary('Preview', $previewAction),
        ], [$statusText, $reportText]);
        $hero->name('panel_purgeHero');
        $hero->visible($bindings['purgeHeroVisible']);

        // ── 运行态: 进度条 ──
        $progress  = (new Progress($bindings['purgeProgress']))
            ->style(Style::make()
                ->height(3)
                ->backgroundColor(Theme::HAIRLINE));

        // ── 完成横幅 ──
        $doneDetail = new Binding('purgeDoneDetail', '');
        $doneBanner = TaskReport::doneBanner('Purged', $doneDetail, $tool->accent());

        // ── 命名容器 + visible() 绑定 ──
        $runningSection = (new VStack($progress))
            ->name('panel_purgeRunning')
            ->visible($bindings['purgeRunningVisible']);
        $doneSection = (new VStack($doneBanner))
            ->name('panel_purgeDone')
            ->visible($bindings['purgeDoneVisible']);

        return new VStack(
            new Spacer(),
            $hero,
            $runningSection,
            $doneSection,
        );
    }
}
