<script setup>
import { computed, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Toast from 'primevue/toast'
import { useAuthStore } from '@/stores/auth'
import OtpCodeInput from '@/components/auth/OtpCodeInput.vue'

const router = useRouter()
const toast = useToast()
const authStore = useAuthStore()

const otpInput = ref('')
const otpSent = ref(false)
const loading = ref(false)
const timer = ref(0)
let intervalId = null

const phone = computed(() => authStore.user?.phone || '')
const maskedPhone = computed(() => {
  if (!phone.value) return 'chưa cập nhật'
  return `${phone.value.slice(0, 3)}••••${phone.value.slice(-3)}`
})

const startTimer = () => {
  timer.value = 60
  clearInterval(intervalId)
  intervalId = setInterval(() => {
    timer.value -= 1
    if (timer.value <= 0) {
      timer.value = 0
      clearInterval(intervalId)
    }
  }, 1000)
}

const sendOtp = async () => {
  if (!phone.value) {
    toast.add({
      severity: 'error',
      summary: 'Chưa có số điện thoại',
      detail: 'Vui lòng cập nhật số điện thoại trong hồ sơ trước khi xác minh.',
      life: 4000
    })
    return
  }

  loading.value = true
  try {
    await authStore.sendPhoneOtp(phone.value)
    otpInput.value = ''
    otpSent.value = true
    startTimer()
    toast.add({
      severity: 'success',
      summary: 'Đã gửi mã',
      detail: 'Mã OTP 8 chữ số đã được gửi qua SMS.',
      life: 3000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Không thể gửi mã',
      detail: error.response?.data?.message || 'Vui lòng thử lại sau.',
      life: 4000
    })
  } finally {
    loading.value = false
  }
}

const verifyOtp = async () => {
  if (!/^\d{8}$/.test(otpInput.value)) {
    toast.add({
      severity: 'warn',
      summary: 'Mã chưa đầy đủ',
      detail: 'Vui lòng nhập đúng 8 chữ số.',
      life: 3000
    })
    return
  }

  loading.value = true
  try {
    const response = await authStore.verifyPhoneOtp(phone.value, otpInput.value)
    if (response.status !== 'success') {
      throw new Error('OTP verification did not confirm the current account.')
    }

    toast.add({
      severity: 'success',
      summary: 'Xác thực thành công',
      detail: 'Số điện thoại của bạn đã được xác minh.',
      life: 3000
    })
    await router.push({ name: 'author-register' })
  } catch (error) {
    otpInput.value = ''
    toast.add({
      severity: 'error',
      summary: 'Xác thực thất bại',
      detail: error.response?.data?.message || 'Mã OTP không chính xác hoặc đã hết hạn.',
      life: 4000
    })
  } finally {
    loading.value = false
  }
}

onUnmounted(() => {
  clearInterval(intervalId)
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex items-center justify-center py-12 px-4">
    <Toast />

    <div class="bg-white w-full max-w-[520px] rounded-2xl shadow-md p-8 border border-slate-200 flex flex-col items-center text-center">
      <div class="w-16 h-16 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mb-6">
        <i class="pi pi-verified text-3xl"></i>
      </div>

      <h1 class="text-2xl font-bold text-slate-800 mb-2">Xác minh tài khoản</h1>
      <p class="text-sm text-slate-500 mb-8 max-w-sm">
        Mã OTP gồm 8 chữ số sẽ được gửi tới số điện thoại {{ maskedPhone }}.
      </p>

      <template v-if="phone">
        <Button
          v-if="!otpSent"
          label="Gửi mã OTP"
          :loading="loading"
          class="w-full p-button-primary bg-indigo-600 text-white h-12 rounded-lg font-bold shadow-sm mb-6"
          @click="sendOtp"
        />

        <template v-else>
          <OtpCodeInput v-model="otpInput" :length="8" :disabled="loading" />
          <Button
            label="Xác minh"
            :loading="loading"
            :disabled="otpInput.length !== 8"
            class="w-full p-button-primary bg-indigo-600 text-white h-12 rounded-lg font-bold shadow-sm my-6"
            @click="verifyOtp"
          />

          <div class="text-sm text-slate-600 flex flex-wrap items-center justify-center gap-2 mb-6">
            <span>Bạn chưa nhận được mã?</span>
            <button
              type="button"
              class="text-indigo-600 font-semibold hover:underline bg-transparent border-none p-0 cursor-pointer disabled:text-slate-400 disabled:no-underline disabled:cursor-not-allowed"
              :disabled="timer > 0 || loading"
              @click="sendOtp"
            >
              Gửi lại
            </button>
            <span v-if="timer > 0" class="text-xs text-slate-500 bg-slate-100 px-2 py-1 rounded font-mono">
              00:{{ timer < 10 ? `0${timer}` : timer }}
            </span>
          </div>
        </template>
      </template>

      <div v-else class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        Tài khoản chưa có số điện thoại để xác minh.
      </div>

      <div class="w-full pt-4 border-t border-slate-100 mt-2">
        <button
          type="button"
          class="inline-flex items-center gap-1 text-slate-400 hover:text-slate-600 text-sm font-semibold transition-colors bg-transparent border-none cursor-pointer"
          @click="router.push({ name: 'author-register' })"
        >
          <i class="pi pi-arrow-left text-xs"></i>
          Quay lại
        </button>
      </div>
    </div>
  </div>
</template>
