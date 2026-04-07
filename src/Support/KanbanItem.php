<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Support;

use InvalidArgumentException;

final readonly class KanbanItem
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public string $id,
        public string $html,
        public array $attributes = [],
    ) {
        if ($this->id === '') {
            throw new InvalidArgumentException('Kanban item id must not be empty.');
        }

        if ($this->html === '') {
            throw new InvalidArgumentException('Kanban item html must not be empty.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $id = isset($payload['id']) ? (string) $payload['id'] : '';
        $html = isset($payload['html']) ? (string) $payload['html'] : '';
        unset($payload['id'], $payload['html']);

        return new self($id, $html, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_replace($this->attributes, [
            'id' => $this->id,
            'html' => $this->html,
        ]);
    }
}
