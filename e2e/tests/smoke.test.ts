import { test, expect } from './fixtures';

test('visitor can open the public page and reach the login form', async ({ page }, testInfo) => {
    const response = await page.goto('/');

    expect(response, 'The homepage should return a response').not.toBeNull();
    expect(response!.status()).toBe(200);

    await expect(page.locator('body')).toBeVisible();
    await page.getByRole('link', { name: 'Masuk', exact: true }).click();

    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('heading', { name: /^(Log in to your account|Masuk ke akun Anda)$/ })).toBeVisible();
    await expect(page.getByLabel(/^(Email address|Alamat email)$/)).toBeVisible();
    await expect(page.getByLabel(/^(Password|Kata sandi)$/)).toBeVisible();
    await expect(page.getByRole('button', { name: /^(Log in|Masuk)$/ })).toBeVisible();

    await page.screenshot({ path: testInfo.outputPath('login.png'), fullPage: true });
});

test('guest visiting the protected dashboard is redirected to login', async ({ page }) => {
    const response = await page.goto('/dashboard');

    expect(response, 'The dashboard request should return a response').not.toBeNull();
    expect(response!.status()).toBe(200);
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('button', { name: /^(Log in|Masuk)$/ })).toBeVisible();
});
