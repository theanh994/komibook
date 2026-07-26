import { describe, expect, it, vi } from 'vitest'
import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createPinia, setActivePinia } from 'pinia'

import OtpCodeInput from '../components/auth/OtpCodeInput.vue'
import AccountVerificationView from '../views/auth/AccountVerificationView.vue'
import { useAuthStore } from '../stores/auth'

const { routerPush, toastAdd } = vi.hoisted(() => ({
  routerPush: vi.fn(),
  toastAdd: vi.fn()
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: routerPush })
}))

vi.mock('primevue/usetoast', () => ({
  useToast: () => ({ add: toastAdd })
}))

async function createSetupState(modelValue = '') {
  const emit = vi.fn()
  let exposed = null
  const app = createSSRApp({
    setup() {
      OtpCodeInput.setup(
        { modelValue, length: 8, disabled: false },
        {
          emit,
          expose(value) {
            exposed = value
          }
        }
      )
      return () => null
    }
  })
  await renderToString(app)
  return { emit, exposed }
}

describe('Phase 3 eight-digit OTP input', () => {
  it('renders exactly eight accessible numeric input boxes', async () => {
    const app = createSSRApp({
      render: () => h(OtpCodeInput, { modelValue: '', length: 8 })
    })
    const html = await renderToString(app)

    expect((html.match(/aria-label="Chữ số OTP thứ/g) || []).length).toBe(8)
    expect((html.match(/inputmode="numeric"/g) || []).length).toBe(8)
    expect(html).toContain('autocomplete="one-time-code"')
  })

  it('accepts a pasted code, strips non-digits and emits only eight digits', async () => {
    const { emit, exposed } = await createSetupState()

    exposed.insertDigits(0, '12 34-567890')

    expect(emit).toHaveBeenCalledWith('update:modelValue', '12345678')
    expect(emit).toHaveBeenCalledWith('complete', '12345678')
  })

  it('removes the current digit on Backspace', async () => {
    const { emit, exposed } = await createSetupState('12345678')

    exposed.handleKeydown(7, {
      key: 'Backspace',
      preventDefault: vi.fn()
    })

    expect(emit).toHaveBeenCalledWith('update:modelValue', '1234567')
  })

  it('uses the real phone OTP store flow in account verification', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const authStore = useAuthStore()
    authStore.user = { id: 1, phone: '0989999999' }
    const sendSpy = vi.spyOn(authStore, 'sendPhoneOtp').mockResolvedValue({ status: 'success' })
    const verifySpy = vi.spyOn(authStore, 'verifyPhoneOtp').mockResolvedValue({ status: 'success' })
    let setupState = null

    const app = createSSRApp({
      setup(props, context) {
        setupState = AccountVerificationView.setup(props, context)
        return () => null
      }
    })
    app.use(pinia)
    await renderToString(app)

    await setupState.sendOtp()
    expect(sendSpy).toHaveBeenCalledWith('0989999999')
    expect(setupState.otpSent.value).toBe(true)

    setupState.otpInput.value = '1234567'
    await setupState.verifyOtp()
    expect(verifySpy).not.toHaveBeenCalled()

    setupState.otpInput.value = '12345678'
    await setupState.verifyOtp()
    expect(verifySpy).toHaveBeenCalledWith('0989999999', '12345678')
    expect(routerPush).toHaveBeenCalledWith({ name: 'author-register' })
  })
})
