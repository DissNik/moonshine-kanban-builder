<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Tests\Unit;

use DissNik\MoonShineKanBanBuilder\Support\KanbanColumn;
use DissNik\MoonShineKanBanBuilder\Support\KanbanItem;
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
                    'locked' => false,
                    'header_html' => '',
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
                    'locked' => false,
                    'header_html' => '',
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

    public function test_column_serializes_lock_state_and_trusted_header_markup(): void
    {
        $column = new KanbanColumn(
            id: 'basket',
            label: 'Basket',
            items: [],
            locked: true,
            headerHtml: '<button type="button">Actions</button>',
        );

        $this->assertSame([
            'id' => 'basket',
            'label' => 'Basket',
            'items' => [],
            'locked' => true,
            'header_html' => '<button type="button">Actions</button>',
        ], $column->toArray());
        $this->assertSame(
            $column->toArray(),
            KanbanColumn::fromArray($column->toArray())->toArray(),
        );
    }

    public function test_snapshot_rejects_duplicate_column_ids(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Kanban snapshot contains duplicate column ids.');

        KanbanSnapshot::fromArray([
            'columns' => [
                [
                    'id' => 'new',
                    'label' => 'New',
                    'items' => [],
                ],
                [
                    'id' => 'new',
                    'label' => 'Duplicate',
                    'items' => [],
                ],
            ],
        ]);
    }

    public function test_column_rejects_duplicate_item_ids(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Kanban column [qualification] contains duplicate item ids.');

        KanbanColumn::fromArray([
            'id' => 'qualification',
            'label' => 'Qualification',
            'items' => [
                [
                    'id' => 'lead-1',
                    'html' => '<div>Lead 1</div>',
                ],
                [
                    'id' => 'lead-1',
                    'html' => '<div>Lead 1 duplicate</div>',
                ],
            ],
        ]);
    }
}
