import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: [
                'resources/views/**/*.blade.php',
                'app/**/*.php',
                'routes/**/*.php',
            ],
        }),
    ],
    server: {
        host: '127.0.0.1',
        port: 5173,
        https: false,
        hmr: {
            host: '127.0.0.1',
            protocol: 'ws',
        },
        watch: {
            usePolling: true, // Better for some Windows environments
        }
    },
    // Enhanced build optimization for ChatCore
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor': ['axios', 'bootstrap'],
                    'echo': ['laravel-echo', 'pusher-js'],
                    'chat': ['./resources/js/chat/ChatCore.js']
                }
            }
        },
        // Better chunking for production
        chunkSizeWarningLimit: 1000,
    },
    // Better development experience
    optimizeDeps: {
        include: ['axios', 'bootstrap'],
        exclude: ['laravel-echo'] // Let this be handled by Laravel
    },
    // Resolve aliases for cleaner imports (optional)
    resolve: {
        alias: {
            '@': '/resources/js',
            '~': '/resources'
        }
    }
});