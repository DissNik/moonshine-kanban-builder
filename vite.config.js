import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
    ],
    build: {
        emptyOutDir: false,
        manifest: 'manifest.json',
        rollupOptions: {
            input: ['resources/js/script.js', 'resources/css/stylesheet.css'],
            output: {
                entryFileNames: `js/script.js`,
                assetFileNames: file => {
                    let ext = file.name.split('.').pop()
                    if (ext === 'css') {
                        return 'css/stylesheet.css'
                    }
                }
            }
        },
        outDir: 'public',
    },
});
