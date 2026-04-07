<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Support;

final class KanbanReorderPayload
{
    public const ITEM_ID = 'item_id';

    public const TARGET_COLUMN_ID = 'column_id';

    public const PREVIOUS_COLUMN_ID = 'previous_column_id';

    public const ORDERED_IDS = 'ordered_ids';

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
}
