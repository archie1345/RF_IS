import { request, type Locator, type Page } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { toDataURL } from 'qrcode';
import { accounts, allRoles, navigation, type NavigationPage } from './role-coverage';
import { expect, test } from './fixtures';

function generateTotp(base32Secret: string, timestampMs = Date.now()): string {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    const clean = base32Secret.toUpperCase().replace(/[^A-Z2-7]/g, '');
    let bits = '';
    for (const char of clean) {
        bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
    }
    const keyBytes: number[] = [];
    for (let i = 0; i + 8 <= bits.length; i += 8) {
        keyBytes.push(parseInt(bits.slice(i, i + 8), 2));
    }
    const counter = Math.floor(timestampMs / 1000 / 30);
    const counterBuffer = Buffer.alloc(8);
    counterBuffer.writeBigUInt64BE(BigInt(counter));
    const hmac = createHmac('sha1', Buffer.from(keyBytes)).update(counterBuffer).digest();
    const offset = hmac[hmac.length - 1] & 0x0f;
    const code =
        (((hmac[offset] & 0x7f) << 24) |
            ((hmac[offset + 1] & 0xff) << 16) |
            ((hmac[offset + 2] & 0xff) << 8) |
            (hmac[offset + 3] & 0xff)) %
        1_000_000;
    return String(code).padStart(6, '0');
}

const sectionNames: Record<NavigationPage['section'], RegExp> = {
    Utama: /^(Utama|Home)$/,
    'Latihan & Absensi': /^(Latihan & Absensi|Training & attendance)$/,
    'Keuangan & Pengguna': /^(Keuangan & Pengguna|Finance & users)$/,
    'Kompetisi & Informasi': /^(Kompetisi & Informasi|Competitions & information)$/,
};

const diagnosticsByPage = new WeakMap<Page, string[]>();

test.beforeEach(async ({ page }) => {
    const diagnostics: string[] = [];
    diagnosticsByPage.set(page, diagnostics);

    page.on('console', (message) => {
        if (message.type() === 'error') diagnostics.push(`console error: ${message.text()}`);
    });
    page.on('pageerror', (error) => diagnostics.push(`uncaught page error: ${error.message}`));
    page.on('requestfailed', (request) => {
        if (!request.url().startsWith('ws:') && !request.url().startsWith('wss:')) {
            diagnostics.push(
                `request failed: ${request.method()} ${request.url()} (${request.failure()?.errorText ?? 'unknown'})`,
            );
        }
    });
    page.on('response', (response) => {
        if (response.status() >= 400)
            diagnostics.push(`HTTP ${response.status()}: ${response.request().method()} ${response.url()}`);
    });

    await page.setViewportSize({ width: 1440, height: 1000 });
});

test.afterEach(async ({ page }, testInfo) => {
    const diagnostics = diagnosticsByPage.get(page) ?? [];

    if (diagnostics.length > 0) {
        await testInfo.attach('browser-diagnostics', {
            body: diagnostics.join('\n'),
            contentType: 'text/plain',
        });
    }

    expect(diagnostics, 'Covered role workflows should not generate browser errors or failed requests').toEqual([]);
});

async function loginAs(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/email address|alamat email/i).fill(email);
    await page.getByLabel(/password|kata sandi/i).fill(process.env.RFIS_DEMO_PASSWORD ?? '');
    await page.getByTestId('login-button').click();
    await page.waitForURL((url) => url.pathname === '/dashboard');
    await page.goto('/locale/id');
    await page.waitForURL((url) => url.pathname === '/dashboard');
    await expect(page.locator('#app')).toBeVisible();
}

async function logout(page: Page): Promise<void> {
    await page.getByTestId('user-menu-trigger').click();
    await page.getByTestId('logout-button').click();
    await page.waitForURL((url) => url.pathname === '/');
}

async function visitNavigationPage(page: Page, item: NavigationPage): Promise<void> {
    const currentPath = new URL(page.url()).pathname.replace(/\/$/, '') || '/';
    const targetPath = item.href.replace(/\/$/, '') || '/';
    let routeLink: Locator = page.locator(`a[href="${item.href}"]`).first();

    if ((await routeLink.count()) === 0 || !(await routeLink.isVisible())) {
        const sectionButton = page.getByRole('button', { name: sectionNames[item.section] });
        if ((await sectionButton.count()) > 0) {
            await sectionButton.click();
        }
        routeLink = page.locator(`a[href="${item.href}"]`).first();
    }

    await expect(routeLink).toBeVisible();
    const responsePromise = page.waitForResponse((response) => {
        const responsePath = new URL(response.url()).pathname.replace(/\/$/, '') || '/';
        return response.request().method() === 'GET' && responsePath === targetPath;
    });
    await routeLink.click();
    const response = await responsePromise;
    expect(response.status(), `Navigation to ${item.href} should succeed`).toBe(200);

    if (currentPath !== targetPath) {
        await page.waitForURL((url) => url.pathname.replace(/\/$/, '') === targetPath);
    }

    await expect(page.locator('main')).toBeVisible();

    const registrationInvoiceDialog = page
        .getByRole('dialog')
        .filter({ hasText: /Tagihan pendaftaran tersedia|Registration payment available/i });
    if (await registrationInvoiceDialog.isVisible()) {
        await registrationInvoiceDialog.getByRole('button', { name: /Tutup dialog|Close dialog/i }).click();
        await expect(registrationInvoiceDialog).toBeHidden();
    }
}

async function expectForbiddenAnnouncementWrite(page: Page): Promise<void> {
    const csrfCookie = (await page.context().cookies()).find(({ name }) => name === 'XSRF-TOKEN');
    expect(csrfCookie, 'An authenticated browser session should have a CSRF cookie').toBeDefined();
    const response = await page.context().request.post(new URL('/announcements', page.url()).toString(), {
        failOnStatusCode: false,
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(csrfCookie?.value ?? ''),
        },
        data: {
            title: 'Forbidden E2E announcement',
            message: 'This request must never create a record.',
            target_role: 'ALL',
        },
    });

    expect(response.status(), 'Server authorization must reject a direct unauthorized mutation').toBe(403);
}

for (const account of accounts) {
    test(`${account.name} account receives the role union and can visit every authorized navigation page`, async ({
        page,
    }) => {
        test.setTimeout(120_000);
        await loginAs(page, account.email);

        const authorizedPages = navigation.filter((item) => item.roles.some((role) => account.roles.includes(role)));
        for (const item of authorizedPages) {
            await visitNavigationPage(page, item);
        }

        const restrictedPages = [
            ...(!account.roles.includes('admin')
                ? ['/admin', '/admin/attendance', '/admin/groups', '/admin/billing-settings', '/users']
                : []),
            ...(!account.roles.includes('admin') && !account.roles.includes('coach') ? ['/sessions'] : []),
            ...(!account.roles.includes('admin')
                ? ['/admin/data-export/download?datasets%5B%5D=users&fields%5Busers%5D%5B%5D=name']
                : []),
        ];

        for (const path of restrictedPages) {
            const response = await page
                .context()
                .request.get(new URL(path, page.url()).toString(), { failOnStatusCode: false });
            expect(response.status(), `${account.name} must be denied ${path}`).toBe(403);
        }

        if (!account.roles.includes('admin')) {
            await expectForbiddenAnnouncementWrite(page);
        }

        await logout(page);
        const guestResponse = await page.context().request.get(new URL('/dashboard', page.url()).toString(), {
            failOnStatusCode: false,
            maxRedirects: 0,
        });
        expect(guestResponse.status()).toBe(302);
        expect(guestResponse.headers().location).toMatch(/\/login/);
    });
}

test('athlete profile edit persists after reload and rejects an email already owned by another account', async ({
    page,
}) => {
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/settings/profile');

    const accountSection = page.getByRole('heading', { name: 'Data akun' }).locator('xpath=ancestor::section[1]');
    const nameField = accountSection.getByLabel('Nama lengkap');
    const emailField = accountSection.getByLabel('Email');
    const originalName = await nameField.inputValue();
    const originalEmail = await emailField.inputValue();
    const updatedName = `${originalName} E2E ${Date.now()}`;

    await accountSection.getByRole('button', { name: 'Ubah' }).click();
    await emailField.fill('coach@rfis.test');
    await accountSection.getByRole('button', { name: 'Simpan data akun' }).click();
    await expect(accountSection.getByText(/email.*(taken|already|terdaftar|digunakan|diambil)/i).first()).toBeVisible();
    await expect(nameField).toHaveValue(originalName);
    await page.getByRole('dialog').getByLabel('Tutup popup').click();

    await emailField.fill(originalEmail);
    await nameField.fill(updatedName);
    await accountSection.getByRole('button', { name: 'Simpan data akun' }).click();
    await expect(nameField).toHaveValue(updatedName);
    await page.reload();
    await expect(
        page
            .getByRole('heading', { name: 'Data akun' })
            .locator('xpath=ancestor::section[1]')
            .getByLabel('Nama lengkap'),
    ).toHaveValue(updatedName);

    const reloadedSection = page.getByRole('heading', { name: 'Data akun' }).locator('xpath=ancestor::section[1]');
    await reloadedSection.getByRole('button', { name: 'Ubah' }).click();
    await reloadedSection.getByLabel('Nama lengkap').fill(originalName);
    await reloadedSection.getByRole('button', { name: 'Simpan data akun' }).click();
    await expect(reloadedSection.getByLabel('Nama lengkap')).toHaveValue(originalName);
});

test('athlete manages a manual achievement and another account cannot change it', async ({ page }) => {
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/achievements');

    const achievementName = `E2E Achievement ${Date.now()}`;
    const updatedAchievementName = `${achievementName} Updated`;
    await page.getByRole('button', { name: 'Tambah prestasi' }).click();

    let dialog = page.getByRole('dialog').last();
    await dialog.getByLabel('Nama kejuaraan atau prestasi').fill(achievementName);
    await dialog.locator('#achievement-file').setInputFiles({
        name: 'invalid-achievement.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('not an allowed achievement document'),
    });
    await dialog.getByRole('button', { name: 'Simpan prestasi' }).click();
    await expect(dialog.getByText(/file.*(type|jenis)|berkas.*(jenis|format)/i)).toBeVisible();
    await expect(page.getByRole('row').filter({ hasText: achievementName })).toHaveCount(0);

    await dialog.getByRole('button', { name: 'Batal' }).click();
    await page.getByRole('button', { name: 'Tambah prestasi' }).click();
    dialog = page.getByRole('dialog').last();
    await dialog.getByLabel('Nama kejuaraan atau prestasi').fill(achievementName);
    await dialog.getByRole('button', { name: 'Medali' }).click();
    await page.getByRole('button', { name: 'Emas', exact: true }).click();
    await dialog.getByLabel('Lokasi').fill('Jakarta');
    await dialog.getByLabel('Catatan').fill('Synthetic browser fixture.');
    await dialog.getByRole('button', { name: 'Simpan prestasi' }).click();
    await expect(dialog).toBeHidden();

    let row = page.getByRole('row').filter({ hasText: achievementName });
    await expect(row).toContainText('GOLD');
    await row.getByRole('button', { name: 'Ubah' }).click();
    dialog = page.getByRole('dialog').last();
    await dialog.getByLabel('Nama kejuaraan atau prestasi').fill(updatedAchievementName);
    await dialog.getByLabel('Catatan').fill('Edited and persisted by the owner.');
    await dialog.getByRole('button', { name: 'Simpan prestasi' }).click();
    await expect(dialog).toBeHidden();
    row = page.getByRole('row').filter({ hasText: updatedAchievementName });
    await expect(row).toBeVisible();
    await page.reload();
    row = page.getByRole('row').filter({ hasText: updatedAchievementName });
    await expect(row).toBeVisible();

    const pageData = JSON.parse((await page.locator('#app').getAttribute('data-page')) ?? '{}') as {
        props?: { achievements?: Array<{ championship_name: string; route_id: string }> };
    };
    const savedAchievement = pageData.props?.achievements?.find(
        (achievement) => achievement.championship_name === updatedAchievementName,
    );
    expect(savedAchievement?.route_id, 'The page should expose the UUID route key for this record').toBeTruthy();

    await logout(page);
    await loginAs(page, 'coach@rfis.test');
    const csrfCookie = (await page.context().cookies()).find(({ name }) => name === 'XSRF-TOKEN');
    expect(csrfCookie).toBeDefined();
    const deniedUpdate = await page
        .context()
        .request.post(new URL(`/achievements/${savedAchievement!.route_id}`, page.url()).toString(), {
            failOnStatusCode: false,
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(csrfCookie?.value ?? ''),
            },
            form: {
                _method: 'PUT',
                championship_name: 'Unauthorized achievement edit',
                medal: 'GOLD',
            },
        });
    expect(deniedUpdate.status(), 'A different account must not update a private achievement').toBe(403);

    await logout(page);
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/achievements');
    row = page.getByRole('row').filter({ hasText: updatedAchievementName });
    await expect(row).toBeVisible();
    await expect(row).not.toContainText('Unauthorized achievement edit');
    await row.getByRole('button', { name: 'Hapus' }).click();
    await page
        .getByRole('dialog')
        .filter({ hasText: /Hapus prestasi\?/ })
        .getByRole('button', { name: 'Hapus prestasi' })
        .click();
    await expect(page.getByRole('row').filter({ hasText: updatedAchievementName })).toHaveCount(0);
});

test('profile settings update and delete achievement and certification records through UUID routes', async ({
    page,
}) => {
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/settings/profile');

    const achievements = page
        .getByRole('heading', { name: 'Prestasi', exact: true })
        .locator('xpath=ancestor::div[contains(@class,"rounded-xl")][1]');
    const achievementName = `E2E Profile Achievement ${Date.now()}`;
    await achievements.getByRole('button', { name: 'Tambah prestasi' }).click();
    let dialog = page.getByRole('dialog').last();
    await dialog.getByLabel('Nama kejuaraan atau prestasi').fill(achievementName);
    await dialog.getByRole('button', { name: 'Tambah prestasi' }).click();
    await expect(dialog).toBeHidden();

    let achievementRow = achievements.getByRole('row').filter({ hasText: achievementName });
    await expect(achievementRow).toBeVisible();
    await achievementRow.getByRole('button', { name: 'Ubah' }).click();
    dialog = page.getByRole('dialog').last();
    await dialog.getByLabel('Nama kejuaraan atau prestasi').fill(`${achievementName} Updated`);
    await dialog.getByRole('button', { name: 'Simpan prestasi' }).click();
    await expect(dialog).toBeHidden();
    achievementRow = achievements.getByRole('row').filter({ hasText: `${achievementName} Updated` });
    await expect(achievementRow).toBeVisible();
    await achievementRow.getByRole('button', { name: 'Hapus' }).click();
    await page
        .getByRole('dialog')
        .filter({ hasText: /Hapus prestasi\?/ })
        .getByRole('button', { name: 'Hapus prestasi' })
        .click();
    await expect(achievements.getByRole('row').filter({ hasText: `${achievementName} Updated` })).toHaveCount(0);

    const certifications = page
        .getByRole('heading', { name: 'Sertifikasi', exact: true })
        .locator('xpath=ancestor::div[contains(@class,"rounded-xl")][1]');
    const certificationName = `E2E Profile Certification ${Date.now()}`;
    await certifications.getByRole('button', { name: 'Tambah sertifikasi' }).click();
    dialog = page.getByRole('dialog').last();
    await dialog.getByLabel('Nama sertifikasi').fill(certificationName);
    await dialog.getByRole('button', { name: 'Tambah sertifikasi' }).click();
    await expect(dialog).toBeHidden();

    let certificationRow = certifications.getByRole('row').filter({ hasText: certificationName });
    await expect(certificationRow).toBeVisible();
    await certificationRow.getByRole('button', { name: 'Ubah' }).click();
    dialog = page.getByRole('dialog').last();
    await dialog.getByLabel('Nama sertifikasi').fill(`${certificationName} Updated`);
    await dialog.getByRole('button', { name: 'Simpan sertifikasi' }).click();
    await expect(dialog).toBeHidden();
    certificationRow = certifications.getByRole('row').filter({ hasText: `${certificationName} Updated` });
    await expect(certificationRow).toBeVisible();
    await certificationRow.getByRole('button', { name: 'Hapus' }).click();
    await page
        .getByRole('dialog')
        .filter({ hasText: /Hapus sertifikasi\?/ })
        .getByRole('button', { name: 'Hapus sertifikasi' })
        .click();
    await expect(certifications.getByRole('row').filter({ hasText: `${certificationName} Updated` })).toHaveCount(0);
});

test('achievement documents stay private, reject invalid files, and allow owner/admin downloads', async ({ page }) => {
    test.skip(
        process.env.RFIS_E2E_PERSISTENT_STORAGE === '1',
        'Achievement documents persist in private storage; exercise this workflow only with disposable E2E storage.',
    );

    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/achievements');
    await page.getByRole('button', { name: 'Tambah prestasi' }).click();

    const dialog = page.getByRole('dialog').last();
    const achievementName = `E2E Private Achievement ${Date.now()}`;
    await dialog.getByLabel('Nama kejuaraan atau prestasi').fill(achievementName);
    await dialog.locator('#achievement-file').setInputFiles({
        name: 'invalid-achievement.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('not a permitted certificate format'),
    });
    await dialog.getByRole('button', { name: 'Simpan prestasi' }).click();
    await expect(dialog.locator('#achievement-file').locator('xpath=..').locator('p.text-destructive')).toBeVisible();

    await dialog.locator('#achievement-file').setInputFiles({
        name: 'private-certificate.pdf',
        mimeType: 'application/pdf',
        buffer: Buffer.from('%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n'),
    });
    await dialog.getByRole('button', { name: 'Simpan prestasi' }).click();
    await expect(dialog).toBeHidden();

    const achievementRow = page.getByRole('row').filter({ hasText: achievementName });
    const documentLink = achievementRow.getByRole('link', { name: 'private-certificate.pdf' }).last();
    await expect(documentLink).toBeVisible();
    const documentUrl = await documentLink.getAttribute('href');
    expect(documentUrl).toBeTruthy();

    const ownerResponse = await page.request.get(documentUrl!);
    expect(ownerResponse.status()).toBe(200);
    expect(ownerResponse.headers()['content-disposition']).toContain('private-certificate.pdf');
    expect((await ownerResponse.body()).subarray(0, 8).toString('ascii')).toBe('%PDF-1.4');

    await logout(page);
    await loginAs(page, 'nadia@rfis.test');
    expect((await page.request.get(documentUrl!)).status()).toBe(403);

    await logout(page);
    await loginAs(page, 'admin@rfis.test');
    expect((await page.request.get(documentUrl!)).status()).toBe(200);
});

test('API user endpoint rejects browser-session and guest requests without a Sanctum token', async ({ page }) => {
    await loginAs(page, 'athlete@rfis.test');

    const sessionResponse = await page.context().request.get(new URL('/api/user', page.url()).toString(), {
        failOnStatusCode: false,
        headers: { Accept: 'application/json' },
    });
    expect(sessionResponse.status()).toBe(401);

    await logout(page);
    const guestResponse = await page.context().request.get(new URL('/api/user', page.url()).toString(), {
        failOnStatusCode: false,
        headers: { Accept: 'application/json' },
    });
    expect(guestResponse.status()).toBe(401);
});

