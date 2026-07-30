import { describe, expect, it, vi } from 'vitest'
import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'

import OtpCodeInput from '../components/auth/OtpCodeInput.vue'

const { routerPush, toastAdd, apiGet, apiPost } = vi.hoisted(() => ({
  routerPush: vi.fn(),
  toastAdd: vi.fn(),
  apiGet: vi.fn(),
  apiPost: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: routerPush })
}))

vi.mock('primevue/usetoast', () => ({
  useToast: () => ({ add: toastAdd })
}))

vi.mock('@/services/axios', () => ({
  default: {
    get: apiGet,
    post: apiPost,
  },
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

})
