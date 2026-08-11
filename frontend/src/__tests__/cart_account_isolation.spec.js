import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Polyfill localStorage cho môi trường node/vitest
const createLocalStorageMock = () => {
  let store = {}
  return {
    getItem: (key) => (key in store ? store[key] : null),
    setItem: (key, value) => { store[key] = String(value) },
    removeItem: (key) => { delete store[key] },
    clear: () => { store = {} }
  }
}

if (typeof globalThis.localStorage === 'undefined' || !globalThis.localStorage.getItem) {
  globalThis.localStorage = createLocalStorageMock()
}

import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import apiClient from '@/services/axios'

describe('Cart Per-Account Isolation & Logout Auto-Reset', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  const sampleBook1 = {
    id: 1,
    title: 'Đắc Nhân Tâm',
    price: 100000,
    sale_price: 80000,
    stock: 10,
    type: 'paper'
  }

  const sampleBook2 = {
    id: 2,
    title: 'Nhà Giả Kim',
    price: 150000,
    sale_price: 120000,
    stock: 5,
    type: 'paper'
  }

  it('stores items in guest cart when no user is logged in', () => {
    const cartStore = useCartStore()
    expect(cartStore.items.length).toBe(0)

    cartStore.addToCart(sampleBook1, 1)
    expect(cartStore.items.length).toBe(1)
    expect(localStorage.getItem('komibook_cart_guest')).toContain('Đắc Nhân Tâm')
  })

  it('switches to user cart when User A logs in and retains User A items', async () => {
    const authStore = useAuthStore()
    const cartStore = useCartStore()

    // 1. User A đăng nhập
    authStore.user = { id: 101, name: 'User A', email: 'usera@example.com' }
    cartStore.loadCartForUser(101)

    cartStore.addToCart(sampleBook1, 2)
    expect(cartStore.totalItems).toBe(2)
    expect(localStorage.getItem('komibook_cart_user_101')).toContain('Đắc Nhân Tâm')
  })

  it('switches storage scopes through the cart auth watcher without manually loading a cart', () => {
    const authStore = useAuthStore()
    localStorage.setItem('komibook_cart_user_101', JSON.stringify([{ book: sampleBook1, quantity: 2, selected: true }]))
    localStorage.setItem('komibook_cart_user_202', JSON.stringify([{ book: sampleBook2, quantity: 1, selected: true }]))
    const cartStore = useCartStore()

    authStore.user = { id: 101, name: 'User A' }
    expect(cartStore.currentUserId).toBe(101)
    expect(cartStore.items[0].book.id).toBe(1)

    authStore.user = { id: 202, name: 'User B' }
    expect(cartStore.currentUserId).toBe(202)
    expect(cartStore.items).toHaveLength(1)
    expect(cartStore.items[0].book.id).toBe(2)
  })

  it('migrates a valid legacy cart only into an empty guest cart and does so idempotently', () => {
    localStorage.setItem('komibook_cart', JSON.stringify([{ book: sampleBook1, quantity: 0, selected: false }]))
    const cartStore = useCartStore()

    expect(cartStore.items).toHaveLength(1)
    expect(cartStore.items[0].quantity).toBe(1)
    expect(cartStore.items[0].selected).toBe(false)
    expect(localStorage.getItem('komibook_cart')).toBeNull()
    expect(JSON.parse(localStorage.getItem('komibook_cart_guest'))).toHaveLength(1)

    cartStore.loadCartForUser(null)
    expect(cartStore.items).toHaveLength(1)
    expect(localStorage.getItem('komibook_cart')).toBeNull()
  })

  it('preserves parseable but structurally invalid legacy carts without writing the guest scope', () => {
    const objectLegacy = JSON.stringify({ book: sampleBook1, quantity: 1 })
    localStorage.setItem('komibook_cart', objectLegacy)
    const cartStore = useCartStore()

    expect(cartStore.items).toEqual([])
    expect(localStorage.getItem('komibook_cart')).toBe(objectLegacy)
    expect(localStorage.getItem('komibook_cart_guest')).toBeNull()

    const mixedLegacy = JSON.stringify([{ book: sampleBook1, quantity: 1 }, { quantity: 1 }])
    localStorage.setItem('komibook_cart', mixedLegacy)
    cartStore.loadCartForUser(null)

    expect(cartStore.items).toEqual([])
    expect(localStorage.getItem('komibook_cart')).toBe(mixedLegacy)
    expect(localStorage.getItem('komibook_cart_guest')).toBeNull()
  })

  it('does not replace structurally invalid current guest data with an otherwise valid legacy cart', () => {
    const validLegacy = JSON.stringify([{ book: sampleBook1, quantity: 1, selected: true }])
    const objectGuest = JSON.stringify({ book: sampleBook2, quantity: 1 })
    localStorage.setItem('komibook_cart_guest', objectGuest)
    localStorage.setItem('komibook_cart', validLegacy)
    const cartStore = useCartStore()

    expect(cartStore.items).toEqual([])
    expect(localStorage.getItem('komibook_cart_guest')).toBe(objectGuest)
    expect(localStorage.getItem('komibook_cart')).toBe(validLegacy)

    const mixedGuest = JSON.stringify([{ book: sampleBook2, quantity: 1 }, { quantity: 1 }])
    localStorage.setItem('komibook_cart_guest', mixedGuest)
    cartStore.loadCartForUser(null)

    expect(cartStore.items).toEqual([])
    expect(localStorage.getItem('komibook_cart_guest')).toBe(mixedGuest)
    expect(localStorage.getItem('komibook_cart')).toBe(validLegacy)

    localStorage.setItem('komibook_cart_guest', '')
    cartStore.loadCartForUser(null)

    expect(cartStore.items).toEqual([])
    expect(localStorage.getItem('komibook_cart_guest')).toBe('')
    expect(localStorage.getItem('komibook_cart')).toBe(validLegacy)
  })

  it('preserves corrupt legacy data and never migrates or deletes it from an account scope', () => {
    const authStore = useAuthStore()
    localStorage.setItem('komibook_cart', '{not-json')
    authStore.user = { id: 101, name: 'User A' }
    const cartStore = useCartStore()

    expect(cartStore.items).toEqual([])
    expect(localStorage.getItem('komibook_cart')).toBe('{not-json')

    authStore.user = null
    expect(cartStore.items).toEqual([])
    expect(localStorage.getItem('komibook_cart')).toBe('{not-json')
  })

  it('does not overwrite corrupt scoped JSON during watcher-driven account hydration', () => {
    const authStore = useAuthStore()
    const corruptUserBCart = '{not-json'
    localStorage.setItem('komibook_cart_user_101', JSON.stringify([{ book: sampleBook1, quantity: 1, selected: true }]))
    localStorage.setItem('komibook_cart_user_202', corruptUserBCart)
    const cartStore = useCartStore()

    authStore.user = { id: 101, name: 'User A' }
    expect(cartStore.items[0].book.id).toBe(1)

    authStore.user = { id: 202, name: 'User B' }
    expect(cartStore.items).toEqual([])
    expect(localStorage.getItem('komibook_cart_user_202')).toBe(corruptUserBCart)

    cartStore.addToCart(sampleBook2, 1)
    expect(JSON.parse(localStorage.getItem('komibook_cart_user_202'))).toEqual([
      expect.objectContaining({ book: expect.objectContaining({ id: 2 }), quantity: 1, selected: true })
    ])
  })

  it('rejects checkout with no selected items before making an API request', async () => {
    const cartStore = useCartStore()
    cartStore.addToCart(sampleBook1, 1)
    cartStore.toggleSelectItem(sampleBook1.id)
    const postSpy = vi.spyOn(apiClient, 'post')

    await expect(cartStore.checkout({ payment_method: 'COD' })).rejects.toThrow('at least one selected cart item')
    expect(postSpy).not.toHaveBeenCalled()
    postSpy.mockRestore()
  })

  it('resets cart state on logout and isolates cart between User A and User B', async () => {
    const authStore = useAuthStore()
    const cartStore = useCartStore()

    // --- Bước 1: User A (ID 101) đăng nhập và thêm Sách 1 vào giỏ ---
    authStore.user = { id: 101, name: 'User A', email: 'usera@example.com' }
    cartStore.loadCartForUser(101)

    cartStore.addToCart(sampleBook1, 2)
    expect(cartStore.totalItems).toBe(2)
    expect(cartStore.items[0].book.title).toBe('Đắc Nhân Tâm')

    // --- Bước 2: User A Đăng xuất ---
    await authStore.logout(true) // skipApi = true trong test
    expect(authStore.user).toBeNull()

    // Giỏ hàng hiện tại phải tự động chuyển về guest (trống)
    expect(cartStore.items.length).toBe(0)
    expect(cartStore.totalItems).toBe(0)

    // --- Bước 3: User B (ID 202) Đăng nhập ---
    authStore.user = { id: 202, name: 'User B', email: 'userb@example.com' }
    cartStore.loadCartForUser(202)

    // Giỏ hàng của User B phải hoàn toàn trống, KHÔNG chứa món của User A
    expect(cartStore.items.length).toBe(0)
    expect(cartStore.totalItems).toBe(0)

    // User B thêm Sách 2 vào giỏ của mình
    cartStore.addToCart(sampleBook2, 1)
    expect(cartStore.totalItems).toBe(1)
    expect(cartStore.items[0].book.title).toBe('Nhà Giả Kim')
    expect(localStorage.getItem('komibook_cart_user_202')).toContain('Nhà Giả Kim')

    // --- Bước 4: User B Đăng xuất và User A Đăng nhập lại ---
    await authStore.logout(true)
    expect(cartStore.items.length).toBe(0)

    authStore.user = { id: 101, name: 'User A', email: 'usera@example.com' }
    cartStore.loadCartForUser(101)

    // Giỏ hàng của User A khôi phục chính xác Sách 1 ban đầu
    expect(cartStore.items.length).toBe(1)
    expect(cartStore.totalItems).toBe(2)
    expect(cartStore.items[0].book.title).toBe('Đắc Nhân Tâm')
  })
})
