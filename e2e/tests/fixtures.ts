import { createHash } from 'node:crypto';
import { expect, test as base } from '@playwright/test';

export const test = base.extend<{ browserDiagnostics: string[]; simulatedClientIp: string }>({
    simulatedClientIp: async ({}, use, testInfo) => {
        const digest = createHash('sha256').update(`${testInfo.project.name}|${testInfo.testId}`).digest();
        const clientIp = `198.18.${1 + (digest[0] % 254)}.${1 + (digest[1] % 254)}`;

        await use(clientIp);
    },
    context: async ({ context, simulatedClientIp }, use) => {
        const appOrigin = new URL(process.env.APP_URL ?? 'http://localhost:9200').origin;

        await context.route(`${appOrigin}/**`, (route) =>
            route.continue({
                headers: {
                    ...route.request().headers(),
                    'x-forwarded-for': simulatedClientIp,
                },
            }),
        );

        await use(context);
    },
    browserDiagnostics: async ({ page }, use) => {
        const diagnostics: string[] = [];

        page.on('console', (message) => {
            if (message.type() === 'error') {
                diagnostics.push(`console error: ${message.text()}`);
            }
        });

        page.on('pageerror', (error) => {
            diagnostics.push(`uncaught page error: ${error.message}`);
        });

        page.on('requestfailed', (request) => {
            diagnostics.push(
                `request failed: ${request.method()} ${request.url()} (${request.failure()?.errorText ?? 'unknown error'})`,
            );
        });

        page.on('request', (request) => {
            if (!['GET', 'HEAD'].includes(request.method())) {
                diagnostics.push(`unexpected non-read request: ${request.method()} ${request.url()}`);
            }
        });

        page.on('response', (response) => {
            if (response.status() >= 400) {
                diagnostics.push(`HTTP ${response.status()}: ${response.request().method()} ${response.url()}`);
            }
        });

        await use(diagnostics);
    },
});

test.afterEach(async ({ browserDiagnostics }, testInfo) => {
    if (browserDiagnostics.length > 0) {
        await testInfo.attach('browser-diagnostics', {
            body: browserDiagnostics.join('\n'),
            contentType: 'text/plain',
        });
    }
});

export { expect };
