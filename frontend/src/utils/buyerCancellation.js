const canonicalOrderCode = (value) => (
  typeof value === 'string' ? value.trim().toLowerCase() : ''
)

export const isValidCancellationScope = (order) => {
  const scope = order?.cancellation_scope
  const orderId = order?.id
  const canonicalOrderCodes = Array.isArray(scope?.order_codes)
    ? scope.order_codes.map(canonicalOrderCode)
    : []

  return order?.can_cancel === true
    && Number.isInteger(orderId)
    && orderId > 0
    && ['single_order', 'checkout_session'].includes(scope?.type)
    && Number.isInteger(scope?.count)
    && scope.count > 0
    && (scope.type !== 'single_order' || scope.count === 1)
    && Array.isArray(scope.order_ids)
    && Array.isArray(scope.order_codes)
    && scope.count === scope.order_ids.length
    && scope.count === scope.order_codes.length
    && scope.order_ids.every(id => Number.isInteger(id) && id > 0)
    && new Set(scope.order_ids).size === scope.order_ids.length
    && canonicalOrderCodes.every(code => code.length > 0)
    && new Set(canonicalOrderCodes).size === canonicalOrderCodes.length
    && scope.order_ids.includes(orderId)
}

export const canCancelOrder = (order) => isValidCancellationScope(order)

export const formatCancellationScope = (scope) => (
  scope.order_codes.map(code => `#${code}`).join(', ')
)
