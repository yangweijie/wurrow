<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Ui\Views;

use Perry\UI\Action;
use Perry\UI\Binding;
use Perry\UI\Styling\Style;
use Perry\UI\Widget\Button;
use Perry\UI\Widget\HStack;
use Perry\UI\Widget\ScrollView;
use Perry\UI\Widget\Spacer;
use Perry\UI\Widget\Text;
use Perry\UI\Widget\VStack;
use Perry\UI\Widget\WebView;
use Yangweijie\Wurrow\Ui\Components\ToolHero;
use Yangweijie\Wurrow\Ui\Theme;
use Yangweijie\Wurrow\Ui\Tool;

/**
 * 磁盘分析视图 — 对应 Burrow AnalyzeView.swift。
 *
 * 左侧: 侧栏（orb + 摘要 + 最大条目列表）
 * 右侧: 面包屑工具栏 + WebView2 Treemap
 */
final class AnalyzeView
{
    /**
     * @param array<string, Binding> $bindings
     */
    public static function build(array $bindings): HStack
    {
        $tool = Tool::Analyze;

        // ── 左侧栏 ──
        $sidebar = self::buildSidebar($bindings, $tool);

        // ── 右侧主区域 ──
        $mainArea = self::buildMainArea($bindings, $tool);

        return new HStack($sidebar, $mainArea);
    }

    private static function buildSidebar(array $bindings, Tool $tool): VStack
    {
        // Orb 图标
        $orb = (new Text($tool->glyph()))
            ->style(Style::make()
                ->fontSize(28)
                ->textAlignment('center')
                ->width(78)
                ->height(78)
                ->backgroundColor($tool->accent())
                ->cornerRadius(39)
                ->foregroundColor('#FFFFFF')
                ->opacity(0.85));

        // 摘要文本
        $summary = (new Text($bindings['analyzeSummary']))
            ->style(Theme::mono(Theme::TEXT_SECONDARY));

        // 侧栏头部
        $header = (new VStack($orb, $summary))
            ->style(Style::make()->padding(16));

        // 条目列表占位（C# code-behind 动态填充）
        $entriesList = new ScrollView(
            (new VStack(
                (new Text('Largest items will appear here'))
                    ->style(Theme::mono(Theme::TEXT_TERTIARY))
            ))->style(Style::make()->padding(10))
        );

        return (new VStack($header, $entriesList))
            ->style(Style::make()
                ->width(232)
                ->backgroundColor(Theme::SURFACE));
    }

