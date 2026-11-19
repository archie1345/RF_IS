import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig, loadEnv } from 'vite';

function parseBoolean(value: string | undefined, fallback: boolean): boolean {
    if (value === undefined || value.trim() === '') {
        return fallback;
    }

    return ['true', '1', 'yes', 'on'].includes(value.trim().toLowerCase());
}

function parsePort(value: string | undefined, fallback: number): number {
    const port = Number(value);

    return Number.isInteger(port) && port >= 1 && port <= 65535 ? port : fallback;
}

function parseUrl(value: string, variableName: string): URL {
    try {
        return new URL(value);
    } catch {
        throw new Error(`${variableName} must be a valid absolute URL. Received: ${value}`);
    }
}

export default defineConfig(({ command, mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    const isDevServer = command === 'serve';
    const skipWayfinder = parseBoolean(env.SKIP_WAYFINDER, false);

    const rawAppUrl = env.APP_URL?.trim() || (env.VERCEL_URL ? `https://${env.VERCEL_URL}` : 'http://localhost:9200');
    const appUrl = parseUrl(rawAppUrl, 'APP_URL');

    const vitePort = parsePort(env.VITE_PORT, 9201);

    const publicViteUrl = parseUrl(
        env.VITE_DEV_SERVER_URL?.trim() || `${appUrl.protocol}//${appUrl.hostname}:${vitePort}`,
        'VITE_DEV_SERVER_URL',
    );

    const hmrEnabled = parseBoolean(env.VITE_HMR_ENABLED, isDevServer);

    return {
        plugins: [
            laravel({
                input: ['resources/js/app.ts'],
                ssr: 'resources/js/ssr.ts',
                refresh: isDevServer,
            }),

            tailwindcss(),

            ...(skipWayfinder
                ? []
                : [
                      wayfinder({
                          formVariants: true,
                      }),
                  ]),

            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
        ],

        server: {
            host: env.VITE_HOST || '0.0.0.0',
            port: vitePort,
            strictPort: true,
            origin: publicViteUrl.origin,

            cors: {
                origin: appUrl.origin,
                credentials: true,
            },

            hmr: hmrEnabled
                ? {
                      host: publicViteUrl.hostname,
                      protocol: publicViteUrl.protocol === 'https:' ? 'wss' : 'ws',
                      clientPort: parsePort(publicViteUrl.port, publicViteUrl.protocol === 'https:' ? 443 : 80),
                  }
                : false,

            watch: {
                usePolling: true,
                ignored: [
                    '**/app/**',
                    '**/bootstrap/cache/**',
                    '**/database/**',
                    '**/e2e/**',
                    'routes/**',
                    '**/storage/**',
                    '**/tests/**',
                    '**/vendor/**',
                ],
            },
        },
    };
});
