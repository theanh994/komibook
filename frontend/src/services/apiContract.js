export class ApiContractError extends Error {
  constructor(message, payload) {
    super(message)
    this.name = 'ApiContractError'
    this.payload = payload
  }
}

const requireSuccessEnvelope = (payload) => {
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
    throw new ApiContractError('API response must be a JSON object.', payload)
  }

  if (payload.status !== 'success') {
    throw new ApiContractError('API response is missing the success status.', payload)
  }

  if (!Object.prototype.hasOwnProperty.call(payload, 'data')) {
    throw new ApiContractError('API success response is missing data.', payload)
  }

  return payload
}

export const readApiData = (payload) => requireSuccessEnvelope(payload).data

export const readApiList = (payload) => {
  const data = readApiData(payload)

  if (!Array.isArray(data)) {
    throw new ApiContractError('API collection response data must be an array.', payload)
  }

  return data
}

export const readApiPagination = (payload) => {
  requireSuccessEnvelope(payload)

  if (!payload.meta || typeof payload.meta !== 'object') {
    throw new ApiContractError('API paginated response is missing meta.', payload)
  }

  return payload.meta
}
