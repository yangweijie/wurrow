<?php

declare(strict_types=1);

/**
 * Wurrow PHP 后端服务器入口。
 *
 * 用法: php -S localhost:7891 src/Backend/Server.php
 *
 * 提供系统清理、优化、磁盘分析、软件管理的 HTTP API。
 * 由 WinUI 桌面应用在启动时自动启动。
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Yangweijie\Wurrow\Backend\Router;
use Yangweijie\Wurrow\Backend\Services\CleanService;
use Yangweijie\Wurrow\Backend\Services\PurgeService;
use Yangweijie\Wurrow\Backend\Services\InstallerService;
use Yangweijie\Wurrow\Backend\Services\OptimizeService;
use Yangweijie\Wurrow\Backend\Services\AnalyzeService;
use Yangweijie\Wurrow\Backend\Services\SoftwareService;
use Yangweijie\Wurrow\Backend\Services\ConfigService;

// ── CORS 预检请求 ──
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    header('Access-Control-Allow-Origin: ' . ($origin === '' || $origin === 'null' ? '*' : 'null'));
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    return;
}

$router = new Router();

// ── Health Check ──────────────────────────────────────────────────
$router->get('/api/health', function () {
    Router::json([
        'status'  => 'ok',
        'version' => '0.1.0',
        'name'    => 'Wurrow Backend',
        'php'     => PHP_VERSION,
    ]);
});

// ── Clean 端点 ───────────────────────────────────────────────────
$cleanService = new CleanService();
$configService = new ConfigService();

$router->post('/api/clean/preview', function () use ($cleanService, $configService) {
    Router::sseHeaders();
    $targets = $configService->getCleanTargets();
    $cleanService->preview(function (string $event, mixed $data) {
        Router::sseSend($event, $data);
    }, $targets);
});

$router->post('/api/clean/execute', function () use ($cleanService, $configService) {
    Router::sseHeaders();
    $targets = $configService->getCleanTargets();
    $cleanService->execute(function (string $event, mixed $data) {
        Router::sseSend($event, $data);
    }, $targets);
});

$router->post('/api/clean/cancel', function () use ($cleanService) {
    $cleanService->cancel();
    Router::json(['status' => 'cancelled']);
});

// ── Purge 端点 ──────────────────────────────────────────────────
$purgeService = new PurgeService();

$router->post('/api/purge/preview', function () use ($purgeService) {
    Router::sseHeaders();
    $purgeService->preview(function (string $event, mixed $data) {
        Router::sseSend($event, $data);
    });
});

$router->post('/api/purge/execute', function () use ($purgeService) {
    Router::sseHeaders();
    $purgeService->execute(function (string $event, mixed $data) {
        Router::sseSend($event, $data);
    });
});

$router->post('/api/purge/cancel', function () use ($purgeService) {
    $purgeService->cancel();
    Router::json(['status' => 'cancelled']);
});

// ── Installer 端点 ──────────────────────────────────────────────
$installerService = new InstallerService();

$router->post('/api/installer/preview', function () use ($installerService) {
    Router::sseHeaders();
    $installerService->preview(function (string $event, mixed $data) {
        Router::sseSend($event, $data);
    });
});

$router->post('/api/installer/execute', function () use ($installerService) {
    Router::sseHeaders();
    $installerService->execute(function (string $event, mixed $data) {
        Router::sseSend($event, $data);
    });
});

$router->post('/api/installer/cancel', function () use ($installerService) {
    $installerService->cancel();
    Router::json(['status' => 'cancelled']);
});

// ── Optimize 端点 ────────────────────────────────────────────────
$optimizeService = new OptimizeService();

$router->post('/api/optimize/preview', function () use ($optimizeService) {
    Router::sseHeaders();
    $optimizeService->preview(function (string $event, mixed $data) {
        Router::sseSend($event, $data);
    });
});

$router->post('/api/optimize/execute', function () use ($optimizeService) {
    Router::sseHeaders();
    $optimizeService->execute(function (string $event, mixed $data) {
        Router::sseSend($event, $data);
    });
});

// ── Analyze 端点 ─────────────────────────────────────────────────
$analyzeService = new AnalyzeService();

$router->get('/api/analyze', function () use ($analyzeService) {
    $path = Router::queryParam('path', getenv('USERPROFILE') ?: 'C:\\Users');
    try {
        $result = $analyzeService->scan($path);
        Router::json($result);
    } catch (\Throwable $e) {
        Router::error($e->getMessage(), 500);
    }
});

$router->delete('/api/analyze/trash', function () use ($analyzeService) {
    $body = Router::readJsonBody();
    $path = $body['path'] ?? '';
    if (empty($path)) {
        Router::error('Missing path parameter', 400);
        return;
    }
    try {
        $analyzeService->trash($path);
        Router::json(['status' => 'trashed', 'path' => $path]);
    } catch (\Throwable $e) {
        Router::error($e->getMessage(), 500);
    }
});

// ── Software 端点 ────────────────────────────────────────────────
$softwareService = new SoftwareService();

$router->get('/api/software/list', function () use ($softwareService) {
    $sortBy = Router::queryParam('sort', 'size');
    try {
        $apps = $softwareService->listInstalled($sortBy);
        Router::json(['apps' => $apps, 'count' => count($apps)]);
    } catch (\Throwable $e) {
        Router::error($e->getMessage(), 500);
    }
});

$router->get('/api/software/search', function () use ($softwareService) {
    $query  = Router::queryParam('q', '');
    $sortBy = Router::queryParam('sort', 'size');
    try {
        $apps = $softwareService->search($query, $sortBy);
        Router::json(['apps' => $apps, 'count' => count($apps)]);
    } catch (\Throwable $e) {
        Router::error($e->getMessage(), 500);
    }
});

$router->post('/api/software/uninstall', function () use ($softwareService) {
    $body = Router::readJsonBody();
    $ids  = $body['ids'] ?? [];
    if (empty($ids)) {
        Router::error('Missing ids parameter', 400);
        return;
    }
    try {
        $result = $softwareService->uninstall($ids);
        Router::json($result);
    } catch (\Throwable $e) {
        Router::error($e->getMessage(), 500);
    }
});

// ── Settings 端点 ────────────────────────────────────────────────

$router->get('/api/settings', function () use ($configService) {
    Router::json($configService->load());
});

$router->post('/api/settings', function () use ($configService) {
    $body = Router::readJsonBody();
    $configService->save($body);
    Router::json(['status' => 'saved', 'config' => $configService->load()]);
});

// ── 路由分发 ─────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri    = $_SERVER['REQUEST_URI'];

$router->dispatch($method, $uri);

if (!$router->isMatched()) {
    Router::error("Not found: {$method} {$uri}", 404);
}
