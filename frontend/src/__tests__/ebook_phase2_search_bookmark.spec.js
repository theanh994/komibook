import { describe, it, expect } from 'vitest'

describe('Ebook Reader Phase 2 - Bookmarks & In-Book Search Logic', () => {
  const sampleAnnotations = [
    { id: 1, page_number: 3, type: 'note', note_content: 'Ghi chú chương 1' },
    { id: 2, page_number: 5, type: 'bookmark', note_content: 'Đã đánh dấu trang 5' },
    { id: 3, page_number: 10, type: 'bookmark', note_content: 'Đã đánh dấu trang 10' }
  ]

  it('filters bookmarks correctly from annotations list', () => {
    const bookmarks = sampleAnnotations.filter(a => a.type === 'bookmark')
    expect(bookmarks.length).toBe(2)
    expect(bookmarks.map(b => b.page_number)).toEqual([5, 10])
  })

  it('checks if current page is bookmarked', () => {
    const currentPage = 5
    const isBookmarked = sampleAnnotations.some(
      a => Number(a.page_number) === currentPage && a.type === 'bookmark'
    )
    expect(isBookmarked).toBe(true)

    const unbookmarkedPage = 8
    const isPage8Bookmarked = sampleAnnotations.some(
      a => Number(a.page_number) === unbookmarkedPage && a.type === 'bookmark'
    )
    expect(isPage8Bookmarked).toBe(false)
  })

  it('generates highlighted context snippet for text search matches', () => {
    const pageText = 'Vào năm 1945, nhân loại bắt đầu bước sang một kỷ nguyên phát triển kinh tế hoàn toàn mới với nhiều biến động sâu sắc.'
    const query = 'kỷ nguyên'
    
    const lowerText = pageText.toLowerCase()
    const matchIndex = lowerText.indexOf(query)
    expect(matchIndex).toBeGreaterThan(-1)

    const start = Math.max(0, matchIndex - 15)
    const end = Math.min(pageText.length, matchIndex + query.length + 15)
    let snippet = pageText.substring(start, end)
    if (start > 0) snippet = '...' + snippet
    if (end < pageText.length) snippet = snippet + '...'

    expect(snippet).toContain('kỷ nguyên')
    expect(snippet).toContain('bước sang')
  })
})
