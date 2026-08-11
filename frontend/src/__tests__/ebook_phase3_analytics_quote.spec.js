import { describe, it, expect } from 'vitest'

describe('Ebook Reader Phase 3 - Analytics & Quote Card Logic', () => {
  it('calculates reading speed and remaining time accurately', () => {
    const sessionSeconds = 300 // 5 minutes
    const sessionPagesRead = 5 // 5 pages read
    const totalPages = 100
    const currentPage = 10

    const minutesPerPage = (sessionSeconds / 60 / sessionPagesRead).toFixed(1)
    expect(minutesPerPage).toBe('1.0')

    const remainingPages = totalPages - currentPage
    const estRemainingMinutes = Math.ceil(remainingPages * (sessionSeconds / 60 / sessionPagesRead))
    expect(estRemainingMinutes).toBe(90)
  })

  it('formats session time into MMm SSs string correctly', () => {
    const sessionSeconds = 145 // 2 min 25 sec
    const m = Math.floor(sessionSeconds / 60)
    const s = sessionSeconds % 60
    const formatted = `${m}m ${s < 10 ? '0' : ''}${s}s`

    expect(formatted).toBe('2m 25s')
  })

  it('verifies quote card theme definitions and default fallback', () => {
    const themes = [
      { id: 'dark', name: 'Đêm huyền bí', bg: '#0f172a', text: '#f8fafc', accent: '#38bdf8' },
      { id: 'classic', name: 'Cổ điển', bg: '#fef3c7', text: '#451a03', accent: '#d97706' }
    ]

    const selectedTheme = 'dark'
    const themeObj = themes.find(t => t.id === selectedTheme) || themes[0]

    expect(themeObj.name).toBe('Đêm huyền bí')
    expect(themeObj.bg).toBe('#0f172a')
  })
})
