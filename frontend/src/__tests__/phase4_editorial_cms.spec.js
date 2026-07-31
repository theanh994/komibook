import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const source = (path) => readFileSync(fileURLToPath(new URL(path, import.meta.url)), 'utf8')

describe('Phase 4C.1 editorial CMS contracts', () => {
  it('loads only the public articles endpoint and links to persisted slugs', () => {
    const blog = source('../views/BlogView.vue')
    expect(blog).toContain("apiClient.get('/api/articles',")
    expect(blog).toContain('/blog/${article.slug}')
    expect(blog).toContain('Chưa có bài viết phù hợp')
  })

  it('sanitizes article HTML again at the rendering boundary', () => {
    const detail = source('../views/ArticleView.vue')
    expect(detail).toContain('DOMPurify.sanitize')
    expect(detail).toContain('v-html="safeBody"')
  })

  it('keeps admin creation and lifecycle mutations behind admin endpoints', () => {
    const admin = source('../views/admin/ArticlesView.vue')
    const manager = source('../views/shared/NewsroomManagerView.vue')
    expect(admin).toContain('NewsroomManagerView')
    expect(manager).toContain("`/api/${props.role}/articles`")
    expect(manager).toContain('/transition')
    expect(manager).toContain('operation_key')
  })
})
