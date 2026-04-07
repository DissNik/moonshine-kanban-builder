import Sortable from 'sortablejs'

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
        window.dispatchEvent(new CustomEvent(eventName, {
            detail,
            bubbles: true,
            composed: true,
        }))
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

window.kanbanBoardScroll = function kanbanBoardScroll() {
    return {
        init() {
            const container = this.$el
            const edge = 100
            const speed = 30

            const handleDragOver = (event) => {
                const rect = container.getBoundingClientRect()
                const x = event.clientX

                if (x < rect.left + edge) {
                    container.scrollLeft -= speed
                } else if (x > rect.right - edge) {
                    container.scrollLeft += speed
                }
            }

            document.addEventListener('dragover', handleDragOver)
        },
    }
}

window.kanbanBoard = function kanbanBoard(config = {}) {
    const transport = config.transport ?? {}
    const allowedTransportModes = normalizeEvents(transport.allowedModes)
    const transportMode = normalizeTransportMode(transport.mode, allowedTransportModes)
    const pollInterval = normalizePositiveNumber(transport.polling?.interval, 0)
    const reorderRefreshCooldownMs = normalizePositiveNumber(config.reorderRefreshCooldownMs, 0)

    return {
        columns: config.initialSnapshot?.columns ?? [],
        version: config.initialSnapshot?.version ?? null,
        dragging: null,
        pendingRefresh: false,
        refreshInFlight: false,
        queuedRefreshForce: false,
        reorderInFlight: false,
        intervalId: null,
        sortables: [],
        refreshRequestId: 0,
        appliedRefreshRequestId: 0,
        refreshGeneration: 0,
        renderNonce: 0,
        snapshotUrl: config.snapshotUrl ?? '',
        reorderUrl: config.reorderUrl ?? '',
        refreshEvents: normalizeEvents(
            config.refreshEvents,
            normalizeEvents(transport.signals?.refresh),
        ),
        transport,
        transportMode,
        pollInterval,
        allowedTransportModes,
        cardClickEvent: config.cardClickEvent ?? '',
        reorderRefreshCooldownMs,
        refreshBlockedUntil: 0,
        deferredRefreshTimeoutId: null,
        initialized: false,

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
            this.handleRefreshEvent = () => this.refresh({ force: true })

            this.refreshEvents.forEach((eventName) => {
                window.addEventListener(eventName, this.handleRefreshEvent)
            })

            if (this.transportMode === 'polling' && this.pollInterval > 0) {
                this.intervalId = window.setInterval(() => {
                    if (!document.hidden && !this.reorderInFlight) {
                        this.refresh()
                    }
                }, this.pollInterval)
            }

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
            this.initialized = false

            if (this.$el?.__kanbanBoardInstance === this) {
                delete this.$el.__kanbanBoardInstance
            }
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
                this.refresh({ force })
            }, delay)
        },

        withRenderKeys(columns = []) {
            return (columns || []).map((column) => ({
                ...column,
                renderKey: ++this.renderNonce,
            }))
        },

        mergeSnapshotColumns(columns = []) {
            return this.withRenderKeys((columns || []).map((incomingColumn) => {
                const currentColumn = this.columns.find((column) => column.status === incomingColumn.status)

                if (!currentColumn || !haveSameIdsInSameCount(currentColumn.items || [], incomingColumn.items || [])) {
                    return incomingColumn
                }

                const incomingItemsById = new Map(
                    (incomingColumn.items || []).map((item) => [item.id, item]),
                )

                const mergedItems = (currentColumn.items || []).map((item) => {
                    const incomingItem = incomingItemsById.get(item.id)

                    return incomingItem
                        ? { ...incomingItem, status: incomingColumn.status }
                        : item
                })

                return {
                    ...incomingColumn,
                    items: mergedItems,
                    count: mergedItems.length,
                }
            }))
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

                    this.columns = this.withRenderKeys(this.columns.map((column) => {
                        const columnElement = this.$el.querySelector(`[data-column-status="${column.status}"]`)

                        if (!columnElement) {
                            return column
                        }

                        const items = Array.from(columnElement.children)
                            .filter((item) => item instanceof HTMLElement && item.matches('.kanban-draggable[data-id]'))
                            .map((item) => itemPool.get(item.dataset.id) ?? null)
                            .filter((item) => item !== null)
                            .map((item) => ({
                                ...item,
                                status: column.status,
                            }))

                        return {
                            ...column,
                            items,
                            count: items.length,
                        }
                    }))
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
                columnScrollTopByStatus: Object.fromEntries(
                    Array.from(this.$el.querySelectorAll('[data-column-status]')).map((column) => [
                        column.dataset.columnStatus,
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

            Object.entries(state.columnScrollTopByStatus ?? {}).forEach(([status, scrollTop]) => {
                const column = this.$el.querySelector(`[data-column-status="${status}"]`)

                if (column && typeof scrollTop === 'number') {
                    column.scrollTop = scrollTop
                }
            })
        },

        async refresh({ force = false, allowDuringDrag = false } = {}) {
            if (!this.snapshotUrl) {
                return
            }

            if ((this.dragging || this.reorderInFlight) && !allowDuringDrag) {
                this.pendingRefresh = true

                return
            }

            if (this.isRefreshBlocked()) {
                this.pendingRefresh = true
                this.scheduleDeferredRefresh(force)

                return
            }

            if (this.refreshInFlight) {
                this.queuedRefreshForce = this.queuedRefreshForce || force

                return
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

                if (generation !== this.refreshGeneration) {
                    return
                }

                if (requestId < this.refreshRequestId || requestId < this.appliedRefreshRequestId) {
                    return
                }

                if (!payload.changed) {
                    this.version = payload.version ?? this.version
                    this.appliedRefreshRequestId = requestId

                    return
                }

                const scrollState = this.captureScrollState()

                this.version = payload.version ?? null
                this.columns = this.mergeSnapshotColumns(payload.columns ?? [])
                this.appliedRefreshRequestId = requestId

                this.$nextTick(() => {
                    this.restoreScrollState(scrollState)
                    this.bindSortables()
                })
            } finally {
                this.refreshInFlight = false

                if (this.queuedRefreshForce) {
                    const queuedForce = this.queuedRefreshForce
                    this.queuedRefreshForce = false
                    this.refresh({ force: queuedForce })
                }
            }
        },

        startDrag(cardId, fromStatus) {
            this.dragging = { cardId, fromStatus }
        },

        clearDrag() {
            this.dragging = null

            if (this.pendingRefresh) {
                this.pendingRefresh = false

                if (!this.isRefreshBlocked()) {
                    this.refresh({ force: true })
                }
            }
        },

        async persistReorder(cardId, fromStatus, toStatus, orderedIds) {
            if (!this.reorderUrl) {
                this.clearDrag()

                return
            }

            this.reorderInFlight = true
            this.blockRefreshTemporarily()
            let reorderSucceeded = false

            try {
                await axios.post(this.reorderUrl, {
                    moved_id: cardId,
                    parent: toStatus,
                    data: orderedIds.join(','),
                }, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json, text/plain, */*',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                })

                reorderSucceeded = true
            } catch (error) {
                this.scheduleDeferredRefresh(true)
                throw error
            } finally {
                this.reorderInFlight = false
                this.clearDrag()

                if (reorderSucceeded) {
                    this.syncLocalStateAfterReorder()
                }
            }
        },

        clickCard(card, event) {
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
        },

        async handleSortableChange(event, fallbackStatus) {
            const movedId = event.item.dataset.id
            const sourceStatus = event.from.dataset.columnStatus || fallbackStatus
            const targetStatus = event.to.dataset.columnStatus || fallbackStatus
            const orderedIds = orderedIdsForContainer(event.to)

            if (!movedId || orderedIds.length === 0) {
                this.clearDrag()

                return
            }

            try {
                await this.persistReorder(
                    movedId,
                    sourceStatus,
                    targetStatus,
                    orderedIds,
                )
            } catch (error) {
                console.error('Reorder error:', error)
            }
        },

        bindSortables() {
            this.destroySortables()

            this.$el.querySelectorAll('[data-column-status]').forEach((column) => {
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

                    onStart: (event) => {
                        event.item.classList.add('kanban-lift')
                        this.startDrag(event.item.dataset.id, column.dataset.columnStatus)
                    },

                    onUpdate: async (event) => {
                        await this.handleSortableChange(event, column.dataset.columnStatus)
                    },

                    onAdd: async (event) => {
                        await this.handleSortableChange(event, column.dataset.columnStatus)
                    },

                    onEnd: (event) => {
                        event.item.classList.remove('kanban-lift')

                        if (
                            !this.reorderInFlight
                            && event.from === event.to
                            && event.oldDraggableIndex === event.newDraggableIndex
                        ) {
                            this.clearDrag()
                        }
                    },
                })

                this.sortables.push(sortable)
            })
        },
    }
}

window.kanbanReorderable = function kanbanReorderable(sortRoute, options = {}) {
    return {
        init() {
            const container = this.$el
            const csrfToken = container.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content
            const refreshEvents = normalizeEvents(
                options.refreshEvents,
                normalizeEvents(options.transport?.events?.reorderRefresh),
            )

            Sortable.create(container, {
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
                draggable: '[data-id]',
                ghostClass: 'kanban-ghost',
                chosenClass: 'kanban-chosen',

                onStart: (event) => {
                    event.item.classList.add('kanban-lift')
                },

                onEnd: async (event) => {
                    event.item.classList.remove('kanban-lift')

                    if (
                        event.from === event.to
                        && event.oldDraggableIndex === event.newDraggableIndex
                    ) {
                        return
                    }

                    const allItems = event.to.querySelectorAll('[data-id]')
                    const itemIds = Array.from(allItems).map((item) => item.dataset.id)

                    const formData = new FormData()
                    formData.append('_token', csrfToken)
                    formData.append('data', itemIds.join(','))
                    formData.append('parent', event.to.dataset.parentKey || event.to.dataset.parent_key)
                    formData.append('moved_id', event.item.dataset.id)

                    try {
                        await fetch(sortRoute, {
                            method: 'POST',
                            body: formData,
                        })

                        dispatchBrowserEvents(refreshEvents, {
                            movedId: event.item.dataset.id,
                            parent: event.to.dataset.parentKey || event.to.dataset.parent_key,
                        })
                    } catch (error) {
                        console.error('Reorder error:', error)
                        event.item.style.backgroundColor = '#fee'
                        setTimeout(() => {
                            event.item.style.backgroundColor = ''
                        }, 1000)
                    }
                },

                onMove: (event) => {
                    const rect = container.getBoundingClientRect()
                    const y = event.originalEvent.clientY

                    if (y < rect.top + 80) {
                        container.scrollTop -= 25
                    } else if (y > rect.bottom - 80) {
                        container.scrollTop += 25
                    }
                },
            })
        },
    }
}
