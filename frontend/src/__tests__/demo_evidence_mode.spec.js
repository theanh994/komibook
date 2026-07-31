import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath, URL } from 'node:url'

const source = (path) => readFileSync(fileURLToPath(new URL(`../${path}`, import.meta.url)), 'utf8')

describe('demo evidence mode', () => {
  it('lets axios choose the multipart boundary for FormData', () => {
    const axiosSource = source('services/axios.js')
    expect(axiosSource).toContain("config.headers.delete?.('Content-Type')")
  })

  it('labels simulated organizations and never asks for invented banking data', () => {
    const organization = source('views/vendor/OrganizationPartnersView.vue')
    const registration = source('views/auth/VendorRegisterView.vue')
    const publicProfile = source('views/OrganizationView.vue')

    expect(organization).toContain('Dữ liệu mô phỏng – không có giá trị pháp lý.')
    expect(organization).toContain("payload.append('_method', 'PATCH')")
    expect(registration).toContain(':disabled="isDemo"')
    expect(registration).toContain('chức năng rút tiền thật bị khóa')
    expect(publicProfile).toContain('Dữ liệu mô phỏng phục vụ trình diễn hệ thống.')
  })
})
