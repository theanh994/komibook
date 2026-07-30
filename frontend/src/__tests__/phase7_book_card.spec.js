import { describe, expect, it } from 'vitest'
import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import BookCard from '../components/BookCard.vue'

const RouterLink = {
  props: ['to'],
  setup(props, { slots }) {
    return () => h('a', { href: `/books/${props.to?.params?.slug || ''}` }, slots.default?.())
  },
}

const renderCard = async (book, props = {}) => {
  const app = createSSRApp({
    render: () => h(BookCard, { book, ...props }),
  })
  app.component('router-link', RouterLink)
  return renderToString(app)
}

describe('Phase 7 shared BookCard restoration', () => {
  it('prioritizes the complete cover, title, price and actions without category or author metadata', async () => {
    const html = await renderCard({
      id: 7,
      slug: 'ky-uc-mua-he',
      title: 'Ký ức mùa hè',
      author: 'Tác giả không được hiện',
      category: { name: 'Thể loại không được hiện' },
      cover_image: 'covers/ky-uc.jpg',
      type: 'ebook',
      price: 120000,
      sale_price: 90000,
      latest_ebook_version: { version: '2.1' },
    }, { showWishlist: true })

    expect(html).toContain('object-contain')
    expect(html).not.toContain('object-cover')
    expect(html).toContain('rounded-b-lg')
    expect(html).toContain('Ký ức mùa hè')
    expect(html).toContain('Phiên bản 2.1')
    expect(html).not.toContain('Tác giả không được hiện')
    expect(html).not.toContain('Thể loại không được hiện')
    expect(html).toContain('aria-label="Thêm Ký ức mùa hè vào yêu thích"')
    expect(html).toContain('aria-label="Xem nhanh Ký ức mùa hè"')
    expect(html).toContain('aria-label="Thêm Ký ức mùa hè vào giỏ"')
    expect(html).toContain('aria-label="Mua ngay Ký ức mùa hè"')
  })

  it('does not render purchase actions for an unavailable physical book', async () => {
    const html = await renderCard({
      id: 8,
      slug: 'sach-het-hang',
      title: 'Sách hết hàng',
      cover_image: null,
      type: 'physical',
      price: 50000,
      stock: 0,
      status: 'published',
    }, { showWishlist: true, isFavorite: true })

    expect(html).toContain('Sách đã hết hàng')
    expect(html).toContain('aria-pressed="true"')
    expect(html).not.toContain('aria-label="Mua ngay Sách hết hàng"')
    expect(html).not.toContain('aria-label="Thêm Sách hết hàng vào giỏ"')
  })

  it('renders an accessible, layout-stable placeholder when no cover is provided', async () => {
    const html = await renderCard({
      id: 9,
      slug: 'sach-chua-co-bia',
      title: 'Sách chưa có bìa',
      cover_image: null,
      type: 'physical',
      price: 50000,
      stock: 2,
      status: 'published',
    })

    expect(html).toContain('role="img"')
    expect(html).toContain('aria-label="Chưa có ảnh bìa cho Sách chưa có bìa"')
    expect(html).toContain('Chưa có ảnh bìa')
  })
})
