<?php

declare(strict_types=1);

namespace DissNik\MoonShineKanBanBuilder\Components;

use Closure;
use DissNik\MoonShineKanBanBuilder\Contracts\KanbanTransportContract;
use DissNik\MoonShineKanBanBuilder\Support\KanbanConfig;
use DissNik\MoonShineKanBanBuilder\Support\KanbanColumnOrderPayload;
use DissNik\MoonShineKanBanBuilder\Support\KanbanReorderPayload;
use DissNik\MoonShineKanBanBuilder\Support\KanbanSnapshot;
use DissNik\MoonShineKanBanBuilder\Support\NullKanbanTransport;
use MoonShine\AssetManager\Css;
use MoonShine\AssetManager\Js;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Components\Components;
use MoonShine\UI\Components\IterableComponent;

use function call_user_func;
use function is_array;
use function is_null;

final class KanBanBuilder extends IterableComponent
{
    protected string $view = 'moonshine-kanban-builder::components.kanban';

    protected array $translates = [
        'notfound' => 'moonshine::ui.notfound',
        'emptyColumn' => 'moonshine-kanban-builder::ui.empty_column',
        'reorderColumn' => 'moonshine-kanban-builder::ui.reorder_column',
    ];

    /**
     * @var array<string, mixed>
     */
    protected array $snapshot = [
        'changed' => true,
        'columns' => [],
    ];

    protected string $snapshotUrl = '';

    protected string $reorderUrl = '';

    protected string $columnReorderUrl = '';

    /**
     * @var list<string>
     */
    protected array $refreshEvents = [];

    protected string $transportMode;

    protected int $pollInterval;

    protected string $cardClickEvent = '';

    protected ?Closure $topLeft = null;

    protected ?Closure $topRight = null;

    protected ?Closure $afterColumns = null;

    public function __construct()
    {
        parent::__construct();

        $this->transportMode = KanbanConfig::transportMode();
        $this->pollInterval = KanbanConfig::pollingInterval();

        $this->withAttributes([]);
    }

    protected function assets(): array
    {
        return [
            Js::make('vendor/moonshine-kanban-builder/js/script.js?v='.$this->assetVersion('js/script.js')),
            Css::make('vendor/moonshine-kanban-builder/css/stylesheet.css?v='.$this->assetVersion('css/stylesheet.css')),
        ];
    }

    private function assetVersion(string $relativePath): string
    {
        $path = dirname(__DIR__, 2).'/public/'.$relativePath;
        $hash = is_file($path) ? hash_file('sha256', $path) : false;

        return is_string($hash) ? substr($hash, 0, 12) : '2';
    }

    public function snapshot(KanbanSnapshot|array $snapshot): static
    {
        $this->snapshot = $snapshot instanceof KanbanSnapshot
            ? $snapshot->toArray()
            : KanbanSnapshot::fromArray($snapshot)->toArray();

        return $this;
    }

    public function snapshotUrl(string $url): static
    {
        $this->snapshotUrl = $url;

        return $this;
    }

    public function reorderUrl(string $url): static
    {
        $this->reorderUrl = $url;

        return $this;
    }

    public function columnReorderUrl(string $url): static
    {
        $this->columnReorderUrl = $url;

        return $this;
    }

    /**
     * @param string|list<string> $events
     */
    public function refreshEvents(string|array $events): static
    {
        $this->refreshEvents = collect(is_array($events) ? $events : [$events])
            ->filter(static fn (mixed $event): bool => is_string($event) && $event !== '')
            ->values()
            ->all();

        return $this;
    }

    public function transportMode(string $mode): static
    {
        $this->transportMode = KanbanConfig::normalizeTransportMode($mode);

        return $this;
    }

    public function pollInterval(int $milliseconds): static
    {
        $this->pollInterval = $milliseconds > 0
            ? $milliseconds
            : KanbanConfig::pollingInterval();

        return $this;
    }

    public function cardClickEvent(string $event): static
    {
        $this->cardClickEvent = $event;

        return $this;
    }

    /**
     * @param Closure(self): list<ComponentContract> $callback
     */
    public function topLeft(Closure $callback): static
    {
        $this->topLeft = $callback;

        return $this;
    }

    /**
     * @param Closure(self): list<ComponentContract> $callback
     */
    public function topRight(Closure $callback): static
    {
        $this->topRight = $callback;

        return $this;
    }

    /**
     * @param Closure(self): list<ComponentContract> $callback
     */
    public function afterColumns(Closure $callback): static
    {
        $this->afterColumns = $callback;

        return $this;
    }

    private function getTopLeft(): Components
    {
        $components = is_null($this->topLeft) ? [] : call_user_func($this->topLeft, $this);

        return Components::make($components);
    }

    private function getTopRight(): Components
    {
        $components = is_null($this->topRight) ? [] : call_user_func($this->topRight, $this);

        return Components::make($components);
    }

    private function getAfterColumns(): Components
    {
        $components = is_null($this->afterColumns) ? [] : call_user_func($this->afterColumns, $this);

        return Components::make($components);
    }

    /**
     * @return array<string, mixed>
     */
    private function boardConfig(): array
    {
        $transport = app()->bound(KanbanTransportContract::class)
            ? app(KanbanTransportContract::class)->clientConfig()
            : (new NullKanbanTransport)->clientConfig();

        $transport['mode'] = $this->transportMode;
        $transport['polling'] = array_replace((array) ($transport['polling'] ?? []), [
            'interval' => $this->pollInterval,
        ]);

        return [
            'initialSnapshot' => $this->snapshot,
            'snapshotUrl' => $this->snapshotUrl,
            'reorderUrl' => $this->reorderUrl,
            'columnReorderUrl' => $this->columnReorderUrl,
            'refreshEvents' => $this->refreshEvents,
            'transport' => $transport,
            'cardClickEvent' => $this->cardClickEvent,
            'reorderRefreshCooldownMs' => KanbanConfig::reorderRefreshCooldown(),
            'reorderRequest' => KanbanReorderPayload::clientConfig(),
            'columnReorderRequest' => KanbanColumnOrderPayload::clientConfig(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        return [
            'board' => $this->boardConfig(),
            'name' => $this->getName(),
            'topLeft' => $this->getTopLeft(),
            'topRight' => $this->getTopRight(),
            'afterColumns' => $this->getAfterColumns(),
            'translates' => $this->getTranslates(),
        ];
    }
}
