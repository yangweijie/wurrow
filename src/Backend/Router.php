<?php

declare(strict_types=1);

namespace Yangweijie\Wurrow\Backend;

/**
 * 轻量级 HTTP 路由器。
 *
 * 为 PHP 内置服务器设计的简单路由，支持 GET/POST/DELETE 方法和 JSON 响应。
 * 无需外部框架依赖。
 */
final class Router
{
    /** @var array<string, array<string, callable>> method => [pattern => handler] */
    private array $routes = [];

    /**
     * 注册 GET 路由。
     */
    public function get(string $pattern, callable $handler): self
    {
        $this->routes['GET'][$pattern] = $handler;
        return $this;
    }

    /**
     * 注册 POST 路由。
     */
    public function post(string $pattern, callable $handler): self
    {
        $this->routes['POST'][$pattern] = $handler;
        return $this;
    }

    /**
     * 注册 DELETE 路由。
     */
    public function delete(string $pattern, callable $handler): self
    {
        $this->routes['DELETE'][$pattern] = $handler;
        return $this;
    }

    /** @var bool 当前请求是否已匹配路由 */
    private bool $matched = false;

    /**
     * 当前请求是否已匹配路由。
     */
    public function isMatched(): bool
    {
        return $this->matched;
    }

    /**
     * 分发请求到匹配的处理器。
     *
     * @return mixed 处理器返回值
     */
    public function dispatch(string $method, string $uri): mixed
    {
        $this->matched = false;

        // 去掉查询字符串
        $path = parse_url($uri, PHP_URL_PATH) ?: $uri;
        $path = rtrim($path, '/') ?: '/';

        $methodRoutes = $this->routes[$method] ?? [];

        // 精确匹配优先
        if (isset($methodRoutes[$path])) {
            $this->matched = true;
            return ($methodRoutes[$path])();
        }

        // 正则匹配
        foreach ($methodRoutes as $pattern => $handler) {
            if (str_contains($pattern, '{')) {
                $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
                $regex = '#^' . $regex . '$#';
                if (preg_match($regex, $path, $matches)) {
                    $this->matched = true;
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    return $handler($params);
                }
            }
        }

        return null;
    }

    /**
     * 设置安全的 CORS Origin：仅允许空 Origin（同源）或 null Origin（file://, WebView2）。
     * 阻止任意外部网站调用本地 API。
     */
    private static function setCorsOrigin(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin === '' || $origin === 'null') {
            header('Access-Control-Allow-Origin: *');
        } else {
            header('Access-Control-Allow-Origin: null');
        }
    }

    /**
     * 发送 JSON 响应。
     */
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        self::setCorsOrigin();
        header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * 发送错误响应。
     */
    public static function error(string $message, int $status = 500): void
    {
        self::json(['error' => $message, 'status' => $status], $status);
    }

    /**
     * 发送 SSE（Server-Sent Events）头。
     */
    public static function sseHeaders(): void
    {
        http_response_code(200);
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        self::setCorsOrigin();
        header('X-Accel-Buffering: no'); // nginx 兼容
    }

    /**
     * 发送 SSE 数据行。
     */
    public static function sseSend(string $event, mixed $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        echo "event: {$event}\n";
        echo "data: {$json}\n\n";
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    /**
     * 读取请求体 JSON。
     */
    public static function readJsonBody(): array
    {
        $body = file_get_contents('php://input');
        if (empty($body)) {
            return [];
        }
        return json_decode($body, true) ?? [];
    }

    /**
     * 获取查询参数。
     */
    public static function queryParam(string $key, string $default = ''): string
    {
        return $_GET[$key] ?? $default;
    }
}
