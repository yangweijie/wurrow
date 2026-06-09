<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Yangweijie\Wurrow\Backend\Router;

/**
 * @covers \Yangweijie\Wurrow\Backend\Router
 */
class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testGetRoute(): void
    {
        $called = false;
        $this->router->get('/api/test', function () use (&$called) {
            $called = true;
            Router::json(['ok' => true]);
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $this->router->dispatch('GET', '/api/test');
        $this->assertTrue($this->router->isMatched());
    }

    public function testNotFound(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/nonexistent';
        $this->router->dispatch('GET', '/api/nonexistent');
        $this->assertFalse($this->router->isMatched());
    }

    public function testPostRoute(): void
    {
        $called = false;
        $this->router->post('/api/data', function () use (&$called) {
            $called = true;
        });
        $this->router->dispatch('POST', '/api/data');
        $this->assertTrue($called);
    }

    public function testDeleteRoute(): void
    {
        $called = false;
        $this->router->delete('/api/data/{id}', function ($params) use (&$called) {
            $called = true;
            $this->assertEquals('42', $params['id']);
        });
        $this->router->dispatch('DELETE', '/api/data/42');
        $this->assertTrue($called);
    }

    public function testParamRoute(): void
    {
        $params = null;
        $this->router->get('/api/user/{id}', function ($p) use (&$params) {
            $params = $p;
        });
        $this->router->dispatch('GET', '/api/user/42');
        $this->assertIsArray($params);
        $this->assertEquals('42', $params['id']);
    }
}
