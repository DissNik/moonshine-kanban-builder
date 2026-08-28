import '../css/stylesheet.css'
import Sortable from 'sortablejs'
import { columnOrderRequestPayload, reorderMovableColumns, withColumnOrderVersion } from './column-order.js'
import { horizontalWheelScrollLeft } from './board-scroll.js'
import { isTopLevelColumnMove, sortablePositionChanged } from './column-drag.js'

if (!Sortable.__kanbanDestroyedInstanceGuardApplied) {
    const originalHandleEvent = Sortable.prototype.handleEvent
    const originalOnDragOver = Sortable.prototype._onDragOver

    Sortable.prototype.handleEvent = function patchedHandleEvent(event) {
        if (!this.el) {
            return
        }

        return originalHandleEvent.call(this, event)
    }

    Sortable.prototype._onDragOver = function patchedOnDragOver(event) {
        if (!this.el) {
            return false
        }

        return originalOnDragOver.call(this, event)
    }

    Sortable.__kanbanDestroyedInstanceGuardApplied = true
}

function normalizeEvents(events, fallback = []) {
    if (Array.isArray(events)) {
        return events.filter((eventName) => typeof eventName === 'string' && eventName !== '')
    }

    if (typeof events === 'string' && events !== '') {
        return [events]
    }

    return fallback
}

function dispatchBrowserEvents(events, detail = {}) {
    normalizeEvents(events).forEach((eventName) => {
        window.dispatchEvent(
            new CustomEvent(eventName, {
                detail,
                bubbles: true,
                composed: true,
            }),
        )
    })
}

function normalizeTransportMode(mode, allowedModes = []) {
    if (allowedModes.includes(mode)) {
        return mode
    }

    return allowedModes[0] ?? mode
}

function normalizePositiveNumber(value, fallback) {
    const parsed = Number(value)

    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback
}

function orderedIdsForContainer(container) {
    const sortable = Sortable.get(container)

    if (sortable) {
        return sortable.toArray().filter((id) => typeof id === 'string' && id !== '')
    }

    return Array.from(container.children)
        .filter((item) => item instanceof HTMLElement && item.matches('.kanban-draggable[data-id]'))
        .map((item) => item.dataset.id)
        .filter((id) => typeof id === 'string' && id !== '')
}

function haveSameIdsInSameCount(currentItems = [], incomingItems = []) {
    if (currentItems.length !== incomingItems.length) {
        return false
    }

    const currentIds = currentItems.map((item) => item.id).sort()
    const incomingIds = incomingItems.map((item) => item.id).sort()

    return currentIds.every((id, index) => id === incomingIds[index])
}

function shouldUseTouchFallbackDrag() {
    return window.matchMedia?.('(pointer: coarse)')?.matches || 'ontouchstart' in window || navigator.maxTouchPoints > 0
}

function syncFallbackClone(clone, item) {
    if (!(clone instanceof HTMLElement) || !(item instanceof HTMLElement)) {
        return
    }

    clone.innerHTML = item.innerHTML
    clone.dataset.id = item.dataset.id || ''
    clone.classList.remove('kanban-lift', 'kanban-touch-dragging')
    clone.classList.add('kanban-fallback')

    clone.querySelectorAll('*').forEach((element) => stripAlpineAttributes(element))
    stripAlpineAttributes(clone)
}

function syncColumnFallbackClone(clone) {
    if (!(clone instanceof HTMLElement)) {
        return
    }

    clone.classList.remove('kanban-column-lift', 'kanban-column-ghost', 'kanban-column-chosen')
    clone.classList.add('kanban-column-fallback')
    clone.querySelectorAll('*').forEach((element) => stripAlpineAttributes(element))
    stripAlpineAttributes(clone)
    clone.setAttribute('x-ignore', '')
}

function stripAlpineAttributes(element) {
    Array.from(element.attributes).forEach((attribute) => {
        if (attribute.name.startsWith('x-') || attribute.name.startsWith('@') || attribute.name.startsWith(':')) {
            element.removeAttribute(attribute.name)
        }
    })
}

function initializeAlpineTree(element) {
    if (!(element instanceof HTMLElement)) {
        return
    }

    window.Alpine?.initTree?.(element)
}

function destroyAlpineTree(element) {
    if (!(element instanceof HTMLElement)) {
        return
    }

    window.Alpine?.destroyTree?.(element)
}

