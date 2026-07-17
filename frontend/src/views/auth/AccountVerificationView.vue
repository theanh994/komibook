<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Toast from 'primevue/toast'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const toast = useToast()
const authStore = useAuthStore()

const otpDigits = ref(['', '', '', '', '', ''])
const inputRefs = ref([])
const timer = ref(59)
let intervalId = null

const startTimer = () => {
  timer.value = 59
  clearInterval(intervalId)
  intervalId = setInterval(() => {
    if (timer.value > 0) {
      timer.value--
    } else {
      clearInterval(intervalId)
    }
  }, 1000)
}

const handleInput = (index, event) => {
  const val = event.target.value
  // Allow only digits
  if (!/^\d*$/.test(val)) {
    otpDigits.value[index] = ''
    return
  }
  otpDigits.value[index] = val.slice(-1)

  // Move to next input if typing a digit
  if (val && index < 5) {
    const nextInput = document.getElementById(`otp-${index + 1}`)
    nextInput?.focus()
  }
}

const handleKeyDown = (index, event) => {
  if (event.key === 'Backspace' && !otpDigits.value[index] && index > 0) {
    otpDigits.value[index - 1] = ''
    const prevInput = document.getElementById(`otp-${index - 1}`)
    prevInput?.focus()
  }
}

const resendOtp = () => {
  startTimer()
  toast.add({ severity: 'success', summary: 'Đã gửi lại mã', detail: 'Mã xác thực OTP mới đã được gửi.', life: 3000 })
}

const verifyOtp = () => {
  const code = otpDigits.value.join('')
  if (code.length < 6) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Vui lòng nhập đủ 6 chữ số.', life: 3000 })
    return
  }

  // Simulate OTP Verification Success
  toast.add({ severity: 'success', summary: 'Xác thực thành công', detail: 'Số điện thoại của bạn đã được xác minh.', life: 3000 })
  setTimeout(() => {
    // Redirect back to profile or author dashboard
    router.push({ name: 'author-register' })
  }, 1500)
}

onMounted(() => {
  startTimer()
})

onUnmounted(() => {
  clearInterval(intervalId)
})
</script>

<template>
  <div class="otp-verification min-h-screen bg-slate-50 flex items-center justify-center py-12 px-4">
    <Toast />
    
    <div class="bg-white w-full max-w-[460px] rounded-2xl shadow-md p-8 border border-slate-200 flex flex-col items-center text-center">
      <!-- Icon -->
      <div class="w-16 h-16 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mb-6">
        <i class="pi pi-verified text-3xl"></i>
      </div>

      <!-- Header -->
      <h1 class="text-2xl font-bold text-slate-800 mb-2">Xác minh tài khoản</h1>
      <p class="text-sm text-slate-500 mb-8 max-w-sm">
        Vui lòng nhập mã gồm 6 chữ số đã được gửi đến số điện thoại của bạn
      </p>

      <!-- Code inputs -->
      <div class="flex gap-2 justify-center w-full mb-8" dir="ltr">
        <input 
          v-for="(digit, idx) in otpDigits" 
          :key="idx"
          :id="`otp-${idx}`"
          v-model="otpDigits[idx]"
          type="text" 
          inputmode="numeric"
          pattern="[0-9]*"
          maxlength="1" 
          class="w-12 h-14 text-center text-xl font-bold border border-slate-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors shadow-sm"
          @input="handleInput(idx, $event)"
          @keydown="handleKeyDown(idx, $event)"
        />
      </div>

      <!-- Button -->
      <Button label="Xác minh" class="w-full p-button-primary bg-indigo-600 hover:bg-indigo-700 text-white h-12 rounded-lg font-bold shadow-sm mb-6" @click="verifyOtp" />

      <!-- Resend & Timer -->
      <div class="text-sm text-slate-600 flex items-center justify-center gap-2 mb-6">
        <span>Bạn chưa nhận được mã?</span>
        <button 
          class="text-indigo-600 font-semibold hover:underline bg-transparent border-none p-0 cursor-pointer disabled:text-slate-400 disabled:no-underline"
          :disabled="timer > 0"
          @click="resendOtp"
        >
          Gửi lại
        </button>
        <span v-if="timer > 0" class="text-xs text-slate-500 bg-slate-100 px-2 py-1 rounded font-mono">00:{{ timer < 10 ? '0' + timer : timer }}</span>
      </div>

      <!-- Back Link -->
      <div class="w-full pt-4 border-t border-slate-100 mt-2">
        <button class="inline-flex items-center gap-1 text-slate-400 hover:text-slate-600 text-sm font-semibold transition-colors bg-transparent border-none cursor-pointer" @click="router.push({ name: 'author-register' })">
          <i class="pi pi-arrow-left text-xs"></i> Quay lại
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.otp-verification {
  font-family: 'Inter', sans-serif;
}
</style>
