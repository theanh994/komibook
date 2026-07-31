/* global process */
import { fileURLToPath, URL } from 'node:url'

import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), 'VITE_')
  const backendTarget = env.VITE_BACKEND_URL || 'http://127.0.0.1:8000'
  const mediaTarget = env.VITE_MEDIA_URL || backendTarget
  const cookieDomainRewrite = env.VITE_COOKIE_DOMAIN
    ? { cookieDomainRewrite: env.VITE_COOKIE_DOMAIN }
    : {}
  const localCookieProxy = env.VITE_LOCAL_COOKIE_MODE === 'true'
    ? {
        configure(proxy) {
          proxy.on('proxyReq', (proxyRequest) => {
            const statefulOrigin = env.VITE_LOCAL_STATEFUL_ORIGIN || backendTarget

            proxyRequest.setHeader('origin', statefulOrigin)
            proxyRequest.setHeader('referer', `${statefulOrigin.replace(/\/$/, '')}/`)
          })

          proxy.on('proxyRes', (proxyResponse) => {
            const cookies = proxyResponse.headers['set-cookie']
            if (!cookies) return

            proxyResponse.headers['set-cookie'] = cookies.map((cookie) => cookie
              .replace(/;\s*domain=[^;]+/i, '')
              .replace(/;\s*secure/gi, ''))
          })
        },
      }
    : {}

  return {
    plugins: [
      tailwindcss(),
      vue(),
    ],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url))
      },
    },
    server: {
      allowedHosts: ['komibook.id.vn'],
      proxy: {
        '/api': {
          target: backendTarget,
          changeOrigin: true,
          headers: { Accept: 'application/json' },
          ...cookieDomainRewrite,
          ...localCookieProxy,
        },
        '/sanctum': {
          target: backendTarget,
          changeOrigin: true,
          headers: { Accept: 'application/json' },
          ...cookieDomainRewrite,
          ...localCookieProxy,
        },
        '/storage': {
          target: mediaTarget,
          changeOrigin: true,
          ...cookieDomainRewrite,
        }
      }
    }
  }
})
