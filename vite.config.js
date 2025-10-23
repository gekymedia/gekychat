import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
               'resources/css/app.css',
                'resources/css/chat-events.css', // Add this line
                'resources/js/app.js',
                'resources/js/chat-events.js', // Add this line
            ],
            refresh: true,
        }),
    ],
    server: {
        host: '127.0.0.1', // Add this line
        port: 5173, // Explicit port
        https: false, // ← FORCE HTTP
        hmr: {
            host: '127.0.0.1',
            protocol: 'ws', // Explicit WebSocket protocol
        },
    },
    // Add build optimization
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor': ['axios', 'laravel-echo']
                }
            }
        }
    }
});