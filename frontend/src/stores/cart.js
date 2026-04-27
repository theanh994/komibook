import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import apiClient from '@/services/axios'

export const useCartStore = defineStore('cart', () => {
  const items = ref([])

  // Khởi tạo từ localStorage
  const stored = localStorage.getItem('komibook_cart')
  if (stored) {
    try {
      items.value = JSON.parse(stored)
    } catch (e) {
      console.error('Lỗi parse giỏ hàng', e)
    }
  }

  // Tự động lưu khi có thay đổi
  watch(items, (newItems) => {
    localStorage.setItem('komibook_cart', JSON.stringify(newItems))
  }, { deep: true })

  // Tổng số lượng sách
  const totalItems = computed(() => {
    return items.value.reduce((total, item) => total + item.quantity, 0)
  })

  // Tổng tiền
  const totalPrice = computed(() => {
    return items.value.reduce((total, item) => {
      const price = item.book.sale_price || item.book.price
      return total + (price * item.quantity)
    }, 0)
  })

  // Gom nhóm theo vendor (Shop)
  const groupedItems = computed(() => {
    const groups = {}
    items.value.forEach(item => {
      const vendorId = item.book.vendor_id || (item.book.vendor?.id) || 'unknown'
      const vendorName = item.book.vendor?.name || 'KomiBook'
      
      if (!groups[vendorId]) {
        groups[vendorId] = {
          vendorId,
          vendorName,
          items: []
        }
      }
      groups[vendorId].items.push(item)
    })
    return Object.values(groups)
  })

  const addToCart = (book, quantity = 1) => {
    const existing = items.value.find(item => item.book.id === book.id)
    if (existing) {
      existing.quantity += quantity
    } else {
      items.value.push({ book, quantity })
    }
  }

  const updateQuantity = (bookId, newQuantity) => {
    const item = items.value.find(item => item.book.id === bookId)
    if (item) {
      item.quantity = Math.max(1, newQuantity)
    }
  }

  const removeFromCart = (bookId) => {
    items.value = items.value.filter(item => item.book.id !== bookId)
  }

  const clearCart = () => {
    items.value = []
  }

  const checkout = async (shippingData) => {
    const payloadItems = items.value.map(item => ({
      book_id: item.book.id,
      quantity: item.quantity
    }))

    const payload = {
      items: payloadItems,
      shipping_address: shippingData.shipping_address,
      phone: shippingData.phone
    }

    const response = await apiClient.post('/api/checkout', payload)
    return response.data
  }

  return {
    items,
    totalItems,
    totalPrice,
    groupedItems,
    addToCart,
    updateQuantity,
    removeFromCart,
    clearCart,
    checkout
  }
})