test('admin creates a multirole account, rejects duplicate email, and updates account status', async ({ page }) => {
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/admin');

    const accountName = `E2E Multirole ${Date.now()}`;
    const accountEmail = `e2e.multirole.${Date.now()}@rfis.test`;
    await page.getByRole('button', { name: 'Add new user' }).click();
    let dialog = page.getByRole('dialog').filter({ hasText: 'Create user account' });
    await dialog.getByLabel('Full name').fill(accountName);
    await dialog.getByLabel('Email').fill(accountEmail);
    await dialog.getByLabel('Roles').click();
    const coachOption = page.getByRole('button', { name: 'Pelatih', exact: true });
    await coachOption.click();
    await page.keyboard.press('Escape');
    await expect(dialog.getByLabel('Roles')).toHaveAttribute('aria-expanded', 'false');
    await dialog.locator('#admin-status').click();
    await page.getByRole('button', { name: 'Aktif', exact: true }).click();
    await dialog.getByRole('button', { name: 'Generate' }).click();
    await dialog.getByRole('button', { name: 'Create account' }).click();
    await expect(dialog).toBeHidden();

    const search = page.getByPlaceholder(/Cari pengguna berdasarkan nama|Search users by name/i);
    await search.fill(accountEmail);
    let accountRow = page.getByRole('row').filter({ hasText: accountEmail });
    await expect(accountRow).toContainText('Coach');
    await expect(accountRow).toContainText('Athlete');
    await expect(accountRow).toContainText(/Aktif|Active/);

    await page.getByRole('button', { name: 'Add new user' }).click();
    dialog = page.getByRole('dialog').filter({ hasText: 'Create user account' });
    await dialog.getByLabel('Full name').fill(`${accountName} duplicate`);
    await dialog.getByLabel('Email').fill(accountEmail);
    await dialog.getByRole('button', { name: 'Create account' }).click();
    await expect(dialog.getByText(/email.*(already|terdaftar|digunakan|diambil)/i)).toBeVisible();
    await dialog.getByRole('button', { name: /Batal|Cancel/i }).click();
    accountRow = page.getByRole('row').filter({ hasText: accountEmail });
    await expect(accountRow).toHaveCount(1);

    await accountRow.getByRole('button', { name: 'Edit account' }).click();
    dialog = page.getByRole('dialog').filter({ hasText: 'Edit account' });
    await dialog.locator('#admin-status').click();
    await page.getByRole('button', { name: /Tidak aktif|Not active/i }).click();
    await dialog.getByRole('button', { name: 'Save changes' }).click();
    await expect(dialog).toBeHidden();
    accountRow = page.getByRole('row').filter({ hasText: accountEmail });
    await expect(accountRow).toContainText(/Tidak aktif|Not active/);
});

test('admin can soft delete, restore, and permanently delete a non-admin account', async ({ page }) => {
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/admin');

    const accountName = `E2E Lifecycle ${Date.now()}`;
    const accountEmail = `e2e.lifecycle.${Date.now()}@rfis.test`;
    await page.getByRole('button', { name: 'Add new user' }).click();
    const createDialog = page.getByRole('dialog').filter({ hasText: 'Create user account' });
    await createDialog.getByLabel('Full name').fill(accountName);
    await createDialog.getByLabel('Email').fill(accountEmail);
    await createDialog.locator('#admin-status').click();
    await page.getByRole('button', { name: /Aktif|Active/, exact: true }).click();
    await createDialog.getByRole('button', { name: 'Generate' }).click();
    await createDialog.getByRole('button', { name: 'Create account' }).click();
    await expect(createDialog).toBeHidden();

    const search = page.getByPlaceholder(/Cari pengguna berdasarkan nama|Search users by name/i);
    await search.fill(accountEmail);
    let accountRow = page.getByRole('row').filter({ hasText: accountEmail });
    await expect(accountRow).toHaveCount(1);

    await accountRow
        .getByRole('button', { name: /Hapus|Delete/ })
        .first()
        .click();
    let confirmation = page.getByRole('dialog').filter({ hasText: 'Delete this account?' });
    await confirmation.getByRole('button', { name: 'Delete account' }).click();
    await expect(confirmation).toBeHidden();
    accountRow = page.getByRole('row').filter({ hasText: accountEmail });
    await expect(accountRow.getByRole('button', { name: 'Restore' })).toBeVisible();

    await accountRow.getByRole('button', { name: 'Restore' }).click();
    accountRow = page.getByRole('row').filter({ hasText: accountEmail });
    await expect(accountRow).toContainText('-');
    await expect(accountRow.getByRole('button', { name: 'Edit account' })).toBeVisible();

    await accountRow
        .getByRole('button', { name: /Hapus|Delete/ })
        .first()
        .click();
    confirmation = page.getByRole('dialog').filter({ hasText: 'Delete this account?' });
    await confirmation.getByRole('button', { name: 'Delete account' }).click();
    await expect(confirmation).toBeHidden();
    accountRow = page.getByRole('row').filter({ hasText: accountEmail });
    await expect(accountRow.getByRole('button', { name: 'Restore' })).toBeVisible();

    await accountRow
        .getByRole('button', { name: /Hapus|Delete/ })
        .last()
        .click();
    confirmation = page.getByRole('dialog').filter({ hasText: 'Permanently delete this account?' });
    await confirmation.getByRole('button', { name: 'Permanently delete' }).click();
    await expect(confirmation).toBeHidden();
    await expect(page.getByRole('row').filter({ hasText: accountEmail })).toHaveCount(0);
});

