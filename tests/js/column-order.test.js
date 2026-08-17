import assert from 'node:assert/strict'
import test from 'node:test'

import {
    columnOrderRequestPayload,
    movableColumnIds,
    reorderMovableColumns,
    withColumnOrderVersion,
} from '../../resources/js/column-order.js'

const columns = () => [
    { id: 'a', locked: false },
    { id: 'b', locked: false },
    { id: 'basket', locked: true },
]

test('returns only movable column ids', () => {
    assert.deepEqual(movableColumnIds(columns()), ['a', 'b'])
})

test('reorders movable columns and keeps locked columns last', () => {
    assert.deepEqual(reorderMovableColumns(columns(), ['b', 'a']), [
        { id: 'b', locked: false },
        { id: 'a', locked: false },
        { id: 'basket', locked: true },
    ])
})

test('rejects incomplete or duplicate orders without mutating source', () => {
    const source = columns()

    assert.throws(() => reorderMovableColumns(source, ['a']), /Invalid movable column order/)
    assert.throws(() => reorderMovableColumns(source, ['a', 'a']), /Invalid movable column order/)
    assert.deepEqual(source, columns())
})

test('stores the successful optimistic lock version in snapshot meta', () => {
    assert.deepEqual(
        withColumnOrderVersion({ timestamp: 'now', column_order_version: 7 }, { version: 8 }),
        { timestamp: 'now', column_order_version: 8 },
    )
    assert.deepEqual(withColumnOrderVersion({ timestamp: 'now' }, {}), { timestamp: 'now' })
})

test('builds a generic request without locked column ids', () => {
    assert.deepEqual(columnOrderRequestPayload(
        columns(),
        { orderedColumnIds: 'ordered_column_ids', version: 'version' },
        7,
    ), {
        ordered_column_ids: ['a', 'b'],
        version: 7,
    })
})
