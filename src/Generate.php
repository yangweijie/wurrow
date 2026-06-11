<?php

declare(strict_types=1);

/**
 * Wurrow 代码生成 + 构建脚本。
 *
 * 用法: php src/Generate.php [winui] [--no-build]
 *
 * 1. 生成 MainWindow.xaml / MainWindow.xaml.cs / MainWindow.html 到 generated/
 * 2. 生成 Wurrow.csproj 项目文件
 * 3. 复制 hand-written C# 文件 (cs/*.cs) 到 generated/
 * 4. 执行 dotnet publish 生成可执行文件 (除非指定 --no-build)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Yangweijie\Wurrow\Ui\WurrowApp;

$target = 'winui';
$noBuild = false;

// 解析命令行参数
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--no-build') {
        $noBuild = true;
    } elseif (!str_starts_with($arg, '--')) {
        $target = $arg;
    }
}

$rootDir = realpath(__DIR__ . '/..');
$outputDir = $rootDir . DIRECTORY_SEPARATOR . 'generated';
$csDir = $rootDir . DIRECTORY_SEPARATOR . 'cs';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

echo "Wurrow Code Generator\n";
echo "=====================\n";
echo "Target: {$target}\n";
echo "Output: {$outputDir}\n\n";

try {
    $wurrow = new WurrowApp();

    // ── 1. 生成 XAML ──
    $xaml = $wurrow->generate();
    $xamlPath = $outputDir . DIRECTORY_SEPARATOR . 'MainWindow.xaml';
    file_put_contents($xamlPath, $xaml);
    echo "Generated: MainWindow.xaml (" . strlen($xaml) . " bytes)\n";

    // ── 2. 生成 C# code-behind ──
    $csCode = $wurrow->generateCodeBehind();
    $csPath = $outputDir . DIRECTORY_SEPARATOR . 'MainWindow.xaml.cs';
    file_put_contents($csPath, $csCode);
    echo "Generated: MainWindow.xaml.cs (" . strlen($csCode) . " bytes)\n";

    // ── 3. 提取 WebView HTML (treemap 页面) ──
    $webHtml = $wurrow->getWebViewHtml();
    if ($webHtml !== null) {
        $htmlPath = $outputDir . DIRECTORY_SEPARATOR . 'MainWindow.html';
        file_put_contents($htmlPath, $webHtml);
        echo "Generated: MainWindow.html (" . strlen($webHtml) . " bytes)\n";
    }

    // ── 4. 生成 .csproj ──
    $csprojPath = $outputDir . DIRECTORY_SEPARATOR . 'Wurrow.csproj';
    $csproj = generateCsproj();
    file_put_contents($csprojPath, $csproj);
    echo "Generated: Wurrow.csproj\n";

    // ── 5. 复制 hand-written C# 文件（包括 App.xaml） ──
    if (is_dir($csDir)) {
        foreach (array_merge(
            glob($csDir . DIRECTORY_SEPARATOR . '*.cs'),
            glob($csDir . DIRECTORY_SEPARATOR . '*.xaml')
        ) as $csFile) {
            $dest = $outputDir . DIRECTORY_SEPARATOR . basename($csFile);
            copy($csFile, $dest);
            echo "Copied: " . basename($csFile) . "\n";
        }
    }

    // ── 6. 后处理：修复 WPF 中 JsonElement (struct) 的 null 比较问题 ──
    postProcessGeneratedCode($outputDir);

    echo "\nDone! Files written to: {$outputDir}\n\n";

    // ── 7. dotnet publish (生成 exe) ──
    if ($noBuild) {
        echo "Skipping build (--no-build).\n";
    } else {
        buildExe($outputDir);
    }
} catch (\Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}

/**
 * 生成 Wurrow.csproj 项目文件内容。
 */
function generateCsproj(): string
{
    return <<<'XML'
<Project Sdk="Microsoft.NET.Sdk">

  <PropertyGroup>
    <OutputType>WinExe</OutputType>
    <TargetFramework>net9.0-windows</TargetFramework>
    <UseWPF>true</UseWPF>
    <Nullable>enable</Nullable>
    <ImplicitUsings>enable</ImplicitUsings>
    <ApplicationIcon />
    <StartupObject>Wurrow.App</StartupObject>
    <AssemblyName>Wurrow</AssemblyName>
    <RootNamespace>Wurrow</RootNamespace>
  </PropertyGroup>

  <ItemGroup>
    <PackageReference Include="Microsoft.Web.WebView2" Version="1.0.2903.40" />
  </ItemGroup>

  <ItemGroup>
    <None Update="MainWindow.html">
      <CopyToOutputDirectory>PreserveNewest</CopyToOutputDirectory>
    </None>
  </ItemGroup>

</Project>
XML;
}

/**
 * 复制 PHP 运行时需要的文件到 publish 目录。
 * 包括 src/, vendor/, composer.json, composer.lock, patch.php, patches/。
 */
