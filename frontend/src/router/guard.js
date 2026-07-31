/**
 * Pure function to evaluate route navigation permissions.
 *
 * @param {Object} to - Target route object
 * @param {Object} authState - Auth state containing isAuthenticated and userRole
 * @returns {boolean|Object} Returns true to allow, or a route location object to redirect
 */
export function evaluateRouteGuard(to, { isAuthenticated, userRole, capabilities = {} }) {
  // 1. General auth requirement check
  if (to.meta?.requiresAuth && !isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  // 2. Prevent guest routes (login/register) when authenticated
  if (to.meta?.guestOnly && isAuthenticated) {
    return { name: 'dashboard' }
  }

  if (to.meta?.capability && !capabilities[to.meta.capability]) {
    if (to.meta.capability === 'active_vendor') {
      return { name: 'vendor-register', query: { redirect: to.fullPath } }
    }

    return { name: 'home' }
  }

  // 3. Role-based access control check. Capability is evaluated first so an
  // incomplete seller application receives an actionable onboarding route.
  if (to.meta?.role && userRole !== to.meta.role) {
    return { name: 'home' }
  }

  return true
}

/**
 * Production route guard coordinator.
 * Ensures user state is loaded before evaluating route access.
 *
 * @param {Object} to - Target route object
 * @param {Object} authStore - Pinia auth store instance
 * @returns {Promise<boolean|Object>}
 */
export async function runRouteGuard(to, authStore) {
  if (!authStore.userFetched) {
    await authStore.fetchUser()
  }

  const isAuthenticated = authStore.isAuthenticated
  const userRole = authStore.user?.role
  const capabilities = authStore.user?.capabilities || {}

  return evaluateRouteGuard(to, { isAuthenticated, userRole, capabilities })
}

/**
 * Resolve the default management channel for the active account capabilities.
 */
export function getDashboardRedirect({ isAdmin, isActiveVendor, isWarehouseManager = false }) {
  if (isAdmin) return { name: 'admin-dashboard' }
  if (isActiveVendor) return { name: 'vendor-dashboard' }
  if (isWarehouseManager) return { name: 'warehouse-manager-dashboard' }
  return { name: 'home' }
}

/**
 * Helper to determine the post-login redirect target.
 * Preserves query redirect strings (e.g. /profile?tab=security) or defaults strictly to { name: 'dashboard' }.
 *
 * @param {Object} route - Current route instance or location object
 * @returns {string|Object}
 */
export function getPostLoginRedirect(route) {
  const redirect = route?.query?.redirect
  if (redirect && typeof redirect === 'string' && redirect.startsWith('/')) {
    return redirect
  }
  return { name: 'dashboard' }
}
