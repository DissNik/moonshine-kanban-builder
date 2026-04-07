<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Support;

use DissNik\MoonShineKanBanBuilder\Contracts\KanbanTransportContract;

final class NullKanbanTransport implements KanbanTransportContract
{
    public function clientConfig(): array
    {
        return [
            'mode' => KanbanConfig::transportMode(),
            'allowedModes' => KanbanConfig::allowedTransportModes(),
            'polling' => [
                'interval' => KanbanConfig::pollingInterval(),
            ],
            'signals' => [
                'refresh' => KanbanConfig::transportRefreshSignal(),
            ],
            'events' => [
                'reorderRefresh' => KanbanConfig::reorderRefreshEvents(),
            ],
            'adapter' => [
                'configured' => false,
            ],
        ];
    }
}
