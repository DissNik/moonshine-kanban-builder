@props([
    'board' => [],
    'name' => 'default',
    'topLeft' => null,
    'topRight' => null,
    'translates' => [],
])

@php
    use Illuminate\Support\Js;
@endphp

<div class="js-cards-builder-container">
    <div
        x-data="kanbanBoard({{ Js::from($board) }})"
        x-init="init()"
        x-on:beforeunload.window="destroy()"
        {{ $attributes }}
    >
        <x-moonshine::iterable-wrapper>
            <x-slot:topLeft>
                {!! $topLeft ?? '' !!}
            </x-slot:topLeft>

            <x-slot:topRight>
                {!! $topRight ?? '' !!}
            </x-slot:topRight>

            <div class="w-full overflow-hidden">
                <div
                    class="w-full overflow-x-auto"
                    style="scrollbar-width: thin; -webkit-overflow-scrolling: touch; overflow-x: scroll;"
                    x-data="kanbanBoardScroll"
                    x-ref="boardScroll"
                >
                    <div class="flex gap-2 select-none items-start min-w-max">
                        <template x-if="! Array.isArray(columns) || columns.length === 0">
                            <x-moonshine::alert type="default" class="my-4" icon="s.no-symbol">
                                {{ $translates['notfound'] }}
                            </x-moonshine::alert>
                        </template>

                        <template x-for="column in columns" :key="`${column.id}-${column.renderKey ?? 0}`">
                            <section
                                class="box space-elements kanban-column p-0"
                                style="min-width: 20rem; max-width: 20rem; width: 20rem; flex: 0 0 20rem;"
                            >
                                <div class="kanban-column-header flex justify-between items-center gap-2">
                                    <h4 x-text="column.label"></h4>
                                    <span class="badge badge-gray" x-text="(column.items || []).length"></span>
                                </div>

                                <div
                                    class="kanban-column-scroll flex flex-col gap-2"
                                    :data-column-id="column.id"
                                >
                                    <template x-for="card in column.items" :key="`${card.id}-${column.renderKey ?? 0}`">
                                        <article
                                            class="kanban-draggable handle"
                                            :data-id="card.id"
                                            @click="clickCard(card, $event)"
                                        >
                                            <div class="kanban-card-content" x-html="card.html"></div>
                                        </article>
                                    </template>
                                </div>
                            </section>
                        </template>
                    </div>
                </div>
            </div>
        </x-moonshine::iterable-wrapper>
    </div>
</div>
