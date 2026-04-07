<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Contracts;

interface KanbanTransportContract
{
    /**
     * @return array<string, mixed>
     */
    public function clientConfig(): array;
}