test('admin invitations activate once, reject mismatched passwords, and permit login', async ({ page }) => {
    test.skip(
        process.env.RFIS_E2E_DEV_TARGET !== 'rf-is-dev',
        'Invitation email delivery is verified against the development Mailpit service only.',
    );

    const emailAddress = `e2e.invited.${Date.now()}@rfis.test`;
    const accountName = `E2E Invited ${Date.now()}`;
    const password = `Invitation-RFIS-${Date.now()}-A7!`;
    const mailpit = await request.newContext({ baseURL: 'http://localhost:9204' });

    try {
        const previousMessagesResponse = await mailpit.get('/api/v1/messages?limit=100');
        expect(previousMessagesResponse.ok()).toBe(true);
        const previousMessages = (await previousMessagesResponse.json()) as {
            messages?: Array<{ ID?: string }>;
        };
        const previousMessageIds = new Set(previousMessages.messages?.map((message) => message.ID).filter(Boolean));

        await loginAs(page, 'admin@rfis.test');
        await page.goto('/admin');
        await page.getByRole('button', { name: 'Add new user' }).click();
        const dialog = page.getByRole('dialog').filter({ hasText: 'Create user account' });
        await dialog.getByLabel('Full name').fill(accountName);
        await dialog.getByLabel('Email').fill(emailAddress);
        await dialog.getByRole('button', { name: 'Create account' }).click();
        await expect(dialog).toBeHidden();

        const search = page.getByPlaceholder(/Cari pengguna berdasarkan nama|Search users by name/i);
        await search.fill(emailAddress);
        const accountRow = page.getByRole('row').filter({ hasText: emailAddress });
        await expect(accountRow).toContainText(/Undangan|Invited/);

        let firstMessageId = '';
        await expect
            .poll(
                async () => {
                    const response = await mailpit.get('/api/v1/messages?limit=100');
                    expect(response.ok()).toBe(true);
                    const result = (await response.json()) as {
                        messages?: Array<{ ID?: string; Subject?: string; To?: Array<{ Address?: string }> }>;
                    };
                    firstMessageId =
                        result.messages?.find(
                            (message) =>
                                !previousMessageIds.has(message.ID) &&
                                message.Subject?.includes('RF IS account invitation') &&
                                message.To?.some((recipient) => recipient.Address?.toLowerCase() === emailAddress),
                        )?.ID ?? '';
                    return firstMessageId;
                },
                { timeout: 10_000, intervals: [250, 500, 1000] },
            )
            .not.toBe('');
        previousMessageIds.add(firstMessageId);

        const firstMessageResponse = await mailpit.get(`/api/v1/message/${encodeURIComponent(firstMessageId)}`);
        expect(firstMessageResponse.ok()).toBe(true);
        const firstMessage = (await firstMessageResponse.json()) as { Text?: string; HTML?: string };
        const firstBody = `${firstMessage.Text ?? ''}\n${firstMessage.HTML ?? ''}`;
        const firstInvitationUrl = Array.from(firstBody.matchAll(/https?:\/\/[^\s<>"']+/g), ([candidate]) => {
            try {
                return new URL(candidate.replaceAll('&amp;', '&'));
            } catch {
                return null;
            }
        }).find((url): url is URL => url !== null && url.pathname.startsWith('/invitations/'));
        expect(firstInvitationUrl).toBeDefined();

        await accountRow.getByRole('button', { name: /Resend invite|Resend invitation|Kirim ulang/i }).click();

        let messageId = '';
        await expect
            .poll(
                async () => {
                    const response = await mailpit.get('/api/v1/messages?limit=100');
                    expect(response.ok()).toBe(true);
                    const result = (await response.json()) as {
                        messages?: Array<{ ID?: string; Subject?: string; To?: Array<{ Address?: string }> }>;
                    };
                    messageId =
                        result.messages?.find(
                            (message) =>
                                !previousMessageIds.has(message.ID) &&
                                message.Subject?.includes('RF IS account invitation') &&
                                message.To?.some((recipient) => recipient.Address?.toLowerCase() === emailAddress),
                        )?.ID ?? '';
                    return messageId;
                },
                { timeout: 10_000, intervals: [250, 500, 1000] },
            )
            .not.toBe('');

        const messageResponse = await mailpit.get(`/api/v1/message/${encodeURIComponent(messageId)}`);
        expect(messageResponse.ok()).toBe(true);
        const message = (await messageResponse.json()) as { Text?: string; HTML?: string };
        const body = `${message.Text ?? ''}\n${message.HTML ?? ''}`;
        const invitationUrl = Array.from(body.matchAll(/https?:\/\/[^\s<>"']+/g), ([candidate]) => {
            try {
                return new URL(candidate.replaceAll('&amp;', '&'));
            } catch {
                return null;
            }
        }).find((url): url is URL => url !== null && url.pathname.startsWith('/invitations/'));
        expect(invitationUrl).toBeDefined();
        expect(invitationUrl!.origin).toBe(new URL(page.url()).origin);

        await logout(page);
        await page.goto(firstInvitationUrl!.toString());
        await expect(page).toHaveURL(/\/login$/);
        await expect(
            page
                .getByText(
                    /invitation is no longer active|invitation link is invalid|undangan.*tidak aktif|undangan tidak valid/i,
                )
                .first(),
        ).toBeVisible();
        const invalidInviteToast = page.getByRole('status');
        if ((await invalidInviteToast.count()) > 0) {
            await invalidInviteToast.first().getByRole('button').click();
        }

        await page.goto(invitationUrl!.toString());
        await expect(page.getByText(emailAddress)).toBeVisible();
        await page.locator('#password').fill(password);
        await page.locator('#password_confirmation').fill(`${password}-mismatch`);
        await page.getByRole('button', { name: /Activate account|Aktifkan akun/ }).click();
        await expect(page.getByText(/confirmation does not match|konfirmasi kata sandi/i)).toBeVisible();

        await page.locator('#password_confirmation').fill(password);
        await page.getByRole('button', { name: /Activate account|Aktifkan akun/ }).click();
        await expect(page).toHaveURL(/\/dashboard$/);

        const anonymousBrowser = await request.newContext({ baseURL: invitationUrl!.origin });
        try {
            const replayResponse = await anonymousBrowser.get(invitationUrl!.pathname, {
                failOnStatusCode: false,
                maxRedirects: 0,
            });
            expect(replayResponse.status(), 'An accepted invitation must not be reusable').toBe(302);
            expect(replayResponse.headers().location).toContain('/login');
        } finally {
            await anonymousBrowser.dispose();
        }

        await logout(page);
        await page.goto('/login');
        await page.getByLabel(/email address|alamat email/i).fill(emailAddress);
        await page.getByLabel(/password|kata sandi/i).fill(password);
        await page.getByTestId('login-button').click();
        await page.waitForURL((url) => url.pathname === '/dashboard');
        await expect(page).toHaveURL(/\/dashboard$/);
    } finally {
        await mailpit.dispose();
    }
});

test('desktop attendance exposes camera scanning when the browser supports media capture', async ({ page }) => {
    await page.addInitScript(() => {
        Object.defineProperty(navigator.mediaDevices, 'getUserMedia', {
            configurable: true,
            value: async () => {
                throw new DOMException('Permission denied', 'NotAllowedError');
            },
        });
    });
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/attendance');

    await expect(page.getByRole('heading', { name: 'Scan coach QR inside this page' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Start scan' })).toBeVisible();
    await expect(page.getByRole('textbox', { name: /\/attendance\/scan/i })).toBeVisible();
    await page.getByRole('button', { name: 'Start scan' }).click();
    await expect(page.getByText(/Camera permission was denied/i)).toBeVisible();
    await expect(page.getByRole('textbox', { name: /\/attendance\/scan/i })).toBeVisible();
});

test('desktop camera scans a live attendance QR and releases its stream after navigation', async ({ page }) => {
    test.setTimeout(120_000);
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/sessions');

    const search = page.getByPlaceholder(/Search all session columns|Cari semua kolom sesi/i);
    await search.fill('Junior Sparring');
    const sessionRow = page.getByRole('row').filter({ hasText: 'Junior Sparring' }).first();
    await expect(sessionRow).toBeVisible();
    const attendanceUrl = await sessionRow.getByRole('link', { name: /^(Edit|Ubah)$/ }).getAttribute('href');
    expect(attendanceUrl).toBeTruthy();

    await page.goto(new URL(attendanceUrl!, page.url()).toString());
    const childAttendance = page.getByRole('row').filter({ hasText: 'Anak Semua Peran' }).first();
    await expect(childAttendance).toBeVisible();
    await childAttendance.getByRole('button', { name: 'Tidak hadir' }).click();

    const qrToggle = page.getByRole('button', { name: /^(Buka QR cepat|Tutup QR)$/ }).first();
    if ((await qrToggle.innerText()).trim() === 'Buka QR cepat') await qrToggle.click();
    const scanUrl = (
        await page
            .getByText(/\/attendance\/scan\//)
            .first()
            .innerText()
    ).trim();
    expect(new URL(scanUrl).pathname).toMatch(/^\/attendance\/scan\//);

    const qrDataUrl = await toDataURL(scanUrl, { width: 320, margin: 1 });
    const stoppedTrackStates: string[] = [];
    await page.exposeFunction('__rfisTrackStopped', (readyState: string) => {
        stoppedTrackStates.push(readyState);
    });
    await page.addInitScript(async (qrImageData: string) => {
        Object.defineProperty(navigator.mediaDevices, 'getUserMedia', {
            configurable: true,
            value: async () => {
                const canvas = document.createElement('canvas');
                canvas.width = 640;
                canvas.height = 480;
                const context = canvas.getContext('2d');
                if (!context) throw new Error('Could not create the deterministic camera frame.');
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, canvas.width, canvas.height);

                const image = new Image();
                image.src = qrImageData;
                await image.decode();
                context.drawImage(image, 210, 130, 220, 220);

                const stream = canvas.captureStream(15);
                for (const track of stream.getTracks()) {
                    const stop = track.stop.bind(track);
                    track.stop = () => {
                        stop();
                        void (window as Window & { __rfisTrackStopped?: (state: string) => void }).__rfisTrackStopped?.(
                            track.readyState,
                        );
                    };
                }
                return stream;
            },
        });
    }, qrDataUrl);

    await logout(page);
    await loginAs(page, 'allroles.child@rfis.test');
    await page.goto('/attendance');
    await page.context().grantPermissions(['camera']);
    await page.getByRole('button', { name: 'Start scan' }).click();
    await page.waitForURL((url) => /^\/attendance\/scan\/[^/]+$/.test(url.pathname), { timeout: 30_000 });
    await expect(page.getByRole('heading', { name: 'QR detected' })).toBeVisible();
    await page.getByRole('button', { name: 'Save attendance now' }).click();
    await expect(page.getByRole('heading', { name: 'Attendance saved' })).toBeVisible();

    expect(stoppedTrackStates.length).toBeGreaterThan(0);
    expect(stoppedTrackStates.every((state) => state === 'ended')).toBe(true);
});

for (const account of accounts) {
    test(`${account.name} keeps locale settings synchronized across navigation and reload`, async ({ page }) => {
        await loginAs(page, account.email);

        await page.goto('/locale/en');
        await page.waitForURL((url) => url.pathname === '/dashboard');
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
        await page.getByTestId('user-menu-trigger').click();
        await expect(page.getByRole('menuitem', { name: 'Settings' })).toBeVisible();

        await page.keyboard.press('Escape');
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');

        await page.goto('/locale/id');
        await page.waitForURL((url) => url.pathname === '/dashboard');
        await expect(page.locator('html')).toHaveAttribute('lang', 'id');
        await page.getByTestId('user-menu-trigger').click();
        await expect(page.getByRole('menuitem', { name: 'Pengaturan' })).toBeVisible();
        await page.keyboard.press('Escape');
    });
}

test('connection state on attendance emits one toast per browser event', async ({ page }) => {
    await page.route('**/favicon.svg', (route) => route.fulfill({ status: 204, body: '' }));
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/attendance');

    await page.context().setOffline(true);
    const offlineToast = page.getByRole('status');
    await expect(offlineToast).toHaveCount(1);
    await expect(offlineToast).toContainText('Perangkat sedang offline. Perubahan belum dapat dikirim ke server.');
    await offlineToast.getByRole('button').click();
    await expect(offlineToast).toHaveCount(0);

    await page.context().setOffline(false);
    const onlineToast = page.getByRole('status');
    await expect(onlineToast).toHaveCount(1);
    await expect(onlineToast).toContainText(
        'Koneksi internet tersedia kembali. Silakan ulangi tindakan yang sebelumnya gagal.',
    );
});

test('admin creates, edits, and deletes a training group with accessible row actions and validation feedback', async ({
    page,
}) => {
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/admin/groups');

    const groupName = `E2E Group ${Date.now()}`;
    const updatedGroupName = `${groupName} Updated`;

    await page.getByRole('button', { name: /Tambah Grup|Add group/i }).click();
    const createDialog = page.getByRole('dialog').last();
    await createDialog.getByRole('button', { name: /Save Grup|Save group/i }).click();
    await expect(createDialog.getByText(/required|wajib|diperlukan/i)).toBeVisible();
    const validationToast = page.getByRole('status');
    await expect(validationToast).toHaveCount(1);
    await validationToast.getByRole('button').click();
    await createDialog.getByRole('button', { name: /Save Grup|Save group/i }).click();
    await expect(validationToast).toHaveCount(1);

    await createDialog.getByPlaceholder(/Contoh: Junior|Example: Junior/i).fill(groupName);
    await createDialog.getByPlaceholder(/Keterangan grup|Group description/i).fill('Disposable browser test group.');
    await createDialog.getByRole('button', { name: /Save Grup|Save group/i }).click();
    await expect(createDialog).toBeHidden();

    const groupSearch = page.getByPlaceholder(/Cari grup|Search group/i);
    await groupSearch.fill(groupName);
    await expect(page.getByRole('table').getByText(groupName, { exact: true })).toBeVisible();
    await page.getByRole('button', { name: `Ubah grup ${groupName}` }).click();

    const editDialog = page.getByRole('dialog').last();
    await editDialog.getByPlaceholder(/Contoh: Junior|Example: Junior/i).fill(updatedGroupName);
    await editDialog.getByRole('button', { name: /Save Grup|Save group/i }).click();
    await expect(editDialog).toBeHidden();

    await groupSearch.fill(updatedGroupName);
    await expect(page.getByRole('table').getByText(updatedGroupName, { exact: true })).toBeVisible();
    await page.getByRole('button', { name: `Hapus grup ${updatedGroupName}` }).click();
    await page
        .getByRole('dialog')
        .filter({ hasText: /Hapus atau nonaktifkan grup\?|Delete or deactivate this group\?/i })
        .getByRole('button', { name: /Lanjutkan|Continue/i })
        .click();
    await expect(page.getByRole('table').getByText(updatedGroupName, { exact: true })).toBeHidden();
});

test('admin creates, edits, and deletes a location with server-matched coordinate and radius rules', async ({
    page,
}) => {
    await page.route('https://*.tile.openstreetmap.org/**', (route) =>
        route.fulfill({
            status: 200,
            contentType: 'image/png',
            body: Buffer.from(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/m4kAAAAASUVORK5CYII=',
                'base64',
            ),
        }),
    );

    await loginAs(page, 'admin@rfis.test');
    await page.goto('/admin/locations');

    const locationName = `E2E Location ${Date.now()}`;
    const updatedLocationName = `${locationName} Updated`;

    await page.getByRole('button', { name: 'Tambah Lokasi' }).click();
    const dialog = page.getByRole('dialog').last();
    await dialog.getByRole('button', { name: 'Save Lokasi' }).click();
    await expect(dialog.getByText(/required|wajib|diperlukan/i)).toBeVisible();

    await dialog.getByPlaceholder('Contoh: Central Dojang').fill(locationName);
    await dialog.getByRole('textbox', { name: 'Alamat' }).fill('Jalan latihan 10');
    await dialog.getByRole('textbox', { name: 'Kota' }).fill('Jakarta');
    await dialog.getByRole('textbox', { name: 'Provinsi' }).fill('DKI Jakarta');
    await dialog.getByRole('spinbutton', { name: 'Latitude' }).fill('91');
    await dialog.getByRole('spinbutton', { name: 'Longitude' }).fill('106.8');

    const activeCheckbox = dialog.getByRole('checkbox');
    await expect(activeCheckbox).toBeDisabled();
    await expect(dialog.getByLabel('Radius Absensi')).toHaveAttribute('min', '20');
    await expect(dialog.getByLabel('Radius Absensi')).toHaveAttribute('max', '5000');

    await dialog.getByRole('spinbutton', { name: 'Latitude' }).fill('-6.2');
    await expect(activeCheckbox).toBeEnabled();
    await activeCheckbox.check();
    await dialog.getByRole('button', { name: 'Save Lokasi' }).click();
    await expect(dialog).toBeHidden();

    let row = page.getByRole('row').filter({ hasText: locationName });
    await expect(row).toContainText('AKTIF');
    await row.getByRole('button', { name: `Edit lokasi ${locationName}` }).click();

    const editDialog = page.getByRole('dialog').last();
    await editDialog.getByPlaceholder('Contoh: Central Dojang').fill(updatedLocationName);
    await editDialog.getByLabel('Radius Absensi').fill('250');
    await editDialog.getByRole('button', { name: 'Save Lokasi' }).click();
    await expect(editDialog).toBeHidden();

    row = page.getByRole('row').filter({ hasText: updatedLocationName });
    await expect(row).toContainText('250m');
    await row.getByRole('button', { name: `Hapus lokasi ${updatedLocationName}` }).click();
    await page
        .getByRole('dialog')
        .filter({ hasText: /Hapus atau nonaktifkan lokasi\?/i })
        .getByRole('button', { name: 'Lanjutkan' })
        .click();
    await expect(page.getByRole('row').filter({ hasText: updatedLocationName })).toHaveCount(0);
});

test('admin announcement lifecycle is visible to its target role and hidden from other roles', async ({ page }) => {
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/announcements');

    const title = `E2E Athlete Notice ${Date.now()}`;
    const updatedTitle = `${title} Updated`;

    await page.getByRole('button', { name: /Buat pengumuman|Create announcement/i }).click();
    await page.getByLabel('Judul').fill(title);
    await page.getByLabel('Isi pengumuman').fill('Synthetic role-targeted announcement for browser verification.');
    await page.locator('#announcement-target').click();
    await page.getByRole('button', { name: 'Atlet saja', exact: true }).click();
    await page.getByRole('button', { name: /Simpan pengumuman|Save announcement/i }).click();
    await expect(page.locator('article').filter({ hasText: title })).toBeVisible();

    const announcement = page.locator('article').filter({ hasText: title });
    await announcement.getByRole('button', { name: /^(Ubah|Edit)$/ }).click();
    await page.getByLabel('Judul').fill(updatedTitle);
    await page.getByRole('button', { name: /Simpan pengumuman|Save announcement/i }).click();
    await expect(page.locator('article').filter({ hasText: updatedTitle })).toBeVisible();

    await logout(page);
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/announcements');
    await expect(page.locator('article').filter({ hasText: updatedTitle })).toBeVisible();
    await expect(page.getByText(title, { exact: true })).toHaveCount(0);

    await logout(page);
    await loginAs(page, 'coach@rfis.test');
    await page.goto('/announcements');
    await expect(page.locator('article').filter({ hasText: updatedTitle })).toHaveCount(0);

    await logout(page);
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/announcements');
    const updatedAnnouncement = page.locator('article').filter({ hasText: updatedTitle });
    await updatedAnnouncement.getByRole('button', { name: /^(Hapus|Delete)$/ }).click();
    const confirmDelete = page
        .getByRole('dialog')
        .filter({ hasText: /Hapus pengumuman\?|Delete this announcement\?/i })
        .getByRole('button', { name: /Hapus pengumuman|Delete announcement/i });
    const deleteResponse = page.waitForResponse(
        (response) =>
            response.request().method() === 'DELETE' && new URL(response.url()).pathname.startsWith('/announcements/'),
    );
    const refreshedAnnouncements = page.waitForResponse(
        (response) =>
            response.request().method() === 'GET' &&
            new URL(response.url()).pathname === '/announcements' &&
            response.status() === 200,
    );
    await confirmDelete.click();
    expect((await deleteResponse).status()).toBe(303);
    expect((await refreshedAnnouncements).status()).toBe(200);
    await expect(updatedAnnouncement).toHaveCount(0);
});

test('coach checks in to an assigned session and cannot open another coach’s session', async ({ page }) => {
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/sessions');

    const search = page.getByPlaceholder(/Search all session columns|Cari semua kolom sesi/i);
    await search.fill('Competition Team');
    const otherCoachRow = page
        .getByRole('row')
        .filter({ hasText: 'Competition Team' })
        .filter({ hasText: 'Siti Rahma' })
        .filter({ hasNotText: 'Budi Santoso' })
        .first();
    await expect(otherCoachRow, 'Select a Competition Team session that Budi does not coach').toContainText(
        'Competition Team',
    );
    await expect(otherCoachRow).toContainText('Siti Rahma');
    await expect(otherCoachRow).not.toContainText('Budi Santoso');
    const attendanceLink = otherCoachRow.getByRole('link', { name: /^(Ubah|Edit)$/ });
    const otherCoachAttendanceUrl = await attendanceLink.getAttribute('href');
    expect(otherCoachAttendanceUrl).toBeTruthy();
    const protectedAttendanceUrl = new URL(otherCoachAttendanceUrl!, page.url()).toString();
    await page.goto(protectedAttendanceUrl);
    await expect(page.getByRole('heading', { name: 'Competition Team', exact: true })).toBeVisible();
    await expect(page.locator('main')).toContainText('Siti Rahma');

    await logout(page);
    await loginAs(page, 'coach@rfis.test');
    const forbiddenSessionStatus = await page.evaluate(async (url) => {
        const response = await fetch(url, { credentials: 'same-origin', redirect: 'manual' });

        return response.status;
    }, protectedAttendanceUrl);
    expect(forbiddenSessionStatus, 'An unrelated coach must be denied another coach’s attendance sheet').toBe(403);
    const diagnostics = diagnosticsByPage.get(page) ?? [];
    const expectedForbiddenResponse = `HTTP 403: GET ${protectedAttendanceUrl}`;
    const expectedForbiddenIndex = diagnostics.indexOf(expectedForbiddenResponse);
    expect(
        expectedForbiddenIndex,
        'The browser should record the expected authorization response',
    ).toBeGreaterThanOrEqual(0);
    diagnostics.splice(expectedForbiddenIndex, 1);
    const expectedForbiddenConsoleIndex = diagnostics.findIndex((diagnostic) =>
        diagnostic.startsWith('console error: Failed to load resource: the server responded with a status of 403'),
    );
    expect(
        expectedForbiddenConsoleIndex,
        'The deliberate forbidden request should be the only expected browser error',
    ).toBeGreaterThanOrEqual(0);
    diagnostics.splice(expectedForbiddenConsoleIndex, 1);

    await page.goto('/attendance');
    await expect(
        page.getByRole('heading', { name: /Sesi mengajar yang tersedia|Available teaching sessions/i }),
    ).toBeVisible();
    const checkInRow = page.getByRole('row').filter({ hasText: 'Simulation Current-Day Coach Check-In' });
    await expect(checkInRow, 'The test seed should always provide a current-day assigned session').toContainText(
        'Available today',
    );
    const checkInButton = checkInRow.getByRole('button', { name: 'Hadir', exact: true });
    await expect(checkInButton, 'Seeded current-day coach session should offer check-in').toBeVisible();
    await checkInButton.click();
    await expect(checkInRow.getByText('Sudah hadir', { exact: true })).toBeVisible();
    await page.reload();
    await expect(
        page.getByRole('row').filter({ hasText: 'Simulation Current-Day Coach Check-In' }).getByText('Sudah hadir', {
            exact: true,
        }),
    ).toBeVisible();
});

test('athlete registers their own account for an open championship and receives the resulting registration state', async ({
    page,
}) => {
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/championships');

    const eventRow = page.getByRole('row').filter({ hasText: 'Jakarta Open Championship' });
    await expect(eventRow).toBeVisible();
    await eventRow.getByRole('button', { name: 'Daftar', exact: true }).click();

    await page.getByLabel('Klasifikasi').fill('E2E Cadet');
    await page.getByLabel('Kelas').fill('E2E Under 50 kg');
    await page.getByLabel('Divisi').fill('E2E Putra');
    await page.getByRole('button', { name: 'Kirim pendaftaran' }).click();

    await expect(
        page
            .getByRole('row')
            .filter({ hasText: 'Jakarta Open Championship' })
            .getByRole('button', { name: 'Ubah pendaftaran' }),
    ).toBeVisible();
});

test('coach and athlete roles on one account can switch attendance context without losing either workflow', async ({
    page,
}) => {
    await loginAs(page, 'multirole@rfis.test');
    await page.goto('/attendance');
    await expect(
        page.getByRole('heading', { name: /Sesi mengajar yang tersedia|Available teaching sessions/i }),
    ).toBeVisible();
    await expect(page.getByRole('button', { name: 'Atlet', exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Atlet', exact: true }).click();
    await page.waitForURL(/mode=athlete/);
    await expect(page.getByRole('heading', { name: /Scan QR attendance/i })).toBeVisible();
    await expect(page.getByText(/Tidak tersedia tombol hadir manual untuk atlet/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Hadir', exact: true })).toHaveCount(0);
});

test('admin creates a payroll receipt and the server rejects a duplicate coach-period receipt', async ({ page }) => {
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/admin/payroll');
    await page.getByRole('button', { name: /Buat payroll/i }).click();

    await page.locator('#payroll-coach').click();
    await page.getByRole('button', { name: /^Budi Santoso - coach@rfis\.test$/ }).click();
    await page.locator('#payroll-period').fill('2035-01');
    await page.locator('#payroll-basis').click();
    await page.getByRole('button', { name: 'Nominal tetap' }).click();
    await page.locator('#payroll-paid-at').fill(new Date().toISOString().slice(0, 10));
    await page.locator('#payroll-base').fill('555000');
    await page.locator('#payroll-bonus').fill('25000');

    const dialog = page.getByRole('dialog');
    await expect(dialog.getByText(/580\.000/)).toBeVisible();
    await dialog.getByRole('button', { name: /Terbitkan slip dibayar/i }).click();
    await expect(dialog).toBeHidden();

    const payrollSearch = page.getByPlaceholder(/Cari pelatih, periode, atau nomor slip/i);
    await payrollSearch.fill('2035');
    const payrollRow = page.locator('table tbody tr').filter({ hasText: '2035' });
    await expect(payrollRow).toHaveCount(1);
    await expect(payrollRow).toContainText(/580\.000/);

    await page.getByRole('button', { name: /Buat payroll/i }).click();
    await page.locator('#payroll-coach').click();
    await page.getByRole('button', { name: /^Budi Santoso - coach@rfis\.test$/ }).click();
    await page.locator('#payroll-period').fill('2035-01');
    await page.locator('#payroll-basis').click();
    await page.getByRole('button', { name: 'Nominal tetap' }).click();
    await page.locator('#payroll-paid-at').fill(new Date().toISOString().slice(0, 10));
    await page.locator('#payroll-base').fill('580000');
    await page
        .getByRole('dialog')
        .getByRole('button', { name: /Terbitkan slip dibayar/i })
        .click();
    await expect(
        page.getByText('Slip payroll pelatih ini untuk periode tersebut sudah ada.', { exact: true }),
    ).toBeVisible();

    await page.getByRole('dialog').getByRole('button', { name: 'Batal' }).click();
    await payrollSearch.fill('2035');
    await expect(page.locator('table tbody tr').filter({ hasText: '2035' })).toHaveCount(1);
});

test('admin payroll estimates use recorded workload for each basis and deny non-admin requests', async ({ page }) => {
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/admin/payroll');
    await page.getByRole('button', { name: /Buat payroll/i }).click();
    await page.locator('#payroll-coach').click();
    await page.getByRole('button', { name: /^Budi Santoso - coach@rfis\.test$/ }).click();
    await page.locator('#payroll-period').fill(new Date().toISOString().slice(0, 7));

    const units = page.locator('#payroll-units');
    await expect.poll(async () => Number(await units.inputValue())).toBeGreaterThan(0);
    await expect(page.getByText(/Sumber perhitungan: .*sesi mengajar tercatat/i)).toBeVisible();

    await page.locator('#payroll-basis').click();
    await page.getByRole('button', { name: /Jumlah jam dari sesi mengajar/i }).click();
    await expect.poll(async () => Number(await units.inputValue())).toBeGreaterThan(0);
    await expect(page.getByText(/Sumber perhitungan: .*jam mengajar/i)).toBeVisible();

    await page.locator('#payroll-basis').click();
    await page.getByRole('button', { name: /1 periode bulan/i }).click();
    await expect(units).toHaveValue('1');
    await expect(page.getByText(/Sumber perhitungan: 1 bulan untuk periode/i)).toBeVisible();
    await page.getByRole('dialog').getByRole('button', { name: 'Batal' }).click();
    await expect(page.getByRole('dialog')).toBeHidden();

    await logout(page);
    await loginAs(page, 'coach@rfis.test');
    const denied = await page
        .context()
        .request.get(
            new URL(
                '/admin/payroll/estimate?coach_user_id=1&payroll_period=2035-01&basis_type=MONTH',
                page.url(),
            ).toString(),
            { failOnStatusCode: false },
        );
    expect(denied.status(), 'Payroll estimates must be restricted server-side to administrators').toBe(403);
});

test('admin billing rules reject an unscoped monthly rule and issue one-time invoices idempotently', async ({
    page,
}) => {
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/admin/billing-settings');
    await page.getByRole('button', { name: /Tambah aturan/i }).click();

    const ruleName = `E2E one-time rule ${Date.now()}`;
    const ruleDialog = page.getByRole('dialog');
    await ruleDialog.locator('#billing-rule-name').fill(ruleName);
    await ruleDialog.locator('#billing-rule-amount').fill('12345');
    await ruleDialog.getByRole('button', { name: 'Simpan aturan' }).click();
    await expect(
        ruleDialog.getByText(
            'Pilih cabang atau kelas. Tarif SPP umum sudah menggunakan Tarif default di bagian Jadwal SPP otomatis.',
            { exact: true },
        ),
    ).toBeVisible();

    await ruleDialog.locator('#billing-rule-kind').click();
    await page.getByRole('button', { name: 'Tagihan satu kali', exact: true }).click();
    await ruleDialog.locator('#billing-rule-due-days').fill('14');
    await ruleDialog.locator('#billing-rule-notes').fill('Synthetic browser-only idempotency fixture.');
    await ruleDialog.getByRole('button', { name: 'Simpan aturan' }).click();
    await expect(ruleDialog).toBeHidden();

    const ruleCard = page.locator('article').filter({ hasText: ruleName });
    await expect(ruleCard).toBeVisible();
    await expect(ruleCard).toContainText(/12\.345/);
    await ruleCard.getByRole('button', { name: 'Terbitkan tagihan' }).click();

    const issueDialog = page.getByRole('dialog');
    await issueDialog.locator('#one-time-issue-date').fill('2035-01-15');
    await issueDialog.getByRole('button', { name: 'Terbitkan tagihan' }).click();
    await expect(issueDialog).toBeHidden();
    const firstIssueNotice = page.getByText(/Tagihan satu kali dibuat: [1-9]\d*; dilewati karena sudah ada: 0\./);
    await expect(firstIssueNotice).toBeVisible();
    const issuedInvoiceCount = ruleCard.locator('dl > div').filter({ hasText: 'Tagihan terbit' }).locator('dd');
    await expect(issuedInvoiceCount).toHaveText(/[1-9]\d*/);
    const issuedCount = await issuedInvoiceCount.innerText();
    await expect(firstIssueNotice).toBeHidden();

    await ruleCard.getByRole('button', { name: 'Terbitkan tagihan' }).click();
    await page.getByRole('dialog').locator('#one-time-issue-date').fill('2035-01-15');
    await page.getByRole('dialog').getByRole('button', { name: 'Terbitkan tagihan' }).click();
    await expect(page.getByText(/Tagihan satu kali dibuat: 0; dilewati karena sudah ada: [1-9]\d*\./)).toBeVisible();
    await expect(issuedInvoiceCount).toHaveText(issuedCount);

    await ruleCard.getByRole('button', { name: 'Arsipkan', exact: true }).click();
    const archiveDialog = page.getByRole('dialog').filter({ hasText: 'Arsipkan aturan tagihan?' });
    await archiveDialog.getByRole('button', { name: 'Arsipkan aturan' }).click();
    await expect(ruleCard).toHaveCount(0);
});

test('admin exports a selected Excel workbook and non-admins cannot download it directly', async ({ page }) => {
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/admin/data-export');
    await page.getByRole('button', { name: /^(Clear|Bersihkan)$/ }).click();
    const downloadButton = page.getByRole('button', { name: /Download selected data/i });
    await expect(downloadButton).toBeDisabled();
    await page.getByRole('button', { name: 'Select all', exact: true }).click();

    const downloadPromise = page.waitForEvent('download');
    await downloadButton.click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toMatch(/^System_Data_Export_.*\.xlsx$/);
    const path = await download.path();
    expect(path).toBeTruthy();
    if (!path) throw new Error('The browser did not provide a downloaded workbook.');
    const workbook = await readFile(path);
    expect(workbook.byteLength).toBeGreaterThan(500);
    expect(workbook.subarray(0, 2).toString('ascii')).toBe('PK');

    // Chromium reports the navigation request as aborted after handing the response to its download manager.
    const diagnostics = diagnosticsByPage.get(page) ?? [];
    const appOrigin = new URL(process.env.APP_URL ?? 'http://localhost:9200').origin;
    const expectedDownloadFailure = diagnostics.findIndex(
        (diagnostic) =>
            diagnostic.startsWith(`request failed: GET ${appOrigin}/admin/data-export/download?`) &&
            diagnostic.includes('net::ERR_ABORTED'),
    );
    if (expectedDownloadFailure >= 0) diagnostics.splice(expectedDownloadFailure, 1);

    await logout(page);
    await loginAs(page, 'coach@rfis.test');
    const forbiddenExport = await page
        .context()
        .request.get(
            new URL(
                '/admin/data-export/download?datasets%5B%5D=users&fields%5Busers%5D%5B%5D=name',
                page.url(),
            ).toString(),
            { failOnStatusCode: false },
        );
    expect(forbiddenExport.status(), 'The export endpoint must enforce admin authorization').toBe(403);
});

test('admin records a partial invoice payment, rejects overpayment, then closes the ledger balance', async ({
    page,
}) => {
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/payments');
    await page.getByRole('button', { name: 'Buat tagihan', exact: true }).click();

    await page.locator('#payment-recipient').click();
    await page.getByRole('button', { name: /^Adit Pratama/ }).click();
    await page.locator('#payment-total').fill('9876543');
    await page.locator('#payment-date').fill(new Date().toISOString().slice(0, 10));
    const dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + 14);
    await page
        .locator('#payment-due-date')
        .fill(
            `${dueDate.getFullYear()}-${String(dueDate.getMonth() + 1).padStart(2, '0')}-${String(dueDate.getDate()).padStart(2, '0')}`,
        );
    await page.locator('#payment-notes').fill('Disposable E2E invoice for ledger workflow verification.');
    await page.getByRole('button', { name: /Terbitkan tagihan/i }).click();

    const paymentSearch = page.getByPlaceholder(/Cari nomor tagihan, penerima, kategori, atau status/i);
    await paymentSearch.fill('9.876.543');
    const paymentRow = page.locator('table tbody tr').filter({ hasText: '9.876.543' });
    await expect(paymentRow).toBeVisible();

    await paymentRow.getByRole('button', { name: 'Catat pembayaran' }).click();
    await page.locator('#manual-payment-amount').fill('1234567');
    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await expect(paymentRow).toContainText('1.234.567');
    await expect(paymentRow).toContainText('8.641.976');

    await paymentRow.getByRole('button', { name: 'Catat pembayaran' }).click();
    await page.locator('#manual-payment-amount').fill('9000000');
    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await expect(
        page.getByText('The recorded amount cannot exceed the current balance.', { exact: true }),
    ).toBeVisible();
    await expect(paymentRow).toContainText('1.234.567');
    await expect(paymentRow).toContainText('8.641.976');

    await page.locator('#manual-payment-amount').fill('8641976');
    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await expect(paymentRow).toContainText('9.876.543');
    await expect(paymentRow).toContainText('0');
});

test('athlete submits a payment proof and admin approves only the verified installment', async ({ page }) => {
    test.skip(
        process.env.RFIS_E2E_PERSISTENT_STORAGE === '1',
        'Payment proof uploads are retained audit records; run this workflow only in the disposable tmpfs E2E environment.',
    );

    const total = 500_000 + (Date.now() % 400_000);
    const formattedTotal = total.toLocaleString('id-ID');
    const approvedAmount = 300_000;
    const remainingAmount = total - approvedAmount;

    await loginAs(page, 'admin@rfis.test');
    await page.goto('/payments');
    await page.getByRole('button', { name: 'Buat tagihan', exact: true }).click();
    await page.locator('#payment-recipient').click();
    await page.getByRole('button', { name: /^Adit Pratama/ }).click();
    await page.locator('#payment-total').fill(String(total));
    await page.locator('#payment-date').fill(new Date().toISOString().slice(0, 10));
    const dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + 14);
    await page.locator('#payment-due-date').fill(dueDate.toISOString().slice(0, 10));
    await page.locator('#payment-notes').fill('Synthetic browser proof-review workflow.');
    await page.getByRole('button', { name: /Terbitkan tagihan/i }).click();

    const paymentSearch = page.getByPlaceholder(/Cari nomor tagihan, penerima, kategori, atau status/i);
    await paymentSearch.fill(formattedTotal);
    const adminRow = page.locator('table tbody tr').filter({ hasText: formattedTotal });
    await expect(adminRow).toHaveCount(1);
    const invoiceNumber = (await adminRow.locator('td').first().innerText()).trim();
    expect(invoiceNumber).toMatch(/^INV-/);

    await logout(page);
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/payments');
    await page.getByPlaceholder(/Cari nomor tagihan, penerima, kategori, atau status/i).fill(invoiceNumber);
    const athleteRow = page.locator('table tbody tr').filter({ hasText: invoiceNumber });
    await expect(athleteRow).toBeVisible();
    await athleteRow.getByRole('button', { name: 'Upload bukti' }).click();

    const proofDialog = page.getByRole('dialog');
    await proofDialog.getByLabel('Catatan bukti').fill(`RFIS-E2E-CLEANUP:${Date.now()}`);
    await proofDialog.locator('#proof-file').setInputFiles({
        name: 'synthetic-payment-proof.pdf',
        mimeType: 'application/pdf',
        buffer: Buffer.from('%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n'),
    });
    await proofDialog.getByRole('button', { name: 'Kirim untuk review' }).click();
    await expect(proofDialog).toBeHidden();
    const proofLink = athleteRow.getByRole('link', { name: 'Lihat bukti' });
    await expect(proofLink).toBeVisible();
    const proofUrl = await proofLink.getAttribute('href');
    expect(proofUrl).toBeTruthy();

    const ownerProofResponse = await page.request.get(proofUrl!);
    expect(ownerProofResponse.status(), 'The invoice recipient can view their own private proof').toBe(200);
    expect(ownerProofResponse.headers()['cache-control']).toContain('private');
    expect(ownerProofResponse.headers()['cache-control']).toContain('no-store');
    expect(ownerProofResponse.headers()['content-disposition']).toContain('inline');

    await logout(page);
    await loginAs(page, 'nadia@rfis.test');
    const unrelatedProofResponse = await page.request.get(proofUrl!);
    expect(unrelatedProofResponse.status(), 'Another athlete cannot fetch a private payment proof directly').toBe(403);

    await logout(page);
    await loginAs(page, 'admin@rfis.test');
    const adminProofResponse = await page.request.get(proofUrl!);
    expect(adminProofResponse.status(), 'An administrator can inspect the proof for review').toBe(200);
    await page.goto('/payments');
    await page.getByPlaceholder(/Cari nomor tagihan, penerima, kategori, atau status/i).fill(invoiceNumber);
    const reviewRow = page.locator('table tbody tr').filter({ hasText: invoiceNumber });
    await reviewRow.getByRole('button', { name: 'Review bukti' }).click();

    const reviewDialog = page.getByRole('dialog');
    await reviewDialog.getByLabel('Nominal yang disetujui').fill(String(approvedAmount));
    await reviewDialog.getByLabel('Catatan admin').fill('Verified partial transfer in local test data.');
    await reviewDialog.getByRole('button', { name: 'Setujui nominal' }).click();
    await expect(reviewDialog).toBeHidden();
    await expect(reviewRow).toContainText(approvedAmount.toLocaleString('id-ID'));
    await expect(reviewRow).toContainText(remainingAmount.toLocaleString('id-ID'));
});

test('athlete can validate, crop, save, and reload a profile photo in disposable storage', async ({ page }) => {
    test.skip(
        process.env.RFIS_E2E_PERSISTENT_STORAGE === '1',
        'Profile photos persist in public storage; run this workflow only in the disposable tmpfs E2E environment.',
    );

    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/settings/profile');

    const photoSection = page.getByRole('heading', { name: 'Foto 3×4 dan bio' }).locator('xpath=ancestor::section[1]');
    await photoSection.getByRole('button', { name: 'Ubah' }).click();

    const photoInput = page.locator('#profile-photo-file');
    await photoInput.setInputFiles({
        name: 'invalid-profile.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('not an image'),
    });
    await expect(page.getByText('Selected file must be an image.')).toBeVisible();

    const pngDataUrl = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = 180;
        canvas.height = 240;
        const context = canvas.getContext('2d');
        if (!context) throw new Error('Canvas is unavailable in this browser.');
        context.fillStyle = '#0f766e';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = '#facc15';
        context.fillRect(45, 30, 90, 180);
        return canvas.toDataURL('image/png');
    });

    await photoInput.setInputFiles({
        name: 'synthetic-profile.png',
        mimeType: 'image/png',
        buffer: Buffer.from(pngDataUrl.split(',')[1], 'base64'),
    });
    await expect(photoSection.getByRole('button', { name: 'Gunakan crop 3×4' })).toBeVisible();
    await photoSection.getByRole('button', { name: 'Perbesar' }).click();
    await photoSection.getByRole('button', { name: 'Putar' }).click();
    await photoSection.getByRole('button', { name: /Reset|Atur ulang/ }).click();
    await photoSection.getByRole('button', { name: 'Gunakan crop 3×4' }).click();
    await expect(photoSection.getByText('Foto 3×4 siap disimpan.')).toBeVisible();

    const saveResponse = page.waitForResponse((response) => {
        const request = response.request();
        return new URL(response.url()).pathname === '/settings/profile/details' && request.method() === 'POST';
    });
    await photoSection.getByRole('button', { name: 'Simpan foto dan bio' }).click();
    const response = await saveResponse;
    expect(response.status()).toBeLessThan(400);

    await page.reload();
    const profileHeader = page
        .getByRole('heading', { name: 'Adit Pratama', exact: true })
        .locator('xpath=ancestor::section[1]');
    const avatar = profileHeader.getByRole('img', { name: 'Adit Pratama' });
    await expect(avatar).toBeVisible();
    await expect.poll(() => avatar.evaluate((image: HTMLImageElement) => image.naturalWidth)).toBeGreaterThan(0);
});

test('password recovery uses only local Mailpit, rejects a mismatch, and restores the demo credential', async ({
    page,
}) => {
    test.skip(
        process.env.RFIS_E2E_DEV_TARGET !== 'rf-is-dev',
        'Password-reset delivery is verified against the development Mailpit service only.',
    );

    const emailAddress = 'athlete@rfis.test';
    const originalPassword = process.env.RFIS_DEMO_PASSWORD;
    expect(originalPassword).toBeTruthy();
    const temporaryPassword = `Reset-RFIS-${Date.now()}-A7!`;
    const mailpit = await request.newContext({ baseURL: 'http://localhost:9204' });

    try {
        await page.goto('/locale/en');
        await page.goto('/forgot-password');
        const previousMessagesResponse = await mailpit.get('/api/v1/messages?limit=100');
        expect(previousMessagesResponse.ok()).toBe(true);
        const previousMessages = (await previousMessagesResponse.json()) as {
            messages?: Array<{ ID?: string }>;
        };
        const previousMessageIds = new Set(previousMessages.messages?.map((message) => message.ID).filter(Boolean));
        await page.getByLabel('Email address').fill(emailAddress);
        await page.getByTestId('email-password-reset-link-button').click();

        let messageId = '';
        await expect
            .poll(
                async () => {
                    const response = await mailpit.get('/api/v1/messages?limit=100');
                    expect(response.ok()).toBe(true);
                    const result = (await response.json()) as {
                        messages?: Array<{ ID?: string; Subject?: string; To?: Array<{ Address?: string }> }>;
                    };
                    messageId =
                        result.messages?.find(
                            (message) =>
                                !previousMessageIds.has(message.ID) &&
                                message.Subject?.includes('Reset Password Notification') &&
                                message.To?.some((recipient) => recipient.Address?.toLowerCase() === emailAddress),
                        )?.ID ?? '';
                    return messageId;
                },
                { timeout: 10_000, intervals: [250, 500, 1000] },
            )
            .not.toBe('');

        const messageResponse = await mailpit.get(`/api/v1/message/${encodeURIComponent(messageId)}`);
        expect(messageResponse.ok()).toBe(true);
        const message = (await messageResponse.json()) as { Text?: string; HTML?: string };
        const body = `${message.Text ?? ''}\n${message.HTML ?? ''}`;
        const resetUrl = Array.from(body.matchAll(/https?:\/\/[^\s<>"']+/g), ([candidate]) => {
            try {
                return new URL(candidate.replaceAll('&amp;', '&'));
            } catch {
                return null;
            }
        }).find((url): url is URL => url !== null && url.pathname.startsWith('/reset-password/'));
        expect(resetUrl).toBeDefined();
        expect(resetUrl!.origin).toBe(new URL(page.url()).origin);
        await page.goto(resetUrl!.toString());
        await page.getByLabel('Password', { exact: true }).fill(temporaryPassword);
        await page.getByLabel('Confirm Password', { exact: true }).fill(`${temporaryPassword}-mismatch`);
        await page.getByTestId('reset-password-button').click();
        await expect(page.getByText('The password confirmation does not match.', { exact: true })).toBeVisible();
        const validationToast = page.getByRole('status');
        await expect(validationToast).toHaveCount(1);
        await expect(validationToast).toContainText('The password confirmation does not match.');

        await page.getByLabel('Confirm Password', { exact: true }).fill(temporaryPassword);
        await page.getByTestId('reset-password-button').click();
        await expect(page).toHaveURL(/\/login$/);

        await page.getByLabel('Email address').fill(emailAddress);
        await page.getByLabel('Password', { exact: true }).fill(temporaryPassword);
        await page.getByRole('button', { name: 'Log in' }).click();
        await expect(page).toHaveURL(/\/dashboard$/);

        await page.goto('/settings/password');
        await page.getByLabel('Current password').fill(temporaryPassword);
        await page.getByLabel('New password').fill(originalPassword!);
        await page.getByLabel('Confirm password').fill(originalPassword!);
        await page.getByTestId('update-password-button').click();
        await expect(page.getByText('Saved.')).toBeVisible();

        await logout(page);
        await loginAs(page, emailAddress);
        await expect(page).toHaveURL(/\/dashboard$/);
    } finally {
        await mailpit.dispose();
    }
});

test('public welcome page exposes WhatsApp contact links, blocks self-registration, and rejects invalid reset tokens', async ({
    page,
}) => {
    await page.goto('/');
    const primaryWhatsappLink = page.getByRole('link', { name: /Hubungi admin (melalui|via) WhatsApp/i });
    await expect(primaryWhatsappLink).toBeVisible();
    await expect(primaryWhatsappLink).toHaveAttribute('href', /^https:\/\/wa\.me\/\d+/);
    await expect(primaryWhatsappLink).toHaveAttribute('target', '_blank');
    await expect(primaryWhatsappLink).toHaveAttribute('rel', /noopener/);

    await page
        .getByRole('link', { name: /^(Masuk|Masuk ke akun|Masuk ke klub)$/ })
        .first()
        .click();
    await expect(page).toHaveURL(/\/login$/);

    const registerGet = await page.context().request.get(new URL('/register', page.url()).toString(), {
        failOnStatusCode: false,
    });
    expect(registerGet.status(), 'Self-registration GET route must be disabled').toBe(404);

    const registerPost = await page.context().request.post(new URL('/register', page.url()).toString(), {
        failOnStatusCode: false,
        data: { name: 'Uninvited', email: 'uninvited@rfis.test', password: 'password' },
    });
    expect([404, 405, 419], 'Self-registration POST route must not create an account').toContain(registerPost.status());

    await page.goto('/locale/en');
    await page.goto('/reset-password/invalid-e2e-token?email=athlete%40rfis.test');
    await page.getByLabel('Password', { exact: true }).fill('Invalid-Token-Pass-99!');
    await page.getByLabel('Confirm Password', { exact: true }).fill('Invalid-Token-Pass-99!');
    await page.getByTestId('reset-password-button').click();
    await expect(page.getByText(/password reset token is invalid|token.*tidak valid/i).first()).toBeVisible();
});

test('user confirms password, enables and challenges 2FA with TOTP and recovery codes, and disables 2FA cleanly', async ({
    page,
}) => {
    const emailAddress = 'athlete@rfis.test';
    const demoPassword = process.env.RFIS_DEMO_PASSWORD ?? 'password';

    await loginAs(page, emailAddress);
    await page.goto('/locale/en');
    await page.waitForURL((url) => url.pathname === '/dashboard');

    await page.goto('/user/confirm-password');
    await page.locator('#password').fill('wrong-password');
    await page.getByTestId('confirm-password-button').click();
    await expect(page.getByText(/password (was|is) incorrect|kata sandi salah/i).first()).toBeVisible();
    const errorToast = page.getByRole('status');
    if ((await errorToast.count()) > 0) {
        await errorToast.first().getByRole('button').click();
    }

    await page.locator('#password').fill(demoPassword);
    await page.getByTestId('confirm-password-button').click();
    await page.waitForURL((url) => url.pathname !== '/user/confirm-password');

    await page.goto('/settings/two-factor');
    await expect(page.getByText('Disabled', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Enable 2FA' }).click();

    const setupDialog = page.getByRole('dialog');
    await expect(setupDialog).toBeVisible();
    const secretInput = setupDialog.locator('input[readonly]');
    await expect(secretInput).toBeVisible();
    await expect.poll(async () => (await secretInput.inputValue()).trim().length).toBeGreaterThanOrEqual(16);
    const manualSetupKey = (await secretInput.inputValue()).trim();

    await setupDialog.getByRole('button', { name: 'Continue' }).click();
    const otpInput = setupDialog.locator('#otp');
    await expect(otpInput).toBeVisible();
    await otpInput.fill(generateTotp(manualSetupKey));
    await setupDialog.getByRole('button', { name: 'Confirm' }).click();
    await expect(setupDialog).toBeHidden();

    await expect(page.getByText('Enabled', { exact: true })).toBeVisible();
    const hideCodesButton = page.getByRole('button', { name: /Hide Recovery Codes|View Recovery Codes/i });
    if ((await hideCodesButton.innerText()).includes('View')) {
        await hideCodesButton.click();
    }
    const codeElements = page.locator('.font-mono.text-sm > div');
    await expect(codeElements).toHaveCount(8);
    const initialFirstCode = (await codeElements.first().innerText()).trim();
    expect(initialFirstCode).toBeTruthy();

    await page.getByRole('button', { name: 'Regenerate Codes' }).click();
    await expect.poll(async () => (await codeElements.first().innerText()).trim()).not.toBe(initialFirstCode);
    const regeneratedRecoveryCode = (await codeElements.first().innerText()).trim();

    await logout(page);
    await page.goto('/login');
    await page.getByLabel(/email address|alamat email/i).fill(emailAddress);
    await page.getByLabel(/password|kata sandi/i).fill(demoPassword);
    await page.getByTestId('login-button').click();
    await expect(page).toHaveURL(/\/two-factor-challenge$/);

    await page.getByRole('button', { name: /login using a recovery code|masuk menggunakan kode pemulihan/i }).click();
    await page.locator('input[name="recovery_code"]').fill('invalid-recovery-code-000');
    await page.getByRole('button', { name: /^(Continue|Lanjutkan)$/ }).click();
    await expect(page.getByText(/recovery code was invalid|kode pemulihan.*tidak valid/i).first()).toBeVisible();
    const challengeToast = page.getByRole('status');
    if ((await challengeToast.count()) > 0) {
        await challengeToast.first().getByRole('button').click();
    }

    await page.locator('input[name="recovery_code"]').fill(regeneratedRecoveryCode);
    await page.getByRole('button', { name: /^(Continue|Lanjutkan)$/ }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    await page.goto('/settings/two-factor');
    if (new URL(page.url()).pathname === '/user/confirm-password') {
        await page.locator('#password').fill(demoPassword);
        await page.getByTestId('confirm-password-button').click();
        await page.waitForURL((url) => url.pathname === '/settings/two-factor');
    }
    await page.getByRole('button', { name: /Disable 2FA|Nonaktifkan 2FA/i }).click();
    await expect(page.getByText(/^(Disabled|Nonaktif)$/)).toBeVisible();

    await page.goto('/dashboard');
    await page.goto('/locale/id');
    await expect(page).toHaveURL(/\/dashboard$/);
});

test('settings overview, appearance tabs, self athlete and coach profile edits, and self account deletion guard work end to end', async ({
    page,
}) => {
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/settings');
    await expect(page.getByRole('heading', { name: 'Adit Pratama' })).toBeVisible();
    await expect(page.locator('a[href="/settings/profile"]').first()).toBeVisible();
    await expect(page.locator('a[href="/settings/password"]').first()).toBeVisible();
    await expect(page.locator('a[href="/settings/two-factor"]').first()).toBeVisible();
    await expect(page.locator('a[href="/settings/appearance"]').first()).toBeVisible();

    await page.goto('/settings/appearance');
    await page.getByRole('button', { name: /^(Dark|Gelap)$/ }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);
    await page.getByRole('button', { name: /^(Light|Terang)$/ }).click();
    await expect(page.locator('html')).not.toHaveClass(/dark/);
    await page.getByRole('button', { name: /^(System|Sistem)$/ }).click();

    await page.goto('/settings/profile');
    const athleteSection = page.getByRole('heading', { name: 'Data atlet' }).locator('xpath=ancestor::section[1]');
    const guardianNameInput = athleteSection.locator('#profile-athlete-guardian-name');
    const guardianPhoneInput = athleteSection.locator('#profile-athlete-guardian-phone');
    const originalGuardianName = await guardianNameInput.inputValue();
    const originalGuardianPhone = await guardianPhoneInput.inputValue();
    const updatedGuardianName = `Wali E2E ${Date.now()}`;
    const updatedGuardianPhone = '081299998888';

    await athleteSection.getByRole('button', { name: 'Ubah' }).click();
    await guardianNameInput.fill(updatedGuardianName);
    await guardianPhoneInput.fill(updatedGuardianPhone);
    await athleteSection.getByRole('button', { name: 'Simpan data atlet' }).click();
    await expect(guardianNameInput).toBeDisabled();
    await page.reload();
    await expect(page.locator('#profile-athlete-guardian-name')).toHaveValue(updatedGuardianName);
    await expect(page.locator('#profile-athlete-guardian-phone')).toHaveValue(updatedGuardianPhone);

    const reloadedAthleteSection = page
        .getByRole('heading', { name: 'Data atlet' })
        .locator('xpath=ancestor::section[1]');
    await reloadedAthleteSection.getByRole('button', { name: 'Ubah' }).click();
    await page.locator('#profile-athlete-guardian-name').fill(originalGuardianName);
    await page.locator('#profile-athlete-guardian-phone').fill(originalGuardianPhone);
    await reloadedAthleteSection.getByRole('button', { name: 'Simpan data atlet' }).click();
    await expect(page.locator('#profile-athlete-guardian-name')).toBeDisabled();

    const csrfCookie = (await page.context().cookies()).find(({ name }) => name === 'XSRF-TOKEN');
    expect(csrfCookie).toBeDefined();
    const rejectedDelete = await page.context().request.delete(new URL('/settings/profile', page.url()).toString(), {
        failOnStatusCode: false,
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(csrfCookie?.value ?? ''),
        },
        data: { password: 'wrong-password' },
    });
    expect(rejectedDelete.status(), 'Deleting own account with wrong password must fail validation').toBe(422);

    await logout(page);
    await loginAs(page, 'coach@rfis.test');
    await page.goto('/settings/profile');
    const coachSection = page.getByRole('heading', { name: 'Data pelatih' }).locator('xpath=ancestor::section[1]');
    const specializationInput = coachSection.locator('#profile-coach-specialization');
    const originalSpecialization = await specializationInput.inputValue();
    const updatedSpecialization = `Kyorugi & Poomsae E2E ${Date.now()}`;

    await coachSection.getByRole('button', { name: 'Ubah' }).click();
    await specializationInput.fill(updatedSpecialization);
    await coachSection.getByRole('button', { name: 'Simpan data pelatih' }).click();
    await expect(specializationInput).toBeDisabled();
    await page.reload();
    await expect(page.locator('#profile-coach-specialization')).toHaveValue(updatedSpecialization);

    const reloadedCoachSection = page
        .getByRole('heading', { name: 'Data pelatih' })
        .locator('xpath=ancestor::section[1]');
    await reloadedCoachSection.getByRole('button', { name: 'Ubah' }).click();
    await page.locator('#profile-coach-specialization').fill(originalSpecialization);
    await reloadedCoachSection.getByRole('button', { name: 'Simpan data pelatih' }).click();
    await expect(page.locator('#profile-coach-specialization')).toBeDisabled();
});

test('admin filters user directory, edits athlete guardian and profile records via UUID routes, and coach profile access stays scoped', async ({
    page,
}) => {
    test.setTimeout(60_000);
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/users');

    const athleteTable = page.locator('table').first();
    await expect(athleteTable.getByRole('row').filter({ hasText: 'Adit Pratama' })).toBeVisible();

    await page.locator('#athlete-branch-filter').click();
    await page.getByRole('button', { name: 'Central Dojang', exact: true }).click();
    await expect(athleteTable.getByRole('row').filter({ hasText: 'Adit Pratama' })).toBeVisible();

    await page.locator('#athlete-group-filter').click();
    await page.getByRole('button', { name: 'Junior Sparring', exact: true }).click();
    await expect(athleteTable.getByRole('row').filter({ hasText: 'Adit Pratama' })).toBeVisible();

    await page.locator('#athlete-status-filter').click();
    await page.getByRole('button', { name: /^(Active|Aktif)$/ }).click();
    await expect(athleteTable.getByRole('row').filter({ hasText: 'Adit Pratama' })).toBeVisible();

    const coachSearch = page.getByPlaceholder(
        /Search coaches by name, email, status, or specialization|Cari pelatih berdasarkan nama/i,
    );
    await coachSearch.fill('Budi Santoso');
    await expect(page.locator('table').nth(1).getByRole('row').filter({ hasText: 'Budi Santoso' })).toBeVisible();

    const aditRow = athleteTable.getByRole('row').filter({ hasText: 'Adit Pratama' });
    await aditRow.getByRole('button', { name: /^(View Profile|Lihat profil)$/i }).click();
    await page.waitForURL((url) => /^\/users\/[0-9a-f-]{36}$/i.test(url.pathname));
    const aditProfileUrl = page.url();

    const guardianNameField = page.locator('#guardian-name');
    const guardianPhoneField = page.locator('#guardian-phone');
    const athleteProfileSection = guardianNameField.locator('xpath=ancestor::section[1]');
    const originalGuardianName = await guardianNameField.inputValue();
    const originalGuardianPhone = await guardianPhoneField.inputValue();
    const adminGuardianName = `Wali Admin E2E ${Date.now()}`;
    const adminGuardianPhone = '081377776666';

    await athleteProfileSection.getByRole('button', { name: /^(Edit|Ubah)$/ }).click();
    await guardianNameField.fill(adminGuardianName);
    await guardianPhoneField.fill(adminGuardianPhone);
    await athleteProfileSection.getByRole('button', { name: /Save Athlete Profile|Simpan/i }).click();
    await expect(guardianNameField).toBeDisabled();
    await page.reload();
    await expect(page.locator('#guardian-name')).toHaveValue(adminGuardianName);
    await expect(page.locator('#guardian-phone')).toHaveValue(adminGuardianPhone);

    const reloadedAthleteSection = page.locator('#guardian-name').locator('xpath=ancestor::section[1]');
    await reloadedAthleteSection.getByRole('button', { name: /^(Edit|Ubah)$/ }).click();
    await page.locator('#guardian-name').fill(originalGuardianName);
    await page.locator('#guardian-phone').fill(originalGuardianPhone);
    await reloadedAthleteSection.getByRole('button', { name: /Save Athlete Profile|Simpan/i }).click();
    await expect(page.locator('#guardian-name')).toBeDisabled();

    const certifications = page
        .getByRole('heading', { name: /^(Sertifikasi|Certifications)$/ })
        .locator('xpath=ancestor::div[contains(@class,"rounded-xl")][1]');
    const certTitle = `Admin Cert E2E ${Date.now()}`;
    await certifications.getByRole('button', { name: /Tambah sertifikasi|Add certification/i }).click();
    let dialog = page.getByRole('dialog').last();
    await dialog.getByLabel(/Nama sertifikasi|Certification name/i).fill(certTitle);
    await dialog.getByRole('button', { name: /Tambah sertifikasi|Add certification/i }).click();
    await expect(dialog).toBeHidden();

    let certRow = certifications.getByRole('row').filter({ hasText: certTitle });
    await expect(certRow).toBeVisible();
    await certRow.getByRole('button', { name: /^(Ubah|Edit)$/ }).click();
    dialog = page.getByRole('dialog').last();
    await dialog.getByLabel(/Nama sertifikasi|Certification name/i).fill(`${certTitle} Edited`);
    await dialog.getByRole('button', { name: /Simpan sertifikasi|Save certification/i }).click();
    await expect(dialog).toBeHidden();
    certRow = certifications.getByRole('row').filter({ hasText: `${certTitle} Edited` });
    await expect(certRow).toBeVisible();
    await certRow.getByRole('button', { name: /^(Hapus|Delete)$/ }).click();
    await page
        .getByRole('dialog')
        .filter({ hasText: /Hapus sertifikasi\?|Delete certification\?/i })
        .getByRole('button', { name: /Hapus sertifikasi|Delete certification/i })
        .click();
    await expect(certifications.getByRole('row').filter({ hasText: `${certTitle} Edited` })).toHaveCount(0);

    await page.goto('/users');
    const nadiaRow = page.locator('table').first().getByRole('row').filter({ hasText: 'nadia@rfis.test' });
    await nadiaRow.getByRole('button', { name: /^(View Profile|Lihat profil)$/i }).click();
    await page.waitForURL((url) => /^\/users\/[0-9a-f-]{36}$/i.test(url.pathname));
    const nadiaProfileUrl = page.url();

    await logout(page);
    await loginAs(page, 'coach@rfis.test');
    const coachViewAthleteResp = await page.context().request.get(aditProfileUrl, { failOnStatusCode: false });
    expect(coachViewAthleteResp.status(), 'Coach must be denied direct admin/self profile access to an athlete').toBe(
        403,
    );

    await logout(page);
    await loginAs(page, 'athlete@rfis.test');
    const ownProfileResp = await page.context().request.get(aditProfileUrl, { failOnStatusCode: false });
    expect(ownProfileResp.status(), 'Athlete can view their own profile record via UUID route').toBe(200);

    const otherAthleteResp = await page.context().request.get(nadiaProfileUrl, { failOnStatusCode: false });
    expect(otherAthleteResp.status(), 'Athlete must be denied another athlete’s profile record').toBe(403);
});

test('admin account safety guards prevent self-demotion, self-suspension, and self-deletion', async ({ page }) => {
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/admin');

    const search = page.getByPlaceholder(/Cari pengguna berdasarkan nama|Search users by name/i);
    await search.fill('admin@rfis.test');
    const adminRow = page.getByRole('row').filter({ hasText: 'admin@rfis.test' });
    await expect(adminRow).toHaveCount(1);

    const pageData = JSON.parse((await page.locator('#app').getAttribute('data-page')) ?? '{}') as {
        props?: {
            users?: Array<{ id: number | string; uuid: string; email: string; name: string }>;
            initialUsers?: Array<{ id: number | string; uuid: string; email: string; name: string }>;
        };
    };
    const allUsers = pageData.props?.initialUsers ?? pageData.props?.users ?? [];
    const adminRecord = allUsers.find((user) => user.email === 'admin@rfis.test');
    expect(adminRecord?.uuid).toBeTruthy();

    await adminRow
        .getByRole('button', { name: /^(Hapus|Delete)$/ })
        .first()
        .click();
    const deleteConfirmDialog = page.getByRole('dialog').last();
    await expect(deleteConfirmDialog).toBeVisible();
    await deleteConfirmDialog.getByRole('button', { name: /Delete account|Hapus akun/i }).click();
    await expect(page.getByText(/You cannot delete your own account/i).first()).toBeVisible();
    const selfDeleteToast = page.getByRole('status');
    if ((await selfDeleteToast.count()) > 0) {
        await selfDeleteToast.first().getByRole('button').click();
    }
    await expect(adminRow).toBeVisible();

    const csrfCookie = (await page.context().cookies()).find(({ name }) => name === 'XSRF-TOKEN');
    expect(csrfCookie).toBeDefined();
    const headers = {
        Accept: 'application/json',
        'X-XSRF-TOKEN': decodeURIComponent(csrfCookie?.value ?? ''),
    };

    const selfDemote = await page
        .context()
        .request.put(new URL(`/admin/accounts/${adminRecord!.uuid}`, page.url()).toString(), {
            failOnStatusCode: false,
            headers,
            data: {
                name: adminRecord!.name,
                email: adminRecord!.email,
                roles: ['coach'],
                status: 'active',
            },
        });
    expect(selfDemote.status(), 'Admin must not be able to remove their own admin role').toBe(422);

    const selfSuspend = await page
        .context()
        .request.put(new URL(`/admin/accounts/${adminRecord!.uuid}`, page.url()).toString(), {
            failOnStatusCode: false,
            headers,
            data: {
                name: adminRecord!.name,
                email: adminRecord!.email,
                roles: ['admin'],
                status: 'suspended',
            },
        });
    expect(selfSuspend.status(), 'Admin must not be able to suspend their own account').toBe(422);

    const selfSoftDelete = await page
        .context()
        .request.delete(new URL(`/admin/accounts/${adminRecord!.uuid}`, page.url()).toString(), {
            failOnStatusCode: false,
            headers,
        });
    expect(selfSoftDelete.status(), 'Admin must not be able to soft-delete their own account').toBe(422);

    const selfForceDelete = await page
        .context()
        .request.delete(new URL(`/admin/accounts/${adminRecord!.id}/hard-delete`, page.url()).toString(), {
            failOnStatusCode: false,
            headers,
        });
    expect(selfForceDelete.status(), 'Admin must not be able to force-delete their own account').toBe(422);
});

test('class member modal and group athletes endpoint enforce admin and assigned-coach scoping', async ({ page }) => {
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/admin/classes');

    const pageData = JSON.parse((await page.locator('#app').getAttribute('data-page')) ?? '{}') as {
        props?: { classes?: Array<{ id: string; uuid?: string; route_id?: string; name: string }> };
    };
    const juniorClass = pageData.props?.classes?.find((cls) => cls.name === 'Junior Sparring');
    const competitionClass = pageData.props?.classes?.find((cls) => cls.name === 'Competition Team');
    expect(juniorClass).toBeDefined();
    expect(competitionClass).toBeDefined();
    const juniorGroupKey = juniorClass!.uuid ?? juniorClass!.route_id ?? juniorClass!.id;
    const competitionGroupKey = competitionClass!.uuid ?? competitionClass!.route_id ?? competitionClass!.id;

    const juniorRow = page.getByRole('row').filter({ hasText: 'Junior Sparring' }).first();
    await juniorRow.getByRole('button', { name: 'Lihat Anggota kelas' }).click();
    const membersDialog = page.getByRole('dialog').filter({ hasText: 'Junior Sparring' });
    await expect(membersDialog).toBeVisible();
    await expect(membersDialog.getByRole('table').getByText('Adit Pratama')).toBeVisible();
    await membersDialog
        .getByRole('button', { name: /Close modal|Tutup|Close/i })
        .first()
        .click();
    await expect(membersDialog).toBeHidden();

    const missingGroupResp = await page
        .context()
        .request.get(
            new URL('/api/admin/groups/00000000-0000-0000-0000-000000000000/athletes', page.url()).toString(),
            { failOnStatusCode: false, headers: { Accept: 'application/json' } },
        );
    expect(missingGroupResp.status(), 'Unknown group UUID must return 404').toBe(404);

    await logout(page);
    await loginAs(page, 'coach@rfis.test');
    const assignedGroupResp = await page
        .context()
        .request.get(
            new URL(`/api/admin/groups/${encodeURIComponent(juniorGroupKey)}/athletes`, page.url()).toString(),
            { failOnStatusCode: false, headers: { Accept: 'application/json' } },
        );
    expect(assignedGroupResp.status(), 'Assigned coach can fetch athletes for their own class').toBe(200);
    const assignedPayload = (await assignedGroupResp.json()) as { athletes?: Array<{ name: string }> };
    expect(assignedPayload.athletes?.some((athlete) => athlete.name === 'Adit Pratama')).toBe(true);

    const unassignedGroupResp = await page
        .context()
        .request.get(
            new URL(`/api/admin/groups/${encodeURIComponent(competitionGroupKey)}/athletes`, page.url()).toString(),
            { failOnStatusCode: false, headers: { Accept: 'application/json' } },
        );
    expect(unassignedGroupResp.status(), 'Coach must be denied athletes for an unassigned class').toBe(403);

    await logout(page);
    await loginAs(page, 'athlete@rfis.test');
    const athleteGroupResp = await page
        .context()
        .request.get(
            new URL(`/api/admin/groups/${encodeURIComponent(juniorGroupKey)}/athletes`, page.url()).toString(),
            { failOnStatusCode: false, headers: { Accept: 'application/json' } },
        );
    expect(athleteGroupResp.status(), 'Athlete must be denied the admin/coach group athletes endpoint').toBe(403);
});

test('admin manages training classes, weekly and daily schedules, session visibility filters, session edits, and coach sessions', async ({
    page,
}) => {
    test.setTimeout(90_000);
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/admin/classes');

    await page.getByRole('button', { name: 'Tambah Grup' }).click();
    const classDialog = page.getByRole('dialog').last();
    await expect(classDialog).toBeVisible();

    const className = `E2E Class ${Date.now()}`;
    const updatedClassName = `${className} Updated`;
    await classDialog.locator('#class-name').fill(className);
    await classDialog.locator('#class-type').click();
    await page.getByRole('button', { name: 'Private', exact: true }).click();
    await classDialog.locator('#branch').click();
    await page.getByRole('button', { name: 'Central Dojang', exact: true }).click();
    await classDialog.locator('#class-coaches').click();
    await page.getByRole('button', { name: 'Budi Santoso', exact: true }).click();
    await page.keyboard.press('Escape');
    await expect(classDialog.locator('#class-coaches')).toHaveAttribute('aria-expanded', 'false');
    await classDialog.getByRole('button', { name: 'Simpan Kelas' }).click();
    await expect(classDialog.getByText(/dedicated athlete ids/i)).toBeVisible();
    await expect(classDialog.getByRole('button', { name: 'Simpan Kelas' })).toBeEnabled();
    const privateErrorToast = page.getByRole('status');
    if ((await privateErrorToast.count()) > 0) {
        await privateErrorToast.first().getByRole('button').click();
    }

    await classDialog.locator('#class-type').click();
    await page.getByRole('button', { name: 'Reguler', exact: true }).click();
    await classDialog.locator('#training-group').click();
    await page.getByRole('button', { name: 'Junior', exact: true }).click();
    await classDialog.getByRole('button', { name: 'Simpan Kelas' }).click();
    await expect(classDialog).toBeHidden();

    const classSearch = page.getByPlaceholder(/Cari kelas, lokasi, atau pelatih/i);
    await classSearch.fill(className);
    let classRow = page.getByRole('row').filter({ hasText: className }).first();
    await expect(classRow).toBeVisible();

    await classRow.getByRole('button', { name: 'Lihat sesi kelas' }).click();
    const sessionsModal = page.getByRole('dialog').filter({ hasText: 'Riwayat Sesi Kelas' });
    await expect(sessionsModal).toBeVisible();
    await sessionsModal.getByRole('button', { name: 'Semua' }).click();
    await sessionsModal
        .getByRole('button', { name: /Close modal|Tutup|Close/i })
        .first()
        .click();
    await expect(sessionsModal).toBeHidden();

    await classRow.getByRole('button', { name: 'Edit kelas' }).click();
    const editDialog = page.getByRole('dialog').last();
    await editDialog.locator('#class-name').fill(updatedClassName);
    await editDialog.getByRole('button', { name: 'Simpan Kelas' }).click();
    await expect(editDialog).toBeHidden();

    await classSearch.fill(updatedClassName);
    classRow = page.getByRole('row').filter({ hasText: updatedClassName }).first();
    await expect(classRow).toBeVisible();

    await classRow.getByRole('button', { name: 'Hapus/nonaktifkan kelas' }).click();
    await page
        .getByRole('dialog')
        .filter({ hasText: /Hapus atau nonaktifkan kelas\?/i })
        .getByRole('button', { name: 'Lanjutkan' })
        .click();
    await expect(page.getByRole('row').filter({ hasText: updatedClassName })).toHaveCount(0);

    await page.goto('/training-schedule');
    await expect(page.getByRole('button', { name: 'Kartu' })).toBeVisible();
    await page.getByRole('button', { name: 'Tabel' }).click();
    await expect(page.getByRole('table')).toBeVisible();
    await page.getByRole('button', { name: 'Kartu' }).click();

    await page.goto('/admin/daily-schedules');
    await expect(page.getByRole('heading', { name: 'Jadwal Latihan Harian' })).toBeVisible();

    await page.goto('/sessions');
    await page.getByRole('button', { name: /^Archived/i }).click();
    await page.waitForURL((url) => url.searchParams.get('visibility') === 'archived');
    await page.getByRole('button', { name: /^All/i }).click();
    await page.waitForURL((url) => url.searchParams.get('visibility') === 'all');
    await page.getByRole('button', { name: /^Upcoming/i }).click();
    await page.waitForURL((url) => url.searchParams.get('visibility') === 'upcoming');

    const juniorSessionRow = page.getByRole('row').filter({ hasText: 'Junior Sparring' }).first();
    await expect(juniorSessionRow).toBeVisible();
    await juniorSessionRow.getByRole('link', { name: /^(Edit|Ubah)$/ }).click();
    await page.waitForURL((url) => /^\/sessions\/[^/]+\/attendance$/.test(url.pathname));

    await page.getByRole('button', { name: 'Ubah sesi' }).click();
    const editSessionModal = page.getByRole('dialog').last();
    await expect(editSessionModal.locator('#session-name')).toBeVisible();
    await editSessionModal.getByRole('button', { name: 'Batal' }).click();
    await expect(editSessionModal).toBeHidden();

    await logout(page);
    await loginAs(page, 'coach@rfis.test');
    await page.goto('/sessions');
    await expect(page.getByRole('row').filter({ hasText: 'Junior Sparring' }).first()).toBeVisible();
});

test('session attendance sheet supports individual and bulk status updates, coach teaching status, QR lifecycle, offline athlete QR sync, and attendance reports', async ({
    page,
}) => {
    test.setTimeout(120_000);
    await page.route('**/favicon.svg', (route) => route.fulfill({ status: 204, body: '' }));

    await loginAs(page, 'admin@rfis.test');
    await page.goto('/sessions');

    const juniorRow = page.getByRole('row').filter({ hasText: 'Junior Sparring' }).first();
    await expect(juniorRow).toBeVisible();
    const attendanceHref = await juniorRow.getByRole('link', { name: /^(Edit|Ubah)$/ }).getAttribute('href');
    expect(attendanceHref).toBeTruthy();

    await page.goto(new URL(attendanceHref!, page.url()).toString());
    await expect(page.getByRole('heading', { name: 'Junior Sparring', exact: true })).toBeVisible();

    const aditRow = page.getByRole('row').filter({ hasText: 'Adit Pratama' }).first();
    await expect(aditRow).toBeVisible();
    await aditRow.getByRole('button', { name: 'Terlambat' }).click();
    await expect(aditRow.locator('td').nth(1)).toHaveText(/^(Late|Terlambat)$/i);
    await page.reload();
    await expect(page.getByRole('row').filter({ hasText: 'Adit Pratama' }).first().locator('td').nth(1)).toHaveText(
        /^(Late|Terlambat)$/i,
    );

    const reloadedAditRow = page.getByRole('row').filter({ hasText: 'Adit Pratama' }).first();
    await reloadedAditRow.getByRole('button', { name: 'Izin' }).click();
    await expect(reloadedAditRow.locator('td').nth(1)).toHaveText(/^(Excused|Izin)$/i);
    await reloadedAditRow.getByRole('button', { name: 'Hadir', exact: true }).click();
    await expect(reloadedAditRow.locator('td').nth(1)).toHaveText(/^(Present|Hadir)$/i);

    await page.getByRole('button', { name: 'Tandai semua hadir' }).click();
    await page
        .getByRole('dialog')
        .filter({ hasText: /Ubah seluruh atlet\?/i })
        .getByRole('button', { name: 'Terapkan perubahan' })
        .click();
    await expect(page.getByRole('row').filter({ hasText: 'Anak Semua Peran' }).first().locator('td').nth(1)).toHaveText(
        /^(Present|Hadir)$/i,
    );

    const childRow = page.getByRole('row').filter({ hasText: 'Anak Semua Peran' }).first();
    await childRow.getByRole('button', { name: 'Tidak hadir' }).click();
    await expect(childRow.locator('td').nth(1)).toHaveText(/^(Absent|Tidak hadir)$/i);

    const coachRow = page.getByRole('row').filter({ hasText: 'Budi Santoso' }).first();
    await expect(coachRow).toBeVisible();
    await coachRow.getByRole('button', { name: 'Mengajar', exact: true }).click();
    await expect(coachRow.locator('td').nth(1)).toHaveText(/^(Teach|Mengajar)$/i);

    const openQrBtn = page.getByRole('button', { name: 'Buka QR sekarang' });
    if (await openQrBtn.isVisible()) {
        await openQrBtn.click();
    }
    await expect(page.getByText('QR tetap aktif sampai admin atau pelatih menekan Tutup QR.')).toBeVisible();
    const scanUrl = (
        await page
            .getByText(/\/attendance\/scan\//)
            .first()
            .innerText()
    ).trim();
    expect(new URL(scanUrl).pathname).toMatch(/^\/attendance\/scan\//);

    await logout(page);
    await loginAs(page, 'allroles.child@rfis.test');
    await page.goto('/attendance');

    await page.context().setOffline(true);
    const offlineToast = page.getByRole('status');
    if ((await offlineToast.count()) > 0) {
        await offlineToast.first().getByRole('button').click();
    }

    await page.getByRole('textbox', { name: /\/attendance\/scan/i }).fill(scanUrl);
    await page.getByRole('button', { name: 'Use pasted link' }).click();
    await expect(page.getByText(/1 pending|1 scan menunggu sinkronisasi/i).first()).toBeVisible();
    const queuedToast = page.getByRole('status');
    if ((await queuedToast.count()) > 0) {
        await queuedToast.first().getByRole('button').click();
    }

    const syncResponsePromise = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' && new URL(response.url()).pathname === new URL(scanUrl).pathname,
    );
    await page.context().setOffline(false);
    const syncResponse = await syncResponsePromise;
    expect(syncResponse.status()).toBeLessThan(400);
    await expect(page.getByText(/1 pending|1 scan menunggu sinkronisasi/i)).toHaveCount(0);

    await logout(page);
    await loginAs(page, 'admin@rfis.test');
    await page.goto(new URL(attendanceHref!, page.url()).toString());
    const verifiedChildRow = page.getByRole('row').filter({ hasText: 'Anak Semua Peran' }).first();
    await expect(verifiedChildRow.locator('td').nth(1)).toHaveText(/^(Present|Hadir)$/i);
    await verifiedChildRow.getByRole('button', { name: 'Tidak hadir' }).click();
    await expect(verifiedChildRow.locator('td').nth(1)).toHaveText(/^(Absent|Tidak hadir)$/i);

    const closeQrBtn = page.getByRole('button', { name: 'Tutup QR' }).first();
    await closeQrBtn.click();
    await page.getByRole('dialog').getByRole('button', { name: 'Tutup QR' }).click();
    await expect(page.getByRole('button', { name: 'Buka QR sekarang' })).toBeVisible();

    await logout(page);
    await loginAs(page, 'allroles.child@rfis.test');
    await page.goto(scanUrl);
    await expect(page.getByRole('button', { name: 'Save attendance now' })).toHaveCount(0);

    await logout(page);
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/admin/attendance');
    await expect(page.getByRole('heading', { name: 'Detail presensi atlet' })).toBeVisible();
    const athleteExportResp = await page
        .context()
        .request.get(new URL('/admin/attendance/export', page.url()).toString());
    expect(athleteExportResp.status()).toBe(200);
    expect(athleteExportResp.headers()['content-type']).toContain('spreadsheetml');

    await page.goto('/admin/instructor-attendance');
    await expect(page.getByRole('heading', { name: 'Ringkasan presensi coach' })).toBeVisible();
    await page.getByRole('button', { name: 'Tambah presensi coach' }).click();
    const manualCoachModal = page.getByRole('dialog').last();
    await expect(manualCoachModal.locator('#manual-coach-id')).toBeVisible();
    await manualCoachModal.getByRole('button', { name: 'Batal' }).click();
    await expect(manualCoachModal).toBeHidden();

    const coachExportResp = await page
        .context()
        .request.get(new URL('/admin/instructor-attendance/export', page.url()).toString());
    expect(coachExportResp.status()).toBe(200);
    expect(coachExportResp.headers()['content-type']).toContain('spreadsheetml');
});

test('finance invoices, metadata lock, invalid and rejected proofs, invoice settings and exports, QRIS config, billing rules, and coach payroll slip work end to end', async ({
    page,
}) => {
    test.setTimeout(120_000);

    await loginAs(page, 'admin@rfis.test');
    await page.goto('/payments');

    const total = 415_000 + (Date.now() % 80_000);
    const formattedTotal = total.toLocaleString('id-ID');

    await page.getByRole('button', { name: 'Buat tagihan', exact: true }).click();
    await page.locator('#payment-recipient').click();
    await page.getByRole('button', { name: /^Adit Pratama/ }).click();
    await page.locator('#payment-total').fill(String(total));
    const today = new Date().toISOString().slice(0, 10);
    await page.locator('#payment-date').fill(today);
    await page.locator('#payment-due-date').fill(today);
    await page.locator('#payment-notes').fill('Synthetic E2E invoice lifecycle.');
    await page.getByRole('button', { name: 'Terbitkan tagihan' }).click();

    const search = page.getByPlaceholder(/Cari nomor tagihan, penerima, kategori, atau status/i);
    await search.fill(formattedTotal);
    const invoiceRow = page.getByRole('row').filter({ hasText: formattedTotal }).first();
    await expect(invoiceRow).toBeVisible();
    const invoiceNumber = (await invoiceRow.innerText()).match(/INV-[^\s\t]+/)?.[0];
    expect(invoiceNumber).toBeTruthy();

    await invoiceRow.getByRole('button', { name: 'Kelola' }).click();
    const manageDialog = page.getByRole('dialog').last();
    await expect(manageDialog.locator('#payment-notes')).toHaveValue('Synthetic E2E invoice lifecycle.');
    await manageDialog.locator('#payment-notes').fill('Synthetic E2E invoice lifecycle updated.');
    await manageDialog.getByRole('button', { name: 'Simpan metadata' }).click();
    await expect(manageDialog).toBeHidden();

    const csvResp = await page.context().request.get(new URL('/payments/export', page.url()).toString());
    expect(csvResp.status()).toBe(200);
    expect(csvResp.headers()['content-type']).toContain('text/csv');

    await page.getByRole('button', { name: 'Pengaturan invoice' }).click();
    const templateModal = page.getByRole('dialog').filter({ hasText: 'Pengaturan invoice' });
    await expect(templateModal.locator('#invoice-company-name')).toBeVisible();
    await templateModal.getByRole('button', { name: 'Simpan pengaturan' }).click();
    await expect(templateModal).toBeHidden();

    await logout(page);
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/payments');
    await page.getByPlaceholder(/Cari nomor tagihan, penerima, kategori, atau status/i).fill(invoiceNumber!);
    const athleteRow = page.getByRole('row').filter({ hasText: invoiceNumber! }).first();
    await expect(athleteRow).toBeVisible();

    await athleteRow.getByRole('button', { name: 'Upload bukti' }).click();
    const proofModal = page.getByRole('dialog').last();
    await proofModal.locator('#proof-notes').fill('Invalid proof format check');
    await proofModal.locator('#proof-file').setInputFiles({
        name: 'invalid-proof.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('not a pdf or image'),
    });
    await proofModal.getByRole('button', { name: 'Kirim untuk review' }).click();
    await expect(proofModal.getByText(/pdf|jpg|jpeg|png|webp|image|format|tipe/i).first()).toBeVisible();
    const invalidProofToast = page.getByRole('status');
    if ((await invalidProofToast.count()) > 0) {
        await invalidProofToast.first().getByRole('button').click();
    }

    if (process.env.RFIS_E2E_PERSISTENT_STORAGE !== '1') {
        await proofModal.locator('#proof-file').setInputFiles({
            name: 'valid-proof.pdf',
            mimeType: 'application/pdf',
            buffer: Buffer.from('%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n'),
        });
        await proofModal.getByRole('button', { name: 'Kirim untuk review' }).click();
        await expect(proofModal).toBeHidden();
    } else {
        await proofModal.getByRole('button', { name: 'Batal' }).click();
        await expect(proofModal).toBeHidden();
    }

    await logout(page);
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/payments');

    if (process.env.RFIS_E2E_PERSISTENT_STORAGE !== '1') {
        await page.getByPlaceholder(/Cari nomor tagihan, penerima, kategori, atau status/i).fill(invoiceNumber!);
        const reviewRow = page.getByRole('row').filter({ hasText: invoiceNumber! }).first();
        await reviewRow.getByRole('button', { name: 'Review bukti' }).click();
        const reviewModal = page.getByRole('dialog').filter({ hasText: 'Review bukti pembayaran' });
        await reviewModal.locator('#review-notes').fill('Bukti tidak terbaca, silakan unggah ulang.');
        await reviewModal.getByRole('button', { name: 'Tolak bukti' }).click();
        await page
            .getByRole('dialog')
            .filter({ hasText: /Tolak bukti pembayaran\?/i })
            .getByRole('button', { name: 'Tolak bukti' })
            .click();
        await expect(reviewModal).toBeHidden();
        await expect(page.getByRole('row').filter({ hasText: invoiceNumber! }).first()).toContainText('Ditolak');
    }

    await page.getByPlaceholder(/Cari nomor tagihan, penerima, kategori, atau status/i).fill('');
    await page.getByRole('button', { name: 'Buat tagihan', exact: true }).click();
    await page.locator('#payment-recipient').click();
    await page.getByRole('button', { name: /^Adit Pratama/ }).click();
    const emptyTotal = total + 111;
    const formattedEmptyTotal = emptyTotal.toLocaleString('id-ID');
    await page.locator('#payment-total').fill(String(emptyTotal));
    await page.locator('#payment-date').fill(today);
    await page.locator('#payment-due-date').fill(today);
    await page.locator('#payment-notes').fill('Disposable empty invoice for deletion.');
    await page.getByRole('button', { name: 'Terbitkan tagihan' }).click();

    await page.getByPlaceholder(/Cari nomor tagihan, penerima, kategori, atau status/i).fill(formattedEmptyTotal);
    const emptyRow = page.getByRole('row').filter({ hasText: formattedEmptyTotal }).first();
    await expect(emptyRow).toBeVisible();
    await emptyRow.getByRole('button', { name: 'Kelola' }).click();
    await page.getByRole('dialog').last().getByRole('button', { name: 'Hapus tagihan kosong' }).click();
    await page
        .getByRole('dialog')
        .filter({ hasText: /Hapus tagihan kosong\?/i })
        .getByRole('button', { name: 'Hapus tagihan' })
        .click();
    await expect(page.getByRole('row').filter({ hasText: formattedEmptyTotal })).toHaveCount(0);

    await page.goto('/payments/qris');
    const qrisLabelInput = page.locator('#qris-label');
    const originalQrisLabel = await qrisLabelInput.inputValue();
    const updatedQrisLabel = `${originalQrisLabel} E2E`;
    await qrisLabelInput.fill(updatedQrisLabel);
    await page.getByRole('button', { name: 'Simpan QRIS' }).click();
    await page.reload();
    await expect(page.locator('#qris-label')).toHaveValue(updatedQrisLabel);
    await page.locator('#qris-label').fill(originalQrisLabel);
    await page.getByRole('button', { name: 'Simpan QRIS' }).click();

    await page.goto('/admin/billing-settings');
    await page.getByRole('button', { name: 'Simpan jadwal' }).click();
    const scopedRuleName = `SPP Cabang E2E ${Date.now()}`;
    await page.getByRole('button', { name: 'Buat aturan', exact: true }).click();
    const ruleDialog = page.getByRole('dialog').last();
    await ruleDialog.locator('#billing-rule-name').fill(scopedRuleName);
    await ruleDialog.locator('#billing-rule-amount').fill('185000');
    await ruleDialog.locator('#billing-rule-branch').click();
    await page.getByRole('button', { name: 'Central Dojang', exact: true }).click();
    await ruleDialog.getByRole('button', { name: 'Simpan aturan' }).click();
    await expect(ruleDialog).toBeHidden();

    const ruleCard = page.locator('article').filter({ hasText: scopedRuleName }).first();
    await expect(ruleCard).toBeVisible();
    await ruleCard.getByRole('button', { name: 'Arsipkan' }).click();
    await page
        .getByRole('dialog')
        .filter({ hasText: /Arsipkan aturan tagihan\?/i })
        .getByRole('button', { name: 'Arsipkan aturan' })
        .click();
    await expect(ruleCard).toHaveCount(0);

    await page.goto('/admin/payroll');
    const slipLink = page.getByRole('link', { name: /Slip/i }).first();
    if ((await slipLink.count()) > 0) {
        const slipHref = await slipLink.getAttribute('href');
        expect(slipHref).toBeTruthy();
        const slipResp = await page.context().request.get(new URL(slipHref!, page.url()).toString());
        expect(slipResp.status()).toBe(200);
        expect(slipResp.headers()['content-type']).toContain('application/pdf');
    }
});

test('championships CRUD, detail coach and athlete registration, result recording, event history edits, WhatsApp templates, data export, and activity logs work end to end', async ({
    page,
}) => {
    test.setTimeout(120_000);
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/championships');

    const eventName = `E2E Championship ${Date.now()}`;
    const updatedLocation = 'GOR E2E Updated';
    await page.getByRole('button', { name: 'Tambah kejuaraan' }).click();
    let eventModal = page.getByRole('dialog').last();
    await eventModal.locator('#event-new-name').fill(eventName);
    await eventModal.locator('#event-new-date').fill('2027-05-10');
    await eventModal.locator('#event-registration-deadline').fill('2027-05-01T18:00');
    await eventModal.locator('#event-new-location').fill('GOR E2E');
    await eventModal.locator('#event-new-price').fill('150000');
    await eventModal.locator('#event-new-slots').fill('20');
    await eventModal.getByRole('button', { name: 'Simpan kejuaraan' }).click();
    await expect(eventModal).toBeHidden();

    const search = page.getByPlaceholder(/Search|Cari/i).first();
    await search.fill(eventName);
    const eventRow = page.getByRole('row').filter({ hasText: eventName }).first();
    await expect(eventRow).toBeVisible();

    await eventRow.getByRole('button', { name: 'Ubah' }).click();
    eventModal = page.getByRole('dialog').last();
    await eventModal.locator('#event-new-location').fill(updatedLocation);
    await eventModal.getByRole('button', { name: 'Simpan kejuaraan' }).click();
    await expect(eventModal).toBeHidden();
    await expect(page.getByRole('row').filter({ hasText: eventName }).first()).toContainText(updatedLocation);

    const detailHref = await page
        .getByRole('row')
        .filter({ hasText: eventName })
        .first()
        .getByRole('link', { name: 'Detail' })
        .getAttribute('href');
    expect(detailHref).toBeTruthy();
    await page.goto(new URL(detailHref!, page.url()).toString());
    await expect(page.getByRole('heading', { name: eventName })).toBeVisible();

    await page.getByRole('button', { name: 'Tambah pelatih' }).click();
    const coachModal = page.getByRole('dialog').last();
    await coachModal.locator('#championship-coach').click();
    await page.getByRole('button', { name: 'Budi Santoso', exact: true }).click();
    await coachModal.locator('#championship-coach-role').fill('Pelatih Utama');
    await coachModal.getByRole('button', { name: 'Tambahkan' }).click();
    await expect(coachModal).toBeHidden();
    await expect(page.getByRole('row').filter({ hasText: 'Budi Santoso' }).first()).toBeVisible();

    await page.getByRole('button', { name: 'Tambah atlet' }).click();
    const participantModal = page.getByRole('dialog').last();
    await participantModal.locator('#managed-event-athlete').click();
    await page.getByRole('button', { name: /^Adit Pratama/ }).click();
    await participantModal.locator('#managed-event-classification').fill('Junior');
    await participantModal.locator('#managed-event-class').fill('Under 55 kg');
    await participantModal.locator('#managed-event-division').fill('Putra');
    await participantModal.getByRole('button', { name: 'Tambahkan atlet' }).click();
    await expect(participantModal).toBeHidden();

    const participantRow = page.getByRole('row').filter({ hasText: 'Adit Pratama' }).first();
    await expect(participantRow).toBeVisible();
    const profileLink = participantRow.getByRole('link', { name: 'Profil' });
    await expect(profileLink).toHaveAttribute('href', /\/users\/[0-9a-f-]{36}$/i);

    await participantRow.getByRole('button', { name: 'Catat hasil' }).click();
    let resultModal = page.getByRole('dialog').last();
    await resultModal.locator('#result-medal').click();
    await page.getByRole('button', { name: 'Emas', exact: true }).click();
    await resultModal.getByRole('button', { name: 'Simpan hasil' }).click();
    await expect(resultModal.getByText(/ongoing or completed/i)).toBeVisible();
    const resultErrorToast = page.getByRole('status');
    if ((await resultErrorToast.count()) > 0) {
        await resultErrorToast.first().getByRole('button').click();
    }
    await resultModal.getByRole('button', { name: 'Batal' }).click();
    await expect(resultModal).toBeHidden();

    const exportCsvHref = await page.getByRole('link', { name: 'Ekspor CSV' }).getAttribute('href');
    expect(exportCsvHref).toBeTruthy();
    const exportCsvResp = await page.context().request.get(new URL(exportCsvHref!, page.url()).toString());
    expect(exportCsvResp.status()).toBe(200);
    expect(exportCsvResp.headers()['content-type']).toContain('text/csv');

    await page.goto('/championships');
    await page
        .getByPlaceholder(/Search|Cari/i)
        .first()
        .fill(eventName);
    await page.getByRole('row').filter({ hasText: eventName }).first().getByRole('button', { name: 'Ubah' }).click();
    eventModal = page.getByRole('dialog').last();
    await eventModal.locator('#event-new-status').click();
    await page.getByRole('button', { name: 'Selesai', exact: true }).click();
    await eventModal.getByRole('button', { name: 'Simpan kejuaraan' }).click();
    await expect(eventModal).toBeHidden();

    await page.goto(new URL(detailHref!, page.url()).toString());
    await page
        .getByRole('row')
        .filter({ hasText: 'Adit Pratama' })
        .first()
        .getByRole('button', { name: 'Catat hasil' })
        .click();
    resultModal = page.getByRole('dialog').last();
    await resultModal.locator('#result-medal').click();
    await page.getByRole('button', { name: 'Emas', exact: true }).click();
    await resultModal.getByRole('button', { name: 'Simpan hasil' }).click();
    await expect(resultModal).toBeHidden();

    await page.goto('/admin/events/history');
    await page.getByPlaceholder(/Cari event, tanggal, lokasi/i).fill(eventName);
    const historyCard = page.locator('article').filter({ hasText: eventName }).first();
    await expect(historyCard).toBeVisible();
    await historyCard.getByRole('button', { name: 'Lihat & edit hasil' }).click();
    const historyRow = historyCard.getByRole('row').filter({ hasText: 'Adit Pratama' }).first();
    await expect(historyRow).toContainText('Emas');
    await historyRow.getByRole('button', { name: /Ubah hasil|Catat hasil/i }).click();
    const historyModal = page.getByRole('dialog').last();
    await historyModal.locator('#history-result-medal').click();
    await page.getByRole('button', { name: 'Perak', exact: true }).click();
    await historyModal.getByRole('button', { name: 'Simpan perubahan hasil' }).click();
    await expect(historyModal).toBeHidden();
    await expect(historyCard.getByRole('row').filter({ hasText: 'Adit Pratama' }).first()).toContainText('Perak');

    await page.goto('/admin/whatsapp-template');
    await page.locator('#public-admin-whatsapp').fill('6281234567890');
    const templateTextarea = page.locator('textarea').first();
    const originalTemplate = await templateTextarea.inputValue();
    await templateTextarea.fill(`${originalTemplate} {unsupported_token}`);
    await page.getByRole('button', { name: 'Simpan pengaturan' }).click();
    await expect(page.getByText(/Placeholder tidak dikenal/i).first()).toBeVisible();
    const waErrorToast = page.getByRole('status');
    if ((await waErrorToast.count()) > 0) {
        await waErrorToast.first().getByRole('button').click();
    }
    await templateTextarea.fill(originalTemplate);
    await page.getByRole('button', { name: 'Simpan pengaturan' }).click();
    await expect(page.getByText(/Placeholder tidak dikenal/i)).toHaveCount(0);

    await page.goto('/admin/data-export');
    await expect(
        page.getByRole('heading', { name: /Build a custom Excel export|Buat ekspor Excel khusus/i }),
    ).toBeVisible();
    const exportExcelResp = await page
        .context()
        .request.get(
            new URL(
                '/admin/data-export/download?datasets[]=athletes&fields[athletes][]=athlete_name&fields[athletes][]=email&fields[athletes][]=address',
                page.url(),
            ).toString(),
        );
    expect(exportExcelResp.status()).toBe(200);
    expect(exportExcelResp.headers()['content-type']).toContain('spreadsheetml');

    await page.goto('/admin/activity-logs');
    await page.locator('#log-search').fill('event');
    await page.getByRole('button', { name: /^(Apply|Terapkan)$/i }).click();
    await expect(page.getByRole('table')).toBeVisible();
    await page.getByRole('button', { name: /^(Reset|Atur ulang)$/i }).click();
});

async function xsrfHeaders(page: Page): Promise<Record<string, string>> {
    const csrfCookie = (await page.context().cookies()).find(({ name }) => name === 'XSRF-TOKEN');
    expect(csrfCookie).toBeDefined();

    return { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(csrfCookie?.value ?? '') };
}

async function inertiaProps<T = Record<string, unknown>>(page: Page): Promise<T> {
    const pageData = JSON.parse((await page.locator('#app').getAttribute('data-page')) ?? '{}') as { props?: T };

    return (pageData.props ?? {}) as T;
}

test('TR-04: weekly schedule detail modal, table search, and standalone schedule create, validate, edit, delete with authorization', async ({
    page,
}) => {
    test.setTimeout(120_000);
    await page.route('https://*.tile.openstreetmap.org/**', (route) =>
        route.fulfill({
            status: 200,
            contentType: 'image/png',
            body: Buffer.from(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/m4kAAAAASUVORK5CYII=',
                'base64',
            ),
        }),
    );
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/training-schedule');

    const props = await inertiaProps<{
        weeklySchedules: Array<{ title: string }>;
        branchOptions: Array<{ value: string | number; label: string }>;
        coachOptions: Array<{ value: string | number; label: string }>;
    }>(page);
    expect(props.weeklySchedules.length).toBeGreaterThan(0);

    const firstCard = page.locator('article:visible').filter({ hasText: /Waktu/ }).first();
    await firstCard.click();
    const detailDialog = page.getByRole('dialog').filter({ hasText: 'Detail Jadwal' });
    await expect(detailDialog).toBeVisible();
    await expect(detailDialog).toContainText('Lokasi');
    await detailDialog
        .getByRole('button', { name: /Close modal|Tutup|Close/i })
        .first()
        .click();
    await expect(detailDialog).toBeHidden();

    await page.getByRole('button', { name: 'Tabel' }).click();
    const tableSearch = page.getByPlaceholder(/Cari jadwal, kelas, pelatih, anak, atau atlet/i);
    await tableSearch.fill('zzz-no-such-schedule');
    await expect(page.getByRole('row').filter({ hasText: props.weeklySchedules[0].title })).toHaveCount(0);
    await tableSearch.fill(props.weeklySchedules[0].title);
    await expect(page.getByRole('row').filter({ hasText: props.weeklySchedules[0].title }).first()).toBeVisible();

    const headers = await xsrfHeaders(page);
    const title = `E2E Weekly ${Date.now()}`;
    const payload = {
        title,
        branch_id: props.branchOptions[0].value,
        coach_id: props.coachOptions[0].value,
        session_type: 'reguler',
        day_of_week: 7,
        start_time: '05:00',
        end_time: '06:00',
        is_active: false,
    };
    const create = await page.context().request.post(new URL('/training-schedules', page.url()).toString(), {
        failOnStatusCode: false,
        headers,
        data: payload,
        maxRedirects: 0,
    });
    expect(create.status(), 'Admin can create a standalone weekly schedule').toBeLessThan(400);

    await page.goto('/training-schedule');
    const created = (
        await inertiaProps<{ weeklySchedules: Array<{ uuid: string; title: string }> }>(page)
    ).weeklySchedules.find((schedule) => schedule.title === title);
    expect(created, 'Created schedule is persisted and visible to admin').toBeDefined();
    expect(created!.uuid).toMatch(/^[0-9a-f-]{36}$/i);

    const invalid = await page.context().request.post(new URL('/training-schedules', page.url()).toString(), {
        failOnStatusCode: false,
        headers,
        data: { ...payload, title: `${title} bad`, end_time: '04:00' },
    });
    expect(invalid.status(), 'End time before start time is rejected').toBe(422);

    const update = await page
        .context()
        .request.put(new URL(`/training-schedules/${created!.uuid}`, page.url()).toString(), {
            failOnStatusCode: false,
            headers,
            data: { ...payload, title: `${title} edited` },
            maxRedirects: 0,
        });
    expect(update.status()).toBeLessThan(400);
    await page.goto('/training-schedule');
    const edited = (
        await inertiaProps<{ weeklySchedules: Array<{ uuid: string; title: string }> }>(page)
    ).weeklySchedules.find((schedule) => schedule.title === `${title} edited`);
    expect(edited, 'Edit persisted').toBeDefined();

    await logout(page);
    await loginAs(page, 'athlete@rfis.test');
    const athleteHeaders = await xsrfHeaders(page);
    const athleteDelete = await page
        .context()
        .request.delete(new URL(`/training-schedules/${edited!.uuid}`, page.url()).toString(), {
            failOnStatusCode: false,
            headers: athleteHeaders,
        });
    expect(athleteDelete.status(), 'Athlete cannot delete a schedule').toBe(403);
    const athleteCreate = await page.context().request.post(new URL('/training-schedules', page.url()).toString(), {
        failOnStatusCode: false,
        headers: athleteHeaders,
        data: payload,
    });
    expect(athleteCreate.status(), 'Athlete cannot create a schedule').toBe(403);

    await logout(page);
    await loginAs(page, 'admin@rfis.test');
    const adminHeaders = await xsrfHeaders(page);
    const del = await page
        .context()
        .request.delete(new URL(`/training-schedules/${edited!.uuid}`, page.url()).toString(), {
            failOnStatusCode: false,
            headers: adminHeaders,
            maxRedirects: 0,
        });
    expect(del.status()).toBeLessThan(400);
    await page.goto('/training-schedule');
    expect(
        (await inertiaProps<{ weeklySchedules: Array<{ title: string }> }>(page)).weeklySchedules.some((schedule) =>
            schedule.title.startsWith(title),
        ),
    ).toBe(false);
});

test('TR-05: open-session join through the browser, duplicate and unauthorized join denial, and attendance-history lock', async ({
    page,
}) => {
    test.setTimeout(120_000);
    const sessionTitle = `E2E Open Session ${Date.now()}`;
    const sessionDate = new Date(Date.now() + 5 * 24 * 3600 * 1000).toISOString().slice(0, 10);

    await loginAs(page, 'admincoach@rfis.test');
    await page.goto('/sessions');
    const sessionProps = await inertiaProps<{ branches: Array<{ value: string | number }> }>(page);
    const headers = await xsrfHeaders(page);
    const create = await page.context().request.post(new URL('/sessions', page.url()).toString(), {
        failOnStatusCode: false,
        headers,
        data: {
            title: sessionTitle,
            branch_id: sessionProps.branches[0].value,
            session_date: sessionDate,
            start_time: '07:00',
            end_time: '08:00',
            status: 'NEEDS_ASSISTANT',
        },
        maxRedirects: 0,
    });
    expect(create.status()).toBeLessThan(400);

    await logout(page);
    await loginAs(page, 'coach@rfis.test');
    await page.goto('/sessions?visibility=all');
    await page.getByPlaceholder(/Search all session columns|Cari semua kolom sesi/i).fill(sessionTitle);
    const sessionRow = page.getByRole('row').filter({ hasText: sessionTitle });
    await expect(sessionRow).toBeVisible();
    await expect(sessionRow.getByRole('link', { name: /^(Edit|Ubah)$/ })).toHaveCount(0);
    await sessionRow.getByRole('button', { name: 'Join' }).click();
    await expect(sessionRow.getByRole('link', { name: /^(Edit|Ubah)$/ })).toBeVisible();
    await expect(sessionRow.getByRole('button', { name: 'Join' })).toHaveCount(0);

    const sessionUuid = (
        (await sessionRow.getByRole('link', { name: /^(Edit|Ubah)$/ }).getAttribute('href')) ?? ''
    ).match(/sessions\/([0-9a-f-]{36})\/attendance/)?.[1];
    expect(sessionUuid).toBeTruthy();

    const duplicate = await page
        .context()
        .request.post(new URL(`/sessions/${sessionUuid}/join`, page.url()).toString(), {
            failOnStatusCode: false,
            headers: await xsrfHeaders(page),
        });
    expect(duplicate.status(), 'Joining twice is rejected').toBe(403);

    await logout(page);
    await loginAs(page, 'athlete@rfis.test');
    const athleteJoin = await page
        .context()
        .request.post(new URL(`/sessions/${sessionUuid}/join`, page.url()).toString(), {
            failOnStatusCode: false,
            headers: await xsrfHeaders(page),
        });
    expect(athleteJoin.status(), 'Athletes cannot join coach sessions').toBe(403);

    await logout(page);
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/sessions?visibility=all');
    await page.getByPlaceholder(/Search all session columns|Cari semua kolom sesi/i).fill(sessionTitle);
    const adminRow = page.getByRole('row').filter({ hasText: sessionTitle });
    await adminRow.getByRole('button').last().click();
    await page
        .getByRole('dialog')
        .filter({ hasText: /Hapus sesi latihan\?/ })
        .getByRole('button', { name: 'Hapus sesi' })
        .click();
    await expect(page.getByRole('row').filter({ hasText: sessionTitle })).toHaveCount(0);

    await page.goto('/sessions?visibility=all');
    await page.getByPlaceholder(/Search all session columns|Cari semua kolom sesi/i).fill('Junior Sparring');
    const attendanceHref = await page
        .getByRole('row')
        .filter({ hasText: 'Junior Sparring' })
        .first()
        .getByRole('link', { name: /^(Edit|Ubah)$/ })
        .getAttribute('href');
    await page.goto(new URL(attendanceHref!, page.url()).toString());
    const sheet = await inertiaProps<{
        session: {
            uuid: string;
            title: string;
            date: string;
            start_time: string;
            end_time: string;
            branch_id: string | number;
            group_id: string | number | null;
            status: string;
        };
    }>(page);
    const lockedDate = new Date(new Date(`${sheet.session.date}T00:00:00Z`).getTime() + 86_400_000)
        .toISOString()
        .slice(0, 10);
    const lockedResponse = await page
        .context()
        .request.put(new URL(`/sessions/${sheet.session.uuid}`, page.url()).toString(), {
            failOnStatusCode: false,
            headers: await xsrfHeaders(page),
            data: {
                title: sheet.session.title,
                branch_id: sheet.session.branch_id,
                group_id: sheet.session.group_id,
                session_date: lockedDate,
                start_time: sheet.session.start_time,
                end_time: sheet.session.end_time,
                status: sheet.session.status,
            },
        });
    expect(lockedResponse.status(), 'Attendance history locks session identity').toBe(422);
    expect(await lockedResponse.text()).toContain('cannot be changed after attendance history exists');
});

test('ID-05 and INT-01: email verification gate, resend, tampered link, and valid link via local Mailpit', async ({
    page,
}) => {
    test.skip(
        process.env.RFIS_E2E_DEV_TARGET !== 'rf-is-dev',
        'Verification email delivery is verified against the development Mailpit service only.',
    );
    test.setTimeout(120_000);

    const emailAddress = `e2e.verify.${Date.now()}@rfis.test`;
    const password = `Verify-RFIS-${Date.now()}-A7!`;
    const mailpit = await request.newContext({ baseURL: 'http://localhost:9204' });

    try {
        await loginAs(page, 'admin@rfis.test');
        await page.goto('/admin');
        await page.getByRole('button', { name: 'Add new user' }).click();
        const dialog = page.getByRole('dialog').filter({ hasText: 'Create user account' });
        await dialog.getByLabel('Full name').fill(`E2E Verify ${Date.now()}`);
        await dialog.getByLabel('Email').fill(emailAddress);
        await dialog.locator('#admin-status').click();
        await page.getByRole('button', { name: 'Aktif', exact: true }).click();
        await dialog.locator('#admin-password').fill(password);
        await dialog.locator('#admin-password-confirmation').fill(password);
        await dialog.getByRole('button', { name: 'Create account' }).click();
        await expect(dialog).toBeHidden();
        await logout(page);

        await page.goto('/login');
        await page.getByLabel(/email address|alamat email/i).fill(emailAddress);
        await page.getByLabel(/password|kata sandi/i).fill(password);
        await page.getByTestId('login-button').click();
        await page.waitForURL((url) => url.pathname === '/verify-email');
        const gated = await page.context().request.get(new URL('/dashboard', page.url()).toString(), {
            failOnStatusCode: false,
            maxRedirects: 0,
        });
        expect(gated.status(), 'Unverified account cannot reach the dashboard').toBe(302);

        await page.getByRole('button', { name: 'Resend verification email' }).click();
        await expect(page.getByText(/A new verification link has been sent/)).toBeVisible();

        let messageId = '';
        await expect
            .poll(
                async () => {
                    const response = await mailpit.get('/api/v1/messages?limit=100');
                    const result = (await response.json()) as {
                        messages?: Array<{ ID?: string; Subject?: string; To?: Array<{ Address?: string }> }>;
                    };
                    messageId =
                        result.messages?.find(
                            (message) =>
                                message.Subject?.includes('Verify Email Address') &&
                                message.To?.some((recipient) => recipient.Address?.toLowerCase() === emailAddress),
                        )?.ID ?? '';

                    return messageId;
                },
                { timeout: 15_000, intervals: [250, 500, 1000] },
            )
            .not.toBe('');

        const message = (await (await mailpit.get(`/api/v1/message/${encodeURIComponent(messageId)}`)).json()) as {
            Text?: string;
            HTML?: string;
        };
        const body = `${message.Text ?? ''}\n${message.HTML ?? ''}`;
        const verifyUrl = Array.from(body.matchAll(/https?:\/\/[^\s<>"']+/g), ([candidate]) => {
            try {
                return new URL(candidate.replaceAll('&amp;', '&'));
            } catch {
                return null;
            }
        }).find((url): url is URL => url !== null && url.pathname.startsWith('/verify-email/'));
        expect(verifyUrl).toBeDefined();
        expect(verifyUrl!.origin).toBe(new URL(page.url()).origin);

        const tampered = new URL(verifyUrl!.toString());
        tampered.pathname = tampered.pathname.replace(/\/[0-9a-f]{40}$/, `/${'0'.repeat(40)}`);
        const tamperedResponse = await page.context().request.get(tampered.toString(), {
            failOnStatusCode: false,
            maxRedirects: 0,
        });
        expect(tamperedResponse.status(), 'A tampered verification hash is rejected').toBe(403);

        await page.goto(verifyUrl!.toString());
        await expect(page).toHaveURL(/\/dashboard/);
        await expect(page.locator('#app')).toBeVisible();

        await logout(page);
        await loginAs(page, 'admin@rfis.test');
        await page.goto('/admin');
        await page.getByPlaceholder(/Cari pengguna berdasarkan nama|Search users by name/i).fill(emailAddress);
        const row = page.getByRole('row').filter({ hasText: emailAddress });
        await row
            .getByRole('button', { name: /Hapus|Delete/ })
            .first()
            .click();
        await page
            .getByRole('dialog')
            .filter({ hasText: 'Delete this account?' })
            .getByRole('button', { name: 'Delete account' })
            .click();
        await page
            .getByRole('row')
            .filter({ hasText: emailAddress })
            .getByRole('button', { name: /Hapus|Delete/ })
            .last()
            .click();
        await page
            .getByRole('dialog')
            .filter({ hasText: 'Permanently delete this account?' })
            .getByRole('button', { name: 'Permanently delete' })
            .click();
        await expect(page.getByRole('row').filter({ hasText: emailAddress })).toHaveCount(0);
    } finally {
        await mailpit.dispose();
    }
});

test('ID-09: invitation resend is admin-only and refused for active accounts; ID-04 unknown tokens are rejected', async ({
    page,
}) => {
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/admin');
    const users = await inertiaProps<{
        initialUsers?: Array<{ uuid: string; email: string }>;
        users?: Array<{ uuid: string; email: string }>;
    }>(page);
    const all = users.initialUsers ?? users.users ?? [];
    const athlete = all.find((user) => user.email === 'athlete@rfis.test');
    expect(athlete?.uuid).toBeTruthy();

    const activeResend = await page
        .context()
        .request.post(new URL(`/admin/accounts/${athlete!.uuid}/invitation`, page.url()).toString(), {
            failOnStatusCode: false,
            headers: await xsrfHeaders(page),
        });
    expect(activeResend.status(), 'Active accounts cannot be re-invited').toBe(422);

    await logout(page);
    await loginAs(page, 'coach@rfis.test');
    const coachResend = await page
        .context()
        .request.post(new URL(`/admin/accounts/${athlete!.uuid}/invitation`, page.url()).toString(), {
            failOnStatusCode: false,
            headers: await xsrfHeaders(page),
        });
    expect(coachResend.status(), 'Non-admin cannot resend invitations').toBe(403);
    await logout(page);

    await page.goto('/locale/en');
    await page.goto('/invitations/invalid-nonexistent-token');
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByText(/invitation link is invalid|no longer active/i).first()).toBeVisible();
});

test('ID-07: admin edits another user avatar, invalid MIME is rejected, and the stored image loads', async ({
    page,
}) => {
    test.skip(
        process.env.RFIS_E2E_PERSISTENT_STORAGE === '1',
        'Profile photos persist in public storage; run this workflow only in the disposable tmpfs E2E environment.',
    );

    await loginAs(page, 'admin@rfis.test');
    await page.goto('/users');
    await page
        .locator('table')
        .first()
        .getByRole('row')
        .filter({ hasText: 'nadia@rfis.test' })
        .getByRole('button', { name: /^(View Profile|Lihat profil)$/i })
        .click();
    await page.waitForURL((url) => /^\/users\/[0-9a-f-]{36}$/i.test(url.pathname));

    const photoSection = page
        .locator('div.rounded-xl')
        .filter({ has: page.getByRole('heading', { name: 'Nadia Kirana', exact: true }) })
        .last();
    await photoSection.getByRole('button', { name: /^(Edit|Ubah)$/ }).click();
    const photoInput = page.locator('#profile-picture');
    await photoInput.setInputFiles({ name: 'invalid.txt', mimeType: 'text/plain', buffer: Buffer.from('nope') });
    await expect(page.getByText('Selected file must be an image.')).toBeVisible();
    const invalidUpload = await page.context().request.post(new URL(`${new URL(page.url()).pathname}/profile`, page.url()).toString(), {
        failOnStatusCode: false,
        headers: await xsrfHeaders(page),
        multipart: {
            bio: 'Invalid avatar MIME must not persist.',
            profile_picture: { name: 'invalid-avatar.txt', mimeType: 'text/plain', buffer: Buffer.from('nope') },
        },
    });
    expect(invalidUpload.status(), 'Server rejects invalid avatar MIME').toBe(422);

    const pngDataUrl = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = 180;
        canvas.height = 240;
        const context = canvas.getContext('2d');
        if (!context) throw new Error('Canvas is unavailable in this browser.');
        context.fillStyle = '#7c3aed';
        context.fillRect(0, 0, canvas.width, canvas.height);

        return canvas.toDataURL('image/png');
    });
    await photoInput.setInputFiles({
        name: 'admin-edit.png',
        mimeType: 'image/png',
        buffer: Buffer.from(pngDataUrl.split(',')[1], 'base64'),
    });
    await photoSection.getByRole('button', { name: /^(Use 3x4 Crop|Gunakan crop 3x4)$/i }).click();
    await expect(photoSection.getByText('Foto 3×4 siap disimpan.')).toBeVisible();
    const saveResponse = page.waitForResponse(
        (response) =>
            /^\/users\/[0-9a-f-]{36}\/profile$/.test(new URL(response.url()).pathname) &&
            response.request().method() === 'POST',
    );
    await photoSection.getByRole('button', { name: 'Save Changes' }).click();
    expect((await saveResponse).status()).toBeLessThan(400);

    await page.reload();
    const avatar = page.getByRole('img', { name: 'Nadia Kirana' }).first();
    await expect(avatar).toBeVisible();
    await expect.poll(() => avatar.evaluate((image: HTMLImageElement) => image.naturalWidth)).toBeGreaterThan(0);

    const profilePath = new URL(page.url()).pathname;
    await logout(page);
    await loginAs(page, 'coach@rfis.test');
    const forbiddenEdit = await page.context().request.post(new URL(`${profilePath}/profile`, page.url()).toString(), {
        failOnStatusCode: false,
        headers: await xsrfHeaders(page),
        data: { bio: 'Unauthorized profile edit must not persist.' },
    });
    expect(forbiddenEdit.status(), 'A coach cannot edit another user profile').toBe(403);
});

test('FI-09: invoice logo upload, rendering, invalid rejection, and removal in disposable storage', async ({
    page,
}) => {
    test.skip(
        process.env.RFIS_E2E_PERSISTENT_STORAGE === '1',
        'Invoice logos persist in public storage; run only in the disposable tmpfs E2E environment.',
    );
    test.setTimeout(90_000);

    await loginAs(page, 'admin@rfis.test');
    await page.goto('/payments');
    await page.getByRole('button', { name: 'Pengaturan invoice' }).click();
    const modal = page.getByRole('dialog').filter({ hasText: 'Pengaturan invoice' });
    await modal.locator('#invoice-logo-file').setInputFiles({
        name: 'logo.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('not an image'),
    });
    await modal.getByRole('button', { name: 'Simpan pengaturan' }).click();
    await expect(modal).toBeVisible();
    await expect(modal.locator('#invoice-logo-file[aria-invalid="true"]')).toHaveCount(1);

    const logoDataUrl = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = 64;
        canvas.height = 32;
        const context = canvas.getContext('2d');
        if (!context) throw new Error('Canvas is unavailable in browser.');
        context.fillStyle = '#7c3aed';
        context.fillRect(0, 0, canvas.width, canvas.height);

        return canvas.toDataURL('image/png');
    });
    const png = Buffer.from(logoDataUrl.split(',')[1], 'base64');
    await modal.locator('#invoice-logo-file').setInputFiles({ name: 'logo.png', mimeType: 'image/png', buffer: png });
    await modal.getByRole('button', { name: 'Simpan pengaturan' }).click();
    await expect(modal).toBeHidden();

    await page.goto('/payments');
    const originalLogoUrl = (await inertiaProps<{ invoiceTemplate: { logo_image_url: string | null } }>(page))
        .invoiceTemplate.logo_image_url;
    expect(originalLogoUrl).toContain('/storage/invoice-logos/');

    await page.getByRole('button', { name: 'Pengaturan invoice' }).click();
    const replacementModal = page.getByRole('dialog').filter({ hasText: 'Pengaturan invoice' });
    const replacementDataUrl = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = 96;
        canvas.height = 48;
        const context = canvas.getContext('2d');
        if (!context) throw new Error('Canvas is unavailable in browser.');
        context.fillStyle = '#0f766e';
        context.fillRect(0, 0, canvas.width, canvas.height);

        return canvas.toDataURL('image/png');
    });
    await replacementModal.locator('#invoice-logo-file').setInputFiles({
        name: 'replacement-logo.png',
        mimeType: 'image/png',
        buffer: Buffer.from(replacementDataUrl.split(',')[1], 'base64'),
    });
    await replacementModal.getByRole('button', { name: 'Simpan pengaturan' }).click();
    await expect(replacementModal).toBeHidden();

    await page.goto('/payments');
    const replacementLogoUrl = (await inertiaProps<{ invoiceTemplate: { logo_image_url: string | null } }>(page))
        .invoiceTemplate.logo_image_url;
    expect(replacementLogoUrl).toContain('/storage/invoice-logos/');
    expect(replacementLogoUrl).not.toBe(originalLogoUrl);
    expect((await page.request.get(new URL(originalLogoUrl!, page.url()).toString())).status()).toBe(404);
    expect((await page.request.get(new URL(replacementLogoUrl!, page.url()).toString())).status()).toBe(200);

    const props = await inertiaProps<{ rows: Array<{ payment_uuid?: string }> }>(page);
    const row = props.rows[0];
    const paymentKey = row.payment_uuid;
    const withLogo = await page.context().request.get(new URL(`/payments/${paymentKey}/export`, page.url()).toString());
    expect(withLogo.status()).toBe(200);
    expect(withLogo.headers()['content-type']).toContain('application/pdf');
    const logoPdf = await withLogo.body();
    expect(logoPdf.subarray(0, 5).toString('ascii')).toBe('%PDF-');
    expect(logoPdf.includes(Buffer.from('/Subtype /Image'))).toBe(true);

    await page.getByRole('button', { name: 'Pengaturan invoice' }).click();
    const reopened = page.getByRole('dialog').filter({ hasText: 'Pengaturan invoice' });
    await reopened.getByLabel('Hapus logo dari invoice').check();
    await reopened.getByRole('button', { name: 'Simpan pengaturan' }).click();
    await expect(reopened).toBeHidden();

    const withoutLogo = await page
        .context()
        .request.get(new URL(`/payments/${paymentKey}/export`, page.url()).toString());
    expect(withoutLogo.headers()['content-type']).toContain('application/pdf');
    const plainPdf = await withoutLogo.body();
    expect(plainPdf.subarray(0, 5).toString('ascii')).toBe('%PDF-');
    expect(plainPdf.includes(Buffer.from('/Subtype /Image'))).toBe(false);
    expect((await page.request.get(new URL(replacementLogoUrl!, page.url()).toString())).status()).toBe(404);
});

test('FI-07: QRIS image upload, invalid rejection, athlete payer flow, and removal in disposable storage', async ({
    page,
}) => {
    test.skip(
        process.env.RFIS_E2E_PERSISTENT_STORAGE === '1',
        'QRIS images persist in public storage; run only in the disposable tmpfs E2E environment.',
    );
    test.setTimeout(90_000);

    await loginAs(page, 'admin@rfis.test');
    await page.goto('/payments/qris');
    await page.locator('#qris-image').setInputFiles({
        name: 'qris.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('not an image'),
    });
    await page.getByRole('button', { name: 'Simpan QRIS' }).click();
    await expect(page.locator('#qris-image[aria-invalid="true"]')).toHaveCount(1);
    const invalidToast = page.getByRole('status');
    if ((await invalidToast.count()) > 0) await invalidToast.first().getByRole('button').click();

    const png = Buffer.from(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/m4kAAAAASUVORK5CYII=',
        'base64',
    );
    await page.locator('#qris-image').setInputFiles({ name: 'qris.png', mimeType: 'image/png', buffer: png });
    await page.getByRole('button', { name: 'Simpan QRIS' }).click();
    await page.reload();
    await expect(page.getByLabel('Hapus gambar QRIS saat ini dan kembali ke placeholder')).toBeVisible();

    await logout(page);
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/payments/qris');
    await expect(page.getByRole('heading', { name: 'Tagihan yang masih terbuka' })).toBeVisible();
    await page.getByRole('link', { name: /Kembali ke keuangan/ }).click();
    await expect(page).toHaveURL(/\/payments$/);

    await logout(page);
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/payments/qris');
    await page.getByLabel('Hapus gambar QRIS saat ini dan kembali ke placeholder').check();
    await page.getByRole('button', { name: 'Simpan QRIS' }).click();
    await page.reload();
    await expect(page.getByLabel('Hapus gambar QRIS saat ini dan kembali ke placeholder')).toHaveCount(0);
});

test('RP-04 and UI-01: admin members and instructors summaries render formatted rows and translate after locale switch and reload', async ({
    page,
}) => {
    await loginAs(page, 'admin@rfis.test');

    await page.goto('/admin/members');
    await expect(page.locator('main')).not.toContainText('GEUP_');
    await expect(page.locator('main')).not.toContainText('\\n');
    const profileLink = page.getByRole('link', { name: 'Profil' }).first();
    await expect(profileLink).toHaveAttribute('href', /\/users\/[0-9a-f-]{36}$/i);
    await profileLink.click();
    await page.waitForURL((url) => /^\/users\/[0-9a-f-]{36}$/i.test(url.pathname));

    await page.goto('/admin/instructors');
    await expect(page.locator('main')).not.toContainText('\\n');

    await page.goto('/locale/en');
    for (const path of ['/attendance', '/training-schedule', '/admin/members', '/admin/instructors']) {
        await page.goto(path);
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    }
    await page.goto('/training-schedule');
    await expect(
        page.getByRole('button', { name: /^(Card|Cards|Kartu)$/ }),
    ).toBeVisible();
    await page.goto('/locale/id');

    await logout(page);
    await loginAs(page, 'coach@rfis.test');
    const forbidden = await page.context().request.get(new URL('/admin/members', page.url()).toString(), {
        failOnStatusCode: false,
    });
    expect(forbidden.status(), 'Non-admin cannot read the members summary').toBe(403);
});

test('API-01: /api/user is 401 for guests and the session user is the only token-less contract', async ({ page }) => {
    await page.goto('/');
    const guest = await page.context().request.get(new URL('/api/user', page.url()).toString(), {
        failOnStatusCode: false,
        headers: { Accept: 'application/json' },
    });
    expect(guest.status()).toBe(401);

    await loginAs(page, 'athlete@rfis.test');
    const missing = await page.context().request.get(new URL('/api/user', page.url()).toString(), {
        failOnStatusCode: false,
        headers: { Accept: 'application/json', Authorization: 'Bearer not-a-real-token' },
    });
    expect(missing.status(), 'Malformed bearer tokens are rejected').toBe(401);
});
