import Sortable from 'sortablejs'

const DEFAULT_REORDER_EVENTS = ['fragment_updated:crud-list']

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
    return {
        columns: config.initialSnapshot?.columns ?? [],
        version: config.initialSnapshot?.version ?? null,
        dragging: null,
        pendingRefresh: false,
        refreshInFlight: false,
        queuedRefreshForce: false,
        intervalId: null,
        sortables: [],
        snapshotUrl: config.snapshotUrl ?? '',
        reorderUrl: config.reorderUrl ?? '',
        refreshEvents: normalizeEvents(config.refreshEvents),
        transportMode: config.transportMode ?? 'manual',
        pollInterval: Number(config.pollInterval || 5000),
        cardClickEvent: config.cardClickEvent ?? '',

        init() {
            this.handleRefreshEvent = () => this.refresh({ force: true })

            this.refreshEvents.forEach((eventName) => {
                window.addEventListener(eventName, this.handleRefreshEvent)
            })

            if (this.transportMode === 'polling' && this.pollInterval > 0) {
                this.intervalId = window.setInterval(() => {
                    if (!document.hidden) {
                        this.refresh()
                    }
                }, this.pollInterval)
            }

            this.$nextTick(() => this.bindSortables())
        },

        destroy() {
            this.refreshEvents.forEach((eventName) => {
                window.removeEventListener(eventName, this.handleRefreshEvent)
            })

            if (this.intervalId) {
                window.clearInterval(this.intervalId)
            }

            this.destroySortables()
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

        async refresh({ force = false } = {}) {
            if (!this.snapshotUrl) {
                return
            }

            if (this.dragging) {
                this.pendingRefresh = true

                return
            }

            if (this.refreshInFlight) {
                this.queuedRefreshForce = this.queuedRefreshForce || force

                return
            }

            this.refreshInFlight = true

            try {
                const response = await axios.get(this.buildSnapshotUrl(force), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                })

                const payload = response.data || {}

                if (!payload.changed) {
                    this.version = payload.version ?? this.version

                    return
                }

                const scrollState = this.captureScrollState()

                this.version = payload.version ?? null
                this.columns = payload.columns ?? []

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
                this.refresh({ force: true })
            }
        },

        moveCard(cardId, toStatus, toIndex = null) {
            let card = null

            this.columns.forEach((column) => {
                const index = (column.items || []).findIndex((item) => item.id === cardId)

                if (index !== -1) {
                    card = column.items.splice(index, 1)[0]
                }
            })

            if (!card) {
                return
            }

            const targetColumn = this.columns.find((column) => column.status === toStatus)

            if (!targetColumn) {
                return
            }

            card.status = toStatus

            if (toIndex === null || toIndex >= targetColumn.items.length) {
                targetColumn.items.push(card)
            } else {
                targetColumn.items.splice(toIndex, 0, card)
            }

            this.recount()
        },

        recount() {
            this.columns.forEach((column) => {
                column.count = (column.items || []).length
            })
        },

        async persistReorder(cardId, toStatus, orderedIds, toIndex) {
            if (!this.reorderUrl) {
                this.clearDrag()

                return
            }

            this.moveCard(cardId, toStatus, toIndex)

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

                await this.refresh({ force: true })
            } catch (error) {
                await this.refresh({ force: true })
                throw error
            } finally {
                this.clearDrag()
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
                    handle: '.handle',
                    draggable: '[data-id]',
                    ghostClass: 'kanban-ghost',
                    chosenClass: 'kanban-chosen',

                    onStart: (event) => {
                        event.item.classList.add('kanban-lift')
                        this.startDrag(event.item.dataset.id, column.dataset.columnStatus)
                    },

                    onEnd: async (event) => {
                        event.item.classList.remove('kanban-lift')

                        const movedId = event.item.dataset.id
                        const targetStatus = event.to.dataset.columnStatus || column.dataset.columnStatus
                        const orderedIds = Array.from(event.to.querySelectorAll('[data-id]')).map((item) => item.dataset.id)

                        if (event.from === event.to && event.oldIndex === event.newIndex) {
                            this.clearDrag()

                            return
                        }

                        try {
                            await this.persistReorder(movedId, targetStatus, orderedIds, event.newIndex)
                        } catch (error) {
                            console.error('Reorder error:', error)
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
            const refreshEvents = normalizeEvents(options.refreshEvents, DEFAULT_REORDER_EVENTS)

            Sortable.create(container, {
                group: {
                    name: 'kanban-group',
                    pull: true,
                    put: true,
                },
                animation: 150,
                handle: '.handle',
                draggable: '[data-id]',
                ghostClass: 'kanban-ghost',
                chosenClass: 'kanban-chosen',

                onStart: (event) => {
                    event.item.classList.add('kanban-lift')
                },

                onEnd: async (event) => {
                    event.item.classList.remove('kanban-lift')

                    if (event.from === event.to && event.oldIndex === event.newIndex) {
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
