<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Tests\Unit;

use DissNik\MoonShineKanBanBuilder\Support\KanbanConfig;
use DissNik\MoonShineKanBanBuilder\Tests\TestCase;

final class KanbanConfigTest extends TestCase
{
    public function test_default_values_are_stable(): void
    {
        $this->assertSame('manual', KanbanConfig::transportMode());
        $this->assertSame(5000, KanbanConfig::pollingInterval());
        $this->assertSame('kanban.refresh', KanbanConfig::transportRefreshSignal());
        $this->assertSame(['fragment_updated:crud-list'], KanbanConfig::reorderRefreshEvents());
        $this->assertSame(['manual', 'polling', 'websocket'], KanbanConfig::allowedTransportModes());
        $this->assertNull(KanbanConfig::transportAdapter());
    }

    public function test_nested_config_keys_override_defaults(): void
    {
        config()->set('moonshine-kanban-builder.events.reorder_refresh', ['lead-kanban:reordered']);
        config()->set('moonshine-kanban-builder.transport.mode', 'polling');
        config()->set('moonshine-kanban-builder.transport.polling.interval', 9000);
        config()->set('moonshine-kanban-builder.transport.signals.refresh', 'lead-kanban:refresh');
        config()->set('moonshine-kanban-builder.transport.allowed_modes', ['manual', 'polling']);
        config()->set('moonshine-kanban-builder.transport.adapter', 'App\\Kanban\\FakeTransport');

        $this->assertSame('polling', KanbanConfig::transportMode());
        $this->assertSame(9000, KanbanConfig::pollingInterval());
        $this->assertSame('lead-kanban:refresh', KanbanConfig::transportRefreshSignal());
        $this->assertSame(['lead-kanban:reordered'], KanbanConfig::reorderRefreshEvents());
        $this->assertSame(['manual', 'polling'], KanbanConfig::allowedTransportModes());
        $this->assertSame('App\\Kanban\\FakeTransport', KanbanConfig::transportAdapter());
    }

    public function test_invalid_transport_mode_falls_back_to_manual(): void
    {
        config()->set('moonshine-kanban-builder.transport.mode', 'invalid');

        $this->assertSame('manual', KanbanConfig::transportMode());
    }
}
