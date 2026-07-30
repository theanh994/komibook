import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'
import process from 'node:process'

const source = (relativePath) => fs.readFileSync(path.resolve(process.cwd(), relativePath), 'utf8')

describe('Phase 7E.2 Vendor operations parity', () => {
  it('explains fee semantics without inventing a fallback rate', () => {
    const finance = source('src/views/vendor/FinanceView.vue')

    expect(finance).toContain('Commission được trừ từ doanh thu gộp')
    expect(finance).toContain('feePolicy.example.seller_net')
    expect(finance).toContain('không tự suy đoán tỷ lệ')
  })

  it('shows truthful Flash Sale load failures and keeps the real registration form', () => {
    const flashSale = source('src/views/vendor/FlashSalesView.vue')

    expect(flashSale).toContain('Không có chiến dịch giả được hiển thị')
    expect(flashSale).toContain('/register`')
    expect(flashSale).toContain('Gửi đề xuất')
  })
})
