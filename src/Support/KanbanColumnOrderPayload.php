<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Support;

use InvalidArgumentException;

final readonly class KanbanColumnOrderPayload
{
    public const ORDERED_COLUMN_IDS = 'ordered_column_ids';

    public const VERSION = 'version';

    /** @param list<string> $orderedColumnIds */
    private function __construct(
        public array $orderedColumnIds,
        public ?string $version,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $rawIds = $payload[self::ORDERED_COLUMN_IDS] ?? null;

        if (! is_array($rawIds) || $rawIds === [] || ! array_is_list($rawIds)) {
            throw self::invalidOrder();
        }

        $ids = [];

        foreach ($rawIds as $rawId) {
            if (! is_scalar($rawId)) {
                throw self::invalidOrder();
            }

            $id = trim((string) $rawId);

            if ($id === '' || in_array($id, $ids, true)) {
                throw self::invalidOrder();
            }

            $ids[] = $id;
        }

        $version = $payload[self::VERSION] ?? null;

        if ($version !== null && ! is_scalar($version)) {
            throw new InvalidArgumentException('Column order version must be scalar or null.');
        }

        return new self($ids, $version === null ? null : (string) $version);
    }

    /** @return array{ordered_column_ids: list<string>, version: string|null} */
    public function toArray(): array
    {
        return [
            self::ORDERED_COLUMN_IDS => $this->orderedColumnIds,
            self::VERSION => $this->version,
        ];
    }

    /** @return array{orderedColumnIds: string, version: string} */
    public static function clientConfig(): array
    {
        return [
            'orderedColumnIds' => self::ORDERED_COLUMN_IDS,
            'version' => self::VERSION,
        ];
    }

    private static function invalidOrder(): InvalidArgumentException
    {
        return new InvalidArgumentException(
            'Column order must be a non-empty unique list of scalar ids.',
        );
    }
}
