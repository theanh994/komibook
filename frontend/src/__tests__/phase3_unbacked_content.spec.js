import { describe, expect, it } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'

import AppFooter from '../components/layout/AppFooter.vue'
import BlogView from '../views/BlogView.vue'

const RouterLinkStub = {
  props: ['to'],
  template: '<a :data-route="to"><slot /></a>'
}

async function render(Component) {
  const app = createSSRApp(Component)
  app.component('RouterLink', RouterLinkStub)
  return renderToString(app)
}

describe('Phase 3C.3 unbacked public content closure', () => {
  it('renders the backed editorial entry point without invented article cards', async () => {
    const html = await render(BlogView)

    expect(html).toContain('KomiBook Editorial')
    expect(html).toContain('Đang tải bài viết')
    expect(html).not.toContain('Nội dung biên tập chưa được mở')
    expect(html).not.toContain('<form')
  })

  it('renders a non-submitting newsletter state and no placeholder footer links', async () => {
    const html = await render(AppFooter)

    expect(html).toContain('Tính năng đăng ký nhận tin chưa khả dụng')
    expect(html).toContain('disabled')
    expect(html).toContain('data-route="/help-center"')
    expect(html).not.toContain('href="#"')
    expect(html).not.toContain('<form')
  })
})
