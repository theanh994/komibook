import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath, URL } from 'node:url'

const readSource = (relativePath) => readFileSync(
  fileURLToPath(new URL(relativePath, import.meta.url)),
  'utf8'
)

describe('Phase 3 Facebook authentication replacement', () => {
  it.each([
    ['LoginView', '../views/auth/LoginView.vue'],
    ['RegisterView', '../views/auth/RegisterView.vue'],
  ])('%s exposes Facebook instead of phone OTP as the second social action', (_name, sourcePath) => {
    const source = readSource(sourcePath)
    const publicForm = source.split('<!-- Social Account Complete Registration Dialog -->')[0]

    expect(source).toContain('@click="openFacebookLogin"')
    expect(source).toContain('Facebook')
    expect(source).not.toContain('@click="openPhoneLogin"')
    expect(publicForm).not.toMatch(/Số điện thoại|SĐT|type="tel"|v-model="form\.phone"/)
  })

  it('posts the Facebook access token through the auth store', () => {
    const storeSource = readSource('../stores/auth.js')
    const sdkSource = readSource('../services/facebookAuth.js')

    expect(storeSource).toContain("apiClient.post('/api/auth/facebook-login'")
    expect(sdkSource).toContain("scope: 'public_profile,email'")
    expect(sdkSource).toContain('authResponse?.accessToken')
  })
})
