import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const source = (path) => readFileSync(fileURLToPath(new URL(path, import.meta.url)), 'utf8')

describe('Phase 4C.2 fee history UI contracts', () => {
  it('uses history and preview APIs with explicit effective time and reason', () => {
    const view = source('../views/admin/FeeSchedulesView.vue')
    expect(view).toContain('/api/admin/fee-schedules')
    expect(view).toContain('/preview')
    expect(view).toContain('effective_at')
    expect(view).toContain('reason')
    expect(view).toContain('operation_key')
  })

  it('removes mutable JSON financial inputs from general system configuration', () => {
    const view = source('../views/admin/SystemConfigView.vue')
    expect(view).not.toContain('commission_rate:')
    expect(view).not.toContain('service_fee:')
    expect(view).toContain('/admin/fee-schedules')
  })
})
