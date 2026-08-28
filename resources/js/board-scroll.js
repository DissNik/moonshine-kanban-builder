export function horizontalWheelScrollLeft({
    deltaX = 0,
    deltaY = 0,
    ctrlKey = false,
    scrollLeft = 0,
    clientWidth = 0,
    scrollWidth = 0,
} = {}) {
    const maxScrollLeft = Math.max(0, scrollWidth - clientWidth)

    if (ctrlKey || maxScrollLeft === 0 || deltaY === 0 || Math.abs(deltaX) >= Math.abs(deltaY)) {
        return null
    }

    const nextScrollLeft = Math.min(maxScrollLeft, Math.max(0, scrollLeft + deltaY))

    return nextScrollLeft === scrollLeft ? null : nextScrollLeft
}

export function verticalWheelScrollCanAdvance({ deltaY = 0, scrollTop = 0, clientHeight = 0, scrollHeight = 0 } = {}) {
    const maxScrollTop = Math.max(0, scrollHeight - clientHeight)

    if (deltaY < 0) {
        return scrollTop > 0
    }

    if (deltaY > 0) {
        return scrollTop < maxScrollTop
    }

    return false
}
