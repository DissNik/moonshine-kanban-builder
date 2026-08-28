import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
    ],
    publicDir: false,
    build: {
        emptyOutDir: true,
        manifest: 'manifest.json',
        rollupOptions: {
            input: 'resources/js/script.js',
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
