import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

// Capa 1 · Cliente React (SPA).
//   VITE_BASE     subcarpeta donde se publica el sitio (p. ej. /inf513/grupoXXsa/proyecto2/)
//   VITE_API_URL  URL de la API Laravel. En desarrollo se usa el proxy /api -> localhost:8000
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  return {
    base: env.VITE_BASE || '/',
    plugins: [react(), tailwindcss()],
    server: {
      port: 5173,
      proxy: {
        '/api': { target: env.VITE_PROXY_API || 'http://127.0.0.1:8000', changeOrigin: true },
      },
    },
    build: { chunkSizeWarningLimit: 900 },
  }
})
