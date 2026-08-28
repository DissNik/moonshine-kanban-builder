import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'

const config = readFileSync(new URL('../../vite.config.js', import.meta.url), 'utf8')
const script = readFileSync(new URL('../../resources/js/script.js', import.meta.url), 'utf8')

test('build does not copy the output directory back into itself', () => {
    assert.match(config, /publicDir:\s*false/)
    assert.match(config, /emptyOutDir:\s*true/)
})

test('build uses one JavaScript entry that imports the stylesheet', () => {
    assert.match(config, /input:\s*['"]resources\/js\/script\.js['"]/)
    assert.match(script, /^import ['"]\.\.\/css\/stylesheet\.css['"]/m)
})
