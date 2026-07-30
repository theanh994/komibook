import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const config = readFileSync(fileURLToPath(new URL('../../vite.config.js', import.meta.url)), 'utf8')

describe('local Vite proxy configuration', () => {
  it('loads VITE variables from local environment files before selecting the backend', () => {
    expect(config).toContain('loadEnv')
    expect(config).toContain("loadEnv(mode, process.cwd(), 'VITE_')")
    expect(config).toContain('env.VITE_BACKEND_URL')
  })

  it('rewrites production cookie attributes only when local cookie mode is enabled', () => {
    expect(config).toContain("env.VITE_LOCAL_COOKIE_MODE === 'true'")
    expect(config).toContain("proxyResponse.headers['set-cookie']")
    expect(config).toContain("replace(/;\\s*domain=[^;]+/i, '')")
    expect(config).toContain("replace(/;\\s*secure/gi, '')")
  })

  it('presents local proxied requests as a configured Sanctum stateful origin', () => {
    expect(config).toContain('env.VITE_LOCAL_STATEFUL_ORIGIN || backendTarget')
    expect(config).toContain("proxyRequest.setHeader('origin', statefulOrigin)")
    expect(config).toContain("proxyRequest.setHeader('referer'")
  })
})
