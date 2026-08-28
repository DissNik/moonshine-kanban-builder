# Changelog

## 2.0.0

### Added

- Independent drag-and-drop ordering for columns.
- Locked columns that stay after movable columns.
- Trusted host-rendered column header actions.
- An `afterColumns()` component slot.
- Strict `KanbanColumnOrderPayload` request parsing.
- Optimistic column-order versions and browser rollback after failed persistence.

### Changed

- `KanbanColumn` snapshots always contain `locked` and `header_html`.
- Column management is opt-in through `columnReorderUrl()` and remains independent from card reorder.
- The Vite build uses one JavaScript entry and preserves the canonical published asset paths.

### Compatibility

Version 2 is a breaking major. Existing external consumers may remain on the latest 1.x release until they intentionally migrate.
