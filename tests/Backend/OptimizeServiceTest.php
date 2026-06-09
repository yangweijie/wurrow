<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Yangweijie\Wurrow\Backend\Services\OptimizeService;

/**
 * @covers \Yangweijie\Wurrow\Backend\Services\OptimizeService
 */
class OptimizeServiceTest extends TestCase
{
    private OptimizeService $service;

    protected function setUp(): void
    {
        $this->service = new OptimizeService();
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
    }

    public function testExecuteCompletes(): void
    {
        $events = [];
        $this->service->execute(function (string $event, mixed $data) use (&$events) {
            $events[] = ['event' => $event, 'data' => $data];
        });

        $this->assertNotEmpty($events);
        $summaryEvents = array_filter($events, fn($e) => $e['event'] === 'summary');
        $this->assertCount(1, $summaryEvents);
    }

    public function testPreviewListsOperations(): void
    {
        $events = [];
        $this->service->preview(function (string $event, mixed $data) use (&$events) {
            $events[] = $data;
        });

        $groups = array_filter($events, fn($e) => ($e['marker'] ?? '') === 'group');
        $this->assertNotEmpty($groups);
    }
}
