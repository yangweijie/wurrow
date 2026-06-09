<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Yangweijie\Wurrow\Backend\Services\AnalyzeService;

/**
 * @covers \Yangweijie\Wurrow\Backend\Services\AnalyzeService
 */
class AnalyzeServiceTest extends TestCase
{
    private AnalyzeService $service;

    protected function setUp(): void
    {
        $this->service = new AnalyzeService();
    }

    public function testScanNonExistentPath(): void
    {
        $result = $this->service->scan('/nonexistent/path');
        $this->assertArrayHasKey('entries', $result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('total_size', $result);
        $this->assertArrayHasKey('total_files', $result);
        $this->assertIsArray($result['entries']);
    }

    public function testScanCurrentDir(): void
    {
        $result = $this->service->scan(__DIR__);
        $this->assertArrayHasKey('entries', $result);
        $this->assertEquals(__DIR__, $result['path']);
        $this->assertIsArray($result['entries']);

        // Entries should be sorted by size descending
        if (count($result['entries']) >= 2) {
            $this->assertGreaterThanOrEqual(
                $result['entries'][1]['size'],
                $result['entries'][0]['size']
            );
        }
    }

    public function testScanReturnsConsistentKeys(): void
    {
        $result = $this->service->scan(__DIR__);
        foreach ($result['entries'] as $entry) {
            $this->assertArrayHasKey('name', $entry);
            $this->assertArrayHasKey('path', $entry);
            $this->assertArrayHasKey('size', $entry);
            $this->assertArrayHasKey('is_dir', $entry);
        }
    }
}
