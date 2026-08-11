import { readFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const source = () => readFile(fileURLToPath(new URL('../views/BookDetailView.vue', import.meta.url)), 'utf8')

describe('Phase 7A book detail truth contracts', () => {
  it('separates loading, ready, 404, and retryable request failures', async () => {
    const detail = await source()

    expect(detail).toContain("fetchState === 'loading'")
    expect(detail).toContain("fetchState === 'ready' && book")
    expect(detail).toContain("fetchState === 'notFound'")
    expect(detail).toContain("fetchState === 'error'")
    expect(detail).toContain('@click="fetchBookDetail"')
    expect(detail).toContain("if (error.response?.status === 404)")
    expect(detail).toContain("fetchState.value = 'notFound'")
    expect(detail).toContain("fetchState.value = 'error'")
    expect(detail.indexOf('book.value = null')).toBeLessThan(detail.indexOf('apiClient.get(`/api/books/${route.params.slug}`)'))
    expect(detail).not.toContain('v-else-if="!book"')
  })

  it('does not invent product metadata or ratings', async () => {
    const detail = await source()

    for (const forbidden of ['Bìa mềm', '13x18', '350g', "'2026'", 'Tiếng Việt', 'Mọi lứa tuổi']) {
      expect(detail).not.toContain(forbidden)
    }
    expect(detail).toContain("value: book.value.commercial_parties?.supplier?.display_name || 'Chưa cập nhật'")
    expect(detail).toContain('commercial_parties?.supplier?.is_demo')
    expect(detail).toContain("average: '0.0'")
    expect(detail).toContain('recommendRate: 0')
    expect(detail).toContain('if (!book.value || !book.value.reviews || book.value.reviews.length === 0) return 0')
    expect(detail).toContain('return Math.round((ratings.reduce((sum, rating) => sum + rating, 0) / ratings.length) * 10) / 10')
    expect(detail).toContain('{{ averageRating }}')
    expect(detail).not.toContain('{{ averageRating }}.0')
    expect(detail).toContain('value: `${averageRating.value} (${book.value?.reviews?.length || 0})`')
    expect(detail).not.toContain('value: `${averageRating.value}.0 (${book.value?.reviews?.length || 0})`')
    expect(detail).toContain("} else if (book.value.cover_format) {")
    expect(detail).not.toContain("tags.push(book.value.cover_format ||")
  })

  it('uses local media normalization and local fallback initials without external avatar services', async () => {
    const detail = await source()

    expect(detail).not.toContain('ui-avatars.com')
    expect(detail).not.toContain('localhost')
    expect(detail).toContain("if (path.startsWith('/storage/')) return path")
    expect(detail).toContain('return `/storage/${path}`')
    expect(detail).toContain('const vendorAvatarBroken = ref(false)')
    expect(detail).toContain('const brokenReviewAvatarKeys = ref([])')
    expect(detail).toContain('@error="vendorAvatarBroken = true"')
    expect(detail).toContain('@error="markReviewAvatarBroken(review)"')
    expect(detail).toContain('const getInitials = (name) => {')
  })

  it('copies chapters before sorting and keeps focal controls keyboard and touch accessible', async () => {
    const detail = await source()

    expect(detail).toContain('const sortedChapters = computed(() => {')
    expect(detail).toContain('return [...(book.value?.chapters || [])].sort')
    expect(detail).toContain('v-for="chapter in sortedChapters"')
    expect(detail).not.toContain('book.chapters.sort(')
    expect(detail).toContain(':aria-pressed="activeImageIndex === idx"')
    expect(detail).toContain(':aria-label="`Chọn ảnh ${idx + 1} của ${book.title}`"')
    expect(detail).toContain('min-h-11 min-w-11')
    expect(detail).toContain('vendor-storefront')
    expect(detail).toContain('inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-primary')
    expect(detail).toContain('inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-outline-variant')
    expect(detail).toContain('inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-emerald-600/80')
  })
})
