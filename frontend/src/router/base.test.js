import { describe, expect, it } from 'vitest'

import { ROUTER_BASE } from './base.js'

describe('public router base', () => {
  it('keeps application routes independent from the asset base', () => {
    expect(ROUTER_BASE).toBe('/')
    expect(new URL('login', `https://komibook.id.vn${ROUTER_BASE}`).pathname).toBe('/login')
  })
})
