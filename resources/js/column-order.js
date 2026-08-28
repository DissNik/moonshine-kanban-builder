export const movableColumnIds = (columns = []) =>
    columns.filter((column) => !column.locked).map((column) => String(column.id))

export const lockedColumnsLast = (columns = []) => [
    ...columns.filter((column) => !column.locked),
    ...columns.filter((column) => column.locked),
]

export function reorderMovableColumns(columns = [], orderedIds = []) {
    const movable = columns.filter((column) => !column.locked)
    const normalizedIds = orderedIds.map((id) => String(id))
    const currentIds = movable.map((column) => String(column.id))

    if (
        normalizedIds.length !== currentIds.length ||
        new Set(normalizedIds).size !== normalizedIds.length ||
        currentIds.some((id) => !normalizedIds.includes(id))
    ) {
        throw new Error('Invalid movable column order.')
    }

    const byId = new Map(movable.map((column) => [String(column.id), column]))

    return [...normalizedIds.map((id) => byId.get(id)), ...columns.filter((column) => column.locked)]
}

export function withColumnOrderVersion(meta = {}, response = {}) {
    return response.version === undefined ? meta : { ...meta, column_order_version: response.version }
}

export function columnOrderRequestPayload(columns = [], request = {}, version = null) {
    return {
        [request.orderedColumnIds ?? 'ordered_column_ids']: movableColumnIds(columns),
        [request.version ?? 'version']: version,
    }
}
