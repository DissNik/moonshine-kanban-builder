<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Support;

final class KanbanConfig
{
    public static function transportMode(): string
    {
        return self::normalizeTransportMode(
            config('moonshine-kanban-builder.transport.mode', 'manual')
        );
    }

    public static function pollingInterval(): int
    {
        $interval = (int) config('moonshine-kanban-builder.transport.polling.interval', 5000);

        return $interval > 0 ? $interval : 5000;
    }

    public static function reorderRefreshCooldown(): int
    {
        $cooldown = (int) config('moonshine-kanban-builder.ui.reorder_refresh_cooldown_ms', 600);

        return $cooldown >= 0 ? $cooldown : 600;
    }

    /**
     * @return list<string>
     */
    public static function reorderRefreshEvents(): array
    {
        $events = config('moonshine-kanban-builder.events.reorder_refresh', [
            'fragment_updated:crud-list',
        ]);

        if (! is_array($events)) {
            return ['fragment_updated:crud-list'];
        }

        $normalized = array_values(array_filter(
            array_map(
                static fn (mixed $event): ?string => is_string($event) && $event !== '' ? $event : null,
                $events,
            ),
            static fn (?string $event): bool => $event !== null,
        ));

        return $normalized !== [] ? $normalized : ['fragment_updated:crud-list'];
    }

    /**
     * @return list<string>
     */
    public static function allowedTransportModes(): array
    {
        $modes = config('moonshine-kanban-builder.transport.allowed_modes', [
            'manual',
            'polling',
            'websocket',
        ]);

        if (! is_array($modes)) {
            return ['manual', 'polling', 'websocket'];
        }

        $normalized = array_values(array_filter(
            array_map(
                static fn (mixed $mode): ?string => is_string($mode) && $mode !== '' ? $mode : null,
                $modes,
            ),
            static fn (?string $mode): bool => $mode !== null,
        ));

        return $normalized !== [] ? $normalized : ['manual', 'polling', 'websocket'];
    }

    public static function transportRefreshSignal(): string
    {
        return (string) config('moonshine-kanban-builder.transport.signals.refresh', 'kanban.refresh');
    }

    public static function transportAdapter(): ?string
    {
        $adapter = config('moonshine-kanban-builder.transport.adapter');

        return is_string($adapter) && $adapter !== '' ? $adapter : null;
    }

    public static function normalizeTransportMode(string $mode): string
    {
        return in_array($mode, self::allowedTransportModes(), true)
            ? $mode
            : self::fallbackTransportMode();
    }

    private static function fallbackTransportMode(): string
    {
        $modes = self::allowedTransportModes();

        return in_array('manual', $modes, true) ? 'manual' : $modes[0];
    }
}
