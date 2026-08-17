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
        public bool $locked = false,
        public string $headerHtml = '',
    ) {
        if ($this->id === '') {
            throw new InvalidArgumentException('Kanban column id must not be empty.');
        }

        if ($this->label === '') {
            throw new InvalidArgumentException('Kanban column label must not be empty.');
        }

        $itemIds = array_map(
            static fn (KanbanItem $item): string => $item->id,
            $this->items,
        );

        if (count($itemIds) !== count(array_unique($itemIds))) {
            throw new InvalidArgumentException(sprintf(
                'Kanban column [%s] contains duplicate item ids.',
                $this->id,
            ));
        }
    }

    /**
     * @param array{id?: mixed, label?: mixed, items?: mixed, locked?: mixed, header_html?: mixed} $payload
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
            locked: (bool) ($payload['locked'] ?? false),
            headerHtml: isset($payload['header_html']) ? (string) $payload['header_html'] : '',
        );
    }

    /**
     * @return array{id: string, label: string, items: list<array<string, mixed>>, locked: bool, header_html: string}
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
            'locked' => $this->locked,
            'header_html' => $this->headerHtml,
        ];
    }
}
