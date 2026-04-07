# KanBanBuilder component for [MoonShine Laravel admin panel](https://moonshine-laravel.com)

**KanBanBuilder** is a specialized UI component for the MoonShine Laravel admin panel. It extends the functionality of the standard `CardsBuilder`, transforming a static grid of cards into a dynamic, organized **KanBan Board**.

<picture>
    <img alt="Queue Dashboard" src="./art/screenshot.png">
</picture>


## Usage

To modify the main IndexPage component,

```php

use DissNik\MoonShineKanBanBuilder\Components\KanBanBuilder;
use MoonShine\Contracts\UI\ComponentContract;

public function modifyListComponent(ComponentContract $component): ComponentContract
{
    $resource = $this->getResource();

    return KanBanBuilder::make()
        ->title('title')
        ->content(fn(Lead $lead) =>
            Flex::make([
                Flex::make([
                    $lead->created_at,

                    Thumbnails::make(
                        $lead->responsible
                            ? $lead->responsible->map(fn($user) => $user->avatar_url)->filter()->toArray()
                            : [],
                    ),
                ])
                    ->class('w-full')
                    ->justifyAlign('between'),

                Flex::make([
                    Badge::make((string) count($lead->comments))->icon('chat-bubble-left-right'),
                ])
                    ->class('w-full')
                    ->justifyAlign('start'),
            ])
        )
        ->fields($component->getFields())
        ->name($this->getListComponentName())
        ->url(fn(Lead $lead) => $resource->getFormPageUrl($lead->getKey()))
        ->async()
        ->cast($this->getResource()->getCaster())
        ->buttons($this->getButtons())
        ->items($component->getOriginalItems())
        ->groups(LeadStatus::assignableStatuses())
        ->reorderRoute($this->resource->getAsyncMethodUrl('reorder'));
}
```

---

## Sync-Capable Mode

Legacy server-rendered usage remains supported. For consumers that need in-place refresh, polling, or consumer-owned card open behavior, the builder also supports an additive sync-capable mode:

```php
use DissNik\MoonShineKanBanBuilder\Components\KanBanBuilder;

KanBanBuilder::make()
    ->name('lead-list')
    ->groups(LeadStatus::assignableStatuses())
    ->reorderRoute($this->getResource()->getAsyncMethodUrl('reorder'))
    ->initialSnapshot($this->buildSnapshotPayload(...))
    ->snapshotUrl($this->getResource()->getAsyncMethodUrl('snapshot'))
    ->refreshEvents('lead-kanban:refresh')
    ->cardClickEvent('lead-kanban:card-open');
```

Transport defaults now live in `config/moonshine-kanban-builder.php`:

```php
return [
    'events' => [
        'reorder_refresh' => [
            'fragment_updated:crud-list',
        ],
    ],
    'transport' => [
        'mode' => 'manual', // manual | polling | websocket
        'polling' => [
            'interval' => 5000,
        ],
        'signals' => [
            'refresh' => 'kanban.refresh',
        ],
        'adapter' => null, // host transport adapter class, null => NullKanbanTransport
    ],
];
```

The package now resolves a transport service in the same style as `moonshine-commentable`:

- `DissNik\MoonShineKanBanBuilder\Contracts\KanbanTransportContract`
- `DissNik\MoonShineKanBanBuilder\Support\NullKanbanTransport`
- `DissNik\MoonShineKanBanBuilder\Support\KanbanTransport`

Use config for application-wide transport defaults, and fluent calls only when a specific board instance must override them:

```php
KanBanBuilder::make()
    ->transportMode('polling')
    ->pollInterval(7000);
```

Current usage model:

- `manual`: board refreshes only from browser events
- `polling`: board also refreshes on configured polling interval
- `websocket`: reserved transport boundary; wire a host adapter class in config when realtime delivery is introduced
- `events.reorder_refresh`: fallback browser events for the legacy reorderable runtime

### Sync Payload Contract

`initialSnapshot()` and `snapshotUrl()` are expected to use the same payload shape:

```json
{
  "changed": true,
  "version": "hash",
  "columns": [
    {
      "status": "new",
      "label": "Новый",
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

- `card_html` is rendered by the consumer and injected as-is by the package runtime.
- `cardClickEvent()` keeps card-open behavior consumer-driven: the package dispatches a browser event with `{ card }` in `event.detail`.
- `refreshEvents()` configures browser events that should trigger a forced snapshot refresh.
- package config owns default transport behavior; `transportMode()` / `pollInterval()` remain additive per-board overrides.
- browser runtime now receives a structured `transport` object from the package instead of independent magic scalars.
- `transportMode('polling')` enables interval refreshes; `manual` keeps refresh event driven behavior only; `websocket` is still future-ready and requires a host adapter before it does anything useful.

## Validation Strategy

This package currently ships build tooling and published assets but does not yet include a standalone PHPUnit/Testbench harness. Until that is added, the expected verification path for sync-capable changes is:

1. rebuild package assets and confirm `public/js/script.js` and `public/manifest.json` are updated;
2. verify the first consumer application with targeted tests around snapshot/reorder behavior;
3. run the consumer application's full check pipeline before release.
