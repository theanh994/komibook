import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8')

describe('series orbit carousel contracts', () => {
  it('supports circular navigation, swipe, keyboard, and reduced motion', () => {
    const source = read('../components/SeriesOrbitCarousel.vue')

    expect(source).toContain('(activeIndex.value + 1) % props.books.length')
    expect(source).toContain('(activeIndex.value - 1 + props.books.length) % props.books.length')
    expect(source).toContain('@keydown.left.prevent="previous"')
    expect(source).toContain('@keydown.right.prevent="next"')
    expect(source).toContain('@pointerdown="startSwipe"')
    expect(source).toContain('swipeConsumed.value = true')
    expect(source).toContain('@media (prefers-reduced-motion: reduce)')
    expect(source).toContain('width: 48px')
  })

  it('renders every API item and marks the currently viewed book', () => {
    const component = read('../components/SeriesOrbitCarousel.vue')
    const detail = read('../views/BookDetailView.vue')

    expect(component).toContain('v-for="(seriesBook, index) in books"')
    expect(component).toContain("seriesBook.id === currentBookId")
    expect(component).toContain('Đang xem')
    expect(detail).toContain(':books="seriesBooks"')
    expect(detail).toContain(':current-book-id="book.id"')
  })
})
