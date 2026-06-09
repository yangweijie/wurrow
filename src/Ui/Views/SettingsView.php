<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Ui\Views;

use Perry\UI\Action;
use Perry\UI\Binding;
use Perry\UI\Styling\Style;
use Perry\UI\Widget\Checkbox;
use Perry\UI\Widget\HStack;
use Perry\UI\Widget\Slider;
use Perry\UI\Widget\Spacer;
use Perry\UI\Widget\Text;
use Perry\UI\Widget\TextInput;
use Perry\UI\Widget\Toggle;
use Perry\UI\Widget\VStack;
use Yangweijie\Wurrow\Ui\Components\PillButton;
use Yangweijie\Wurrow\Ui\Theme;

/**
 * 设置视图 — 对应 Burrow SettingsView.swift。
 *
 * 基本设置项: PHP 服务器端口、清理目标、扫描路径等。
 */
final class SettingsView
{
    /**
     * @param array<string, Binding> $bindings
     */
    public static function build(array $bindings): VStack
    {
        // ── 标题 ──
        $title = (new Text('Settings'))
            ->style(Theme::title());

        // ── 服务器端口 ──
        $portLabel = (new Text('PHP Server Port'))
            ->style(Theme::body());
        $portInput = (new TextInput($bindings['serverPort'], '7891'))
            ->style(Style::make()
                ->backgroundColor(Theme::CARD_FILL)
                ->foregroundColor(Theme::TEXT_PRIMARY)
                ->cornerRadius(8)
                ->padding(8)
                ->width(100));

        $serverSection = self::section('Server', [
            new HStack($portLabel, new Spacer(), $portInput),
        ]);

        // ── 清理目标 ──
        $tempBinding    = new Binding('cleanTemp', true);
        $browserBinding  = new Binding('cleanBrowser', true);
        $thumbBinding   = new Binding('cleanThumbs', true);
        $prefetchBinding = new Binding('cleanPrefetch', false);
        $recycleBinding  = new Binding('cleanRecycle', false);

        $tempToggle = new Toggle('Windows Temp files', $tempBinding);
        $browserToggle = new Toggle('Browser caches (Chrome, Edge, Firefox)', $browserBinding);
        $thumbToggle = new Toggle('Thumbnail cache', $thumbBinding);
        $prefetchToggle = new Toggle('Prefetch files', $prefetchBinding);
        $recycleToggle = new Toggle('Empty Recycle Bin', $recycleBinding);

        $cleanSection = self::section('Cleanup Targets', [
            $tempToggle,
            $browserToggle,
            $thumbToggle,
            $prefetchToggle,
            $recycleToggle,
        ]);

        // ── 保存按钮 ──
        $saveAction = Action::custom(<<<'CS'
var config = new {
    serverPort = int.TryParse(textbox_serverPort.Text, out var p) ? p : 7891,
    cleanTemp = checkbox_cleanTemp.IsChecked == true,
    cleanBrowser = checkbox_cleanBrowser.IsChecked == true,
    cleanThumbs = checkbox_cleanThumbs.IsChecked == true,
    cleanPrefetch = checkbox_cleanPrefetch.IsChecked == true,
    cleanRecycle = checkbox_cleanRecycle.IsChecked == true,
};
var result = await App.Api.PostAsync<System.Text.Json.JsonElement>("/api/settings", config);
if (result != null) {
    settingsStatus = "Settings saved!";
} else {
    settingsStatus = "Failed to save settings.";
}
UpdateUI();
CS);

        $saveBtn = PillButton::accent('Save Settings', $saveAction, Theme::ACCENT_ANALYZE);
        $statusText = (new Text($bindings['settingsStatus']))
            ->style(Theme::mono(Theme::TEXT_TERTIARY));

        $saveRow = new HStack($saveBtn, new Spacer(), $statusText);

        // ── 关于 ──
        $versionText = (new Text('Wurrow v0.1.0 — Windows system cleanup tool'))
            ->style(Theme::mono(Theme::TEXT_TERTIARY));
        $poweredBy = (new Text('Powered by perry-php'))
            ->style(Theme::mono(Theme::TEXT_TERTIARY));

        $aboutSection = self::section('About', [
            $versionText,
            $poweredBy,
        ]);

        return new VStack(
            (new VStack($title))->style(Style::make()->padding(18)),
            $serverSection,
            $cleanSection,
            (new VStack($saveRow))->style(Style::make()->padding(18)),
            $aboutSection,
            new Spacer(),
        );
    }

    /**
     * 创建设置分区。
     *
     * @param string   $title    分区标题
     * @param Widget[] $items    分区内容
     */
    private static function section(string $title, array $items): VStack
    {
        $header = (new Text(strtoupper($title)))
            ->style(Style::make()
                ->fontSize(10)
                ->fontWeight('bold')
                ->foregroundColor(Theme::ACCENT_ANALYZE)
                ->letterSpacing(0.7));

        return (new VStack($header, ...$items))
            ->style(Theme::card()->margin(18));
    }
}
