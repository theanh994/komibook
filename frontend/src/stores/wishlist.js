import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/services/axios'
import { readApiData, readApiList } from '@/services/apiContract'
import { useAuthStore } from './auth'

export const useWishlistStore = defineStore('wishlist', () => {
  const wishlistIds = ref(new Set())
  const loading = ref(false)
  const authStore = useAuthStore()

  const fetchWishlistIds = async () => {
    if (!authStore.isAuthenticated) {
      wishlistIds.value = new Set()
      return
    }
    loading.value = true
    try {
      const res = await apiClient.get('/api/wishlist')
      const items = readApiList(res.data)
      wishlistIds.value = new Set(items.map(b => b.id))
    } catch (error) {
      console.error('Error fetching wishlist ids:', error)
    } finally {
      loading.value = false
    }
  }

  const toggleWishlist = async (bookId) => {
    if (!authStore.isAuthenticated) return { state: 'unauthorized' }
    try {
      const res = await apiClient.post(`/api/wishlist/${bookId}/toggle`)
      const result = readApiData(res.data)
      if (result.state === 'added') {
        wishlistIds.value.add(bookId)
      } else {
        wishlistIds.value.delete(bookId)
      }
      return result
    } catch (error) {
      console.error('Error toggling wishlist:', error)
      throw error
    }
  }

  const isFavorite = (bookId) => wishlistIds.value.has(bookId)

  return { wishlistIds, loading, fetchWishlistIds, toggleWishlist, isFavorite }
})
