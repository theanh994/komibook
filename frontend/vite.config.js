/* global process */
import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    tailwindcss(),
    vue(),
    vueDevTools(),
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
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        headers: { Accept: 'application/json' },
        ...(process.env.VITE_COOKIE_DOMAIN ? { cookieDomainRewrite: process.env.VITE_COOKIE_DOMAIN } : {})
      },
      '/sanctum': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        headers: { Accept: 'application/json' },
        ...(process.env.VITE_COOKIE_DOMAIN ? { cookieDomainRewrite: process.env.VITE_COOKIE_DOMAIN } : {})
      },
      '/storage': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        ...(process.env.VITE_COOKIE_DOMAIN ? { cookieDomainRewrite: process.env.VITE_COOKIE_DOMAIN } : {})
      }
    }
  }
})
