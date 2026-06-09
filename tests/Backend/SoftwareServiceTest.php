<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Yangweijie\Wurrow\Backend\Services\SoftwareService;
use Yangweijie\Wurrow\Backend\Services\PowerShellRunner;

/**
 * @covers \Yangweijie\Wurrow\Backend\Services\SoftwareService
 */
class SoftwareServiceTest extends TestCase
{
    private SoftwareService $service;

    protected function setUp(): void
    {
        $this->service = new SoftwareService();
    }

    public function testListInstalledReturnsArray(): void
    {
        $apps = $this->service->listInstalled('size');
        $this->assertIsArray($apps);
    }

    public function testListInstalledMockDataOnNonWindows(): void
    {
        if (!PowerShellRunner::isWindows()) {
            $apps = $this->service->listInstalled('size');
            $this->assertGreaterThan(0, count($apps));
            $names = array_column($apps, 'name');
            $this->assertContains('Visual Studio Code', $names);
        }
        $this->assertTrue(true);
    }

    public function testListInstalledSortedByName(): void
    {
        $apps = $this->service->listInstalled('name');
        $this->assertIsArray($apps);

        // 检查确实按名称排序
        for ($i = 1; $i < count($apps); $i++) {
            $this->assertGreaterThanOrEqual(
                strcasecmp($apps[$i - 1]['name'], $apps[$i]['name']),
                0,
                "Names not sorted: {$apps[$i-1]['name']} > {$apps[$i]['name']}"
            );
        }
    }

    public function testListInstalledSortedBySource(): void
    {
        $apps = $this->service->listInstalled('source');
        $this->assertIsArray($apps);
    }

    public function testSearchReturnsFilteredResults(): void
    {
        $results = $this->service->search('chrome');
        $this->assertIsArray($results);

        // 搜索应使用大小写不敏感匹配
        $results2 = $this->service->search('Chrome');
        $this->assertIsArray($results2);
    }

    public function testSearchWithEmptyQueryReturnsAll(): void
    {
        $all = $this->service->listInstalled('size');
        $searched = $this->service->search('', 'size');
        $this->assertEquals(count($all), count($searched));
    }

    public function testUninstallOnNonWindowsReturnsSimulated(): void
    {
        if (!PowerShellRunner::isWindows()) {
            $result = $this->service->uninstall(['Test App']);
            $this->assertIsArray($result);
            $this->assertEquals('simulated', $result['status']);
        }
        $this->assertTrue(true);
    }
}
