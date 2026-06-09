<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Yangweijie\Wurrow\Backend\Services\ConfigService;

/**
 * @covers \Yangweijie\Wurrow\Backend\Services\ConfigService
 */
class ConfigServiceTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/wurrow_test_' . uniqid();
        mkdir($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $configFile = $this->testDir . '/config.json';
        if (file_exists($configFile)) {
            unlink($configFile);
        }
        rmdir($this->testDir);
    }

    public function testLoadDefaults(): void
    {
        $service = new ConfigService($this->testDir);
        $config = $service->load();

        $this->assertArrayHasKey('serverPort', $config);
        $this->assertEquals(7891, $config['serverPort']);
        $this->assertTrue($config['cleanTemp']);
        $this->assertTrue($config['cleanBrowser']);
        $this->assertTrue($config['cleanThumbs']);
        $this->assertFalse($config['cleanPrefetch']);
        $this->assertFalse($config['cleanRecycle']);
    }

    public function testSaveAndLoadRoundTrip(): void
    {
        $service = new ConfigService($this->testDir);

        $service->save([
            'serverPort' => 8888,
            'cleanTemp' => false,
            'cleanPrefetch' => true,
        ]);

        $config = $service->load();

        $this->assertEquals(8888, $config['serverPort']);
        $this->assertFalse($config['cleanTemp']);
        $this->assertTrue($config['cleanPrefetch']);
        // 未保存的字段应保持默认值
        $this->assertTrue($config['cleanBrowser']);
        $this->assertTrue($config['cleanThumbs']);
    }

    public function testGetCleanTargets(): void
    {
        $service = new ConfigService($this->testDir);
        $targets = $service->getCleanTargets();

        $this->assertArrayHasKey('temp', $targets);
        $this->assertArrayHasKey('browser', $targets);
        $this->assertArrayHasKey('thumbs', $targets);
        $this->assertArrayHasKey('prefetch', $targets);
        $this->assertArrayHasKey('recycle', $targets);

        $this->assertIsBool($targets['temp']);
        $this->assertIsBool($targets['browser']);
    }

    public function testLoadNonExistentFileReturnsDefaults(): void
    {
        $service = new ConfigService($this->testDir . '/nonexistent_subdir');
        $config = $service->load();
        $this->assertEquals(7891, $config['serverPort']);
    }

    public function testSaveOnlyWhitelistedKeys(): void
    {
        $service = new ConfigService($this->testDir);

        // Try to save unknown keys
        $service->save([
            'serverPort' => 9999,
            'unknownKey' => 'should_not_be_saved',
            'malicious' => 'injection',
        ]);

        $config = $service->load();
        $this->assertEquals(9999, $config['serverPort']);
        $this->assertArrayNotHasKey('unknownKey', $config);
        $this->assertArrayNotHasKey('malicious', $config);
    }
}
