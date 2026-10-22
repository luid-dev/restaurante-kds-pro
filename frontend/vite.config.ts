import path from 'node:path'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

// En desarrollo el SPA y la API comparten origen a través del proxy de Vite,
// igual que en producción detrás del reverse proxy: cookies de sesión first-party
// (Sanctum SPA) sin CORS con credenciales.
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const apiTarget = env.DEV_API_TARGET ?? 'http://localhost:8000'

  return {
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: {
        '@': path.resolve(import.meta.dirname, './src'),
      },
    },
    build: {
      rolldownOptions: {
        output: {
          // Vendors separados por ritmo de cambio: mejor caché en las terminales tras cada despliegue.
          codeSplitting: {
            groups: [
              { name: 'vendor-react', test: /node_modules[\\/](react|react-dom|scheduler)[\\/]/ },
              { name: 'vendor-realtime', test: /node_modules[\\/](pusher-js|laravel-echo)[\\/]/ },
              { name: 'vendor-data', test: /node_modules[\\/](axios|zod|zustand|@tanstack)[\\/]/ },
              { name: 'vendor-dates', test: /node_modules[\\/](react-day-picker|date-fns|@date-fns)[\\/]/ },
              {
                name: 'vendor-ui',
                test: /node_modules[\\/](radix-ui|@radix-ui|@floating-ui|sonner|lucide-react|cn|class-variance-authority|next-themes)[\\/]/,
              },
            ],
          },
        },
      },
    },
    server: {
      port: 5173,
      strictPort: true,
      proxy: {
        '/api': { target: apiTarget, changeOrigin: false, xfwd: true },
        '/sanctum': { target: apiTarget, changeOrigin: false, xfwd: true },
      },
    },
  }
})
