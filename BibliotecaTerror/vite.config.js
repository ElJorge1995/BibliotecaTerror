import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
    strictPort: true,
    // Proxy de desarrollo: en producción frontend y backends viven en el
    // mismo dominio bajo /auth y /api. En dev redirigimos esas rutas a los
    // servidores PHP locales para que el frontend use las mismas URLs en
    // ambos entornos.
    proxy: {
      '/auth': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
        // El backend de libros vive en libros_api.php directamente, sin
        // prefijo /api, así que lo eliminamos al hacer proxy.
        rewrite: (path) => path.replace(/^\/api/, ''),
      },
    },
  },
})
