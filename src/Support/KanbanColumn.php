<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Support;

use InvalidArgumentException;

final readonly class KanbanColumn
{
    /**
     * @param list<KanbanItem> $items
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $items = [],
    ) {
        if ($this->id === '') {
            throw new InvalidArgumentException('Kanban column id must not be empty.');
        }

        if ($this->label === '') {
            throw new InvalidArgumentException('Kanban column label must not be empty.');
        }
    }

    /**
     * @param array{id?: mixed, label?: mixed, items?: mixed} $payload
     */
    public static function fromArray(array $payload): self
    {
        $items = collect($payload['items'] ?? [])
            ->map(static fn (mixed $item): KanbanItem => $item instanceof KanbanItem
                ? $item
                : KanbanItem::fromArray((array) $item))
            ->values()
            ->all();

        return new self(
            id: isset($payload['id']) ? (string) $payload['id'] : '',
            label: isset($payload['label']) ? (string) $payload['label'] : '',
            items: $items,
        );
    }

    /**
     * @return array{id: string, label: string, items: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'items' => array_map(
                static fn (KanbanItem $item): array => $item->toArray(),
                $this->items,
            ),
        ];
    }
}