window.kanbanBoard = function kanbanBoard(config = {}) {
    const transport = config.transport ?? {}
    const allowedTransportModes = normalizeEvents(transport.allowedModes)
    const transportMode = normalizeTransportMode(transport.mode, allowedTransportModes)
    const pollInterval = normalizePositiveNumber(transport.polling?.interval, 0)
    const reorderRefreshCooldownMs = normalizePositiveNumber(config.reorderRefreshCooldownMs, 0)
    const reorderRequest = config.reorderRequest ?? {}
    const columnReorderRequest = config.columnReorderRequest ?? {}

    return {
        columns: config.initialSnapshot?.columns ?? [],
        version: config.initialSnapshot?.version ?? null,
        snapshotMeta: config.initialSnapshot?.meta ?? {},
        dragging: null,
        pendingRefresh: false,
        refreshInFlight: false,
        queuedRefreshForce: false,
        reorderInFlight: false,
        columnReorderInFlight: false,
        columnDragging: false,
        touchFallbackDrag: shouldUseTouchFallbackDrag(),
        intervalId: null,
        sortables: [],
        columnSortable: null,
        boardScrollContainer: null,
        boardScrollDragOverHandler: null,
        boardScrollWheelHandler: null,
        pointerDown: null,
        suppressClickUntil: 0,
        refreshRequestId: 0,
        appliedRefreshRequestId: 0,
        refreshGeneration: 0,
        renderNonce: 0,
        snapshotUrl: config.snapshotUrl ?? '',
        reorderUrl: config.reorderUrl ?? '',
        columnReorderUrl: config.columnReorderUrl ?? '',
        refreshEvents: normalizeEvents(config.refreshEvents, normalizeEvents(transport.signals?.refresh)),
        transport,
        transportMode,
        pollInterval,
        allowedTransportModes,
        cardClickEvent: config.cardClickEvent ?? '',
        reorderRefreshCooldownMs,
        refreshBlockedUntil: 0,
        deferredRefreshTimeoutId: null,
        initialized: false,
        reorderRequest,
        columnReorderRequest,

        init() {
            if (this.initialized) {
                this.destroy()
            }

            const previousInstance = this.$el.__kanbanBoardInstance

            if (previousInstance && previousInstance !== this && typeof previousInstance.destroy === 'function') {
                previousInstance.destroy()
            }

            this.$el.__kanbanBoardInstance = this
            this.initialized = true
            this.handleRefreshEvent = () => {
                void this.refresh({ force: true })
            }

            this.refreshEvents.forEach((eventName) => {
                window.addEventListener(eventName, this.handleRefreshEvent)
            })

            if (this.transportMode === 'polling' && this.pollInterval > 0) {
                this.intervalId = window.setInterval(() => {
                    if (
                        !document.hidden &&
                        !this.reorderInFlight &&
                        !this.columnReorderInFlight &&
                        !this.columnDragging
                    ) {
                        void this.refresh()
                    }
                }, this.pollInterval)
            }

            this.columns = this.withRenderKeys(this.columns)
            this.$nextTick(() => this.bindSortables())
        },

        destroy() {
            if (this.handleRefreshEvent) {
                this.refreshEvents.forEach((eventName) => {
                    window.removeEventListener(eventName, this.handleRefreshEvent)
                })
            }

            if (this.intervalId) {
                window.clearInterval(this.intervalId)
                this.intervalId = null
            }

            if (this.deferredRefreshTimeoutId) {
                window.clearTimeout(this.deferredRefreshTimeoutId)
                this.deferredRefreshTimeoutId = null
            }

            this.destroySortables()
            this.destroyBoardScroll()
            this.columnDragging = false
            this.initialized = false

            if (this.$el?.__kanbanBoardInstance === this) {
                delete this.$el.__kanbanBoardInstance
            }
        },

        initBoardScroll(container) {
            this.destroyBoardScroll()
            this.boardScrollContainer = container
            this.boardScrollDragOverHandler = (event) => {
                const rect = container.getBoundingClientRect()
                const edge = 100
                const speed = 30

                if (event.clientX < rect.left + edge) {
                    container.scrollLeft -= speed
                } else if (event.clientX > rect.right - edge) {
                    container.scrollLeft += speed
                }
            }
            this.boardScrollWheelHandler = (event) => {
                const nextScrollLeft = horizontalWheelScrollLeft({
                    deltaX: event.deltaX,
                    deltaY: event.deltaY,
                    ctrlKey: event.ctrlKey,
                    scrollLeft: container.scrollLeft,
                    clientWidth: container.clientWidth,
                    scrollWidth: container.scrollWidth,
                })

                if (nextScrollLeft === null) {
                    return
                }

                event.preventDefault()
                container.scrollLeft = nextScrollLeft
            }

            document.addEventListener('dragover', this.boardScrollDragOverHandler)
            container.addEventListener('wheel', this.boardScrollWheelHandler, { passive: false })
        },

        destroyBoardScroll() {
            if (this.boardScrollDragOverHandler) {
                document.removeEventListener('dragover', this.boardScrollDragOverHandler)
            }

            if (this.boardScrollContainer && this.boardScrollWheelHandler) {
                this.boardScrollContainer.removeEventListener('wheel', this.boardScrollWheelHandler)
            }

            this.boardScrollContainer = null
            this.boardScrollDragOverHandler = null
            this.boardScrollWheelHandler = null
        },

        isRefreshBlocked() {
            return Date.now() < this.refreshBlockedUntil
        },

        blockRefreshTemporarily() {
            this.refreshGeneration += 1
            this.refreshBlockedUntil = Date.now() + this.reorderRefreshCooldownMs
        },

        scheduleDeferredRefresh(force = true) {
            if (this.deferredRefreshTimeoutId) {
                window.clearTimeout(this.deferredRefreshTimeoutId)
            }

            const delay = Math.max(this.refreshBlockedUntil - Date.now(), 0)

            this.deferredRefreshTimeoutId = window.setTimeout(() => {
                this.deferredRefreshTimeoutId = null
                void this.refresh({ force })
            }, delay)
        },

        reportRefreshError(error) {
            console.error('Kanban refresh error:', error)
        },

        withRenderKeys(columns = []) {
            return (columns || []).map((column) => ({
                ...column,
                renderKey: ++this.renderNonce,
            }))
        },

        mergeSnapshotColumns(columns = []) {
            return this.withRenderKeys(
                (columns || []).map((incomingColumn) => {
                    const currentColumn = this.columns.find((column) => column.id === incomingColumn.id)

                    if (
                        !currentColumn ||
                        !haveSameIdsInSameCount(currentColumn.items || [], incomingColumn.items || [])
                    ) {
                        return incomingColumn
                    }

                    const incomingItemsById = new Map((incomingColumn.items || []).map((item) => [item.id, item]))

                    return {
                        ...incomingColumn,
                        items: (currentColumn.items || []).map((item) => incomingItemsById.get(item.id) ?? item),
                    }
                }),
            )
        },

        syncLocalStateAfterReorder() {
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    if (this.dragging || this.reorderInFlight) {
                        return
                    }

                    const itemPool = new Map(
                        this.columns.flatMap((column) => (column.items || []).map((item) => [item.id, item])),
                    )

                    this.columns = this.withRenderKeys(
                        this.columns.map((column) => {
                            const columnElement = this.$el.querySelector(`[data-column-id="${column.id}"]`)

                            if (!columnElement) {
                                return column
                            }

                            const items = Array.from(columnElement.children)
                                .filter(
                                    (item) => item instanceof HTMLElement && item.matches('.kanban-draggable[data-id]'),
                                )
                                .map((item) => itemPool.get(item.dataset.id) ?? null)
                                .filter((item) => item !== null)

                            return {
                                ...column,
                                items,
                            }
                        }),
                    )
                })
            })
        },

        currentQueryString() {
            const params = new URLSearchParams(window.location.search)
            const clean = new URLSearchParams()

            params.forEach((value, key) => {
                if (!key.startsWith('_')) {
                    clean.append(key, value)
                }
            })

            return clean.toString()
        },

        buildSnapshotUrl(force = false) {
            const url = new URL(this.snapshotUrl, window.location.origin)
            const queryString = this.currentQueryString()

            if (queryString) {
                const params = new URLSearchParams(queryString)
                params.forEach((value, key) => url.searchParams.append(key, value))
            }

            if (!force && this.version) {
                url.searchParams.set('version', this.version)
            }

            return url.toString()
        },

        captureScrollState() {
            const board = this.$refs.boardScroll

            return {
                boardScrollLeft: board?.scrollLeft ?? 0,
                columnScrollTopById: Object.fromEntries(
                    Array.from(this.$el.querySelectorAll('[data-column-id]')).map((column) => [
                        column.dataset.columnId,
                        column.scrollTop,
                    ]),
                ),
            }
        },

        restoreScrollState(state = {}) {
            const board = this.$refs.boardScroll

            if (board && typeof state.boardScrollLeft === 'number') {
                board.scrollLeft = state.boardScrollLeft
            }

            Object.entries(state.columnScrollTopById ?? {}).forEach(([columnId, scrollTop]) => {
                const column = this.$el.querySelector(`[data-column-id="${columnId}"]`)

                if (column && typeof scrollTop === 'number') {
                    column.scrollTop = scrollTop
                }
            })
        },

        isRefreshBusy() {
            return Boolean(this.dragging || this.columnDragging || this.reorderInFlight || this.columnReorderInFlight)
        },

        async refresh({ force = false, allowDuringDrag = false } = {}) {
            if (!this.snapshotUrl) {
                return false
            }

            if (this.isRefreshBusy() && !allowDuringDrag) {
                this.pendingRefresh = true

                return false
            }

            if (this.isRefreshBlocked()) {
                this.pendingRefresh = true
                this.scheduleDeferredRefresh(force)

                return false
            }

            if (this.refreshInFlight) {
                this.queuedRefreshForce = this.queuedRefreshForce || force

                return false
            }

            this.refreshInFlight = true
            const requestId = ++this.refreshRequestId
            const generation = this.refreshGeneration

            try {
                const response = await axios.get(this.buildSnapshotUrl(force), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                })

                const payload = response.data || {}

                if (this.isRefreshBusy() && !allowDuringDrag) {
                    this.pendingRefresh = true

                    return false
                }

                if (generation !== this.refreshGeneration) {
                    return false
                }

                if (requestId < this.refreshRequestId || requestId < this.appliedRefreshRequestId) {
                    return false
                }

                if (!payload.changed) {
                    this.version = payload.version ?? this.version
                    this.snapshotMeta = payload.meta ?? this.snapshotMeta
                    this.appliedRefreshRequestId = requestId

                    return true
                }

                const scrollState = this.captureScrollState()

                this.version = payload.version ?? null
                this.snapshotMeta = payload.meta ?? {}
                this.columns = this.mergeSnapshotColumns(payload.columns ?? [])
                this.appliedRefreshRequestId = requestId

                this.$nextTick(() => {
                    this.restoreScrollState(scrollState)
                    this.bindSortables()
                })
                return true
            } catch (error) {
                this.reportRefreshError(error)

                return false
            } finally {
                this.refreshInFlight = false

                if (this.queuedRefreshForce) {
                    const queuedForce = this.queuedRefreshForce
                    this.queuedRefreshForce = false
                    void this.refresh({ force: queuedForce })
                }
            }
        },

        startDrag(cardId, fromColumnId) {
            this.dragging = { cardId, fromColumnId }
        },

        clearDrag() {
            this.dragging = null

            if (this.pendingRefresh) {
                this.pendingRefresh = false

                if (!this.isRefreshBlocked()) {
                    void this.refresh({ force: true })
                }
            }
        },

        clearColumnDrag() {
            this.columnDragging = false

            if (!this.pendingRefresh || this.dragging || this.reorderInFlight || this.columnReorderInFlight) {
                return
            }

            this.pendingRefresh = false

            if (this.isRefreshBlocked()) {
                this.scheduleDeferredRefresh(true)
            } else {
                void this.refresh({ force: true })
            }
        },

        hydrateCard(element, card) {
            if (!(element instanceof HTMLElement)) {
                return
            }

            element.dataset.id = card?.id || ''
            element.__kanbanCard = card || null

            const content = element.querySelector('.kanban-card-content')

            if (content) {
                destroyAlpineTree(content)
                content.replaceChildren()
                content.innerHTML = card?.html || ''
                initializeAlpineTree(content)
            }

            element.removeAttribute('x-init')
        },

        handleCardPointerDown(event) {
            if (!event.target.closest('.kanban-draggable')) {
                this.pointerDown = null

                return
            }

            this.rememberPointerDown(event)
        },

        handleCardPointerMove(event) {
            if (!this.pointerDown) {
                return
            }

            this.rememberPointerMove(event)
        },

        rememberPointerDown(event) {
            const pointer = event.changedTouches?.[0] ?? event.touches?.[0] ?? event

            if (!pointer || typeof pointer.clientX !== 'number' || typeof pointer.clientY !== 'number') {
                this.pointerDown = null

                return
            }

            this.pointerDown = {
                x: pointer.clientX,
                y: pointer.clientY,
            }
        },

        rememberPointerMove(event) {
            if (!this.pointerDown) {
                return
            }

            const pointer = event.changedTouches?.[0] ?? event.touches?.[0] ?? event

            if (!pointer || typeof pointer.clientX !== 'number' || typeof pointer.clientY !== 'number') {
                return
            }

            const deltaX = Math.abs(pointer.clientX - this.pointerDown.x)
            const deltaY = Math.abs(pointer.clientY - this.pointerDown.y)

            if (deltaX > 8 || deltaY > 8) {
                this.suppressClickUntil = Date.now() + 400
            }
        },

        clearPointerTracking() {
            this.pointerDown = null
        },

        handleBoardClick(event) {
            const cardElement = event.target.closest('.kanban-draggable')

            if (!(cardElement instanceof HTMLElement)) {
                return
            }

            this.clickCard(cardElement.__kanbanCard, event)
        },

        async persistReorder(cardId, fromColumnId, toColumnId, orderedIds) {
            if (!this.reorderUrl) {
                this.clearDrag()

                return
            }

            this.reorderInFlight = true
            this.blockRefreshTemporarily()
            let reorderSucceeded = false

            try {
                await axios.post(
                    this.reorderUrl,
                    {
                        [this.reorderRequest.itemId ?? 'item_id']: cardId,
                        [this.reorderRequest.targetColumnId ?? 'column_id']: toColumnId,
                        [this.reorderRequest.previousColumnId ?? 'previous_column_id']: fromColumnId,
                        [this.reorderRequest.orderedIds ?? 'ordered_ids']: orderedIds,
                    },
                    {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json, text/plain, */*',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                    },
                )

                reorderSucceeded = true
            } catch (error) {
                this.scheduleDeferredRefresh(true)
                throw error
            } finally {
                this.reorderInFlight = false
                this.clearDrag()

                if (reorderSucceeded) {
                    this.syncLocalStateAfterReorder()
                    dispatchBrowserEvents(this.transport.events?.reorderRefresh, {
                        itemId: cardId,
                        columnId: toColumnId,
                        previousColumnId: fromColumnId,
                        orderedIds,
                    })
                }
            }
        },

        clickCard(card, event) {
            if (Date.now() < this.suppressClickUntil) {
                event.preventDefault()

                return
            }

            if (this.dragging || event.target.closest('button, a, input, textarea, select, label, form')) {
                return
            }

            if (!this.cardClickEvent) {
                return
            }

            dispatchBrowserEvents(this.cardClickEvent, { card })
        },

        destroySortables() {
            this.sortables.forEach((sortable) => sortable.destroy())
            this.sortables = []

            if (this.columnSortable) {
                this.columnSortable.destroy()
                this.columnSortable = null
            }
        },

        async persistColumnOrder() {
            if (!this.columnReorderUrl || this.columnReorderInFlight) {
                return
            }

            const previousColumns = [...this.columns]
            const orderedColumnIds = Array.from(
                this.$refs.columns?.querySelectorAll(':scope > .kanban-column[data-column-locked="0"]') ?? [],
            )
                .map((column) => column.dataset.kanbanColumnId)
                .filter((id) => typeof id === 'string' && id !== '')

            let nextColumns

            try {
                nextColumns = reorderMovableColumns(previousColumns, orderedColumnIds)
            } catch (error) {
                this.columns = this.withRenderKeys(previousColumns)
                this.$nextTick(() => this.bindSortables())
                console.error('Column reorder error:', error)

                return
            }

            this.columnReorderInFlight = true
            this.blockRefreshTemporarily()

            try {
                const response = await axios.post(
                    this.columnReorderUrl,
                    columnOrderRequestPayload(
                        nextColumns,
                        this.columnReorderRequest,
                        this.snapshotMeta.column_order_version ?? null,
                    ),
                    {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json, text/plain, */*',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                    },
                )

                this.columns = this.withRenderKeys(nextColumns)
                this.snapshotMeta = withColumnOrderVersion(this.snapshotMeta, response.data ?? {})
                dispatchBrowserEvents(this.transport.events?.reorderRefresh, { orderedColumnIds })
            } catch (error) {
                this.columns = this.withRenderKeys(previousColumns)
                console.error('Column reorder error:', error)
            } finally {
                this.columnReorderInFlight = false
                this.$nextTick(() => this.bindSortables())

                if (this.pendingRefresh) {
                    this.pendingRefresh = false
                    this.scheduleDeferredRefresh(true)
                }
            }
        },

        async handleSortableChange(event, fallbackColumnId) {
            const movedId = event.item.dataset.id
            const sourceColumnId = event.from.dataset.columnId || fallbackColumnId
            const targetColumnId = event.to.dataset.columnId || fallbackColumnId
            const orderedIds = orderedIdsForContainer(event.to)

            if (!movedId || orderedIds.length === 0) {
                this.clearDrag()

                return
            }

            try {
                await this.persistReorder(movedId, sourceColumnId, targetColumnId, orderedIds)
            } catch (error) {
                console.error('Reorder error:', error)
            }
        },

        bindSortables() {
            this.destroySortables()

            this.$el.querySelectorAll('[data-column-id]').forEach((column) => {
                const sortable = Sortable.create(column, {
                    group: {
                        name: 'kanban-group',
                        pull: true,
                        put: true,
                    },
                    animation: 150,
                    swapThreshold: 0.65,
                    invertSwap: true,
                    invertedSwapThreshold: 0.65,
                    handle: '.handle',
                    draggable: '.kanban-draggable',
                    dataIdAttr: 'data-id',
                    ghostClass: 'kanban-ghost',
                    chosenClass: 'kanban-chosen',
                    delay: 180,
                    delayOnTouchOnly: true,
                    touchStartThreshold: 8,
                    fallbackTolerance: 6,
                    forceFallback: this.touchFallbackDrag,
                    fallbackClass: 'kanban-fallback',
                    fallbackOnBody: true,
                    setData: (dataTransfer, dragElement) => {
                        const value =
                            dragElement?.querySelector?.('.lead-kanban-card__title')?.textContent?.trim() ||
                            dragElement?.dataset?.id ||
                            dragElement?.getAttribute?.('data-id') ||
                            ''

                        dataTransfer.clearData()
                        dataTransfer.setData('Text', value)
                        dataTransfer.setData('text/plain', value)
                    },

                    onClone: (event) => {
                        syncFallbackClone(event.clone, event.item)
                    },

                    onStart: (event) => {
                        syncFallbackClone(event.clone, event.item)
                        event.item.classList.add('kanban-lift')
                        this.startDrag(event.item.dataset.id, column.dataset.columnId)
                    },

                    onUpdate: async (event) => {
                        await this.handleSortableChange(event, column.dataset.columnId)
                    },

                    onAdd: async (event) => {
                        await this.handleSortableChange(event, column.dataset.columnId)
                    },

                    onEnd: (event) => {
                        event.item.classList.remove('kanban-lift')

                        if (
                            !this.reorderInFlight &&
                            event.from === event.to &&
                            event.oldDraggableIndex === event.newDraggableIndex
                        ) {
                            this.clearDrag()
                        }
                    },
                })

                this.sortables.push(sortable)
            })

            if (this.columnReorderUrl && this.$refs.columns) {
                this.columnSortable = Sortable.create(this.$refs.columns, {
                    animation: 150,
                    direction: 'horizontal',
                    handle: '.kanban-column-header',
                    filter: '.kanban-column-actions, button, a, input, textarea, select, option, label, [role="button"], [contenteditable="true"]',
                    preventOnFilter: false,
                    draggable: '.kanban-column[data-column-locked="0"]',
                    swapThreshold: 0.65,
                    invertSwap: true,
                    invertedSwapThreshold: 0.65,
                    ghostClass: 'kanban-column-ghost',
                    chosenClass: 'kanban-column-chosen',
                    forceFallback: true,
                    fallbackTolerance: 4,
                    fallbackClass: 'kanban-column-fallback',
                    fallbackOnBody: true,
                    onMove: (event) =>
                        isTopLevelColumnMove({
                            root: this.$refs.columns,
                            dragged: event.dragged,
                            from: event.from,
                            to: event.to,
                        }),
                    onChoose: (event) => {
                        event.item.setAttribute('x-ignore', '')
                    },
                    onUnchoose: (event) => {
                        event.item.removeAttribute('x-ignore')
                    },
                    onClone: (event) => {
                        syncColumnFallbackClone(event.clone)
                    },
                    onStart: (event) => {
                        syncColumnFallbackClone(event.clone)
                        event.item.classList.add('kanban-column-lift')
                        this.columnDragging = true
                    },
                    onEnd: (event) => {
                        event.item.removeAttribute('x-ignore')
                        event.item.classList.remove('kanban-column-lift')

                        if (sortablePositionChanged(event)) {
                            void this.persistColumnOrder()
                        }

                        this.clearColumnDrag()
                    },
                })
            }
        },
    }
}
