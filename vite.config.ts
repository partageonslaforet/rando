import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { resolve } from 'path'
import tsconfigPaths from 'vite-tsconfig-paths'
import type { ViteDevServer } from 'vite'
import type { IncomingMessage, ServerResponse } from 'http'

export default defineConfig({
  plugins: [
    react(), 
    tsconfigPaths(),
    {
      name: 'spa-fallback',
      configureServer(server: ViteDevServer) {
        return () => {
          server.middlewares.use((req: IncomingMessage, res: ServerResponse, next) => {
            const url = req.url || '';
            
            // Si c'est un fichier statique ou une route d'API, continuer normalement
            if (url.includes('.') || url.startsWith('/@') || url.startsWith('/api/')) {
              next();
              return;
            }
            
            // Pour toutes les autres routes, servir index.html
            req.url = '/';
            next();
          });
        };
      }
    }
  ],
  build: {
    sourcemap: true,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'index.html')
      }
    }
  },
  server: {
    host: true,
    port: 3000,
    strictPort: true
  }
})