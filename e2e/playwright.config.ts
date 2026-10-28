import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.APP_URL ?? 'http://localhost:9200';
const appUrl = new URL(baseURL);
const isLoopback =
    appUrl.hostname === 'localhost' || appUrl.hostname === '[::1]' || /^127(?:\.\d{1,3}){3}$/.test(appUrl.hostname);
const isHttp = appUrl.protocol === 'http:' || appUrl.protocol === 'https:';
const isolatedTarget = appUrl.port === '9205';
const developmentTarget =
    process.env.RFIS_E2E_DEV_TARGET === 'rf-is-dev' && appUrl.protocol === 'http:' && appUrl.port === '9200';
const productionAuditTarget =
    process.env.RFIS_E2E_PROD_TARGET === 'rf-is-prod' &&
    appUrl.hostname === 'localhost' &&
    appUrl.protocol === 'https:' &&
    appUrl.port === '9208';

if (
    !isLoopback ||
    !isHttp ||
    (!isolatedTarget && !developmentTarget && !productionAuditTarget) ||
    appUrl.username ||
    appUrl.password
) {
    throw new Error(
        'APP_URL must target isolated port 9205, guarded development port 9200, or loopback production audit port 9208.',
    );
}

export default defineConfig({
    testDir: './tests',
    fullyParallel: false,
    retries: 0,
    workers: 1,
    outputDir: 'test-results',

    reporter: [['list'], ['html', { outputFolder: 'playwright-report', open: 'never' }]],

    use: {
        baseURL: appUrl.toString(),
        browserName: 'chromium',
        headless: process.env.PW_HEADED !== '1',
        ignoreHTTPSErrors: productionAuditTarget,
        testIdAttribute: 'data-test',
        actionTimeout: 10_000,
        navigationTimeout: 30_000,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        video: 'retain-on-failure',
    },

    projects: [
        {
            name: 'desktop-chromium',
            testMatch: /(?:smoke|role-workflows)\.test\.ts/,
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'mobile-small-chromium',
            testMatch: /mobile-workflows\.test\.ts/,
            use: { ...devices['Pixel 7'], viewport: { width: 360, height: 800 } },
        },
        {
            name: 'mobile-large-chromium',
            testMatch: /mobile-workflows\.test\.ts/,
            use: { ...devices['Pixel 7'], viewport: { width: 412, height: 915 } },
        },
    ],
});
