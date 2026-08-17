<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Tests\Unit;

use DissNik\MoonShineKanBanBuilder\Components\KanBanBuilder;
use DissNik\MoonShineKanBanBuilder\Tests\TestCase;
use ReflectionClass;

final class KanBanBuilderTest extends TestCase
{
    public function test_builder_exposes_column_reorder_contract_and_after_column_components(): void
    {
        $reflection = new ReflectionClass(KanBanBuilder::class);
        /** @var KanBanBuilder $component */
        $component = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('transportMode')->setValue($component, 'manual');
        $reflection->getProperty('pollInterval')->setValue($component, 5000);

        $result = $component
            ->columnReorderUrl('/columns/reorder')
            ->afterColumns(fn (): array => []);

        $boardConfig = $reflection->getMethod('boardConfig')->invoke($component);

        self::assertSame($component, $result);
        self::assertSame('/columns/reorder', $boardConfig['columnReorderUrl']);
        self::assertSame('ordered_column_ids', $boardConfig['columnReorderRequest']['orderedColumnIds']);
        self::assertNotNull($reflection->getProperty('afterColumns')->getValue($component));
    }

    public function test_view_separates_column_drag_identity_from_card_drop_identity(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/components/kanban.blade.php');

        self::assertIsString($view);
        self::assertStringContainsString('x-ref="columns"', $view);
        self::assertStringContainsString('kanban-column-handle', $view);
        self::assertStringContainsString(':data-kanban-column-id="column.id"', $view);
        self::assertStringContainsString(':data-column-locked=', $view);
        self::assertStringContainsString('x-html="column.header_html"', $view);
        self::assertStringContainsString('{!! $afterColumns ?? \'\' !!}', $view);
        self::assertSame(1, substr_count($view, ':data-column-id="column.id"'));
    }
}
