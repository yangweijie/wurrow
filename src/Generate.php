<?php

declare(strict_types=1);

/**
 * Wurrow XAML 代码生成脚本。
 *
 * 用法: php src/Generate.php [winui]
 *
 * 生成 MainWindow.xaml 和 MainWindow.xaml.cs 到 generated/ 目录。
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Yangweijie\Wurrow\Ui\WurrowApp;

$target = $argv[1] ?? 'winui';
$outputDir = __DIR__ . '/../generated';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

echo "Wurrow Code Generator\n";
echo "=====================\n";
echo "Target: {$target}\n";
echo "Output: {$outputDir}\n\n";

try {
    $wurrow = new WurrowApp();

    // 生成 XAML
    $xaml = $wurrow->generate();
    $xamlPath = $outputDir . '/MainWindow.xaml';
    file_put_contents($xamlPath, $xaml);
    echo "Generated: MainWindow.xaml (" . strlen($xaml) . " bytes)\n";

    // 生成 C# code-behind
    $csCode = $wurrow->generateCodeBehind();
    $csPath = $outputDir . '/MainWindow.xaml.cs';
    file_put_contents($csPath, $csCode);
    echo "Generated: MainWindow.xaml.cs (" . strlen($csCode) . " bytes)\n";

    // 提取 WebView HTML (treemap 页面)
    $webHtml = $wurrow->getWebViewHtml();
    if ($webHtml !== null) {
        $htmlPath = $outputDir . '/MainWindow.html';
        file_put_contents($htmlPath, $webHtml);
        echo "Generated: MainWindow.html (" . strlen($webHtml) . " bytes)\n";
    }

    echo "\nDone! Files written to: {$outputDir}\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
