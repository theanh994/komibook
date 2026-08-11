import { readFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const source = async (relativePath) => readFile(
  fileURLToPath(new URL(relativePath, import.meta.url)),
  'utf8',
)

describe('Phase 7A public shell accessibility', () => {
  it('keeps the route focus target available without suppressing its focus indicator', async () => {
    const app = await source('../App.vue')

    expect(app).toContain('href="#main-content"')
    expect(app).toContain('data-route-focus')
    expect(app).not.toMatch(/class="[^"]*(?:outline-none|focus:outline-none|focus:ring-0)[^"]*"\s*\n\s*data-route-focus/)
  })

  it('uses disclosure semantics, retryable category loading, and explicit focus restoration in the header', async () => {
    const header = await source('../components/layout/AppHeader.vue')

    expect(header).not.toContain("from 'primevue/menu'")
    expect(header).not.toContain('role="menu"')
    expect(header).toContain('aria-controls="user-account-disclosure"')
    expect(header).toContain('aria-controls="desktop-category-disclosure"')
    expect(header).toContain('aria-controls="mobile-category-menu"')
    expect(header).toContain('@click="fetchTopCategories"')
    expect(header).toContain("if (event.key !== 'Escape') return")
    expect(header).toContain('focusElement(userMenuTriggerRef)')
    expect(header).toContain('focusElement(categoryMenuTriggerRef)')
    expect(header).toContain('ref="mobileMenuTriggerRef"')
    expect(header).toContain('focusElement(mobileMenuTriggerRef)')
    expect(header).toContain('focusElement(mobileSearchRef)')
    expect(header).toContain('focus-visible:ring-primary-fixed-dim')
    expect(header).toContain('to="/register" class="inline-flex min-h-11')
    expect(header).toContain('to="/login" class="inline-flex min-h-11')
    expect(header).toContain('hidden lg:flex flex-col text-left')
    expect(header).toContain('flex min-h-11 w-full items-center gap-2.5 rounded-xl px-3 py-2.5')
    expect(header).toContain('to="/" aria-label="KomiBook"')
    expect(header).toContain('class="hidden sm:flex flex-col justify-center"')
    expect(header).toContain('>Thử lại</button>')
    expect(header).not.toContain('transition-all')
  })

  it('does not publish personal contact or social links and keeps newsletter unavailable', async () => {
    const footer = await source('../components/layout/AppFooter.vue')

    expect(footer).not.toContain('theanht057@gmail.com')
    expect(footer).not.toContain('facebook.com/tran.connhaho.99')
    expect(footer).toContain('disabled')
    expect(footer).toContain('aria-disabled="true"')
    expect(footer).toContain('min-height: 2.75rem')
    expect(footer).not.toContain('transition-all')
    expect(footer).toContain('var(--color-text-muted)')
    expect(footer).toContain('var(--color-primary)')
    expect(footer).not.toContain('--md-sys-color-')
  })

  it('keeps toast notifications within the mobile viewport', async () => {
    const app = await source('../App.vue')

    expect(app).toContain('position="top-right"')
    expect(app).toContain('class="app-toast !w-[calc(100vw-2rem)] sm:!w-96"')
  })
})
