import { defineConfig } from 'vite';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const HOT_FILE = resolve(__dirname, 'public/hot');

function writeHotFilePlugin() {
    return {
        name: 'jaiminho-write-hot-file',
        apply: 'serve',
        configureServer(server) {
            server.httpServer?.once('listening', () => {
                const address = server.httpServer.address()
                const protocol = server.config.server.https ? 'https' : 'http'
                const host =
                    typeof address === 'string'
                        ? address
                        : address.address === '::' || address.address === '0.0.0.0'
                            ? 'localhost'
                            : address.address
                const port = address.port
                fs.writeFileSync(HOT_FILE, `${protocol}://${host}:${port}`)
            });
            const cleanup = () => {
                if (fs.existsSync(HOT_FILE)) fs.unlinkSync(HOT_FILE)
            }
            process.on('exit', cleanup)
            process.on('SIGINT', () => { cleanup(); process.exit() });
            process.on('SIGTERM', () => { cleanup(); process.exit() });
            process.on('SIGHUP', () => { cleanup(); process.exit() });
        }
    }
}

export default defineConfig(({ command }) => ({
    base: command === 'build' ? '/assets/' : '/',

    build: {
        manifest: 'manifest.json',
        outDir: 'public/assets',
        emptyOutDir: true,
        sourcemap: false,
        cssCodeSplit: true,
        rolldownOptions: {
            input: {
                app: resolve(__dirname, 'resources/js/app.js'),
                'home-css': resolve(__dirname, 'resources/css/home.css'),
                'css/customer': resolve(__dirname, 'resources/css/pages/customer.css'),
                'css/sale': resolve(__dirname, 'resources/css/pages/sale.css'),
                'css/supplier': resolve(__dirname, 'resources/css/pages/supplier.css'),
                'css/product': resolve(__dirname, 'resources/css/pages/product.css'),
                'pages/customer': resolve(__dirname, 'resources/js/pages/customer.js'),
                'pages/enterprise': resolve(__dirname, 'resources/js/pages/supplier.js'),
                'pages/list-customer': resolve(__dirname, 'resources/js/pages/list-customer.js'),
                'pages/list-product': resolve(__dirname, 'resources/js/pages/list-product.js'),
                'pages/list-supplier': resolve(__dirname, 'resources/js/pages/list-supplier.js'),
                'pages/list-users': resolve(__dirname, 'resources/js/pages/list-users.js'),
                'pages/login': resolve(__dirname, 'resources/js/pages/login.js'),
                'pages/product': resolve(__dirname, 'resources/js/pages/product.js'),
                'pages/register': resolve(__dirname, 'resources/js/pages/register.js'),
                'pages/sale': resolve(__dirname, 'resources/js/pages/sale.js'),
                'pages/service-order': resolve(__dirname, 'resources/js/pages/service-order.js'),
                'pages/list-service-order': resolve(__dirname, 'resources/js/pages/list-service-order.js'),
                'pages/list-sale': resolve(__dirname, 'resources/js/pages/list-sale.js'),
                'pages/list-supplier': resolve(__dirname, 'resources/js/pages/list-supplier.js'),
                'pages/list-service': resolve(__dirname, 'resources/js/pages/list-service.js'),
                'pages/supplier': resolve(__dirname, 'resources/js/pages/supplier.js'),
                'pages/users': resolve(__dirname, 'resources/js/pages/users.js'),
            },
            output: {
                entryFileNames: '[name]-[hash].js',
                chunkFileNames: 'chunks/[name]-[hash].js',
                assetFileNames: (assetInfo) => {
                    const name = assetInfo.name ?? ''
                    if (/\.(png|jpe?g|gif|svg|webp|ico)$/i.test(name)) {
                        return 'images/[name]-[hash][extname]'
                    }
                    if (/\.(woff2?|ttf|otf|eot)$/i.test(name)) {
                        return 'fonts/[name]-[hash][extname]'
                    }
                    return 'assets/[name]-[hash][extname]'
                }
            }
        }
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        cors: true,
        origin: 'http://localhost:5173',
        hmr: {
            host: 'localhost',
            protocol: 'ws'
        }
    },
    plugins: [
        writeHotFilePlugin()
    ]
}));