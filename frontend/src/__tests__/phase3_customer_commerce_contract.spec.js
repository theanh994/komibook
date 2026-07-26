import { describe, expect, it } from 'vitest'
import {
  ApiContractError,
  readApiData,
  readApiList,
  readApiPagination,
} from '@/services/apiContract'

describe('Phase 3D.1 customer commerce API contract', () => {
  it('reads canonical object and collection responses', () => {
    expect(readApiData({
      status: 'success',
      data: { id: 10 },
    })).toEqual({ id: 10 })

    expect(readApiList({
      status: 'success',
      data: [{ id: 10 }],
    })).toEqual([{ id: 10 }])
  })

  it('reads Laravel pagination metadata without changing the data envelope', () => {
    const payload = {
      status: 'success',
      data: [{ id: 10 }],
      meta: { current_page: 1, per_page: 12, total: 1 },
    }

    expect(readApiList(payload)).toHaveLength(1)
    expect(readApiPagination(payload)).toEqual({
      current_page: 1,
      per_page: 12,
      total: 1,
    })
  })

  it.each([
    null,
    [],
    { data: [] },
    { status: 'error', message: 'Nope' },
    { status: 'success' },
  ])('rejects malformed or unsuccessful envelopes: %j', (payload) => {
    expect(() => readApiData(payload)).toThrow(ApiContractError)
  })

  it('rejects non-array collection data and missing pagination metadata', () => {
    expect(() => readApiList({
      status: 'success',
      data: { id: 10 },
    })).toThrow('must be an array')

    expect(() => readApiPagination({
      status: 'success',
      data: [],
    })).toThrow('missing meta')
  })
})
