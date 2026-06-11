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
 * 安装包清理视图 — 对应 Tool::Installer。
 *
 * 扫描并删除残留的安装包文件（.exe, .msi 等）。
 * 与 CleanView 相同模式。
 */
final class InstallerView
{
    /**
     * @param array<string, Binding> $bindings
     */
    public static function build(array $bindings): VStack
    {
        $tool = Tool::Installer;

        // ── 按钮动作 ──
        $cleanAction = Action::custom(<<<'CS'
installerPhase = "running";
installerStatus = "Scanning for installers...";
installerHeroVisible = false;
installerRunningVisible = true;
installerDoneVisible = false;
installerReport = "";
installerProgress = 0;
UpdateUI();
await App.Stream.StartAsync("/api/installer/execute",
    (evt, data) => Dispatcher.Invoke(() => {
        if (evt == "progress" && data.ValueKind == System.Text.Json.JsonValueKind.Number)
            installerProgress = data.GetDouble();
        else if (data.TryGetProperty("text", out var t)) installerReport += t.GetString() + "\n";
        UpdateUI();
    }),
    () => Dispatcher.Invoke(() => { installerPhase = "done"; installerStatus = "Complete"; installerHeroVisible = false; installerRunningVisible = false; installerDoneVisible = true; installerProgress = 100; UpdateUI(); }),
    (ex) => Dispatcher.Invoke(() => { installerStatus = "Error: " + ex.Message; UpdateUI(); })
);
CS);

        $previewAction = Action::custom(<<<'CS'
installerPhase = "running";
installerStatus = "Previewing...";
installerHeroVisible = false;
installerRunningVisible = true;
installerDoneVisible = false;
installerReport = "";
installerProgress = 0;
UpdateUI();
await App.Stream.StartAsync("/api/installer/preview",
    (evt, data) => Dispatcher.Invoke(() => {
        if (evt == "progress" && data.ValueKind == System.Text.Json.JsonValueKind.Number)
            installerProgress = data.GetDouble();
        else if (data.TryGetProperty("text", out var t)) installerReport += t.GetString() + "\n";
        UpdateUI();
    }),
    () => Dispatcher.Invoke(() => { installerPhase = "idle"; installerStatus = "Preview complete"; installerHeroVisible = true; installerRunningVisible = false; installerDoneVisible = false; installerProgress = 100; UpdateUI(); }),
    (ex) => Dispatcher.Invoke(() => { installerStatus = "Error: " + ex.Message; UpdateUI(); })
);
CS);

        // ── 空闲态: ToolHero ──
        $statusText = (new Text($bindings['installerStatus']))
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
            PillButton::primary('Clean Installers', $cleanAction),
            PillButton::secondary('Preview', $previewAction),
        ], [$statusText, $reportText]);
        $hero->name('panel_installerHero');
        $hero->visible($bindings['installerHeroVisible']);

        // ── 运行态: 进度条 ──
        $progress  = (new Progress($bindings['installerProgress']))
            ->style(Style::make()
                ->height(3)
                ->backgroundColor(Theme::HAIRLINE));

        // ── 完成横幅 ──
        $doneDetail = new Binding('installerDoneDetail', '');
        $doneBanner = TaskReport::doneBanner('Cleaned', $doneDetail, $tool->accent());

        // ── 命名容器 + visible() 绑定 ──
        $runningSection = (new VStack($progress))
            ->name('panel_installerRunning')
            ->visible($bindings['installerRunningVisible']);
        $doneSection = (new VStack($doneBanner))
            ->name('panel_installerDone')
            ->visible($bindings['installerDoneVisible']);

        return new VStack(
            new Spacer(),
            $hero,
            $runningSection,
            $doneSection,
        );
    }
}
