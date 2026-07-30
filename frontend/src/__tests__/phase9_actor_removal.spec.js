import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'
import process from 'node:process'

const source = relativePath => fs.readFileSync(path.resolve(process.cwd(), relativePath), 'utf8')

describe('Phase 9 retired actor removal', () => {
  it('removes actor routes, registration choices and management capabilities', () => {
    const router = source('src/router/index.js')
    const guard = source('src/router/guard.js')
    const store = source('src/stores/auth.js')
    const register = source('src/views/auth/RegisterView.vue')
    const login = source('src/views/auth/LoginView.vue')
    const layout = source('src/layouts/AdminLayout.vue')

    expect(router).not.toContain("path: '/author")
    expect(router).not.toContain('approved_author')
    expect(guard).not.toContain('legacyAuthorOnly')
    expect(store).not.toContain('isAuthor')
    expect(register).not.toContain('value="author"')
    expect(login).not.toContain('value="author"')
    expect(layout).not.toContain('/author/manage')
  })

  it('keeps the neutral used-book seller surface and private address workflow', () => {
    const router = source('src/router/index.js')
    const view = source('src/views/used-books/UsedBookManagerView.vue')

    expect(router).toContain("path: '/used-books/manage'")
    expect(view).toContain('/api/used-book-seller/fulfillment-address')
    expect(view).toContain('Địa chỉ chỉ dùng nội bộ')
    expect(view).toContain('addressReady')
  })
})
