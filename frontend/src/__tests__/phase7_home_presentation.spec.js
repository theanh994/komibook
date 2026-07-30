import { beforeEach, describe, expect, it, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'

const route = { fullPath: '/', meta: {} }
vi.mock('vue-router', () => ({
  useRoute: () => route,
  RouterView: { render: () => null },
}))

vi.mock('../components/layout/AppHeader.vue', () => ({ default: { render: () => null } }))
vi.mock('../components/layout/AppFooter.vue', () => ({ default: { render: () => null } }))
vi.mock('primevue/toast', () => ({ default: { render: () => null } }))
vi.mock('primevue/confirmdialog', () => ({ default: { render: () => null } }))

import App from '../App.vue'

function mountAndCapture() {
  let setupState = null
  const Wrapper = {
    setup(props, context) {
      setupState = App.setup(props, context)
      return () => null
    },
  }
  renderToString(createSSRApp(Wrapper))
  return setupState
}

describe('Phase 7 home presentation', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('uses a project bookshelf fallback and limits recommendations to five with responsive visibility rules', () => {
    const source = readFileSync(new URL('../views/HomeView.vue', import.meta.url), 'utf8')

    expect(source).toContain("import bookshelfHero from '@/assets/komibook-bookshelf-hero.webp'")
    expect(source).toContain('cover_image: bookshelfHero')
    expect(source).toContain('.slice(0, 5)')
    expect(source).toContain('grid-template-columns: repeat(2')
    expect(source).toContain('@media (min-width: 768px)')
    expect(source).toContain('grid-template-columns: repeat(4')
    expect(source).toContain('@media (min-width: 1024px)')
    expect(source).toContain('grid-template-columns: repeat(5')
  })

  it('shows the back-to-top state after scrolling and respects reduced motion', () => {
    const scrollTo = vi.fn()
    vi.stubGlobal('window', {
      scrollY: 700,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      matchMedia: vi.fn(() => ({ matches: true })),
      scrollTo,
      requestAnimationFrame: vi.fn((callback) => callback()),
    })
    vi.stubGlobal('document', { querySelector: vi.fn(() => null) })

    const state = mountAndCapture()
    state.updateBackToTop()
    state.scrollToTop()

    expect(state.showBackToTop.value).toBe(true)
    expect(scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'auto' })
  })
})
