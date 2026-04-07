<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Tests\Unit;

use DissNik\MoonShineKanBanBuilder\Support\KanbanColumn;
use DissNik\MoonShineKanBanBuilder\Support\KanbanItem;
use DissNik\MoonShineKanBanBuilder\Support\KanbanReorderPayload;
use DissNik\MoonShineKanBanBuilder\Support\KanbanSnapshot;
use DissNik\MoonShineKanBanBuilder\Tests\TestCase;
use InvalidArgumentException;

final class KanbanSnapshotTest extends TestCase
{
    public function test_snapshot_contract_is_normalized_through_value_objects(): void
    {
        $snapshot = KanbanSnapshot::fromArray([
            'changed' => true,
            'version' => 'snapshot-v1',
            'filters' => ['search' => 'Acme'],
            'meta' => ['timestamp' => '2026-04-07T11:00:00+05:00'],
            'columns' => [
                [
                    'id' => 'new',
                    'label' => 'New',
                    'items' => [
                        [
                            'id' => 'lead-1',
                            'html' => '<div>Lead</div>',
                            'title' => 'Lead',
                            'form_url' => '/leads/form/lead-1',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame([
            'changed' => true,
            'version' => 'snapshot-v1',
            'filters' => ['search' => 'Acme'],
            'meta' => ['timestamp' => '2026-04-07T11:00:00+05:00'],
            'columns' => [
                [
                    'id' => 'new',
                    'label' => 'New',
                    'items' => [
                        [
                            'title' => 'Lead',
                            'form_url' => '/leads/form/lead-1',
                            'id' => 'lead-1',
                            'html' => '<div>Lead</div>',
                        ],
                    ],
                ],
            ],
        ], $snapshot->toArray());
    }

    public function test_contract_rejects_items_without_identity_or_html(): void
    {
        $this->expectException(InvalidArgumentException::class);

        KanbanItem::fromArray([
            'id' => '',
            'html' => '',
        ]);
    }

    public function test_reorder_payload_contract_is_explicit_and_stable(): void
    {
        $this->assertSame([
            'itemId' => 'item_id',
            'targetColumnId' => 'column_id',
            'previousColumnId' => 'previous_column_id',
            'orderedIds' => 'ordered_ids',
        ], KanbanReorderPayload::clientConfig());
    }

    public function test_column_can_be_constructed_from_value_objects(): void
    {
        $column = new KanbanColumn(
            id: 'qualification',
            label: 'Qualification',
            items: [
                new KanbanItem(
                    id: 'lead-2',
                    html: '<div>Lead 2</div>',
                    attributes: ['title' => 'Lead 2'],
                ),
            ],
        );

        $this->assertSame('qualification', $column->toArray()['id']);
        $this->assertSame('Lead 2', $column->toArray()['items'][0]['title']);
    }
}
