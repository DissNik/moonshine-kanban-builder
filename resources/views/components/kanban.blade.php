@props([
    'components' => [],
    'bulkButtons' => [],
    'asyncUrl' => '',
    'async' => false,
    'notfound' => false,
    'name' => 'default',
    'translates' => [],
    'searchable' => false,
    'searchValue' => '',
    'topLeft' => null,
    'topRight' => null,
    'syncEnabled' => false,
    'initialSnapshot' => [],
    'snapshotUrl' => '',
    'refreshEvents' => [],
    'transportMode' => 'manual',
    'pollInterval' => 5000,
    'cardClickEvent' => '',
    'reorderRoute' => '#',
])
@php
    $kanbanExpression = $syncEnabled
        ? 'kanbanBoard('.json_encode([
            'initialSnapshot' => $initialSnapshot,
            'snapshotUrl' => $snapshotUrl,
            'reorderUrl' => $reorderRoute,
            'refreshEvents' => $refreshEvents,
            'transportMode' => $transportMode,
            'pollInterval' => $pollInterval,
            'cardClickEvent' => $cardClickEvent,
        ], JSON_THROW_ON_ERROR).')'
        : 'cardsBuilder('.(int) $async.', '.json_encode($asyncUrl, JSON_THROW_ON_ERROR).')';
@endphp
<div class="js-cards-builder-container">
    <div
        x-data="{{ $kanbanExpression }}"
        x-init="{{ $syncEnabled ? 'init()' : '' }}"
        x-on:beforeunload.window="{{ $syncEnabled ? 'destroy()' : '' }}"
        @defineEventWhen($async && ! $syncEnabled, 'cards_updated', $name, 'asyncRequest')
        {{ $attributes }}
    >
        <x-moonshine::iterable-wrapper
            :searchable="$async && $searchable"
            :search-placeholder="$translates['search']"
            :search-value="$searchValue"
            :search-url="$asyncUrl"
        >
            <x-slot:topLeft>
                {!! $topLeft ?? '' !!}
            </x-slot:topLeft>

            <x-slot:topRight>
                {!! $topRight ?? '' !!}
            </x-slot:topRight>

            @if($syncEnabled)
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

                            <template x-for="column in columns" :key="column.status">
                                <section
                                    class="box space-elements kanban-column p-0"
                                    style="min-width: 20rem; max-width: 20rem; width: 20rem; flex: 0 0 20rem;"
                                >
                                    <div class="kanban-column-header flex justify-between items-center gap-2">
                                        <h4 x-text="column.label"></h4>
                                        <span class="badge badge-gray" x-text="column.count"></span>
                                    </div>

                                    <div class="kanban-column-scroll">
                                        <div
                                            class="kanban-column-track flex flex-col gap-2"
                                            :data-column-status="column.status"
                                        >
                                            <template x-for="card in column.items" :key="card.id">
                                                <div class="kanban-column-card">
                                                    <div
                                                        class="handle cursor-pointer"
                                                        style="border:none; background:transparent; box-shadow:none; padding:0; margin:0;"
                                                        :data-id="card.id"
                                                        x-html="card.card_html"
                                                        @click="clickCard(card, $event)"
                                                    ></div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </section>
                            </template>
                        </div>
                    </div>
                </div>
            @elseif($components->isNotEmpty())
                <div class="w-full overflow-hidden">
                    <div
                        class="w-full overflow-x-auto"
                        style="scrollbar-width: thin; -webkit-overflow-scrolling: touch; overflow-x: scroll;"
                        x-data="kanbanBoardScroll"
                    >
                        <div class="flex gap-2 select-none items-start min-w-max">
                            @foreach ($components as $column)
                                {!! $column !!}
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <x-moonshine::alert type="default" class="my-4" icon="s.no-symbol">
                    {{ $translates['notfound'] }}
                </x-moonshine::alert>
            @endif
        </x-moonshine::iterable-wrapper>
    </div>
</div>
