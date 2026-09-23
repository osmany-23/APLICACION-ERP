import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    host: '127.0.0.1',
    port: 5174,
    open: '/auth/signin',
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
  build: {
    rollupOptions: {
      output: {
        // React/React DOM/React Router casi nunca cambian entre releases:
        // separarlos del resto del codigo de la app permite que el
        // navegador los deje en cache por mucho tiempo, sin tener que
        // volver a descargarlos cada vez que se despliega un cambio de
        // una pagina cualquiera. apexcharts/react-apexcharts ya quedan en
        // su propio chunk automaticamente (solo los importa el Dashboard,
        // que se carga de forma diferida), no hace falta forzarlo aqui.
        manualChunks: {
          'vendor-react': ['react', 'react-dom', 'react-router-dom'],
        },
      },
    },
  },
})
