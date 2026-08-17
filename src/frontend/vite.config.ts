import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'https://mein.ditib-krefeld.info',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: '../public/frontend',
    emptyOutDir: true,
  },
})
