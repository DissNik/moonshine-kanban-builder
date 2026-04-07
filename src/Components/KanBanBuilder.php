<?php

namespace DissNik\MoonShineKanBanBuilder\Components;

use Closure;
use DissNik\MoonShineKanBanBuilder\Contracts\KanbanTransportContract;
use DissNik\MoonShineKanBanBuilder\Support\KanbanConfig;
use DissNik\MoonShineKanBanBuilder\Support\NullKanbanTransport;
use Illuminate\Support\Collection;
use MoonShine\AssetManager\Css;
use MoonShine\AssetManager\Js;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\HasAsyncContract;
use MoonShine\Contracts\UI\HasFieldsContract;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Components\ActionGroup;
use MoonShine\UI\Components\Badge;
use MoonShine\UI\Components\Card;
use MoonShine\UI\Components\Components;
use MoonShine\UI\Components\IterableComponent;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Traits\HasAsync;
use MoonShine\UI\Traits\WithFields;
use Throwable;

final class KanBanBuilder extends IterableComponent implements
    HasFieldsContract,
    HasAsyncContract
{
    use WithFields;
    use HasAsync;

    protected string $view = 'moonshine-kanban-builder::components.kanban';

    protected array $translates = [
        'search' => 'moonshine::ui.search',
        'notfound' => 'moonshine::ui.notfound',
    ];

    protected function assets(): array
    {
        return [
            Js::make('vendor/moonshine-kanban-builder/js/script.js'),
            Css::make('vendor/moonshine-kanban-builder/css/stylesheet.css'),
        ];
    }

    /**
     * @var list<ComponentContract>
     */
    protected array $components = [];

    /**
     * @var (Closure(mixed, int, self): string)|string
     */
    protected Closure|string $title = '';

    /**
     * @var (Closure(mixed, int, self): string)|string
     */
    protected Closure|string $subtitle = '';

    /**
     * @var (Closure(mixed, int, self): string)|string
     */
    protected Closure|string $thumbnail = '';

    /**
     * @var (Closure(mixed, int, self): string)|string
     */
    protected Closure|string $url = '';

    /**
     * @var (Closure(mixed, int, self): string)|string
     */
    protected Closure|string $content = '';

    /**
     * @var (Closure(mixed, int, self): string)|string
     */
    protected Closure|string $header = '';

    protected bool $overlay = false;

    /**
     * @var null|Closure(mixed, int, self): ComponentContract
     */
    protected ?Closure $customComponent = null;

    /**
     * @var (Closure(mixed, int, self): array<string, mixed>)|array<string, mixed>
     */
    protected array|Closure $componentAttributes = [];

    protected ?Closure $topLeft = null;

    protected ?Closure $topRight = null;

    protected bool $searchable = false;

    protected bool $syncEnabled = false;

    /**
     * @var array<string, mixed>
     */
    protected array $initialSnapshot = [];

    protected string $snapshotUrl = '';

    /**
     * @var list<string>
     */
    protected array $refreshEvents = [];

    protected string $transportMode;

    protected int $pollInterval;

    protected string $cardClickEvent = '';

    /**
     * @param  iterable<array-key, TData>  $items
     * @param  FieldsContract|iterable<array-key, FieldContract>  $fields
     */
    public function __construct(
        iterable $items = [],
        FieldsContract|iterable $fields = [],
        protected string $groupByField = 'status',
        protected iterable $groups = [],
        protected string $reorderRoute = '#',
    ) {
        parent::__construct();

        $this->items($items);
        $this->fields($fields);
        $this->transportMode = KanbanConfig::transportMode();
        $this->pollInterval = KanbanConfig::pollingInterval();

        $this->withAttributes([]);
    }

    public function groupByField(string $field): static
    {
        $this->groupByField = $field;

        return $this;
    }

    public function getGroupByField(): string
    {
        return $this->groupByField;
    }

    public function groups(array $groups): static
    {
        $this->groups = $groups;

        return $this;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }


    public function reorderRoute(string $route): static
    {
        $this->reorderRoute = $route;

        return $this;
    }

    public function geReorderRoute(): string
    {
        return $this->reorderRoute;
    }


    /**
     * @param (Closure(mixed $data, int $index, self $ctx): string)|string $value
     */
    public function title(Closure|string $value): self
    {
        $this->title = $value;

        return $this;
    }

    /**
     * @param (Closure(mixed $data, int $index, self $ctx): string)|string $value
     */
    public function subtitle(Closure|string $value): self
    {
        $this->subtitle = $value;

        return $this;
    }

    /**
     * @param (Closure(mixed $data, int $index, self $ctx): string)|string $value
     */
    public function thumbnail(Closure|string $value): self
    {
        $this->thumbnail = $value;

        return $this;
    }

    /**
     * @param (Closure(mixed $data, int $index, self $ctx): string)|string $value
     */
    public function url(Closure|string $value): self
    {
        $this->url = $value;

        return $this;
    }

    public function overlay(): self
    {
        $this->overlay = true;

        return $this;
    }

    /**
     * @param (Closure(mixed $data, int $index, self $ctx): string)|string $value
     */
    public function content(Closure|string $value): self
    {
        $this->content = $value;

        return $this;
    }

    /**
     * @param (Closure(mixed $data, int $index, self $ctx): string)|string $value
     */
    public function header(Closure|string $value): self
    {
        $this->header = $value;

        return $this;
    }

    protected function prepareAsyncUrl(Closure|string|null $url = null): Closure|string
    {
        return $url ?? fn (): string => $this->getCore()->getRouter()->getEndpoints()->component(name: $this->getName());
    }

    /**
     * @param (Closure(mixed $data, int $index, self $ctx): array<string, mixed>)|array<string, mixed> $attributes
     */
    public function componentAttributes(array|Closure $attributes): self
    {
        $this->componentAttributes = $attributes;

        return $this;
    }

    /**
     * @param Closure(mixed $data, int $index, self $ctx): ComponentContract $component
     */
    public function customComponent(Closure $component): self
    {
        $this->customComponent = $component;

        return $this;
    }

    /**
     * @return Collection<array-key, Card>
     * @throws Throwable
     */
    public function getComponents(): Collection
    {
        $cardsByColumn = $this->getCardsGroupedByColumn();
        return $this->createColumnsWithCards($cardsByColumn);
    }

    protected function getCardsGroupedByColumn(): Collection
    {
        $fields = $this->getPreparedFields();
        $groupField = $this->getGroupByField();

        return $this->getItems()->mapToGroups(function (mixed $data, int $index) use ($fields, $groupField) {
            $casted = $this->castData($data);
            $columnKey = $casted->getAttributes()[$groupField] ?? 'uncategorized';

            $card = $this->createCard($data, $index, $fields, $casted);

            return [$columnKey => $card];
        });
    }

    protected function createCard(mixed $data, int $index, Collection $fields, mixed $casted)
    {
        $fields = $this->getFilledFields($casted->toArray(), $casted, $index, $fields);

        if (! \is_null($this->customComponent)) {
            return \call_user_func($this->customComponent, $data, $index, $this);
        }

        $buttons = $this->getButtons($casted);

        return Card::make(...$this->getMapper($data, $fields, $index))
            ->content((string) value($this->content, $data, $index, $this))
            ->header((string) value($this->header, $data, $index, $this))
            ->customAttributes(value($this->componentAttributes, $data, $index, $this))
            ->customAttributes(value($this->componentAttributes, $data, $index, $this))
            ->setAttribute('data-id', $data->id)
            ->class('kanban-card handle')
            ->when(
                $buttons->isNotEmpty(),
                static fn (Card $card): Card => $card->actions(
                    static fn () => ActionGroup::make($buttons->toArray())
                )
            );
    }

    protected function createColumnsWithCards(Collection $cardsByColumn): Collection
    {
        $groups = $this->getGroups();
        $columns = collect();

        foreach ($groups as $key => $label) {
            $cards = $cardsByColumn->get($key, collect());

            $column = Box::make($label, $cards)
                ->setLabel('')
                ->class('kanban-column')
                ->setComponents([
                    Flex::make([
                        "<h4>$label</h4>",
                        Badge::make(sprintf('%d', count($cards)), Color::GRAY)
                    ])
                        ->justifyAlign('between'),
                    Flex::make($cardsByColumn->get($key, collect()))
                        ->withoutSpace()
                        ->class('gap-2')
                        ->justifyAlign('start')
                        ->setAttribute('data-parent-key', $key)
                        ->setAttribute('x-data', "kanbanReorderable('{$this->geReorderRoute()}')")
                        ->setAttribute('x-init', 'init()'),
                ]);

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @return string|array<string, string>
     */
    protected function getMapperValue(string $column, mixed $data, int $index): string|array
    {
        return \is_string($this->{$column})
            ? data_get($data, $this->{$column}, '')
            : value($this->{$column}, $data, $index, $this);
    }

    /**
     * @param  TData  $data
     * @return array<string, mixed>
     */
    protected function getMapper(mixed $data, FieldsContract $fields, int $index): array
    {
        /** @var array<string, string> $values */
        /** @phpstan-ignore-next-line */
        $values = $fields
            ->values()
            ->mapWithKeys(static fn (FieldContract $value): array => [$value->getLabel() => (string) $value->preview()])
            ->toArray();

        return [
            'title' => $this->getMapperValue('title', $data, $index),
            'subtitle' => $this->getMapperValue('subtitle', $data, $index),
            'thumbnail' => $this->getMapperValue('thumbnail', $data, $index),
            'url' => $this->getMapperValue('url', $data, $index),
            'overlay' => $this->overlay,
            'values' => $values,
        ];
    }

    /**
     * @param  Closure(self): list<ComponentContract>  $callback
     */
    public function topLeft(Closure $callback): self
    {
        $this->topLeft = $callback;

        return $this;
    }

    /**
     * @param  Closure(self): list<ComponentContract>  $callback
     */
    public function topRight(Closure $callback): self
    {
        $this->topRight = $callback;

        return $this;
    }

    public function searchable(): static
    {
        $this->searchable = true;

        return $this;
    }

    private function getTopLeft(): Components
    {
        $components = \is_null($this->topLeft) ? [] : \call_user_func($this->topLeft);

        return Components::make($components);
    }

    private function getTopRight(): Components
    {
        $components = \is_null($this->topRight) ? [] : \call_user_func($this->topRight);

        return Components::make($components);
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function initialSnapshot(array $snapshot): static
    {
        $this->syncEnabled = true;
        $this->initialSnapshot = $snapshot;

        return $this;
    }

    public function snapshotUrl(string $url): static
    {
        $this->syncEnabled = true;
        $this->snapshotUrl = $url;

        return $this;
    }

    /**
     * @param  string|list<string>  $events
     */
    public function refreshEvents(string|array $events): static
    {
        $this->syncEnabled = true;
        $this->refreshEvents = collect(\is_array($events) ? $events : [$events])
            ->filter(static fn (mixed $event): bool => \is_string($event) && $event !== '')
            ->values()
            ->all();

        return $this;
    }

    public function transportMode(string $mode): static
    {
        $this->syncEnabled = true;
        $this->transportMode = KanbanConfig::normalizeTransportMode($mode);

        return $this;
    }

    public function pollInterval(int $milliseconds): static
    {
        $this->syncEnabled = true;
        $this->pollInterval = $milliseconds > 0
            ? $milliseconds
            : KanbanConfig::pollingInterval();

        return $this;
    }

    public function cardClickEvent(string $event): static
    {
        $this->syncEnabled = true;
        $this->cardClickEvent = $event;

        return $this;
    }

    public function isSyncEnabled(): bool
    {
        return $this->syncEnabled;
    }

    protected function prepareBeforeRender(): void
    {
        parent::prepareBeforeRender();

        $this->performBeforeRender();
    }

    protected function performBeforeRender(): self
    {
        $this->resolvePaginator();

        if ($this->isAsync() && $this->hasPaginator()) {
            $this->paginator(
                $this->getPaginator()
                    ?->setPath($this->prepareAsyncUrlFromPaginator())
            );
        }

        if ($this->isAsync()) {
            $this->customAttributes([
                'data-events' => $this->getAsyncEvents(),
            ]);
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     * @throws Throwable
     */
    protected function viewData(): array
    {
        $transport = app()->bound(KanbanTransportContract::class)
            ? app(KanbanTransportContract::class)->clientConfig()
            : (new NullKanbanTransport)->clientConfig();

        return [
            'components' => $this->getComponents(),
            'name' => $this->getName(),
            'async' => $this->isAsync(),
            'asyncUrl' => $this->getAsyncUrl(),
            'topLeft' => $this->getTopLeft(),
            'topRight' => $this->getTopRight(),
            'searchable' => $this->isSearchable(),
            'searchValue' => $this->getCore()->getRequest()->getScalar('search', ''),
            'syncEnabled' => $this->isSyncEnabled(),
            'initialSnapshot' => $this->initialSnapshot,
            'snapshotUrl' => $this->snapshotUrl,
            'refreshEvents' => $this->refreshEvents,
            'transportMode' => $this->transportMode,
            'pollInterval' => $this->pollInterval,
            'allowedTransportModes' => KanbanConfig::allowedTransportModes(),
            'reorderRefreshCooldownMs' => KanbanConfig::reorderRefreshCooldown(),
            'transport' => $transport,
            'cardClickEvent' => $this->cardClickEvent,
            'reorderRoute' => $this->geReorderRoute(),
        ];
    }
}
