# MoonShine Kanban Builder

`dissnik/moonshine-kanban-builder` 2.x — управляемый snapshot компонент kanban для MoonShine с независимой сортировкой карточек и колонок.

Пакет предоставляет единый публичный путь интеграции:

- host-приложение формирует snapshot колонок и элементов;
- пакет отрисовывает snapshot и управляет drag-and-drop runtime;
- обновление, сохранение сортировки и открытие карточек остаются ответственностью приложения-потребителя.

Устаревшие серверные колонки builder, field mapper и классическое использование в стиле `CardsBuilder` удалены.

<picture>
    <img alt="Kanban Builder" src="./art/screenshot.png">
</picture>

## Установка

Требуется PHP `^8.2`, MoonShine `^4.0` и Laravel/Illuminate `^11.0|^12.0`.

```bash
composer config repositories.moonshine-kanban-builder vcs https://github.com/DissNik/moonshine-kanban-builder.git
composer require dissnik/moonshine-kanban-builder:^2.0
php artisan vendor:publish --tag=moonshine-kanban-builder-assets
```

Опциональная публикация конфигурации:

```bash
php artisan vendor:publish --tag=moonshine-kanban-builder-config
```

## Использование

```php
use DissNik\MoonShineKanBanBuilder\Components\KanBanBuilder;

KanBanBuilder::make()
    ->name('lead-list')
    ->reorderUrl($this->getResource()->getAsyncMethodUrl('reorder'))
    ->snapshot($this->buildSnapshotPayload(...))
    ->snapshotUrl($this->getSnapshotUrl())
    ->refreshEvents('lead-kanban:refresh')
    ->cardClickEvent('lead-kanban:card-open');
```

Управление колонками включается явно. Host-приложение может добавлять перемещаемые и заблокированные колонки, доверенные действия в заголовке и действие после последней колонки:

```php
use DissNik\MoonShineKanBanBuilder\Support\KanbanColumn;
use MoonShine\UI\Components\ActionButton;

$columns = [
    new KanbanColumn(
        id: 'new',
        label: 'New',
        items: [],
        headerHtml: view('kanban.column-actions')->render(),
    ),
    new KanbanColumn(
        id: 'basket',
        label: 'Basket',
        items: [],
        locked: true,
    ),
];

KanBanBuilder::make()
    ->snapshot(['columns' => $columns, 'meta' => ['column_order_version' => 7]])
    ->columnReorderUrl('/kanban/columns/reorder')
    ->afterColumns(fn (): array => [
        ActionButton::make('Add column', '#add-column'),
    ]);
```

Приложение-потребитель отвечает за:

- формирование snapshot;
- endpoint получения snapshot;
- сохранение drag-and-drop сортировки;
- выбор browser events, принудительно обновляющих доску;
- обработку открытия карточки в host-приложении.

## Контракт snapshot

`snapshot()` и `snapshotUrl()` должны использовать одну форму payload.

Минимальный пример:

```json
{
  "changed": true,
  "version": "hash",
  "columns": [
    {
      "id": "new",
      "label": "New",
      "locked": false,
      "header_html": "<button type=\"button\">...</button>",
      "items": [
        {
          "id": "lead-1",
          "html": "<div>...</div>",
          "title": "Lead",
          "form_url": "/resource/1",
          "open_url": "/resource#lead-1"
        }
      ]
    }
  ]
}
```

Правила:

- `columns[].id` — стабильный ID колонки для JS runtime и протокола сортировки.
- `columns[].locked` исключает колонку из сортировки. Заблокированные колонки отрисовываются после перемещаемых.
- `columns[].header_html` — опциональная область доверенных действий, отрисованная host-приложением.
- `items[].id` — стабильный ID карточки для DOM diff и сохранения сортировки.
- `items[].html` — отрисованная потребителем разметка карточки, вставляемая в доску без изменений.
- дополнительные ключи элемента сохраняются и возвращаются в `cardClickEvent()` как `event.detail.card`.
- при отсутствии изменений разрешён ответ `changed: false` с пустым массивом `columns`.

Пакет предоставляет value objects этого контракта:

- `DissNik\MoonShineKanBanBuilder\Support\KanbanSnapshot`
- `DissNik\MoonShineKanBanBuilder\Support\KanbanColumn`
- `DissNik\MoonShineKanBanBuilder\Support\KanbanItem`

## Протокол сортировки карточек

Drag-and-drop сортировка сохраняется строгим JSON payload:

