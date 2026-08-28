# Переход с 1.x на 2.x

Внешние VCS-приложения могут оставаться на `1.x-dev`. Выполняйте переход намеренно, когда host-приложение готово принять расширенный контракт snapshot.

## Обязательная миграция API

Версия 2 удаляет устаревший API серверной отрисовки builder. Обновите каждую интеграцию до изменения Composer constraint:

| 1.x | 2.x |
| --- | --- |
| `groups()` и field mapper | Сформированные host-приложением колонки `snapshot()` и HTML элементов |
| `reorderRoute()` | `reorderUrl()` |
| `initialSnapshot()` | `snapshot()` |
| Ключ колонки `status` | Ключ колонки `id` |
| Ключ элемента `card_html` | Ключ элемента `html` |
| CSV-поля сортировки `data` и `parent` | JSON-поля `ordered_ids` и `column_id` |

Запрос сортировки 2.x также содержит `item_id` и `previous_column_id`. Разбирайте его через `KanbanReorderPayload::fromArray()`, а сохранение, авторизацию и проверку принадлежности доске оставляйте в host-приложении.

## Изменения snapshot

Каждая сериализованная колонка теперь содержит:

```json
{
  "id": "new",
  "label": "New",
  "items": [],
  "locked": false,
  "header_html": ""
}
```

Snapshot, уже использующие формат 2.x с `id`/`html`, могут не содержать только два новых ключа: они нормализуются в `false` и пустую строку.

## Включение сортировки колонок

1. Поместите принадлежащее host-приложению значение optimistic lock в `snapshot.meta.column_order_version`.
2. Передайте endpoint сохранения через `columnReorderUrl()`.
3. Примите строгий запрос:

```json
{"ordered_column_ids":["stage-b","stage-a"],"version":"7"}
```

4. Верните следующую версию блокировки как `{"version":"8"}`.
5. Отметьте неизменяемые колонки через `locked: true`.

`KanbanColumnOrderPayload` проверяет только структуру запроса. Endpoint сохранения обязан аутентифицировать пользователя и авторизовать доступ к текущей доске, проверить точное совпадение переданных ID с полным набором её перемещаемых колонок, отклонить чужие и заблокированные колонки, а также атомарно сравнить и обновить optimistic-lock версию вместе с сортировкой.

Без `columnReorderUrl()` версия 2.x сохраняет существующее поведение только для карточек и не инициализирует сортировку колонок.

## Действия в заголовке и после колонок

Используйте `headerHtml` для доверенных элементов управления в заголовке, отрисованных host-приложением, а `afterColumns()` — для компонентов MoonShine после последней колонки. Оба являются raw extension points: экранируйте значения из базы данных и пользовательский ввод перед отрисовкой и никогда не передавайте недоверенный HTML напрямую.

## Установка релиза 2.x

Пакет распространяется напрямую из VCS-репозитория GitHub. Измените Composer constraint и повторно опубликуйте собранные ассеты:

```bash
composer config repositories.moonshine-kanban-builder vcs https://github.com/DissNik/moonshine-kanban-builder.git
composer require dissnik/moonshine-kanban-builder:^2.0 --with-all-dependencies
php artisan vendor:publish --tag=moonshine-kanban-builder-assets --force
```

`npm run build` является командой сопровождающего пакета и не требуется в приложении-потребителе.
