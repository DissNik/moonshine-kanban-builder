<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Support;

use InvalidArgumentException;

final readonly class KanbanSnapshot
{
    /**
     * @param list<KanbanColumn> $columns
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public array $columns,
        public ?string $version = null,
        public bool $changed = true,
        public array $meta = [],
        public array $filters = [],
    ) {
        $columnIds = array_map(
            static fn (KanbanColumn $column): string => $column->id,
            $this->columns,
        );

        if (count($columnIds) !== count(array_unique($columnIds))) {
            throw new InvalidArgumentException('Kanban snapshot contains duplicate column ids.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $columns = collect($payload['columns'] ?? [])
            ->map(static fn (mixed $column): KanbanColumn => $column instanceof KanbanColumn
                ? $column
                : KanbanColumn::fromArray((array) $column))
            ->values()
            ->all();

        return new self(
            columns: $columns,
            version: isset($payload['version']) && $payload['version'] !== ''
                ? (string) $payload['version']
                : null,
            changed: (bool) ($payload['changed'] ?? true),
            meta: is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
            filters: is_array($payload['filters'] ?? null) ? $payload['filters'] : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'changed' => $this->changed,
            'version' => $this->version,
            'filters' => $this->filters,
            'meta' => $this->meta,
            'columns' => array_map(
                static fn (KanbanColumn $column): array => $column->toArray(),
                $this->columns,
            ),
        ];
    }
}
