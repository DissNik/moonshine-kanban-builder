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
])
<div class="js-cards-builder-container">
    <div x-data="cardsBuilder(
    {{ (int) $async }},
    '{{ $asyncUrl }}'
)"
        @defineEventWhen($async, 'cards_updated', $name, 'asyncRequest')
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

            @if($components->isNotEmpty())
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
