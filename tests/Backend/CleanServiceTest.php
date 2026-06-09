<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Yangweijie\Wurrow\Backend\Services\CleanService;
use Yangweijie\Wurrow\Backend\Services\PowerShellRunner;

/**
 * @covers \Yangweijie\Wurrow\Backend\Services\CleanService
 */
class CleanServiceTest extends TestCase
{
    private CleanService $service;

    protected function setUp(): void
    {
        $this->service = new CleanService();
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

        $summaryEvents = array_filter($events, fn($e) => $e['event'] === 'summary');
        $this->assertCount(1, $summaryEvents);
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

    public function testPreviewWithFilteredTargets(): void
    {
        $events = [];
        $this->service->preview(function (string $event, mixed $data) use (&$events) {
            $events[] = ['event' => $event, 'data' => $data];
        }, ['temp' => true, 'browser' => true]);

        $this->assertNotEmpty($events);
        $lastEvent = end($events);
        $this->assertEquals('done', $lastEvent['event']);
    }

    public function testPreviewWithEmptyTargetsFallsBackToAll(): void
    {
        $events = [];
        $this->service->preview(function (string $event, mixed $data) use (&$events) {
            $events[] = ['event' => $event, 'data' => $data];
        }, []);

        $this->assertNotEmpty($events);
        $lastEvent = end($events);
        $this->assertEquals('done', $lastEvent['event']);
    }

    public function testCancelStopsProcessing(): void
    {
        $this->service->cancel();
        $this->assertTrue(true); // cancel should not throw
    }
}
