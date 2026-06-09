<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Yangweijie\Wurrow\Backend\Services\PurgeService;

/**
 * @covers \Yangweijie\Wurrow\Backend\Services\PurgeService
 */
class PurgeServiceTest extends TestCase
{
    private PurgeService $service;

    protected function setUp(): void
    {
        $this->service = new PurgeService();
    }

    public function testPreviewEmitsEvents(): void
    {
        $events = [];
        $this->service->preview(function (string $event, mixed $data) use (&$events) {
            $events[] = ['event' => $event, 'data' => $data];
        });

        $this->assertNotEmpty($events);
        $lastEvent = end($events);
        $this->assertEquals('done', $lastEvent['event']);

        $summaries = array_filter($events, fn($e) => $e['event'] === 'summary');
        $this->assertCount(1, $summaries);
    }

    public function testExecuteEmitsEvents(): void
    {
        $events = [];
        $this->service->execute(function (string $event, mixed $data) use (&$events) {
            $events[] = ['event' => $event, 'data' => $data];
        });

        $this->assertNotEmpty($events);
        $lastEvent = end($events);
        $this->assertEquals('done', $lastEvent['event']);
    }

    public function testCancel(): void
    {
        $this->service->cancel();
        $this->assertTrue(true);
    }

    public function testPreviewReportsAllCategories(): void
    {
        $events = [];
        $this->service->preview(function (string $event, mixed $data) use (&$events) {
            $events[] = $data;
        });

        $groups = array_filter($events, fn($e) => ($e['marker'] ?? '') === 'group');
        $expectedCategories = ['Node Modules', 'PHP Vendor', 'Python Cache',
            'Build Output', 'IDE Config', 'Log Files', 'System Junk'];
        foreach ($expectedCategories as $cat) {
            $found = array_filter($groups, fn($g) => ($g['text'] ?? '') === $cat);
            $this->assertNotEmpty($found, "Missing category: {$cat}");
        }
    }
}
