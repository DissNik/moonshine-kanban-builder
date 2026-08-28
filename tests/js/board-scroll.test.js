import assert from 'node:assert/strict'
import { existsSync, readFileSync } from 'node:fs'
import test from 'node:test'

const moduleUrl = new URL('../../resources/js/board-scroll.js', import.meta.url)
const script = readFileSync(new URL('../../resources/js/script.js', import.meta.url), 'utf8')

test('board scroll interaction module exists', () => {
    assert.equal(existsSync(moduleUrl), true)
})

test('vertical wheel movement advances an overflowing board horizontally', async () => {
    const { horizontalWheelScrollLeft } = await import(moduleUrl)

    assert.equal(typeof horizontalWheelScrollLeft, 'function')
    assert.equal(
        horizontalWheelScrollLeft({
            deltaX: 0,
            deltaY: 120,
            scrollLeft: 40,
            clientWidth: 300,
            scrollWidth: 900,
        }),
        160,
    )
})

test('native horizontal gestures and page scrolling at board edges stay untouched', async () => {
    const { horizontalWheelScrollLeft } = await import(moduleUrl)
    assert.equal(typeof horizontalWheelScrollLeft, 'function')
    const board = {
        scrollLeft: 600,
        clientWidth: 300,
        scrollWidth: 900,
    }

    assert.equal(horizontalWheelScrollLeft({ ...board, deltaX: 80, deltaY: 20 }), null)
    assert.equal(horizontalWheelScrollLeft({ ...board, deltaX: 0, deltaY: 120 }), null)
    assert.equal(horizontalWheelScrollLeft({ ...board, deltaX: 0, deltaY: -120 }), 480)
    assert.equal(horizontalWheelScrollLeft({ ...board, deltaX: 0, deltaY: 120, ctrlKey: true }), null)
})

test('nested column scrolling takes precedence while it can advance', async () => {
    const { verticalWheelScrollCanAdvance } = await import(moduleUrl)

    assert.equal(
        verticalWheelScrollCanAdvance({ deltaY: 120, scrollTop: 20, clientHeight: 300, scrollHeight: 900 }),
        true,
    )
    assert.equal(
        verticalWheelScrollCanAdvance({ deltaY: -120, scrollTop: 20, clientHeight: 300, scrollHeight: 900 }),
        true,
    )
    assert.equal(
        verticalWheelScrollCanAdvance({ deltaY: -120, scrollTop: 0, clientHeight: 300, scrollHeight: 900 }),
        false,
    )
    assert.equal(
        verticalWheelScrollCanAdvance({ deltaY: 120, scrollTop: 600, clientHeight: 300, scrollHeight: 900 }),
        false,
    )
    assert.match(script, /closest\('\.kanban-column-scroll'\)/)
    assert.match(script, /verticalWheelScrollCanAdvance\(/)
})
