import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import apiClient from '@/services/axios'
import { readApiList } from '@/services/apiContract'
import { useAuthStore } from '@/stores/auth'

export const useCartStore = defineStore('cart', () => {
  const authStore = useAuthStore()
  const items = ref([])
  const currentUserId = ref(authStore.user?.id || null)
  const isHydratingCart = ref(false)

  const getStorageKey = (userId) => {
    return userId ? `komibook_cart_user_${userId}` : 'komibook_cart_guest'
  }

  const isStructurallyValidCartItem = (item) => (
    item
    && typeof item === 'object'
    && item.book
    && typeof item.book === 'object'
    && item.book.id !== undefined
    && item.book.id !== null
  )

  const isStructurallyValidCartItems = (storedItems) => (
    Array.isArray(storedItems) && storedItems.every(isStructurallyValidCartItem)
  )

  const normalizeCartItems = (storedItems) => {
    if (!Array.isArray(storedItems)) return []

    return storedItems
      .filter(isStructurallyValidCartItem)
      .map(item => ({
        ...item,
        quantity: item.book.type === 'ebook' ? 1 : Math.max(1, Number(item.quantity) || 1),
        selected: item.selected !== false
      }))
  }

  const loadCartForUser = (userId) => {
    currentUserId.value = userId || null
    const key = getStorageKey(currentUserId.value)

    // Legacy data is only migrated into an empty guest scope; account scopes stay isolated.
    const stored = localStorage.getItem(key)
    let loadedItems = []
    let hasStructurallyValidCurrentCart = false
    if (stored !== null) {
      try {
        const currentItems = JSON.parse(stored)
        if (isStructurallyValidCartItems(currentItems)) {
          loadedItems = normalizeCartItems(currentItems)
          hasStructurallyValidCurrentCart = true
        }
      } catch (e) {
        console.error('Lỗi parse giỏ hàng', e)
      }
    }

    if (!currentUserId.value && (stored === null || (hasStructurallyValidCurrentCart && loadedItems.length === 0))) {
      const legacyKey = 'komibook_cart'
      const legacyStored = localStorage.getItem(legacyKey)

      if (legacyStored) {
        try {
          const legacyItems = JSON.parse(legacyStored)
          if (isStructurallyValidCartItems(legacyItems)) {
            const migratedItems = normalizeCartItems(legacyItems)
            const serializedItems = JSON.stringify(migratedItems)
            localStorage.setItem(key, serializedItems)

            if (localStorage.getItem(key) === serializedItems) {
              localStorage.removeItem(legacyKey)
              loadedItems = migratedItems
            }
          }
        } catch (e) {
          // Keep corrupt legacy data intact for a future recovery attempt.
          console.error('Unable to parse legacy cart', e)
        }
      }
    }

    isHydratingCart.value = true
    items.value = loadedItems
    isHydratingCart.value = false
  }

  // Khởi tạo giỏ hàng theo trạng thái đăng nhập hiện tại
  loadCartForUser(authStore.user?.id || null)

  // Tự động chuyển đổi giỏ hàng khi người dùng thay đổi (Đăng nhập / Đăng xuất / Chuyển tài khoản)
  watch(() => authStore.user?.id, (newUserId) => {
    loadCartForUser(newUserId)
  }, { flush: 'sync' })

  // Tự động lưu khi có thay đổi vào khóa tương ứng của user
  watch(items, (newItems) => {
    if (isHydratingCart.value) return

    const key = getStorageKey(currentUserId.value)
    localStorage.setItem(key, JSON.stringify(newItems))
  }, { deep: true, flush: 'sync' })

  // Tất cả các món được tích chọn
  const selectedItems = computed(() => {
    return items.value.filter(item => item.selected !== false)
  })

  // Tổng số lượng tất cả trong giỏ
  const totalItems = computed(() => {
    return items.value.reduce((total, item) => total + item.quantity, 0)
  })

  // Tổng số lượng các món được chọn
  const selectedTotalItems = computed(() => {
    return selectedItems.value.reduce((total, item) => total + item.quantity, 0)
  })

  // Tổng tiền tất cả trong giỏ
  const totalPrice = computed(() => {
    return items.value.reduce((total, item) => {
      const price = item.book.sale_price || item.book.price
      return total + (price * item.quantity)
    }, 0)
  })

  // Tổng tiền các món được chọn
  const selectedTotalPrice = computed(() => {
    return selectedItems.value.reduce((total, item) => {
      const price = item.book.sale_price || item.book.price
      return total + (price * item.quantity)
    }, 0)
  })

  // Gom nhóm theo vendor (Shop) cho tất cả sản phẩm
  const groupedItems = computed(() => {
    const groups = {}
    items.value.forEach(item => {
      const vendorId = item.book.vendor_id || (item.book.vendor?.id) || 'unknown'
      const vendorName = item.book.vendor?.shop_name || item.book.vendor?.name || 'KomiBook'

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

  // Gom nhóm theo vendor (Shop) cho CÁC SẢN PHẨM ĐƯỢC CHỌN
  const selectedGroupedItems = computed(() => {
    const groups = {}
    selectedItems.value.forEach(item => {
      const vendorId = item.book.vendor_id || (item.book.vendor?.id) || 'unknown'
      const vendorName = item.book.vendor?.shop_name || item.book.vendor?.name || 'KomiBook'
      const vendorLogo = item.book.vendor?.logo || null

      if (!groups[vendorId]) {
        groups[vendorId] = {
          vendorId,
          vendorName,
          vendorLogo,
          items: []
        }
      }
      groups[vendorId].items.push(item)
    })
    return Object.values(groups)
  })

  const isAllSelected = computed(() => {
    return items.value.length > 0 && items.value.every(item => item.selected !== false)
  })

  const isVendorAllSelected = (vendorId) => {
    const groupItems = items.value.filter(item => {
      const vId = item.book.vendor_id || (item.book.vendor?.id) || 'unknown'
      return vId === vendorId
    })
    return groupItems.length > 0 && groupItems.every(item => item.selected !== false)
  }

  const toggleSelectItem = (bookId) => {
    const item = items.value.find(entry => entry.book.id === bookId)
    if (item) {
      item.selected = !item.selected
    }
  }

  const toggleSelectVendorGroup = (vendorId, isSelected) => {
    items.value.forEach(item => {
      const vId = item.book.vendor_id || (item.book.vendor?.id) || 'unknown'
      if (vId === vendorId) {
        item.selected = isSelected
      }
    })
  }

  const toggleSelectAll = (isSelected) => {
    items.value.forEach(item => {
      item.selected = isSelected
    })
  }

  const removeSelected = () => {
    items.value = items.value.filter(item => item.selected === false)
  }

  const clearSelectedItems = () => {
    items.value = items.value.filter(item => item.selected === false)
  }

  const addToCart = (book, quantity = 1) => {
    if (!book) return false

    const isOutOfStock = book.type !== 'ebook' && (Number(book.stock) <= 0 || (book.status && book.status !== 'published'))
    if (isOutOfStock) {
      return false
    }

    const existing = items.value.find(item => item.book.id === book.id)
    if (existing) {
      existing.book = { ...existing.book, ...book }
      existing.quantity = book.type === 'ebook' ? 1 : existing.quantity + quantity
      existing.selected = true
    } else {
      items.value.push({ book, quantity: book.type === 'ebook' ? 1 : quantity, selected: true })
    }
    return true
  }

  const updateQuantity = (bookId, newQuantity) => {
    const item = items.value.find(item => item.book.id === bookId)
    if (item) {
      item.quantity = item.book.type === 'ebook' ? 1 : Math.max(1, newQuantity)
    }
  }

  const refreshBook = (bookId, freshBook) => {
    const item = items.value.find(entry => entry.book.id === bookId)
    if (!item || !freshBook) return
    item.book = { ...item.book, ...freshBook }
    if (item.book.type === 'ebook') item.quantity = 1
  }

  const removeFromCart = (bookId) => {
    items.value = items.value.filter(item => item.book.id !== bookId)
  }

  const clearCart = () => {
    items.value = []
  }

  const checkout = async (shippingData) => {
    const targetItems = selectedItems.value
    if (targetItems.length === 0) {
      throw new Error('Checkout requires at least one selected cart item.')
    }

    const payloadItems = targetItems.map(item => ({
      book_id: item.book.id,
      quantity: item.quantity
    }))

    const payload = {
      items: payloadItems,
      shipping_address: shippingData.shipping_address,
      phone: shippingData.phone,
      payment_method: shippingData.payment_method,
      coupon_code: shippingData.coupon_code,
      ebook_terms_accepted: Boolean(shippingData.ebook_terms_accepted)
    }

    const response = await apiClient.post('/api/checkout', payload)
    return readApiList(response.data)
  }

  return {
    items,
    selectedItems,
    totalItems,
    selectedTotalItems,
    totalPrice,
    selectedTotalPrice,
    groupedItems,
    selectedGroupedItems,
    isAllSelected,
    isVendorAllSelected,
    toggleSelectItem,
    toggleSelectVendorGroup,
    toggleSelectAll,
    removeSelected,
    clearSelectedItems,
    addToCart,
    updateQuantity,
    refreshBook,
    removeFromCart,
    clearCart,
    loadCartForUser,
    currentUserId,
    checkout
  }
})