```json
{
  "item_id": "lead-1",
  "column_id": "qualification",
  "previous_column_id": "new",
  "ordered_ids": ["lead-1", "lead-2"]
}
```

Клиентские имена полей также доступны через `KanbanReorderPayload::clientConfig()`.

На сервере разбирайте ту же JSON-структуру через value object пакета:

```php
use DissNik\MoonShineKanBanBuilder\Support\KanbanReorderPayload;

$payload = KanbanReorderPayload::fromArray($request->all());

$payload->itemId;
$payload->targetColumnId;
$payload->previousColumnId;
$payload->orderedIds;
```

`KanbanReorderPayload::fromArray()` выбрасывает `InvalidArgumentException`, если отсутствует обязательное поле или `ordered_ids` не является непустым списком ID карточек.

### Протокол сортировки колонок

Если настроен `columnReorderUrl()`, перемещение колонки отправляет только ID перемещаемых колонок и optimistic-lock версию host-приложения:

```json
{
  "ordered_column_ids": ["qualification", "new"],
  "version": "7"
}
```

Верните следующую версию как `{"version":"8"}`. Пакет сохранит её в `snapshot.meta.column_order_version` для следующего запроса. Разбирайте или генерируйте имена полей через `KanbanColumnOrderPayload`.

`KanbanColumnOrderPayload` выполняет только структурный разбор. Endpoint host-приложения обязан аутентифицировать пользователя и авторизовать текущую доску, требовать точного совпадения ID с полным набором её перемещаемых колонок, отклонять чужие и заблокированные колонки, а также атомарно сравнивать и обновлять optimistic-lock версию вместе с сортировкой.

При ошибке сохранения браузер восстанавливает прежний порядок колонок и запрашивает актуальный snapshot. Сортировка колонок не инициализируется без `columnReorderUrl()`.

### Граница доверенной разметки

`items[].html`, `columns[].header_html` и `afterColumns()` являются доверенными extension points, отрисованными host-приложением. Пакет не очищает этот HTML. Никогда не передавайте пользовательские строки напрямую: экранируйте значения в серверных представлениях или создавайте элементы управления через компоненты MoonShine.

## Transport и конфигурация

Настройки пакета находятся в `config/kanban-builder.php` и публикуются в `config/moonshine-kanban-builder.php`:

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

### Режимы transport

- `manual`: доска обновляется только по browser events.
- `polling`: доска также обновляется через настроенный интервал polling.
- `websocket`: зарезервированная transport boundary; для realtime-доставки host-приложение должно предоставить адаптер.

### Структурная transport boundary

Пакет разрешает transport через:

- `DissNik\MoonShineKanBanBuilder\Contracts\KanbanTransportContract`
- `DissNik\MoonShineKanBanBuilder\Support\KanbanTransport`
- `DissNik\MoonShineKanBanBuilder\Support\NullKanbanTransport`

Для отдельной доски доступны переопределения:

```php
KanBanBuilder::make()
    ->transportMode('polling')
    ->pollInterval(7000);
```

## Breaking changes

Рефакторинг намеренно удаляет прежний API пакета:

- `groups()` удалён.
- `reorderRoute()` заменён на `reorderUrl()`.
- `initialSnapshot()` заменён на `snapshot()`.
- Удалены устаревшие серверные колонки карточек и API field mapper.
- Колонки snapshot используют `id` вместо `status`.
- Элементы snapshot используют `html` вместо `card_html`.
- Сортировка отправляет JSON `ordered_ids` вместо CSV `data`, а также `column_id` вместо `parent`.
- Snapshot колонок 2.x всегда сериализует `locked` и `header_html`.
- Сортировка колонок использует отдельный endpoint и `meta.column_order_version`, не переиспользуя версию snapshot карточек.

Полный checklist перехода с 1.x на 2.x находится в [UPGRADE.md](UPGRADE.md).

## Публикация ассетов

Сопровождающий пакета пересобирает production-ассеты командой:

```bash
npm run build
```

После обновления Composer приложения-потребителя повторно опубликуйте уже собранные ассеты пакета:

```bash
php artisan vendor:publish --tag=moonshine-kanban-builder-assets --force
```

Если приложение продолжает использовать старое browser behavior, сначала проверьте опубликованные ассеты на устаревшую копию.

## Тестирование

Проверки пакета:

```bash
vendor/bin/phpunit -c phpunit.xml.dist
npm run test:js
```
