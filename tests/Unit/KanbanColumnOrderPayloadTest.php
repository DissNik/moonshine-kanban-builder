<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Tests\Unit;

use DissNik\MoonShineKanBanBuilder\Support\KanbanColumnOrderPayload;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KanbanColumnOrderPayloadTest extends TestCase
{
    public function test_it_normalizes_a_valid_order(): void
    {
        $payload = KanbanColumnOrderPayload::fromArray([
            'ordered_column_ids' => [' stage-a ', 'stage-b'],
            'version' => '7',
        ]);

        self::assertSame(['stage-a', 'stage-b'], $payload->orderedColumnIds);
        self::assertSame('7', $payload->version);
        self::assertSame([
            'ordered_column_ids' => ['stage-a', 'stage-b'],
            'version' => '7',
        ], $payload->toArray());
        self::assertSame([
            'orderedColumnIds' => 'ordered_column_ids',
            'version' => 'version',
        ], KanbanColumnOrderPayload::clientConfig());
    }

    #[DataProvider('invalidOrders')]
    public function test_it_rejects_invalid_orders(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Column order must be a non-empty unique list of scalar ids.');

        KanbanColumnOrderPayload::fromArray($payload);
    }

    public static function invalidOrders(): array
    {
        return [
            'missing' => [[]],
            'empty' => [['ordered_column_ids' => []]],
            'duplicates' => [['ordered_column_ids' => ['a', 'a']]],
            'not a list' => [['ordered_column_ids' => ['first' => 'a']]],
            'blank' => [['ordered_column_ids' => [' ']]],
            'non scalar' => [['ordered_column_ids' => [['a']]]],
        ];
    }
}
