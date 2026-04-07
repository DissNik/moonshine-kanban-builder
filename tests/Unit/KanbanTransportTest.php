<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Tests\Unit;

use DissNik\MoonShineKanBanBuilder\Contracts\KanbanTransportContract;
use DissNik\MoonShineKanBanBuilder\Support\KanbanTransport;
use DissNik\MoonShineKanBanBuilder\Support\NullKanbanTransport;
use DissNik\MoonShineKanBanBuilder\Tests\TestCase;

final class KanbanTransportTest extends TestCase
{
    public function test_null_transport_is_resolved_by_default(): void
    {
        $transport = $this->app->make(KanbanTransportContract::class);

        $this->assertInstanceOf(NullKanbanTransport::class, $transport);
        $this->assertSame([
            'mode' => 'manual',
            'allowedModes' => ['manual', 'polling', 'websocket'],
            'polling' => [
                'interval' => 5000,
            ],
            'signals' => [
                'refresh' => 'kanban.refresh',
            ],
            'events' => [
                'reorderRefresh' => ['fragment_updated:crud-list'],
            ],
            'adapter' => [
                'configured' => false,
            ],
        ], $transport->clientConfig());
    }

    public function test_custom_transport_adapter_is_resolved_from_config(): void
    {
        config()->set('moonshine-kanban-builder.transport.adapter', FakeKanbanTransport::class);

        (new \DissNik\MoonShineKanBanBuilder\Providers\MoonShineKanBanBuilderServiceProvider($this->app))->register();

        $transport = $this->app->make(KanbanTransportContract::class);

        $this->assertInstanceOf(FakeKanbanTransport::class, $transport);
        $this->assertSame([
            'mode' => 'websocket',
            'allowedModes' => ['manual', 'polling', 'websocket'],
            'polling' => [
                'interval' => 0,
            ],
            'signals' => [
                'refresh' => 'kanban.refresh',
            ],
            'events' => [
                'reorderRefresh' => ['lead-kanban:reordered'],
            ],
            'adapter' => [
                'configured' => true,
            ],
        ], $this->app->make(KanbanTransport::class)->clientConfig());
    }
}

final class FakeKanbanTransport implements KanbanTransportContract
{
    public function clientConfig(): array
    {
        return [
            'mode' => 'websocket',
            'allowedModes' => ['manual', 'polling', 'websocket'],
            'polling' => [
                'interval' => 0,
            ],
            'signals' => [
                'refresh' => 'kanban.refresh',
            ],
            'events' => [
                'reorderRefresh' => ['lead-kanban:reordered'],
            ],
            'adapter' => [
                'configured' => true,
            ],
        ];
    }
}
