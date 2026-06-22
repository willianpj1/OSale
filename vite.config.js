import { defineConfig } from 'vite';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const HOT_FILE = resolve(__dirname, 'public/hot');

function buildPageEntries() {
    const pagesDir = resolve(__dirname, 'resources/js/pages');
    return Object.fromEntries(
        fs.readdirSync(pagesDir, { recursive: true })
            .filter(file => String(file).endsWith('.js'))
            .map(file => {
                const normalized = String(file).replace(/\\/g, '/');
                return [
                    `pages/${normalized.replace(/\.js$/, '')}`,
                    resolve(pagesDir, normalized)
                ];
            })
    );
}

function buildCssEntries() {
    const cssRoot = resolve(__dirname, 'resources/css');
    const entries = {};

    if (!fs.existsSync(cssRoot)) return entries;

    fs.readdirSync(cssRoot, { recursive: true })
        .filter(file => String(file).endsWith('.css'))
        .forEach(file => {
            const normalized = String(file).replace(/\\/g, '/');
            if (normalized === 'app.css') return;
            const key = `css/${normalized.replace(/\.css$/, '')}`;
            entries[key] = resolve(cssRoot, normalized);
        });

    return entries;
}

function writeHotFilePlugin() {
    return {
        name: 'OSale-write-hot-file',
        apply: 'serve',
        configureServer(server) {
            server.httpServer?.once('listening', () => {
                const address  = server.httpServer.address();
                const protocol = server.config.server.https ? 'https' : 'http';
                const host =
                    typeof address === 'string'
                        ? address
                        : address.address === '::' || address.address === '0.0.0.0'
                            ? 'localhost'
                            : address.address;
                const port = address.port;
                fs.writeFileSync(HOT_FILE, `${protocol}://${host}:${port}`);
            });

            const cleanup = () => {
                if (fs.existsSync(HOT_FILE)) fs.unlinkSync(HOT_FILE);
            };
            process.on('exit',   cleanup);
            process.on('SIGINT',  () => { cleanup(); process.exit(); });
            process.on('SIGTERM', () => { cleanup(); process.exit(); });
            process.on('SIGHUP',  () => { cleanup(); process.exit(); });
        }
    };
}

// Módulos que DEVEM ficar no chunk do app e nunca serem duplicados
const JQUERY_ECOSYSTEM = [
    'jquery',
    'select2',
    'datatables.net',
    'datatables.net-bs5',
    'datatables.net-responsive-bs5',
    'datatables.net-staterestore-bs5',
    'jquery-validation',
];

export default defineConfig(({ command }) => ({
    base: command === 'build' ? '/assets/' : '/',

    // Força resolução única do jQuery em todo o projeto
    resolve: {
        alias: {
            jquery: resolve(__dirname, 'node_modules/jquery/dist/jquery.js'),
        },
        dedupe: ['jquery', 'select2'],
    },

    build: {
        manifest:    'manifest.json',
        outDir:      'public/assets',
        emptyOutDir: true,
        sourcemap:   false,
        cssCodeSplit: true,
        rolldownOptions: {
            input: {
                style: resolve(__dirname, 'resources/css/app.css'),
                app:   resolve(__dirname, 'resources/js/app.js'),
                ...buildPageEntries(),
                ...buildCssEntries()
            },
            output: {
                entryFileNames: '[name]-[hash].js',
                chunkFileNames: 'chunks/[name]-[hash].js',
                assetFileNames: (assetInfo) => {
                    const name = assetInfo.name ?? '';
                    if (/\.(png|jpe?g|gif|svg|webp|ico)$/i.test(name)) {
                        return 'images/[name]-[hash][extname]';
                    }
                    if (/\.(woff2?|ttf|otf|eot)$/i.test(name)) {
                        return 'fonts/[name]-[hash][extname]';
                    }
                    return 'assets/[name]-[hash][extname]';
                },
                // jQuery e plugins ficam SEMPRE no mesmo chunk — nunca duplicam
                manualChunks(id) {
                    if (JQUERY_ECOSYSTEM.some(pkg => id.includes(`/node_modules/${pkg}/`))) {
                        return 'jquery-bundle';
                    }
                },
            }
        }
    },
    server: {
        host:       '0.0.0.0',
        port:       5173,
        strictPort: true,
        cors:       true,
        origin:     'http://localhost:5173',
        hmr: {
            host:     'localhost',
            protocol: 'ws'
        }
    },
    plugins: [
        writeHotFilePlugin()
    ]
}));