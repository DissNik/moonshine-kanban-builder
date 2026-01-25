import Sortable from 'sortablejs'

window.kanbanBoardScroll = function kanbanBoardScroll() {
    return {
        init() {
            const container = this.$el;
            const edge = 100;
            const speed = 30;

            const handleDragOver = (e) => {
                const rect = container.getBoundingClientRect();
                const x = e.clientX;

                if (x < rect.left + edge) {
                    container.scrollLeft -= speed;
                } else if (x > rect.right - edge) {
                    container.scrollLeft += speed;
                }
            };

            document.addEventListener('dragover', handleDragOver);
        }
    }
}

window.kanbanReorderable = function kanbanReorderable(sortRoute) {
    return {
        init() {
            const container = this.$el;
            const csrfToken = container.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content;

            Sortable.create(container, {
                group: {
                    name: 'kanban-group',
                    pull: true,
                    put: true
                },
                animation: 150,
                handle: '.handle',
                draggable: '[data-id]',
                ghostClass: 'kanban-ghost',
                chosenClass: 'kanban-chosen',

                onStart: (evt) => {
                    evt.item.classList.add('kanban-lift');
                },

                onEnd: async (evt) => {
                    evt.item.classList.remove('kanban-lift');

                    if (evt.from === evt.to) {
                        return;
                    }

                    const allItems = evt.to.querySelectorAll('[data-id]');
                    const itemIds = Array.from(allItems).map(item => item.dataset.id);

                    const formData = new FormData();
                    formData.append('_token', csrfToken);
                    formData.append('data', itemIds.join(','));
                    formData.append('parent', evt.to.dataset.parentKey || evt.to.dataset.parent_key);
                    formData.append('moved_id', evt.item.dataset.id);

                    try {
                        await fetch(sortRoute, {
                            method: 'POST',
                            body: formData,
                        });
                        window.dispatchEvent(new CustomEvent("fragment_updated:crud-list"));
                    } catch (error) {
                        console.error('Reorder error:', error);
                        evt.item.style.backgroundColor = '#fee';
                        setTimeout(() => {
                            evt.item.style.backgroundColor = '';
                        }, 1000);
                    }
                },

                onMove: (evt) => {
                    const rect = container.getBoundingClientRect();
                    const y = evt.originalEvent.clientY;

                    if (y < rect.top + 80) {
                        container.scrollTop -= 25;
                    } else if (y > rect.bottom - 80) {
                        container.scrollTop += 25;
                    }
                }
            });
        }
    }
}
