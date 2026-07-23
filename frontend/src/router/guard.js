/**
 * Pure function to evaluate route navigation permissions.
 *
 * @param {Object} to - Target route object
 * @param {Object} authState - Auth state containing isAuthenticated and userRole
 * @returns {boolean|Object} Returns true to allow, or a route location object to redirect
 */
export function evaluateRouteGuard(to, { isAuthenticated, userRole }) {
  // 1. General auth requirement check
  if (to.meta?.requiresAuth && !isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  // 2. Prevent guest routes (login/register) when authenticated
  if (to.meta?.guestOnly && isAuthenticated) {
    return { name: 'dashboard' }
  }

  // 3. Role-based access control check
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

  return evaluateRouteGuard(to, { isAuthenticated, userRole })
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
