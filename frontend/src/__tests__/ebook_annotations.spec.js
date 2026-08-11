import { describe, it, expect } from 'vitest'

describe('Ebook Annotations Feature Logic', () => {
  const sampleAnnotations = [
    {
      id: 1,
      page_number: 5,
      highlighted_text: 'Cuộc sống là những chuỗi lựa chọn',
      note_content: 'Cần ghi nhớ câu này để áp dụng vào công việc.',
      color: '#eab308',
      type: 'note',
      created_at: '2026-08-01T10:00:00Z'
    },
    {
      id: 2,
      page_number: 12,
      highlighted_text: 'Đừng bao giờ từ bỏ ước mơ',
      note_content: null,
      color: '#10b981',
      type: 'highlight',
      created_at: '2026-08-02T14:30:00Z'
    },
    {
      id: 3,
      page_number: 25,
      highlighted_text: null,
      note_content: 'Suy ngẫm về chiến lược kinh doanh quý 3',
      color: '#f43f5e',
      type: 'note',
      created_at: '2026-08-03T09:15:00Z'
    }
  ]

  it('filters annotations by type (highlight vs note)', () => {
    const highlights = sampleAnnotations.filter(a => a.highlighted_text && !a.note_content)
    expect(highlights.length).toBe(1)
    expect(highlights[0].id).toBe(2)

    const notes = sampleAnnotations.filter(a => a.note_content)
    expect(notes.length).toBe(2)
  })

  it('filters annotations by search query keyword', () => {
    const query = 'kinh doanh'
    const results = sampleAnnotations.filter(a =>
      (a.note_content && a.note_content.toLowerCase().includes(query)) ||
      (a.highlighted_text && a.highlighted_text.toLowerCase().includes(query))
    )

    expect(results.length).toBe(1)
    expect(results[0].id).toBe(3)
  })

  it('formats Markdown export content correctly', () => {
    const title = 'Đắc Nhân Tâm'
    const author = 'Dale Carnegie'
    
    let md = `# Ghi chú tác phẩm: ${title}\n`
    md += `**Tác giả**: ${author}\n\n`
    
    sampleAnnotations.forEach((note, index) => {
      md += `### ${index + 1}. Trang ${note.page_number}\n`
      if (note.highlighted_text) md += `> "${note.highlighted_text}"\n\n`
      if (note.note_content) md += `**Suy tư**: ${note.note_content}\n\n`
    })

    expect(md).toContain('# Ghi chú tác phẩm: Đắc Nhân Tâm')
    expect(md).toContain('Trang 5')
    expect(md).toContain('Cuộc sống là những chuỗi lựa chọn')
    expect(md).toContain('Cần ghi nhớ câu này để áp dụng vào công việc.')
  })
})