function copyPhpSourceForPublish(string $rootDir, string $publishDir): void
{
    $phpDir = $publishDir . DIRECTORY_SEPARATOR . 'php';
    if (!is_dir($phpDir)) {
        mkdir($phpDir, 0755, true);
    }

    // 复制顶层 PHP 文件
    foreach (['composer.json', 'composer.lock', 'patch.php'] as $file) {
        $src = $rootDir . DIRECTORY_SEPARATOR . $file;
        if (file_exists($src)) {
            copy($src, $phpDir . DIRECTORY_SEPARATOR . $file);
        }
    }

    // 递归复制目录
    foreach (['src', 'vendor', 'patches'] as $subDir) {
        $srcDir = $rootDir . DIRECTORY_SEPARATOR . $subDir;
        if (is_dir($srcDir)) {
            recurseCopy($srcDir, $phpDir . DIRECTORY_SEPARATOR . $subDir);
        }
    }

    echo "  Copied PHP source to: {$phpDir}\n";
}

/**
 * 递归复制目录。
 */
function recurseCopy(string $src, string $dst): void
{
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $srcPath = $src . DIRECTORY_SEPARATOR . $file;
        $dstPath = $dst . DIRECTORY_SEPARATOR . $file;
        if (is_dir($srcPath)) {
            recurseCopy($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
    closedir($dir);
}

/**
 * 执行 dotnet publish 生成独立的可执行文件。
 */
function buildExe(string $outputDir): void
{
    echo "Building exe...\n";
    echo "=====================\n";

    // 检查 dotnet 是否可用
    $which = DIRECTORY_SEPARATOR === '\\' ? 'where' : 'which';
    exec("{$which} dotnet 2>NUL", $_, $dotnetCheck);
    if ($dotnetCheck !== 0) {
        fwrite(STDERR, "Warning: dotnet CLI not found in PATH. Skipping build.\n");
        fwrite(STDERR, "Install .NET 9.0 SDK and run: cd generated && dotnet publish -c Release\n");
        return;
    }

    $command = sprintf(
        'dotnet publish -c Release -o publish --self-contained false',
    );

    $command = sprintf('cd /d %s && %s', $outputDir, $command);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes);

    if (is_resource($process)) {
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        echo $stdout;
        if ($stderr) {
            echo $stderr;
        }

        if ($exitCode === 0) {
            $publishDir = $outputDir . DIRECTORY_SEPARATOR . 'publish';

            // 复制 PHP 源码到 publish 目录，让 FindProjectRoot() 能找到 composer.json
            $rootDir = realpath(__DIR__ . '/..');
            copyPhpSourceForPublish($rootDir, $publishDir);

            // 修复 WPF 硬件加速兼容性问题: 在 runtimeconfig.json 中禁用硬件加速
            $runtimeConfigPath = $publishDir . DIRECTORY_SEPARATOR . 'Wurrow.runtimeconfig.json';
            if (file_exists($runtimeConfigPath)) {
                $config = json_decode(file_get_contents($runtimeConfigPath), true);
                if (isset($config['runtimeOptions']['configProperties'])) {
                    $config['runtimeOptions']['configProperties']['System.Windows.Media.EnableHardwareAcceleration'] = false;
                    file_put_contents($runtimeConfigPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
                    echo "  Updated runtimeconfig.json (hardware acceleration disabled)\n";
                }
            }

            echo "\n✓ Build succeeded! Output: {$publishDir}" . DIRECTORY_SEPARATOR . "\n";
        } else {
            fwrite(STDERR, "\n✗ Build failed with exit code: {$exitCode}\n");
        }
    }
}

/**
 * 后处理生成的 C# 代码，修复 WPF 兼容性问题。
 *
 * JsonElement 是值类型 (struct)，在 C# 中不能与 null 直接比较。
 * 将 `var result = ...GetAsync<JsonElement>` 改为显式 `JsonElement? result = ...` 以支持 null 比较。
 * 覆盖 generated/ 下所有 .cs 文件（包括 hand-written 复制过来的）。
 */
function postProcessGeneratedCode(string $outputDir): void
{
    foreach (glob($outputDir . DIRECTORY_SEPARATOR . '*.cs') as $csPath) {
        $code = file_get_contents($csPath);

        // Pattern A: var result = await App.Api.GetAsync<System.Text.Json.JsonElement>(...)
        $code = preg_replace(
            '/var (\w+) = await (App\.Api\.(?:Get|Post)Async)<System\.Text\.Json\.JsonElement>/',
            'System.Text.Json.JsonElement? $1 = await $2<System.Text.Json.JsonElement>',
            $code
        );

        // Pattern B: var result = await App.Api.GetAsync<JsonElement>(...)  [短名称，有 using]
        $code = preg_replace(
            '/var (\w+) = await (App\.Api\.(?:Get|Post)Async)<JsonElement>/',
            'JsonElement? $1 = await $2<JsonElement>',
            $code
        );

        // Pattern C: var health = await apiClient.GetAsync<JsonElement>(...)
        $code = preg_replace(
            '/var (\w+) = await (apiClient\.(?:Get|Post)Async)<JsonElement>/',
            'JsonElement? $1 = await $2<JsonElement>',
            $code
        );

        // Pattern D: var result = await apiClient.GetAsync<System.Text.Json.JsonElement>(...)
        $code = preg_replace(
            '/var (\w+) = await (apiClient\.(?:Get|Post)Async)<System\.Text\.Json\.JsonElement>/',
            'System.Text.Json.JsonElement? $1 = await $2<System.Text.Json.JsonElement>',
            $code
        );

        file_put_contents($csPath, $code);
    }
    echo "Post-processed: all .cs files (fixed JsonElement null checks)\n";
}
