<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Support;

use InvalidArgumentException;

final class KanbanReorderPayload
{
    public const ITEM_ID = 'item_id';

    public const TARGET_COLUMN_ID = 'column_id';

    public const PREVIOUS_COLUMN_ID = 'previous_column_id';

    public const ORDERED_IDS = 'ordered_ids';

    /**
     * @param list<string> $orderedIds
     */
    public function __construct(
        public readonly string $itemId,
        public readonly string $targetColumnId,
        public readonly string $previousColumnId,
        public readonly array $orderedIds,
    ) {
        self::assertNormalizedString($this->itemId, self::ITEM_ID);
        self::assertNormalizedString($this->targetColumnId, self::TARGET_COLUMN_ID);
        self::assertNormalizedString($this->previousColumnId, self::PREVIOUS_COLUMN_ID);
        self::assertNormalizedOrderedIds($this->orderedIds);
    }

    /**
     * @return array<string, string>
     */
    public static function clientConfig(): array
    {
        return [
            'itemId' => self::ITEM_ID,
            'targetColumnId' => self::TARGET_COLUMN_ID,
            'previousColumnId' => self::PREVIOUS_COLUMN_ID,
            'orderedIds' => self::ORDERED_IDS,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            itemId: self::normalizeRequiredString($payload, self::ITEM_ID),
            targetColumnId: self::normalizeRequiredString($payload, self::TARGET_COLUMN_ID),
            previousColumnId: self::normalizeRequiredString($payload, self::PREVIOUS_COLUMN_ID),
            orderedIds: self::normalizeOrderedIds($payload),
        );
    }

    /**
     * @return array{
     *     item_id: string,
     *     column_id: string,
     *     previous_column_id: string,
     *     ordered_ids: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            self::ITEM_ID => $this->itemId,
            self::TARGET_COLUMN_ID => $this->targetColumnId,
            self::PREVIOUS_COLUMN_ID => $this->previousColumnId,
            self::ORDERED_IDS => $this->orderedIds,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function normalizeRequiredString(array $payload, string $key): string
    {
        if (! array_key_exists($key, $payload)) {
            throw new InvalidArgumentException("Missing required reorder field [{$key}].");
        }

        $value = $payload[$key];

        if (! is_scalar($value)) {
            throw new InvalidArgumentException("Reorder field [{$key}] must be a non-empty scalar value.");
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            throw new InvalidArgumentException("Reorder field [{$key}] must be a non-empty scalar value.");
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private static function normalizeOrderedIds(array $payload): array
    {
        if (! array_key_exists(self::ORDERED_IDS, $payload) || ! is_array($payload[self::ORDERED_IDS])) {
            throw new InvalidArgumentException('Reorder field [ordered_ids] must be a non-empty list of card ids.');
        }

        $orderedIds = array_map(static function (mixed $value): string {
            if (! is_scalar($value)) {
                throw new InvalidArgumentException('Reorder field [ordered_ids] must contain only non-empty scalar ids.');
            }

            $normalized = trim((string) $value);

            if ($normalized === '') {
                throw new InvalidArgumentException('Reorder field [ordered_ids] must contain only non-empty scalar ids.');
            }

            return $normalized;
        }, $payload[self::ORDERED_IDS]);

        self::assertNormalizedOrderedIds($orderedIds);

        return $orderedIds;
    }

    private static function assertNormalizedString(string $value, string $key): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("Reorder field [{$key}] must be a non-empty scalar value.");
        }
    }

    /**
     * @param list<string> $orderedIds
     */
    private static function assertNormalizedOrderedIds(array $orderedIds): void
    {
        if ($orderedIds === [] || ! array_is_list($orderedIds)) {
            throw new InvalidArgumentException('Reorder field [ordered_ids] must be a non-empty list of card ids.');
        }

        foreach ($orderedIds as $orderedId) {
            if (trim($orderedId) === '') {
                throw new InvalidArgumentException('Reorder field [ordered_ids] must contain only non-empty scalar ids.');
            }
        }
    }
}