    private static function buildMainArea(array $bindings, Tool $tool): VStack
    {
        // 共用的分析 + 推送到 Treemap 代码片段
        $analyzeAndPush = <<<'CS'
var result = await App.Api.GetAsync<System.Text.Json.JsonElement>("/api/analyze?path=" + Uri.EscapeDataString(analyzePath));
if (result != null) {
    var entries = result.Value.GetProperty("entries");
    analyzeSummary = "Found " + entries.GetArrayLength() + " items";
    try {
        webView.CoreWebView2.PostWebMessageAsJson(
            JsonSerializer.Serialize(new { entries }));
    } catch { }
    UpdateUI();
}
CS;

        // ── Browse 按钮 ──
        $browseAction = Action::custom(<<<CS
var dlg = new Microsoft.Win32.OpenFolderDialog();
if (dlg.ShowDialog() == true) {
    analyzePath = dlg.FolderName;
    analyzeSummary = "Scanning...";
    UpdateUI();
    {$analyzeAndPush}
}
CS);

        $browseButton = (new Button("\u{1F4C2}", $browseAction))
            ->style(Style::make()
                ->fontSize(11)
                ->foregroundColor(Theme::TEXT_SECONDARY)
                ->backgroundColor(Theme::CARD_FILL)
                ->cornerRadius(4)
                ->width(24)
                ->height(24));

        // ── 工具栏 ──
        $upButton = (new Button("\u{2191}", Action::custom(<<<CS
var parent = System.IO.Path.GetDirectoryName(analyzePath);
if (!string.IsNullOrEmpty(parent)) {
    analyzePath = parent;
    analyzeSummary = "Scanning...";
    UpdateUI();
    {$analyzeAndPush}
}
CS)))
            ->style(Style::make()
                ->fontSize(11)
                ->foregroundColor(Theme::TEXT_SECONDARY)
                ->backgroundColor(Theme::CARD_FILL)
                ->cornerRadius(4)
                ->width(24)
                ->height(24));

        $pathText = (new Text($bindings['analyzePath']))
            ->style(Theme::mono(Theme::TEXT_PRIMARY));

        $refreshButton = (new Button("\u{21BB}", Action::custom(<<<CS
if (!string.IsNullOrEmpty(analyzePath)) {
    analyzeSummary = "Scanning...";
    UpdateUI();
    {$analyzeAndPush}
}
CS)))
            ->style(Style::make()
                ->fontSize(11)
                ->foregroundColor(Theme::TEXT_SECONDARY)
                ->backgroundColor(Theme::CARD_FILL)
                ->cornerRadius(4)
                ->width(24)
                ->height(24));

        $toolbar = new HStack(
            $browseButton,
            $upButton,
            $pathText,
            new Spacer(),
            $refreshButton,
        );

        // ── Treemap 区域 (WebView2) ──
        // WebView widget 加载 treemap.html — squarified treemap 用 HTML/JS 渲染
        $treemapHtml = <<<'HTML'
<!DOCTYPE html>
<html>
<head><meta charset="utf-8">
<style>
body { margin:0; background:#1a1a1a; overflow:hidden; font-family:'Segoe UI',sans-serif; }
#treemap { width:100vw; height:100vh; position:relative; }
.block { position:absolute; border-radius:3px; cursor:pointer; overflow:hidden;
         transition: opacity 0.15s; box-sizing:border-box; border:1px solid rgba(0,0,0,0.25); }
.block:hover { opacity:0.85; border-color:rgba(255,255,255,0.6); }
.block .label { padding:4px; color:#fff; text-shadow:0 1px 2px rgba(0,0,0,0.5); }
.block .name { font-size:11px; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.block .size { font-size:9px; opacity:0.85; font-family:'Cascadia Code',monospace; }
#empty { display:flex; align-items:center; justify-content:center; height:100vh;
         color:#6b6b6b; font-size:13px; }
</style>
</head>
<body>
<div id="treemap"><div id="empty">Select a folder to analyze</div></div>
<script>
const PALETTE = ['#4FA3E3','#57C2A5','#E6A93C','#F0884E','#8E84F0','#5AA8F0','#E0667E','#6FB06A'];

function squarify(weights, rect) {
    if (!weights.length) return [];
    const total = weights.reduce((a,b) => a+b, 0);
    if (total === 0) return weights.map(() => ({x:0,y:0,w:0,h:0}));
    const rects = [];
    let remaining = [...weights.map((w,i) => ({w, i}))];
    let r = {...rect};
    while (remaining.length) {
        const shorter = Math.min(r.w, r.h);
        let row = [remaining.shift()];
        let rowArea = row[0].w / total * r.w * r.h;
        while (remaining.length) {
            const next = remaining[0];
            const testRow = [...row, next];
            const testArea = testRow.reduce((s,e) => s+e.w, 0) / total * r.w * r.h;
            if (worst(testRow, testArea, shorter) <= worst(row, rowArea, shorter)) {
                row = testRow; rowArea = testArea; remaining.shift();
            } else break;
        }
        const isWide = r.w >= r.h;
        const rowSize = isWide ? rowArea / r.h : rowArea / r.w;
        let offset = 0;
        for (const item of row) {
            const frac = item.w / row.reduce((s,e) => s+e.w, 0);
            const sz = frac * (isWide ? r.h : r.w);
            rects[item.i] = isWide
                ? {x:r.x, y:r.y+offset, w:rowSize, h:sz}
                : {x:r.x+offset, y:r.y, w:sz, h:rowSize};
            offset += sz;
        }
        if (isWide) { r.x += rowSize; r.w -= rowSize; }
        else { r.y += rowSize; r.h -= rowSize; }
    }
    return rects;
}
function worst(row, area, shorter) {
    const s = row.reduce((a,e) => a+e.w, 0);
    const max = Math.max(...row.map(e => e.w));
    const min = Math.min(...row.map(e => e.w));
    return Math.max(shorter*shorter*max/(area*area), area*area/(shorter*shorter*min));
}
function formatBytes(b) {
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    if (b < 1073741824) return (b/1048576).toFixed(1) + ' MB';
    return (b/1073741824).toFixed(1) + ' GB';
}
function render(entries) {
    const container = document.getElementById('treemap');
    container.innerHTML = '';
    if (!entries || !entries.length) {
        container.innerHTML = '<div id="empty">No data</div>';
        return;
    }
    const shown = entries.filter(e => e.size > 0).slice(0, 120);
    const rect = {x:0, y:0, w:container.clientWidth, h:container.clientHeight};
    const weights = shown.map(e => e.size);
    const layout = squarify(weights, rect);
    shown.forEach((entry, i) => {
        const r = layout[i];
        if (!r || r.w < 2 || r.h < 2) return;
        const color = PALETTE[i % PALETTE.length];
        const div = document.createElement('div');
        div.className = 'block';
        div.style.cssText = `left:${r.x}px;top:${r.y}px;width:${r.w-2}px;height:${r.h-2}px;background:linear-gradient(180deg,${color}cc,${color}88);`;
        if (r.w > 66 && r.h > 28) {
            div.innerHTML = `<div class="label"><div class="name">${entry.name}</div><div class="size">${formatBytes(entry.size)}</div></div>`;
        }
        div.onclick = () => {
            if (entry.is_dir && window.chrome && window.chrome.webview) {
                window.chrome.webview.postMessage({type:'drill', path:entry.path});
            }
        };
        div.oncontextmenu = (e) => {
            e.preventDefault();
            if (window.chrome && window.chrome.webview) {
                window.chrome.webview.postMessage({type:'context', path:entry.path, name:entry.name});
            }
        };
        container.appendChild(div);
    });
}
window.addEventListener('message', e => { if (e.data && e.data.entries) render(e.data.entries); });
try { window.chrome.webview.addEventListener('message', e => { if (e.data && e.data.entries) render(e.data.entries); }); } catch(e) {}
</script>
</body></html>
HTML;

        $webView = (new WebView($treemapHtml))
            ->style(Style::make()
                ->backgroundColor(Theme::NEAR_BLACK));

        return new VStack(
            (new HStack($toolbar->style(Style::make()->padding(12))))
                ->style(Style::make()),
            $webView,
        );
    }
}
