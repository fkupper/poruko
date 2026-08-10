import path from "path";
import { fileURLToPath } from "url";
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import { defineConfig } from "vitest/config";

const dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    plugins: [react(), tailwindcss()],
    resolve: {
        alias: {
            '@': path.resolve(dirname, './src'),
        },
    },
    server: {
        host: true, // needed for Docker to expose the port
        watch: {
            usePolling: true, // Needed for Docker on Mac to detect file changes reliably
        },
        proxy: {
            '/api': {
                // In Docker: traffic goes to the `api` service on the internal network.
                // On host (npm run web:dev): falls back to localhost:8000.
                target: process.env.VITE_API_PROXY_TARGET ?? 'http://localhost:8000',
                changeOrigin: true,
            },
        },
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['./src/test/setup.ts'],
    },
});
