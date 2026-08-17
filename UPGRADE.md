# Upgrade from 1.x to 2.x

External applications may remain pinned to 1.x. Upgrade intentionally when the host is ready to accept the expanded snapshot contract.

## Snapshot changes

Every serialized column now contains:

```json
{
  "id": "new",
  "label": "New",
  "items": [],
  "locked": false,
  "header_html": ""
}
```

Old input snapshots without the two new keys remain readable and normalize to `false` and an empty string.

## Enable column sorting

1. Put a host-owned optimistic-lock value in `snapshot.meta.column_order_version`.
2. Pass a persistence endpoint through `columnReorderUrl()`.
3. Accept the strict request:

```json
{"ordered_column_ids":["stage-b","stage-a"],"version":"7"}
```

4. Return the next lock as `{"version":"8"}`.
5. Mark immutable columns with `locked: true`.

Without `columnReorderUrl()`, 2.x preserves existing card-only behavior and does not initialize column sorting.

## Header and trailing actions

Use `headerHtml` for trusted host-rendered header controls and `afterColumns()` for MoonShine components placed after the last column. Both are raw host extension points: escape database/user values before rendering and never pass untrusted HTML directly.

## Assets

Rebuild and republish package assets after upgrading:

```bash
npm run build
php artisan vendor:publish --tag=moonshine-kanban-builder-assets --force
```
