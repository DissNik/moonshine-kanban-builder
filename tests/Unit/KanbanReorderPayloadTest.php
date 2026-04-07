<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Tests\Unit;

use DissNik\MoonShineKanBanBuilder\Support\KanbanReorderPayload;
use DissNik\MoonShineKanBanBuilder\Tests\TestCase;
use InvalidArgumentException;

final class KanbanReorderPayloadTest extends TestCase
{
    public function test_reorder_payload_contract_is_explicit_and_stable(): void
    {
        $this->assertSame([
            'itemId' => 'item_id',
            'targetColumnId' => 'column_id',
            'previousColumnId' => 'previous_column_id',
            'orderedIds' => 'ordered_ids',
        ], KanbanReorderPayload::clientConfig());
    }

    public function test_reorder_payload_is_normalized_through_value_object(): void
    {
        $payload = KanbanReorderPayload::fromArray([
            'item_id' => 'lead-1',
            'column_id' => 'qualification',
            'previous_column_id' => 'new',
            'ordered_ids' => ['lead-1', 'lead-2'],
        ]);

        $this->assertSame('lead-1', $payload->itemId);
        $this->assertSame('qualification', $payload->targetColumnId);
        $this->assertSame('new', $payload->previousColumnId);
        $this->assertSame(['lead-1', 'lead-2'], $payload->orderedIds);
        $this->assertSame([
            'item_id' => 'lead-1',
            'column_id' => 'qualification',
            'previous_column_id' => 'new',
            'ordered_ids' => ['lead-1', 'lead-2'],
        ], $payload->toArray());
    }

    public function test_reorder_payload_rejects_missing_required_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required reorder field [previous_column_id].');

        KanbanReorderPayload::fromArray([
            'item_id' => 'lead-1',
            'column_id' => 'qualification',
            'ordered_ids' => ['lead-1'],
        ]);
    }

    public function test_reorder_payload_rejects_invalid_ordered_ids(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reorder field [ordered_ids] must contain only non-empty scalar ids.');

        KanbanReorderPayload::fromArray([
            'item_id' => 'lead-1',
            'column_id' => 'qualification',
            'previous_column_id' => 'new',
            'ordered_ids' => ['lead-1', ''],
        ]);
    }

    public function test_direct_constructor_keeps_value_object_invariants(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reorder field [item_id] must be a non-empty scalar value.');

        new KanbanReorderPayload(
            itemId: ' ',
            targetColumnId: 'qualification',
            previousColumnId: 'new',
            orderedIds: ['lead-1'],
        );
    }
}
