import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '../stores/auth'
import apiClient from '../services/axios'
import { evaluateRouteGuard, runRouteGuard, getPostLoginRedirect } from '../router/guard'

describe('Auth Store & Guard Matrix Tests', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  // --- 3 Existing Auth Store Tests ---
  it('fetchUser treats 401 from /api/auth/me as normal guest state without throwing error', async () => {
    const authStore = useAuthStore()
    vi.spyOn(apiClient, 'get').mockRejectedValueOnce({
      response: { status: 401, data: { message: 'Unauthenticated' } }
    })

    await authStore.fetchUser()

    expect(authStore.user).toBeNull()
    expect(authStore.userFetched).toBe(true)
    expect(authStore.isAuthenticated).toBe(false)
  })

  it('logout sets user = null and userFetched = true to avoid /me fetch loops', async () => {
    const authStore = useAuthStore()
    authStore.user = { id: 1, name: 'Test User', role: 'customer' }
    authStore.userFetched = true

    vi.spyOn(apiClient, 'post').mockResolvedValueOnce({ data: { status: 'success' } })

    await authStore.logout()

    expect(authStore.user).toBeNull()
    expect(authStore.userFetched).toBe(true)
    expect(authStore.isAuthenticated).toBe(false)
  })

  it('logout with API failure still resets user and sets userFetched = true', async () => {
    const authStore = useAuthStore()
    authStore.user = { id: 1, name: 'Test User', role: 'customer' }
    authStore.userFetched = true

    vi.spyOn(apiClient, 'post').mockRejectedValueOnce(new Error('Network error'))

    await authStore.logout()

    expect(authStore.user).toBeNull()
    expect(authStore.userFetched).toBe(true)
    expect(authStore.isAuthenticated).toBe(false)
  })

  // --- Production Guard Matrix Tests ---
  it('allows unauthenticated guest to access /register', () => {
    const to = { path: '/register', meta: { guestOnly: true } }
    const result = evaluateRouteGuard(to, { isAuthenticated: false, userRole: null })
    expect(result).toBe(true)
  })

  it('allows unauthenticated guest to access /login', () => {
    const to = { path: '/login', meta: { guestOnly: true } }
    const result = evaluateRouteGuard(to, { isAuthenticated: false, userRole: null })
    expect(result).toBe(true)
  })

  it('redirects unauthenticated guest opening protected route to login with preserved to.fullPath', () => {
    const to = { path: '/profile', fullPath: '/profile?tab=security', meta: { requiresAuth: true } }
    const result = evaluateRouteGuard(to, { isAuthenticated: false, userRole: null })
    expect(result).toEqual({ name: 'login', query: { redirect: '/profile?tab=security' } })
  })

  it('runRouteGuard when /api/auth/me returns 401 on /register fetches only once, returns true, userFetched === true', async () => {
    const authStore = useAuthStore()
    const getSpy = vi.spyOn(apiClient, 'get').mockRejectedValueOnce({
      response: { status: 401, data: { message: 'Unauthenticated' } }
    })
    const fetchUserSpy = vi.spyOn(authStore, 'fetchUser')

    const to = { path: '/register', meta: { guestOnly: true } }
    const result = await runRouteGuard(to, authStore)

    expect(result).toBe(true)
    expect(authStore.userFetched).toBe(true)
    expect(authStore.user).toBeNull()
    expect(fetchUserSpy).toHaveBeenCalledTimes(1)
    expect(getSpy).toHaveBeenCalledTimes(1)
  })

  it('redirects authenticated user opening guest-only route to dashboard', () => {
    const to = { path: '/login', meta: { guestOnly: true } }
    const result = evaluateRouteGuard(to, { isAuthenticated: true, userRole: 'customer' })
    expect(result).toEqual({ name: 'dashboard' })
  })

  it('post logout, calling runRouteGuard makes zero calls to fetchUser and zero calls to /api/auth/me', async () => {
    const authStore = useAuthStore()
    authStore.user = { id: 1, name: 'User', role: 'customer' }
    authStore.userFetched = true

    vi.spyOn(apiClient, 'post').mockResolvedValueOnce({ data: { status: 'success' } })
    await authStore.logout()

    const fetchUserSpy = vi.spyOn(authStore, 'fetchUser')
    const getSpy = vi.spyOn(apiClient, 'get')

    const to = { path: '/login', meta: { guestOnly: true } }
    const result = await runRouteGuard(to, authStore)

    expect(result).toBe(true)
    expect(fetchUserSpy).not.toHaveBeenCalled()
    expect(getSpy).not.toHaveBeenCalled()
  })

  it('getPostLoginRedirect preserves exact /profile?tab=security and defaults strictly to { name: "dashboard" }', () => {
    const routeWithRedirect = { query: { redirect: '/profile?tab=security' } }
    expect(getPostLoginRedirect(routeWithRedirect)).toBe('/profile?tab=security')

    const routeWithoutRedirect = { query: {} }
    expect(getPostLoginRedirect(routeWithoutRedirect)).toEqual({ name: 'dashboard' })

    const routeWithInvalidRedirect = { query: { redirect: 'http://malicious.com' } }
    expect(getPostLoginRedirect(routeWithInvalidRedirect)).toEqual({ name: 'dashboard' })
  })

  it('customer entering admin route redirects to home, while admin entering admin route is allowed', () => {
    const adminRoute = { path: '/admin/users', meta: { requiresAuth: true, role: 'admin' } }

    const customerResult = evaluateRouteGuard(adminRoute, { isAuthenticated: true, userRole: 'customer' })
    expect(customerResult).toEqual({ name: 'home' })

    const adminResult = evaluateRouteGuard(adminRoute, { isAuthenticated: true, userRole: 'admin' })
    expect(adminResult).toBe(true)
  })

  it('uses approved_author capability independently from the legacy vendor role', () => {
    const authorRoute = { path: '/author/dashboard', meta: { requiresAuth: true, capability: 'approved_author' } }

    expect(evaluateRouteGuard(authorRoute, {
      isAuthenticated: true,
      userRole: 'customer',
      capabilities: { approved_author: true, active_vendor: false },
    })).toBe(true)

    expect(evaluateRouteGuard(authorRoute, {
      isAuthenticated: true,
      userRole: 'vendor',
      capabilities: { approved_author: false, active_vendor: true },
    })).toEqual({ name: 'author-register' })
  })
})
