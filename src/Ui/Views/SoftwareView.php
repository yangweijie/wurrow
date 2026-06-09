<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Ui\Views;

use Perry\UI\Action;
use Perry\UI\Binding;
use Perry\UI\Styling\Style;
use Perry\UI\Widget\Button;
use Perry\UI\Widget\HStack;
use Perry\UI\Widget\ListWidget;
use Perry\UI\Widget\ScrollView;
use Perry\UI\Widget\Spacer;
use Perry\UI\Widget\Text;
use Perry\UI\Widget\TextInput;
use Perry\UI\Widget\VStack;
use Yangweijie\Wurrow\Ui\Components\PillButton;
use Yangweijie\Wurrow\Ui\Theme;
use Yangweijie\Wurrow\Ui\Tool;

/**
 * 软件管理视图 — 对应 Burrow SoftwareView.swift。
 *
 * 工具栏: 搜索框 + 排序按钮组
 * 主区域: 已安装软件列表（ScrollView + 行组件）
 * 底栏: 选择计数 + Uninstall 按钮
 */
final class SoftwareView
{
    /**
     * @param array<string, Binding> $bindings
     */
    public static function build(array $bindings): VStack
    {
        $tool = Tool::Apps;

        // ── 工具栏 ──
        $searchInput = (new TextInput($bindings['softwareQuery'], 'Search apps...'))
            ->style(Style::make()
                ->backgroundColor(Theme::CARD_FILL)
                ->foregroundColor(Theme::TEXT_PRIMARY)
                ->cornerRadius(16)
                ->padding(8)
                ->width(180));

        $sortBySize = (new Button('size', Action::custom('// TODO: Set sort to size')))
            ->style(Style::make()
                ->fontSize(11)
                ->foregroundColor($tool->accent())
                ->backgroundColor('transparent'));

        $sortByName = (new Button('name', Action::custom('// TODO: Set sort to name')))
            ->style(Style::make()
                ->fontSize(11)
                ->foregroundColor(Theme::TEXT_SECONDARY)
                ->backgroundColor('transparent'));

        $sortBySource = (new Button('source', Action::custom('// TODO: Set sort to source')))
            ->style(Style::make()
                ->fontSize(11)
                ->foregroundColor(Theme::TEXT_SECONDARY)
                ->backgroundColor('transparent'));

        $toolbar = new HStack(
            $searchInput,
            new Spacer(),
            $sortBySize,
            $sortByName,
            $sortBySource,
        );

        // ── 软件列表占位（C# code-behind 动态填充 ItemsControl）──
        $placeholder = (new Text('Installed apps will be loaded here from the PHP backend.'))
            ->style(Theme::mono(Theme::TEXT_TERTIARY));

        $listArea = new ScrollView(
            (new VStack($placeholder))
                ->style(Style::make()->padding(10))
        );

        // ── 底栏 ──
        $selectionLabel = (new Text($bindings['selectedCount']))
            ->style(Theme::mono(Theme::TEXT_SECONDARY));

        $uninstallAction = Action::custom('// TODO: Call POST /api/software/uninstall');
        $uninstallBtn = PillButton::accent('Uninstall', $uninstallAction, $tool->accent());

        $bottomBar = new HStack(
            $selectionLabel,
            new Spacer(),
            $uninstallBtn,
        );

        return new VStack(
            (new VStack($toolbar))
                ->style(Style::make()->padding(18)),
            $listArea,
            (new VStack($bottomBar))
                ->style(Style::make()->padding(10)),
        );
    }
}
