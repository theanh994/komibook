<template>
  <div class="flex w-full justify-center gap-1.5 sm:gap-2" role="group" :aria-label="`Mã OTP ${length} chữ số`">
    <input
      v-for="index in length"
      :key="index"
      :ref="(element) => setInputRef(element, index - 1)"
      :value="digits[index - 1]"
      :disabled="disabled"
      :autocomplete="index === 1 ? 'one-time-code' : 'off'"
      :aria-label="`Chữ số OTP thứ ${index}`"
      class="h-12 min-w-0 flex-1 rounded-xl border border-outline-variant/60 bg-surface-container-lowest text-center text-lg font-bold text-on-surface outline-none transition-all focus:border-primary focus:ring-4 focus:ring-primary/10 disabled:cursor-not-allowed disabled:opacity-60"
      inputmode="numeric"
      maxlength="1"
      pattern="[0-9]*"
      type="text"
      @input="handleInput(index - 1, $event)"
      @keydown="handleKeydown(index - 1, $event)"
      @paste="handlePaste(index - 1, $event)"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  length: {
    type: Number,
    default: 8
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'complete'])
const inputRefs = ref([])

const digits = computed(() => {
  const value = String(props.modelValue || '').replace(/\D/g, '').slice(0, props.length)
  return Array.from({ length: props.length }, (_, index) => value[index] || '')
})

const setInputRef = (element, index) => {
  if (element) inputRefs.value[index] = element
}

const focusInput = async (index) => {
  await nextTick()
  inputRefs.value[index]?.focus()
  inputRefs.value[index]?.select()
}

const commitDigits = (nextDigits) => {
  const value = nextDigits.join('').replace(/\D/g, '').slice(0, props.length)
  emit('update:modelValue', value)
  if (value.length === props.length) emit('complete', value)
  return value
}

const insertDigits = (startIndex, rawValue) => {
  const incoming = String(rawValue || '').replace(/\D/g, '')
  if (!incoming) return

  const nextDigits = [...digits.value]
  incoming.slice(0, props.length - startIndex).split('').forEach((digit, offset) => {
    nextDigits[startIndex + offset] = digit
  })

  const value = commitDigits(nextDigits)
  const nextIndex = Math.min(startIndex + incoming.length, props.length - 1)
  focusInput(value.length === props.length ? props.length - 1 : nextIndex)
}

const handleInput = (index, event) => {
  insertDigits(index, event.target.value)
}

const handlePaste = (index, event) => {
  event.preventDefault()
  insertDigits(index, event.clipboardData?.getData('text') || '')
}

const handleKeydown = (index, event) => {
  if (event.key === 'ArrowLeft' && index > 0) {
    event.preventDefault()
    focusInput(index - 1)
    return
  }
  if (event.key === 'ArrowRight' && index < props.length - 1) {
    event.preventDefault()
    focusInput(index + 1)
    return
  }
  if (event.key !== 'Backspace') return

  event.preventDefault()
  const nextDigits = [...digits.value]
  const targetIndex = nextDigits[index] ? index : Math.max(0, index - 1)
  nextDigits[targetIndex] = ''
  commitDigits(nextDigits)
  focusInput(targetIndex)
}

defineExpose({
  digits,
  handleInput,
  handlePaste,
  handleKeydown,
  insertDigits
})
</script>
