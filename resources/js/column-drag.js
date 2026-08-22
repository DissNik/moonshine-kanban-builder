export function isTopLevelColumnMove({ root, dragged, from, to } = {}) {
    return root != null
        && dragged != null
        && dragged.parentElement === root
        && from === root
        && to === root
}

export function sortablePositionChanged({
    from,
    to,
    oldDraggableIndex,
    newDraggableIndex,
} = {}) {
    return from !== to || oldDraggableIndex !== newDraggableIndex
}
