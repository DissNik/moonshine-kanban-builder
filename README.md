# MoonShine Kanban Builder

`dissnik/moonshine-kanban-builder` is a MoonShine component for rendering kanban-style boards on top of the standard card/list workflow.

It supports two usage modes:

- classic server-rendered kanban columns with drag-and-drop reorder;
- sync-capable boards with consumer-owned snapshot payloads, browser refresh events, polling, and a future-ready transport boundary for websocket integration.

<picture>
    <img alt="Kanban Builder" src="./art/screenshot.png">
</picture>

## Installation

```bash
composer require dissnik/moonshine-kanban-builder
php artisan vendor:publish --tag=moonshine-kanban-builder-assets
```

Optional config publishing:

```bash
php artisan vendor:publish --tag=moonshine-kanban-builder-config
```

## Classic Usage

Use the component like a kanban-oriented `CardsBuilder` replacement when server-rendered columns are enough:

```php
use DissNik\MoonShineKanBanBuilder\Components\KanBanBuilder;
use Modules\LeadManagement\Enums\LeadStatus;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Components\Badge;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Thumbnails;

public function modifyListComponent(ComponentContract $component): ComponentContract
{
    $resource = $this->getResource();

    return KanBanBuilder::make()
        ->title('title')
        ->content(fn (Lead $lead) => Flex::make([
            Flex::make([
                $lead->created_at,
                Thumbnails::make(
                    $lead->responsible
                        ? $lead->responsible->map(fn ($user) => $user->avatar_url)->filter()->toArray()
                        : [],
                ),
            ])->class('w-full')->justifyAlign('between'),
            Flex::make([
                Badge::make((string) count($lead->comments))->icon('chat-bubble-left-right'),
            ])->class('w-full')->justifyAlign('start'),
        ]))
        ->fields($component->getFields())
        ->name($this->getListComponentName())
        ->url(fn (Lead $lead) => $resource->getFormPageUrl($lead->getKey()))
        ->async()
        ->cast($this->getResource()->getCaster())
        ->buttons($this->getButtons())
        ->items($component->getOriginalItems())
        ->groups(LeadStatus::assignableStatuses())
        ->reorderRoute($resource->getAsyncMethodUrl('reorder'));
}
```

## Sync-Capable Usage

Use sync-capable mode when the application owns the board snapshot payload and wants controlled refresh behavior:

```php
use DissNik\MoonShineKanBanBuilder\Components\KanBanBuilder;

KanBanBuilder::make()
    ->name('lead-list')
    ->groups(LeadStatus::assignableStatuses())
    ->reorderRoute($this->getResource()->getAsyncMethodUrl('reorder'))
    ->initialSnapshot($this->buildSnapshotPayload(...))
    ->snapshotUrl($this->getSnapshotUrl())
    ->refreshEvents('lead-kanban:refresh')
    ->cardClickEvent('lead-kanban:card-open');
```

The consumer remains responsible for:

- generating the snapshot payload;
- exposing the snapshot endpoint;
- deciding which browser events should force a refresh;
- handling card-open behavior in the host application.

## Snapshot Payload Contract

`initialSnapshot()` and `snapshotUrl()` must use the same payload shape.

Minimal example:

```json
{
  "changed": true,
  "version": "hash",
  "columns": [
    {
      "status": "new",
      "label": "New",
      "count": 1,
      "items": [
        {
          "id": "lead-1",
          "card_html": "<div>...</div>",
          "form_url": "/resource/1"
        }
      ]
    }
  ]
}
```

Notes:

- `card_html` is rendered by the consumer and injected into the board as-is.
- `version` is used to skip unnecessary refresh payload application.
- `changed: false` may be returned with an empty `columns` array when nothing changed.
- `cardClickEvent()` dispatches a browser event with `{ card }` in `event.detail`.

## Transport And Config

Package defaults live in `config/moonshine-kanban-builder.php`:

```php
return [
    'events' => [
        'reorder_refresh' => [
            'fragment_updated:crud-list',
        ],
    ],
    'transport' => [
        'mode' => 'manual',
        'polling' => [
            'interval' => 5000,
        ],
        'signals' => [
            'refresh' => 'kanban.refresh',
        ],
        'allowed_modes' => [
            'manual',
            'polling',
            'websocket',
        ],
        'adapter' => null,
    ],
    'ui' => [
        'reorder_refresh_cooldown_ms' => 600,
    ],
];
```

### Transport Modes

- `manual`: the board refreshes only from browser events.
- `polling`: the board also refreshes on the configured polling interval.
- `websocket`: reserved transport boundary; add a host adapter when realtime delivery is introduced.

### Structured Transport Boundary

The package now resolves transport in the same style as `moonshine-commentable`:

- `DissNik\MoonShineKanBanBuilder\Contracts\KanbanTransportContract`
- `DissNik\MoonShineKanBanBuilder\Support\KanbanTransport`
- `DissNik\MoonShineKanBanBuilder\Support\NullKanbanTransport`

The browser runtime receives a structured `transport` object instead of scattered magic values. Application-wide defaults should be set in config; fluent builder calls are additive per board instance.

Per-board overrides are still available:

```php
KanBanBuilder::make()
    ->transportMode('polling')
    ->pollInterval(7000);
```

`events.reorder_refresh` is the fallback event list used by the legacy reorderable runtime when explicit refresh events were not passed to that runtime.

## Asset Publishing

After rebuilding package assets, republish them to the consumer application:

```bash
npm run build
php artisan vendor:publish --tag=moonshine-kanban-builder-assets --force
```

If the app still uses old browser behavior after package changes, stale published assets are the first thing to check.

## Testing

The package now includes its own minimal PHPUnit harness:

```bash
vendor/bin/phpunit -c phpunit.xml.dist
```

Current package tests cover:

- config defaults and overrides;
- transport boundary resolution;
- null transport fallback;
- stable transport payload shape.

Consumer validation is still important for real application behavior:

```bash
cd franchise && php artisan test tests/Unit/KanbanPackageContractTest.php
cd franchise && composer check
```

## Current Limits

- websocket transport is a boundary only; realtime delivery is not enabled by default;
- lead-specific offcanvas, hash sync, and card-open behavior belong in the host application, not in this package;
- published assets must be refreshed explicitly in the consumer after package runtime changes.
