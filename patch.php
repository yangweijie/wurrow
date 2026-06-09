<?php

/**
 * 自动应用补丁到 vendor 目录
 * 用于修复第三方库的兼容性问题
 */

function copyPatchesSafely(): bool {
    $source = __DIR__ . '/patches/';
    $destination = __DIR__ . '/vendor/';

    if (!is_dir($source)) {
        echo "没有找到补丁目录，跳过补丁应用\n";
        return false;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $patchedFiles = 0;
    foreach ($iterator as $item) {
        $relativePath = substr($item->getPathname(), strlen($source));
        $target = $destination . $relativePath;

        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }
        } else {
            if (copy($item->getPathname(), $target)) {
                $patchedFiles++;
                echo "已应用补丁: {$relativePath}\n";
            }
        }
    }

    if ($patchedFiles > 0) {
        echo "成功应用 {$patchedFiles} 个补丁文件\n";
    }

    return true;
}

echo "========================================\n";
echo "应用补丁...\n";
echo "========================================\n";
copyPatchesSafely();
