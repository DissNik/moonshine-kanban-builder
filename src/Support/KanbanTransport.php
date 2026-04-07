<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Support;

use DissNik\MoonShineKanBanBuilder\Contracts\KanbanTransportContract;

final readonly class KanbanTransport
{
    public function __construct(
        private KanbanTransportContract $transport,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function clientConfig(): array
    {
        return $this->transport->clientConfig();
    }
}
