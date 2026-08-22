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

    public function test_assets_have_a_package_cache_buster(): void
    {
        $reflection = new ReflectionClass(KanBanBuilder::class);
        /** @var KanBanBuilder $component */
        $component = $reflection->newInstanceWithoutConstructor();

        $assets = $reflection->getMethod('assets')->invoke($component);

        self::assertCount(2, $assets);
        self::assertStringContainsString('?v=', $assets[0]->getLink());
        self::assertStringContainsString('?v=', $assets[1]->getLink());
    }

    public function test_view_separates_column_drag_identity_from_card_drop_identity(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/components/kanban.blade.php');

        self::assertIsString($view);
        self::assertStringContainsString('x-ref="columns"', $view);
        self::assertStringNotContainsString('x-data="kanbanBoardScroll"', $view);
        self::assertStringContainsString('x-init="initBoardScroll($el)"', $view);
        self::assertStringContainsString('kanban-column-handle', $view);
        self::assertStringContainsString('kanban-column-handle-slot', $view);
        self::assertStringContainsString('<span x-show="! column.locked" class="kanban-column-handle-slot">', $view);
        self::assertStringContainsString('kanban-column-title', $view);
        self::assertStringContainsString("\$translates['reorderColumn']", $view);
        self::assertStringContainsString('kanban-column-header--reorderable', $view);
        self::assertStringContainsString(':data-kanban-column-id="column.id"', $view);
        self::assertStringContainsString(':data-column-locked=', $view);
        self::assertStringContainsString('x-html="column.header_html"', $view);
        self::assertStringContainsString('{!! $afterColumns ?? \'\' !!}', $view);
        self::assertSame(1, substr_count($view, ':data-column-id="column.id"'));
    }

    public function test_column_sorting_uses_the_pointer_fallback(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2) . '/resources/js/script.js');

        self::assertIsString($script);
        self::assertStringContainsString("draggable: '.kanban-column[data-column-locked=\"0\"]'", $script);
        self::assertStringContainsString("direction: 'horizontal'", $script);
        self::assertStringContainsString('forceFallback: true', $script);
        self::assertStringContainsString("fallbackClass: 'kanban-column-fallback'", $script);
    }

    public function test_board_scroll_maps_vertical_wheel_input_and_cleans_up_the_listener(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2) . '/resources/js/script.js');

        self::assertIsString($script);
        self::assertStringContainsString('horizontalWheelScrollLeft', $script);
        self::assertStringContainsString("container.addEventListener('wheel', this.boardScrollWheelHandler, { passive: false })", $script);
        self::assertStringContainsString("this.boardScrollContainer.removeEventListener('wheel', this.boardScrollWheelHandler)", $script);
    }

    public function test_column_header_grid_keeps_the_badge_compact_with_and_without_a_drag_handle(): void
    {
        $stylesheet = file_get_contents(dirname(__DIR__, 2) . '/resources/css/stylesheet.css');

        self::assertIsString($stylesheet);
        self::assertStringContainsString('grid-template-columns: minmax(0, 1fr) auto auto;', $stylesheet);
        self::assertStringContainsString('.kanban-column-header--reorderable', $stylesheet);
        self::assertStringContainsString('grid-template-columns: auto minmax(0, 1fr) auto auto;', $stylesheet);
        self::assertMatchesRegularExpression(
            '/\.kanban-column-header--locked\s*\{[^}]*grid-template-columns:\s*minmax\(0, 1fr\) auto auto;/s',
            $stylesheet,
        );

        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/components/kanban.blade.php');

        self::assertIsString($view);
        self::assertStringContainsString("'kanban-column-header--locked': column.locked", $view);
    }
}
