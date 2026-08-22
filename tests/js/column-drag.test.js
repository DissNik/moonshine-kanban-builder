import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'

import {
    isTopLevelColumnMove,
    sortablePositionChanged,
} from '../../resources/js/column-drag.js'

const script = readFileSync(new URL('../../resources/js/script.js', import.meta.url), 'utf8')
const stylesheet = readFileSync(new URL('../../resources/css/stylesheet.css', import.meta.url), 'utf8')

test('accepts a column move only inside the top-level columns strip', () => {
    const root = {}
    const dragged = { parentElement: root }

    assert.equal(isTopLevelColumnMove({ root, dragged, from: root, to: root }), true)
})

test('rejects nested column and card-list destinations', () => {
    const root = {}
    const dragged = { parentElement: root }
    const nestedColumn = { parentElement: root }
    const cardList = { parentElement: nestedColumn }

    assert.equal(isTopLevelColumnMove({ root, dragged, from: root, to: nestedColumn }), false)
    assert.equal(isTopLevelColumnMove({ root, dragged, from: root, to: cardList }), false)
    assert.equal(isTopLevelColumnMove({ root, dragged, from: cardList, to: root }), false)
})

test('detects only real sortable position changes', () => {
    const root = {}

    assert.equal(sortablePositionChanged({
        from: root,
        to: root,
        oldDraggableIndex: 1,
        newDraggableIndex: 1,
    }), false)
    assert.equal(sortablePositionChanged({
        from: root,
        to: root,
        oldDraggableIndex: 1,
        newDraggableIndex: 2,
    }), true)
})

test('column Sortable uses the top-level policy and placeholder lifecycle', () => {
    assert.match(script, /columnDragging: false/)
    assert.match(script, /!this\.columnDragging/)
    assert.match(script, /this\.dragging[\s\S]*\|\| this\.columnDragging[\s\S]*\|\| this\.reorderInFlight/)
    assert.match(script, /isTopLevelColumnMove\(\{/)
    assert.match(script, /syncColumnFallbackClone\(event\.clone\)/)
    assert.match(script, /clone\.setAttribute\('x-ignore', ''\)/)
    assert.match(script, /onChoose:[\s\S]*event\.item\.setAttribute\('x-ignore', ''\)/)
    assert.match(script, /onUnchoose:[\s\S]*event\.item\.removeAttribute\('x-ignore'\)/)
    assert.match(script, /event\.item\.classList\.add\('kanban-column-lift'\)/)
    assert.match(script, /this\.columnDragging = true/)
    assert.match(script, /event\.item\.classList\.remove\('kanban-column-lift'\)/)
    assert.match(script, /this\.clearColumnDrag\(\)/)
    assert.match(script, /sortablePositionChanged\(event\)/)
})

test('column Sortable drags by the header without capturing its actions', () => {
    assert.match(script, /handle: '\.kanban-column-header'/)
    assert.match(script, /filter: '[^']*\.kanban-column-actions[^']*button[^']*a[^']*'/)
    assert.match(script, /preventOnFilter: false/)
    assert.doesNotMatch(script, /handle: '\.kanban-column-handle'/)
})

test('column placeholder hides its contents while the floating clone stays visible', () => {
    assert.match(stylesheet, /\.kanban-column-lift,[\s\S]*\.kanban-column-ghost\s*\{/)
    assert.match(stylesheet, /\.kanban-column-lift\s*>\s*\*,[\s\S]*\.kanban-column-ghost\s*>\s*\*\s*\{[\s\S]*opacity:\s*0/)
    assert.match(stylesheet, /\.kanban-column-fallback\s*>\s*\*\s*\{[\s\S]*opacity:\s*1\s*!important/)
})

test('snapshot refresh rechecks active drag state after the response resolves', () => {
    assert.match(script, /isRefreshBusy\(\)\s*\{[\s\S]*this\.columnDragging/)
    assert.equal(script.match(/this\.isRefreshBusy\(\)/g)?.length, 2)
    assert.match(script, /const payload = response\.data \|\| \{\}[\s\S]*if \(this\.isRefreshBusy\(\) && !allowDuringDrag\)/)
})
