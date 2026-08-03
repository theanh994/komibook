<template>
  <div class="flex flex-col gap-3" aria-label="Đăng nhập bằng tài khoản xã hội">
    <div
      v-if="loadingConfig"
      class="flex h-12 w-full items-center justify-center rounded-xl border border-outline-variant/60 bg-surface text-sm font-semibold text-on-surface-variant"
      role="status"
    >
      Đang tải phương thức đăng nhập…
    </div>

    <template v-else>
      <div
        v-if="config.google.enabled"
        ref="googleButtonContainer"
        class="mx-auto flex min-h-11 w-full max-w-[400px] items-center justify-center overflow-hidden rounded-full"
        :class="{ 'pointer-events-none opacity-50': disabled }"
        :aria-disabled="disabled"
      />
      <button
        v-else
        type="button"
        class="flex min-h-11 w-full cursor-not-allowed items-center justify-center gap-3 rounded-xl border border-outline-variant/60 bg-surface px-6 text-sm font-semibold text-on-surface-variant opacity-60"
        disabled
        title="Google Login chưa được cấu hình"
      >
        Google Login chưa sẵn sàng
      </button>

      <button
        type="button"
        class="mx-auto flex h-10 min-h-10 w-full max-w-[400px] items-center justify-center gap-3 rounded-full border border-[#dadce0] bg-white px-3 [font-family:'Google_Sans',Arial,sans-serif] text-sm font-medium text-[#3c4043] transition-colors duration-200 hover:bg-[#f8faff] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="disabled || !config.facebook.enabled"
        :title="config.facebook.enabled ? 'Tiếp tục với Facebook' : 'Facebook Login chưa được cấu hình'"
        @click="startFacebookLogin"
      >
        <svg class="h-5 w-5 text-[#1877F2]" viewBox="0 0 24 24" aria-hidden="true">
          <path fill="currentColor" d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.414c0-3.026 1.792-4.697 4.533-4.697 1.313 0 2.686.236 2.686.236v2.974h-1.513c-1.49 0-1.956.931-1.956 1.887v2.259h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073Z" />
        </svg>
        {{ config.facebook.enabled ? 'Tiếp tục với Facebook' : 'Facebook Login chưa sẵn sàng' }}
      </button>
    </template>

    <p v-if="configError" class="text-sm leading-5 text-error" role="alert">
      {{ configError }}
    </p>
  </div>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue'
import apiClient from '@/services/axios'
import { requestFacebookLogin } from '@/services/facebookAuth'

defineProps({
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['google-credential', 'facebook-token', 'error'])

const googleButtonContainer = ref(null)
const loadingConfig = ref(true)
const configError = ref('')
const config = ref({
  google: { enabled: false, client_id: null },
  facebook: { enabled: false, app_id: null, graph_version: null },
})

let googleSdkPromise

const loadGoogleSdk = () => {
  if (window.google?.accounts?.id) return Promise.resolve(window.google)
  if (googleSdkPromise) return googleSdkPromise

  googleSdkPromise = new Promise((resolve, reject) => {
    const existingScript = document.querySelector('script[src="https://accounts.google.com/gsi/client"]')

    const resolveSdk = () => {
      if (window.google?.accounts?.id) resolve(window.google)
      else reject(new Error('Google Identity Services không khởi tạo được.'))
    }

    if (existingScript) {
      existingScript.addEventListener('load', resolveSdk, { once: true })
      existingScript.addEventListener('error', () => reject(new Error('Không thể tải Google Identity Services.')), { once: true })
      return
    }

    const script = document.createElement('script')
    script.src = 'https://accounts.google.com/gsi/client'
    script.async = true
    script.defer = true
    script.onload = resolveSdk
    script.onerror = () => reject(new Error('Không thể tải Google Identity Services.'))
    document.head.appendChild(script)
  })

  return googleSdkPromise
}

const renderGoogleButton = async () => {
  if (!config.value.google.enabled) return

  try {
    const google = await loadGoogleSdk()
    await nextTick()

    if (!googleButtonContainer.value) return

    googleButtonContainer.value.replaceChildren()
    google.accounts.id.initialize({
      client_id: config.value.google.client_id,
      callback: (response) => emit('google-credential', response),
      auto_select: false,
      cancel_on_tap_outside: true,
    })
    google.accounts.id.renderButton(googleButtonContainer.value, {
      type: 'standard',
      theme: 'outline',
      size: 'large',
      shape: 'pill',
      text: 'continue_with',
      logo_alignment: 'left',
      width: Math.min(400, Math.max(240, googleButtonContainer.value.clientWidth || 400)),
    })
  } catch (error) {
    configError.value = error.message || 'Không thể khởi tạo đăng nhập Google.'
    emit('error', configError.value)
  }
}

const startFacebookLogin = async () => {
  try {
    const token = await requestFacebookLogin(
      config.value.facebook.app_id,
      config.value.facebook.graph_version,
    )
    emit('facebook-token', token)
  } catch (error) {
    emit('error', error.message || 'Đăng nhập Facebook không thành công.')
  }
}

onMounted(async () => {
  try {
    const response = await apiClient.get('/api/auth/social-login-config')
    config.value = response.data.data
  } catch {
    configError.value = 'Không thể tải cấu hình đăng nhập Google và Facebook.'
  } finally {
    loadingConfig.value = false
  }

  await renderGoogleButton()
})
</script>
