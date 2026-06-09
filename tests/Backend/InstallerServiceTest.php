<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Yangweijie\Wurrow\Backend\Services\InstallerService;

/**
 * @covers \Yangweijie\Wurrow\Backend\Services\InstallerService
 */
class InstallerServiceTest extends TestCase
{
    private InstallerService $service;

    protected function setUp(): void
    {
        $this->service = new InstallerService();
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

    public function testPreviewReportsLocations(): void
    {
        $events = [];
        $this->service->preview(function (string $event, mixed $data) use (&$events) {
            $events[] = $data;
        });

        $groups = array_filter($events, fn($e) => ($e['marker'] ?? '') === 'group');
        $expected = ['Downloads', 'Desktop', 'Temp Installers'];
        foreach ($expected as $loc) {
            $found = array_filter($groups, fn($g) => ($g['text'] ?? '') === $loc);
            $this->assertNotEmpty($found, "Missing location: {$loc}");
        }
    }

    public function testCancel(): void
    {
        $this->service->cancel();
        $this->assertTrue(true);
    }
}
