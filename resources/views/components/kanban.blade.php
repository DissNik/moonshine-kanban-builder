@php
    use DissNik\MoonShineKanBanBuilder\Support\KanbanConfig;
@endphp

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
    'transportMode' => null,
    'pollInterval' => null,
    'allowedTransportModes' => [],
    'reorderRefreshCooldownMs' => null,
    'transport' => [],
    'cardClickEvent' => '',
    'reorderRoute' => '#',
])
@php
    $transportConfig = is_array($transport) ? $transport : [];
    $resolvedTransportMode = $transportMode
        ?? data_get($transportConfig, 'mode')
        ?? KanbanConfig::transportMode();
    $resolvedPollInterval = $pollInterval
        ?? data_get($transportConfig, 'polling.interval')
        ?? KanbanConfig::pollingInterval();
    $resolvedAllowedTransportModes = $allowedTransportModes !== []
        ? $allowedTransportModes
        : (data_get($transportConfig, 'allowedModes') ?: KanbanConfig::allowedTransportModes());
    $resolvedReorderRefreshCooldownMs = $reorderRefreshCooldownMs ?? KanbanConfig::reorderRefreshCooldown();
    $resolvedTransportConfig = array_replace($transportConfig, [
        'mode' => $resolvedTransportMode,
        'allowedModes' => $resolvedAllowedTransportModes,
        'polling' => array_replace((array) data_get($transportConfig, 'polling', []), [
            'interval' => $resolvedPollInterval,
        ]),
        'signals' => array_replace((array) data_get($transportConfig, 'signals', []), [
            'refresh' => data_get($transportConfig, 'signals.refresh', KanbanConfig::transportRefreshSignal()),
        ]),
        'events' => array_replace((array) data_get($transportConfig, 'events', []), [
            'reorderRefresh' => data_get($transportConfig, 'events.reorderRefresh', KanbanConfig::reorderRefreshEvents()),
        ]),
    ]);

    $kanbanExpression = $syncEnabled
        ? 'kanbanBoard('.json_encode([
            'initialSnapshot' => $initialSnapshot,
            'snapshotUrl' => $snapshotUrl,
            'reorderUrl' => $reorderRoute,
            'refreshEvents' => $refreshEvents,
            'transport' => $resolvedTransportConfig,
            'cardClickEvent' => $cardClickEvent,
            'reorderRefreshCooldownMs' => $resolvedReorderRefreshCooldownMs,
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

                            <template x-for="column in columns" :key="`${column.status}-${column.renderKey ?? 0}`">
                                <section
                                    class="box space-elements kanban-column p-0"
                                    style="min-width: 20rem; max-width: 20rem; width: 20rem; flex: 0 0 20rem;"
                                >
                                    <div class="kanban-column-header flex justify-between items-center gap-2">
                                        <h4 x-text="column.label"></h4>
                                        <span class="badge badge-gray" x-text="column.count"></span>
                                    </div>

                                    <div
                                        class="kanban-column-scroll flex flex-col gap-2"
                                        :data-column-status="column.status"
                                    >
                                        <template x-for="card in column.items" :key="`${card.id}-${column.renderKey ?? 0}`">
                                            <article
                                                class="kanban-draggable handle"
                                                :data-id="card.id"
                                                @click="clickCard(card, $event)"
                                            >
                                                <div class="kanban-card-content" x-html="card.card_html"></div>
                                            </article>
                                        </template>
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
