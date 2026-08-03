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

    expect(source).toContain('<SocialLoginButtons')
    expect(source).toContain('@facebook-token="handleFacebookAccessToken"')
    expect(source).not.toContain('@click="openPhoneLogin"')
  })

  it('uses phone as registration profile data and verifies the email with an eight digit OTP', () => {
    const source = readSource('../views/auth/RegisterView.vue')
    const storeSource = readSource('../stores/auth.js')

    expect(source).toContain('v-model="form.phone"')
    expect(source).not.toContain('v-model="form.gender"')
    expect(source).not.toContain('v-model="form.birthday"')
    expect(source).toContain('<OtpCodeInput')
    expect(source).toContain('email_verification_token')
    expect(storeSource).toContain("apiClient.post('/api/auth/email/send-otp'")
    expect(storeSource).toContain("apiClient.post('/api/auth/email/verify-otp'")
  })

  it('posts the Facebook access token through the auth store', () => {
    const storeSource = readSource('../stores/auth.js')
    const sdkSource = readSource('../services/facebookAuth.js')
    const socialButtonsSource = readSource('../components/auth/SocialLoginButtons.vue')

    expect(storeSource).toContain("apiClient.post('/api/auth/facebook-login'")
    expect(sdkSource).toContain("scope: 'public_profile,email'")
    expect(sdkSource).toContain('authResponse?.accessToken')
    expect(socialButtonsSource).toContain("apiClient.get('/api/auth/social-login-config')")
    expect(socialButtonsSource).toContain('google.accounts.id.renderButton')
    expect(socialButtonsSource).toContain("shape: 'pill'")
    expect(socialButtonsSource).toContain('max-w-[400px]')
    expect(socialButtonsSource).not.toContain('VITE_GOOGLE_CLIENT_ID')
    expect(socialButtonsSource).not.toContain('FACEBOOK_APP_SECRET')
  })
})
