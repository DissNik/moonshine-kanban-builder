import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'

test('package Prettier config preserves the established JavaScript style', () => {
    const config = JSON.parse(
        readFileSync(new URL('../../.prettierrc.json', import.meta.url), 'utf8'),
    )

    assert.equal(config.printWidth, 120)
    assert.equal(config.tabWidth, 4)
    assert.equal(config.singleQuote, true)
    assert.equal(config.semi, false)
})
