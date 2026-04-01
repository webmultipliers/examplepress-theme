import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  build: {
    manifest: true,
    outDir: 'dist',
    rollupOptions: {
      input: {
        dashboard: resolve(__dirname, 'assets/src/dashboard/main.js'),
        theme: resolve(__dirname, 'assets/src/theme/main.js'),
        build: resolve(__dirname, 'assets/src/build/main.js'),
        reference: resolve(__dirname, 'assets/src/reference/main.js'),
      },
    },
  },
  server: {
    origin: 'http://localhost:5173',
  },
});
