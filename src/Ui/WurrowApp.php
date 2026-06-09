<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Ui;

use Perry\App;
use Perry\Build\Target;
use Perry\UI\Binding;
use Perry\UI\Styling\Style;
use Perry\UI\Widget\AppContainer;
use Perry\UI\Widget\TabView;
use Perry\UI\Widget\Text;
use Perry\UI\Widget\VStack;

use Yangweijie\Wurrow\Ui\Views\CleanView;
use Yangweijie\Wurrow\Ui\Views\OptimizeView;
use Yangweijie\Wurrow\Ui\Views\AnalyzeView;
use Yangweijie\Wurrow\Ui\Views\SoftwareView;
use Yangweijie\Wurrow\Ui\Views\SettingsView;

/**
 * Wurrow 主应用定义。
 *
 * 对应 Burrow 的 RootView.swift — 组装 AppContainer 和所有工具视图。
 * 使用 perry-php 的 WinUIBackend 生成 WPF XAML + C# 代码。
 */
final class WurrowApp
{
    private App $app;

    /** @var array<string, Binding> 全局共享状态 */
    private array $bindings;

    public function __construct()
    {
        $this->app = new App();
        $this->app->setTarget(Target::Windows);
        $this->bindings = $this->createBindings();
    }

    /**
     * 创建全局共享状态绑定。
     *
     * @return array<string, Binding>
     */
    private function createBindings(): array
    {
        return [
            'activeTab'       => new Binding('activeTab', 0),
            'cleanPhase'      => new Binding('cleanPhase', 'idle'),
            'cleanStatus'     => new Binding('cleanStatus', 'Ready to scan your system'),
            'optimizePhase'   => new Binding('optimizePhase', 'idle'),
            'optimizeStatus'  => new Binding('optimizeStatus', 'Ready to optimize'),
            'analyzePath'     => new Binding('analyzePath', ''),
            'analyzeLoading'  => new Binding('analyzeLoading', false),
            'analyzeSummary'  => new Binding('analyzeSummary', 'Select a folder to analyze'),
            'softwareQuery'   => new Binding('softwareQuery', ''),
            'softwareLoading' => new Binding('softwareLoading', false),
            'selectedCount'   => new Binding('selectedCount', 0),
            'serverPort'      => new Binding('serverPort', 7891),
        ];
    }

    /**
     * 构建完整的 Wurrow UI 并生成 XAML 代码。
     */
    public function generate(): string
    {
        $root = $this->buildRoot();
        $this->app->setRoot($root);
        return $this->app->generateCode('winui');
    }

    /**
     * 构建完整的 C# code-behind 代码。
     */
    public function generateCodeBehind(): string
    {
        $root = $this->buildRoot();
        $this->app->setRoot($root);

        $backend = $this->app->codegen()->get('winui');
        $root->handle(); // trigger generation
        $backend->generate($root);
        return $backend->generateMainActivity('MainWindow');
    }

    /**
     * 获取 WebView HTML 内容（treemap 页面）。
     * 必须在 generate() 之后调用。
     */
    public function getWebViewHtml(): ?string
    {
        $backend = $this->app->codegen()->get('winui');
        return $backend->getWebViewHtml();
    }

    /**
     * 构建根 Widget 树。
     *
     * 对应 RootView.swift 的 body — VStack( TopNav + content )。
     * perry-php 使用 TabView 实现工具切换（对应 Burrow 的 ZStack + tabVisible）。
     */
    private function buildRoot(): AppContainer
    {
        // 构建各工具视图
        $cleanTab    = CleanView::build($this->bindings);
        $optimizeTab = OptimizeView::build($this->bindings);
        $analyzeTab  = AnalyzeView::build($this->bindings);
        $softwareTab = SoftwareView::build($this->bindings);
        $settingsTab = SettingsView::build($this->bindings);

        // 使用 TabView 组装所有工具标签页
        $tabView = (new TabView(
            $cleanTab,
            $optimizeTab,
            $analyzeTab,
            $softwareTab,
            $settingsTab,
        ))
            ->label(0, 'Clean')
            ->label(1, 'Optimize')
            ->label(2, 'Analyze')
            ->label(3, 'Software')
            ->label(4, 'Settings')
            ->withSelected($this->bindings['activeTab']);

        // AppContainer 设置窗口尺寸和全局绑定
        $container = new AppContainer(
            $tabView,
            1060,  // windowWidth  (Burrow minWidth: 940 + padding)
            720,   // windowHeight (Burrow minHeight: 640 + padding)
            ...array_values($this->bindings),
        );

        return $container
            ->title('Wurrow')
            ->namespace('Wurrow')
            ->background(Theme::NEAR_BLACK);
    }
}
