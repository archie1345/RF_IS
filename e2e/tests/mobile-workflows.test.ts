import { request, type Locator, type Page } from '@playwright/test';
import { toDataURL } from 'qrcode';
import { accounts, navigation } from './role-coverage';
import { expect, test } from './fixtures';

const diagnosticsByPage = new WeakMap<Page, string[]>();

test.beforeEach(async ({ page }) => {
    const diagnostics: string[] = [];
    diagnosticsByPage.set(page, diagnostics);

    page.on('console', (message) => {
        if (message.type() === 'error') diagnostics.push(`console error: ${message.text()}`);
    });
    page.on('pageerror', (error) => diagnostics.push(`page error: ${error.message}`));
    page.on('requestfailed', (request) => {
        diagnostics.push(`request failed: ${request.method()} ${request.url()}`);
    });
    page.on('response', (response) => {
        if (response.status() >= 400) {
            diagnostics.push(`HTTP ${response.status()}: ${response.request().method()} ${response.url()}`);
        }
    });
});

test.afterEach(async ({ page }, testInfo) => {
    const diagnostics = diagnosticsByPage.get(page) ?? [];
    if (diagnostics.length > 0) {
        await testInfo.attach('mobile-browser-diagnostics', {
            body: diagnostics.join('\n'),
            contentType: 'text/plain',
        });
    }
    expect(diagnostics, 'Mobile workflows should not produce browser errors or failed requests').toEqual([]);
});

async function loginAs(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/email address|alamat email/i).fill(email);
    await page.getByLabel(/password|kata sandi/i).fill(process.env.RFIS_DEMO_PASSWORD ?? '');
    await page.getByTestId('login-button').tap();
    await page.waitForURL((url) => url.pathname === '/dashboard');
    await expect(page.getByTestId('user-menu-trigger')).toBeVisible();
    const localeResponse = await page.context().request.get(new URL('/locale/id', page.url()).toString());
    expect(localeResponse.ok(), 'Indonesian locale setup succeeds').toBe(true);
    await page.reload();
    await expect(page.getByTestId('user-menu-trigger')).toBeVisible();
}

async function logout(page: Page): Promise<void> {
    await page.getByTestId('user-menu-trigger').tap();
    await page.getByTestId('logout-button').tap();
    await page.waitForURL((url) => url.pathname === '/');
}

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
    const dimensions = await page.evaluate(() => ({
        viewport: document.documentElement.clientWidth,
        content: document.documentElement.scrollWidth,
    }));

    expect(dimensions.content, `Page content should fit the ${dimensions.viewport}px viewport`).toBeLessThanOrEqual(
        dimensions.viewport,
    );
}

async function waitForMotionToFinish(locator: Locator): Promise<void> {
    await locator.evaluate(async (element) => {
        await Promise.all(
            element.getAnimations({ subtree: true }).map((animation) => animation.finished.catch(() => undefined)),
        );
    });
}

for (const account of accounts) {
    test(`${account.name} can navigate every permitted page from the mobile menu and log out`, async ({ page }) => {
        // Admin role unions traverse 21 routes; allow time for each real browser navigation.
        test.setTimeout(120_000);
        await loginAs(page, account.email);

        const expectedPages = navigation.filter((item) => item.roles.some((role) => account.roles.includes(role)));
        const mobileMenu = page.getByRole('button', { name: 'Buka menu navigasi' });
        const mobileSheet = page.locator('[data-slot="sheet-content"]');

        await mobileMenu.tap();
        await expect(mobileSheet).toBeVisible();
        await waitForMotionToFinish(mobileSheet);
        const initialMenu = mobileSheet;
        const visibleHrefs = await initialMenu
            .getByRole('link')
            .evaluateAll((links) => links.map((link) => new URL((link as HTMLAnchorElement).href).pathname));
        expect([...visibleHrefs].sort()).toEqual(expectedPages.map((item) => item.href).sort());
        await page.keyboard.press('Escape');

        for (const item of expectedPages) {
            await expect(mobileSheet).toBeHidden();
            await mobileMenu.tap();
            await expect(mobileSheet).toBeVisible();
            await waitForMotionToFinish(mobileSheet);
            const link = mobileSheet.locator(`a[href="${item.href}"]`);
            await expect(link, `${account.name} should see ${item.href} in the mobile menu`).toBeVisible();

            const responsePromise = page.waitForResponse((response) => {
                const path = new URL(response.url()).pathname.replace(/\/$/, '') || '/';
                const target = item.href.replace(/\/$/, '') || '/';
                return response.request().method() === 'GET' && path === target;
            });
            await link.tap();
            const response = await responsePromise;
            expect(response.status(), `${account.name} should open ${item.href}`).toBe(200);
            await page.waitForURL((url) => (url.pathname.replace(/\/$/, '') || '/') === item.href);
            await expect(page.locator('main')).toBeVisible();
            await expectNoHorizontalOverflow(page);
            await expect(mobileSheet).toBeHidden();

            const registrationInvoiceDialog = page
                .getByRole('dialog')
                .filter({ hasText: /Tagihan pendaftaran tersedia|Registration payment available/i });
            if (await registrationInvoiceDialog.isVisible()) {
                await registrationInvoiceDialog.getByRole('button', { name: /Tutup dialog|Close dialog/i }).tap();
                await expect(registrationInvoiceDialog).toBeHidden();
            }
        }

        await page.getByTestId('user-menu-trigger').tap();
        const logoutButton = page.getByTestId('logout-button');
        await expect(logoutButton).toBeVisible();
        await logoutButton.tap();
        await page.waitForURL((url) => url.pathname === '/');
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);
    });
}

test('mobile navigation and group CRUD remain usable on a touch viewport', async ({ page }) => {
    await loginAs(page, 'admin@rfis.test');

    const menuButton = page.getByRole('button', { name: 'Buka menu navigasi' });
    await expect(menuButton).toBeVisible();
    const targetSize = await menuButton.boundingBox();
    expect(targetSize?.width).toBeGreaterThanOrEqual(44);
    expect(targetSize?.height).toBeGreaterThanOrEqual(44);

    await menuButton.tap();
    const menu = page.getByRole('dialog');
    const groupsLink = menu.getByRole('link', { name: 'Kelompok Atlet' });
    await expect(groupsLink).toBeVisible();
    await groupsLink.tap();
    await page.waitForURL((url) => url.pathname === '/admin/groups');

    await expect(page.getByRole('table')).toBeHidden();
    await expect(page.locator('main article').first()).toBeVisible();
    await expectNoHorizontalOverflow(page);

    const groupName = `Mobile E2E ${Date.now()}`;
    const updatedGroupName = `${groupName} Updated`;
    await page.getByRole('button', { name: /Tambah Grup|Add group/i }).tap();
    const createDialog = page.getByRole('dialog').last();
    await createDialog.getByRole('button', { name: /Save Grup|Save group/i }).tap();
    await expect(createDialog.getByText(/required|wajib|diperlukan/i)).toBeVisible();

    await createDialog.getByPlaceholder(/Contoh: Junior|Example: Junior/i).fill(groupName);
    await createDialog
        .getByPlaceholder(/Keterangan grup|Group description/i)
        .fill('Synthetic mobile workflow fixture.');
    await createDialog.getByRole('button', { name: /Save Grup|Save group/i }).tap();
    await expect(createDialog).toBeHidden();

    const groupSearch = page.getByPlaceholder(/Cari grup|Search group/i);
    await groupSearch.fill(groupName);
    await expect(page.locator('main article').filter({ hasText: groupName }).first()).toBeVisible();
    await page.getByRole('button', { name: `Ubah grup ${groupName}` }).tap();

    const editDialog = page.getByRole('dialog').last();
    await editDialog.getByPlaceholder(/Contoh: Junior|Example: Junior/i).fill(updatedGroupName);
    await editDialog.getByRole('button', { name: /Save Grup|Save group/i }).tap();
    await expect(editDialog).toBeHidden();

    await groupSearch.fill(updatedGroupName);
    await expect(page.locator('main article').filter({ hasText: updatedGroupName }).first()).toBeVisible();
    await page.getByRole('button', { name: `Hapus grup ${updatedGroupName}` }).tap();
    await page
        .getByRole('dialog')
        .filter({ hasText: /Hapus atau nonaktifkan grup\?|Delete or deactivate this group\?/i })
        .getByRole('button', { name: /Lanjutkan|Continue/i })
        .tap();
    await expect(page.locator('main article').filter({ hasText: updatedGroupName })).toHaveCount(0);
    await expectNoHorizontalOverflow(page);
});

test('mobile billing rule and invoice dialogs fit the viewport and work through touch', async ({ page }) => {
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/admin/billing-settings');
    await page.getByRole('button', { name: 'Buat aturan', exact: true }).tap();

    const ruleDialog = page.getByRole('dialog');
    await expect(ruleDialog).toBeVisible();
    const viewport = page.viewportSize();
    const ruleBounds = await ruleDialog.boundingBox();
    expect(ruleBounds).toBeTruthy();
    expect(ruleBounds!.x).toBeGreaterThanOrEqual(0);
    expect(ruleBounds!.y).toBeGreaterThanOrEqual(0);
    expect(ruleBounds!.x + ruleBounds!.width).toBeLessThanOrEqual(viewport!.width);
    expect(ruleBounds!.y + ruleBounds!.height).toBeLessThanOrEqual(viewport!.height);

    const ruleName = `Mobile billing ${Date.now()}`;
    await ruleDialog.locator('#billing-rule-name').fill(ruleName);
    await ruleDialog.locator('#billing-rule-amount').fill('12345');
    await ruleDialog.getByRole('button', { name: 'Simpan aturan' }).tap();
    await expect(
        ruleDialog.getByText(
            'Pilih cabang atau kelas. Tarif SPP umum sudah menggunakan Tarif default di bagian Jadwal SPP otomatis.',
            { exact: true },
        ),
    ).toBeVisible();

    await ruleDialog.locator('#billing-rule-kind').tap();
    await page.getByRole('button', { name: 'Tagihan satu kali', exact: true }).tap();
    await ruleDialog.locator('#billing-rule-due-days').fill('14');
    await ruleDialog.getByRole('button', { name: 'Simpan aturan' }).tap();
    await expect(ruleDialog).toBeHidden();

    const ruleCard = page.locator('article').filter({ hasText: ruleName });
    await expect(ruleCard).toBeVisible();
    await ruleCard.getByRole('button', { name: 'Terbitkan tagihan' }).tap();
    const issueDialog = page.getByRole('dialog');
    await expect(issueDialog).toBeVisible();
    const issueBounds = await issueDialog.boundingBox();
    expect(issueBounds).toBeTruthy();
    expect(issueBounds!.x).toBeGreaterThanOrEqual(0);
    expect(issueBounds!.x + issueBounds!.width).toBeLessThanOrEqual(viewport!.width);
    await issueDialog.locator('#one-time-issue-date').fill('2035-01-15');
    const generatedResponse = page.waitForResponse((response) => {
        const url = new URL(response.url());
        return (
            response.request().method() === 'GET' &&
            url.pathname === '/admin/billing-settings' &&
            response.request().headers()['x-inertia'] === 'true'
        );
    });
    await issueDialog.getByRole('button', { name: 'Terbitkan tagihan' }).tap();
    expect((await generatedResponse).status()).toBe(200);
    await expect(issueDialog).toBeHidden();
    await expect(page.getByText(/Tagihan satu kali dibuat: [1-9]\d*; dilewati karena sudah ada: 0\./)).toBeVisible();
    await expectNoHorizontalOverflow(page);
});

test('mobile payment proof stays private from unrelated athletes', async ({ page }) => {
    test.skip(
        process.env.RFIS_E2E_PERSISTENT_STORAGE === '1',
        'Payment proofs persist in private storage; exercise this workflow only with disposable E2E storage.',
    );
    test.setTimeout(90_000);

    const total = 600_000 + (Date.now() % 300_000);
    const formattedTotal = total.toLocaleString('id-ID');

    await loginAs(page, 'admin@rfis.test');
    await page.goto('/payments');
    await page.getByRole('button', { name: 'Buat tagihan', exact: true }).tap();
    await page.locator('#payment-recipient').tap();
    await page.getByRole('button', { name: /^Adit Pratama/ }).tap();
    await page.locator('#payment-total').fill(String(total));
    await page.locator('#payment-date').fill(new Date().toISOString().slice(0, 10));
    const dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + 14);
    await page.locator('#payment-due-date').fill(dueDate.toISOString().slice(0, 10));
    await page.locator('#payment-notes').fill('Synthetic mobile proof-privacy workflow.');
    await page.getByRole('button', { name: /Terbitkan tagihan/i }).tap();

    await page.getByPlaceholder(/Cari nomor tagihan, penerima, kategori, atau status/i).fill(formattedTotal);
    const adminCard = page.locator('main article').filter({ hasText: formattedTotal }).first();
    await expect(adminCard).toBeVisible();
    const invoiceNumber = (await adminCard.locator('dl').innerText()).match(/INV-[^\s]+/)?.[0];
    expect(invoiceNumber).toBeTruthy();

    await page.getByTestId('user-menu-trigger').tap();
    await page.getByTestId('logout-button').tap();
    await page.waitForURL((url) => url.pathname === '/');
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/payments');
    await page.getByPlaceholder(/Cari nomor tagihan, penerima, kategori, atau status/i).fill(invoiceNumber!);
    const athleteCard = page.locator('main article').filter({ hasText: invoiceNumber! }).first();
    await expect(athleteCard).toBeVisible();
    await athleteCard.getByRole('button', { name: 'Upload bukti' }).tap();

    const proofDialog = page.getByRole('dialog');
    await proofDialog.getByLabel('Catatan bukti').fill(`RFIS-MOBILE-CLEANUP:${Date.now()}`);
    await proofDialog.locator('#proof-file').setInputFiles({
        name: 'mobile-payment-proof.pdf',
        mimeType: 'application/pdf',
        buffer: Buffer.from('%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n'),
    });
    await proofDialog.getByRole('button', { name: 'Kirim untuk review' }).tap();
    await expect(proofDialog).toBeHidden();
    const proofLink = athleteCard.getByRole('link', { name: 'Lihat bukti' });
    await expect(proofLink).toBeVisible();
    await expectNoHorizontalOverflow(page);

    const proofUrl = await proofLink.getAttribute('href');
    expect(proofUrl).toBeTruthy();
    const ownerResponse = await page.request.get(proofUrl!);
    expect(ownerResponse.status()).toBe(200);
    expect(ownerResponse.headers()['cache-control']).toContain('private');
    expect(ownerResponse.headers()['cache-control']).toContain('no-store');

    await page.getByTestId('user-menu-trigger').tap();
    await page.getByTestId('logout-button').tap();
    await page.waitForURL((url) => url.pathname === '/');
    await loginAs(page, 'nadia@rfis.test');
    const unrelatedResponse = await page.request.get(proofUrl!);
    expect(unrelatedResponse.status(), 'Another athlete must be denied even with the direct proof URL').toBe(403);
});

test('mobile achievement documents are private and downloadable only by owner or admin', async ({ page }) => {
    test.skip(
        process.env.RFIS_E2E_PERSISTENT_STORAGE === '1',
        'Achievement documents persist in private storage; exercise this workflow only with disposable E2E storage.',
    );
    test.setTimeout(90_000);

    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/achievements');
    await page.getByRole('button', { name: 'Tambah prestasi' }).tap();

    const dialog = page.getByRole('dialog').last();
    const achievementName = `RFIS Mobile Private ${Date.now()}`;
    await dialog.getByLabel('Nama kejuaraan atau prestasi').fill(achievementName);
    await dialog.locator('#achievement-file').setInputFiles({
        name: 'mobile-certificate.pdf',
        mimeType: 'application/pdf',
        buffer: Buffer.from('%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n'),
    });
    await dialog.getByRole('button', { name: 'Simpan prestasi' }).tap();
    await expect(dialog).toBeHidden();

    const documentLink = page.getByRole('link', { name: 'mobile-certificate.pdf' }).last();
    await expect(documentLink).toBeVisible();
    await expectNoHorizontalOverflow(page);
    const documentUrl = await documentLink.getAttribute('href');
    expect(documentUrl).toBeTruthy();
    expect((await page.request.get(documentUrl!)).status()).toBe(200);

    await page.getByTestId('user-menu-trigger').tap();
    await page.getByTestId('logout-button').tap();
    await page.waitForURL((url) => url.pathname === '/');
    await loginAs(page, 'nadia@rfis.test');
    expect((await page.request.get(documentUrl!)).status()).toBe(403);

    await page.getByTestId('user-menu-trigger').tap();
    await page.getByTestId('logout-button').tap();
    await page.waitForURL((url) => url.pathname === '/');
    await loginAs(page, 'admin@rfis.test');
    expect((await page.request.get(documentUrl!)).status()).toBe(200);
});

test('mobile language selector changes locale and keeps it after reload', async ({ page }) => {
    await loginAs(page, 'athlete@rfis.test');

    const userMenu = page.getByTestId('user-menu-trigger');
    await userMenu.tap();
    await page.getByRole('menuitem', { name: /Bahasa \/ Language/i }).tap();
    await page.getByRole('menuitem', { name: 'English' }).tap();
    await page.waitForURL((url) => url.pathname === '/dashboard');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');

    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await userMenu.tap();
    await expect(page.getByRole('menuitem', { name: 'Settings' })).toBeVisible();

    await page.keyboard.press('Escape');
    await userMenu.tap();
    await page.getByRole('menuitem', { name: /Language/i }).tap();
    await page.getByRole('menuitem', { name: 'Indonesian' }).tap();
    await page.waitForURL((url) => url.pathname === '/dashboard');
    await expect(page.locator('html')).toHaveAttribute('lang', 'id');
});

test('athlete can edit and restore account profile details by touch on a phone', async ({ page }) => {
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/settings/profile');

    const accountSection = page.getByRole('heading', { name: 'Data akun' }).locator('xpath=ancestor::section[1]');
    const nameField = accountSection.getByLabel('Nama lengkap');
    const originalName = await nameField.inputValue();
    const updatedName = `${originalName} Mobile ${Date.now()}`;

    await accountSection.getByRole('button', { name: 'Ubah' }).tap();
    await nameField.fill(updatedName);
    await accountSection.getByRole('button', { name: 'Simpan data akun' }).tap();
    await expect(nameField).toHaveValue(updatedName);
    await expectNoHorizontalOverflow(page);

    await page.reload();
    const reloadedSection = page.getByRole('heading', { name: 'Data akun' }).locator('xpath=ancestor::section[1]');
    await expect(reloadedSection.getByLabel('Nama lengkap')).toHaveValue(updatedName);
    await reloadedSection.getByRole('button', { name: 'Ubah' }).tap();
    await reloadedSection.getByLabel('Nama lengkap').fill(originalName);
    await reloadedSection.getByRole('button', { name: 'Simpan data akun' }).tap();
    await expect(reloadedSection.getByLabel('Nama lengkap')).toHaveValue(originalName);
    await expectNoHorizontalOverflow(page);
});

test('athlete manages profile achievements and certifications with touch controls', async ({ page }) => {
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/settings/profile');

    const achievements = page
        .getByRole('heading', { name: 'Prestasi', exact: true })
        .locator('xpath=ancestor::div[contains(@class,"rounded-xl")][1]');
    const achievementName = `Mobile E2E Achievement ${Date.now()}`;
    await achievements.getByRole('button', { name: 'Tambah prestasi' }).tap();
    let dialog = page.getByRole('dialog').last();
    await dialog.getByLabel('Nama kejuaraan atau prestasi').fill(achievementName);
    await dialog.getByRole('button', { name: 'Tambah prestasi' }).tap();
    await expect(dialog).toBeHidden();

    let achievementCard = page.locator('main article').filter({ hasText: achievementName });
    await expect(achievementCard).toBeVisible();
    await achievementCard.getByRole('button', { name: 'Ubah' }).tap();
    dialog = page.getByRole('dialog').last();
    await dialog.getByLabel('Nama kejuaraan atau prestasi').fill(`${achievementName} Updated`);
    await dialog.getByRole('button', { name: 'Simpan prestasi' }).tap();
    await expect(dialog).toBeHidden();
    achievementCard = page.locator('main article').filter({ hasText: `${achievementName} Updated` });
    await expect(achievementCard).toBeVisible();
    await achievementCard.getByRole('button', { name: 'Hapus' }).tap();
    await page
        .getByRole('dialog')
        .filter({ hasText: /Hapus prestasi\?/ })
        .getByRole('button', { name: 'Hapus prestasi' })
        .tap();
    await expect(page.locator('main article').filter({ hasText: `${achievementName} Updated` })).toHaveCount(0);
    await expectNoHorizontalOverflow(page);

    const certifications = page
        .getByRole('heading', { name: 'Sertifikasi', exact: true })
        .locator('xpath=ancestor::div[contains(@class,"rounded-xl")][1]');
    const certificationName = `Mobile E2E Certification ${Date.now()}`;
    await certifications.getByRole('button', { name: 'Tambah sertifikasi' }).tap();
    dialog = page.getByRole('dialog').last();
    await dialog.getByLabel('Nama sertifikasi').fill(certificationName);
    await dialog.getByRole('button', { name: 'Tambah sertifikasi' }).tap();
    await expect(dialog).toBeHidden();

    let certificationCard = page.locator('main article').filter({ hasText: certificationName });
    await expect(certificationCard).toBeVisible();
    await certificationCard.getByRole('button', { name: 'Ubah' }).tap();
    dialog = page.getByRole('dialog').last();
    await dialog.getByLabel('Nama sertifikasi').fill(`${certificationName} Updated`);
    await dialog.getByRole('button', { name: 'Simpan sertifikasi' }).tap();
    await expect(dialog).toBeHidden();
    certificationCard = page.locator('main article').filter({ hasText: `${certificationName} Updated` });
    await expect(certificationCard).toBeVisible();
    await certificationCard.getByRole('button', { name: 'Hapus' }).tap();
    await page
        .getByRole('dialog')
        .filter({ hasText: /Hapus sertifikasi\?/ })
        .getByRole('button', { name: 'Hapus sertifikasi' })
        .tap();
    await expect(page.locator('main article').filter({ hasText: `${certificationName} Updated` })).toHaveCount(0);
    await expectNoHorizontalOverflow(page);
});

test('mobile QR scanner handles denied camera access and validates its manual fallback', async ({ page }) => {
    await page.addInitScript(() => {
        if (!navigator.mediaDevices) return;
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
    await expectNoHorizontalOverflow(page);

    await page.getByRole('button', { name: 'Start scan' }).tap();
    await expect(page.getByText(/Camera permission was denied/i)).toBeVisible();

    await page
        .getByRole('textbox', { name: /\/attendance\/scan/i })
        .fill('https://example.invalid/not-an-rf-attendance-link');
    await page.getByRole('button', { name: 'Use pasted link' }).tap();
    await expect(page.getByText(/This is not an RF attendance QR code\.|Ini bukan QR absensi RF\./i)).toBeVisible();
    await expect(page).toHaveURL(/\/attendance$/);
    await expectNoHorizontalOverflow(page);
});

test('mobile QR scanner keeps the manual flow when the browser has no camera API', async ({ page }) => {
    await page.addInitScript(() => {
        Object.defineProperty(navigator, 'mediaDevices', {
            configurable: true,
            value: {},
        });
    });

    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/attendance');

    await expect(page.getByText(/Camera access is unavailable/i)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Start scan' })).toHaveCount(0);
    await expect(page.getByRole('textbox', { name: /\/attendance\/scan/i })).toBeVisible();
    await expectNoHorizontalOverflow(page);
});

test('mobile connection events produce one toast on the attendance screen', async ({ page }) => {
    await page.route('**/favicon.svg', (route) => route.fulfill({ status: 204, body: '' }));
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/attendance');
    await page.evaluate(async () => {
        await document.fonts.ready;
        await Promise.all(Array.from(document.images, (image) => image.decode().catch(() => undefined)));
    });

    await page.context().setOffline(true);
    const offlineToast = page.getByRole('status');
    await expect(offlineToast).toHaveCount(1);
    await expect(offlineToast).toContainText('Perangkat sedang offline. Perubahan belum dapat dikirim ke server.');
    await offlineToast.getByRole('button').tap();
    await expect(offlineToast).toBeHidden();

    await page.context().setOffline(false);
    const onlineToast = page.getByRole('status');
    await expect(onlineToast).toHaveCount(1);
    await expect(onlineToast).toContainText(
        'Koneksi internet tersedia kembali. Silakan ulangi tindakan yang sebelumnya gagal.',
    );
    await expectNoHorizontalOverflow(page);
});

test('admin opens a session QR and an eligible athlete scans it once on a phone', async ({ page }) => {
    test.setTimeout(90_000);

    await loginAs(page, 'admin@rfis.test');
    await page.goto('/sessions');

    const search = page.getByPlaceholder(/Search all session columns|Cari semua kolom sesi/i);
    await search.fill('Junior Sparring');
    const sessionCard = page.locator('main article').filter({ hasText: 'Junior Sparring' }).first();
    await expect(sessionCard).toBeVisible();
    const attendanceLink = sessionCard.getByRole('link', { name: /^(Edit|Ubah)$/ });
    const attendanceUrl = await attendanceLink.getAttribute('href');
    expect(attendanceUrl).toBeTruthy();

    await page.goto(new URL(attendanceUrl!, page.url()).toString());
    await expect(page.getByRole('heading', { name: 'Junior Sparring', exact: true })).toBeVisible();
    const childAttendance = page.locator('main article').filter({ hasText: 'Anak Semua Peran' }).first();
    await expect(childAttendance).toBeVisible();
    await childAttendance.getByRole('button', { name: 'Tidak hadir' }).tap();
    await expect(childAttendance).toContainText('Tidak hadir');

    const qrToggle = page.getByRole('button', { name: /^(Buka QR cepat|Tutup QR)$/ }).first();
    if ((await qrToggle.innerText()).trim() === 'Buka QR cepat') {
        await qrToggle.tap();
    }
    await expect(page.getByText('QR tetap aktif sampai admin atau pelatih menekan Tutup QR.')).toBeVisible();

    const scanLinkText = page.getByText(/\/attendance\/scan\//).first();
    await expect(scanLinkText).toBeVisible();
    const scanUrl = (await scanLinkText.innerText()).trim();
    expect(new URL(scanUrl).origin).toBe(new URL(process.env.APP_URL ?? 'http://localhost:9200').origin);
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
                let frame = 0;
                const publishFrame = () => {
                    context.fillStyle = frame++ % 2 === 0 ? '#ffffff' : '#fefefe';
                    context.fillRect(0, 0, 1, 1);
                    if (stream.getTracks().some((track) => track.readyState === 'live')) {
                        requestAnimationFrame(publishFrame);
                    }
                };
                publishFrame();

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

    await page.getByTestId('user-menu-trigger').tap();
    await page.getByTestId('logout-button').tap();
    await page.waitForURL((url) => url.pathname === '/');
    await loginAs(page, 'allroles.child@rfis.test');
    await page.goto('/attendance');
    await page.context().grantPermissions(['camera']);
    await page.getByRole('button', { name: 'Start scan' }).tap();
    await page.waitForURL((url) => /^\/attendance\/scan\/[^/]+$/.test(url.pathname));

    await expect(page.getByRole('heading', { name: 'QR detected' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Junior Sparring', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Save attendance now' }).tap();
    await expect(page.getByRole('heading', { name: 'Attendance saved' })).toBeVisible();
    await expect(page.getByText('Attendance recorded successfully.')).toBeVisible();

    expect(stoppedTrackStates.length).toBeGreaterThan(0);
    expect(stoppedTrackStates.every((state) => state === 'ended')).toBe(true);

    await page.goto(scanUrl);
    await expect(page.getByRole('heading', { name: 'Attendance saved' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Save attendance now' })).toHaveCount(0);
    await expectNoHorizontalOverflow(page);

    await page.getByTestId('user-menu-trigger').tap();
    await page.getByTestId('logout-button').tap();
    await page.waitForURL((url) => url.pathname === '/');
    await loginAs(page, 'admin@rfis.test');
    await page.goto(new URL(attendanceUrl!, page.url()).toString());
    const cleanupAttendance = page.locator('main article').filter({ hasText: 'Anak Semua Peran' }).first();
    await cleanupAttendance.getByRole('button', { name: 'Tidak hadir' }).tap();
    const closeQr = page.getByRole('button', { name: 'Tutup QR' }).first();
    if (await closeQr.isVisible()) {
        await closeQr.tap();
        await page.getByRole('dialog').getByRole('button', { name: 'Tutup QR' }).tap();
    }
    await expect(page.getByRole('button', { name: 'Buka QR cepat' })).toBeVisible();
});

test('athlete can crop and save a profile photo using mobile touch controls', async ({ page }) => {
    test.skip(
        process.env.RFIS_E2E_PERSISTENT_STORAGE === '1',
        'Profile photos persist in public storage; run this workflow only in the disposable tmpfs E2E environment.',
    );

    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/settings/profile');
    await expectNoHorizontalOverflow(page);

    const photoSection = page.getByRole('heading', { name: 'Foto 3×4 dan bio' }).locator('xpath=ancestor::section[1]');
    await photoSection.getByRole('button', { name: 'Ubah' }).tap();
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
    await page.locator('#profile-photo-file').setInputFiles({
        name: 'synthetic-mobile-profile.png',
        mimeType: 'image/png',
        buffer: Buffer.from(pngDataUrl.split(',')[1], 'base64'),
    });

    await photoSection.getByRole('button', { name: 'Perbesar' }).tap();
    await photoSection.getByRole('button', { name: 'Putar' }).tap();
    await photoSection.getByRole('button', { name: 'Gunakan crop 3×4' }).tap();
    await expect(photoSection.getByText('Foto 3×4 siap disimpan.')).toBeVisible();
    await expectNoHorizontalOverflow(page);

    const saveResponse = page.waitForResponse((response) => {
        const request = response.request();
        return new URL(response.url()).pathname === '/settings/profile/details' && request.method() === 'POST';
    });
    await photoSection.getByRole('button', { name: 'Simpan foto dan bio' }).tap();
    expect((await saveResponse).status()).toBeLessThan(400);
    await page.reload();
    const profileHeader = page
        .getByRole('heading', { name: 'Adit Pratama', exact: true })
        .locator('xpath=ancestor::section[1]');
    const avatar = profileHeader.getByRole('img', { name: 'Adit Pratama' });
    await expect(avatar).toBeVisible();
    await expect.poll(() => avatar.evaluate((image: HTMLImageElement) => image.naturalWidth)).toBeGreaterThan(0);
});

test('mobile public landing, WhatsApp CTA, disabled registration, password reset form, and expired invitation redirect', async ({
    page,
}) => {
    await page.goto('/');
    await expectNoHorizontalOverflow(page);

    const whatsappLink = page.getByRole('link', { name: /Hubungi admin (melalui|via) WhatsApp/i });
    await expect(whatsappLink).toBeVisible();
    await expect(whatsappLink).toHaveAttribute('href', /^https:\/\/wa\.me\/\d+/);

    await page
        .getByRole('link', { name: /^(Masuk|Masuk ke akun|Masuk ke klub)$/ })
        .first()
        .tap();
    await expect(page).toHaveURL(/\/login$/);
    await expectNoHorizontalOverflow(page);

    const registerResponse = await page.context().request.get(new URL('/register', page.url()).toString(), {
        failOnStatusCode: false,
    });
    expect(registerResponse.status()).toBe(404);

    await page.goto('/locale/en');
    await page.goto('/forgot-password');
    await expectNoHorizontalOverflow(page);
    await expect(page.getByLabel('Email address')).toBeVisible();
    await expect(page.getByTestId('email-password-reset-link-button')).toBeVisible();

    await page.goto('/reset-password/invalid-mobile-token?email=athlete%40rfis.test');
    await expectNoHorizontalOverflow(page);
    await page.getByLabel('Password', { exact: true }).fill('Mobile-Reset-Pass-99!');
    await page.getByLabel('Confirm Password', { exact: true }).fill('Mobile-Reset-Pass-99!');
    await page.getByTestId('reset-password-button').tap();
    await expect(page.getByText(/password reset token is invalid|token.*tidak valid/i).first()).toBeVisible();
    const resetToast = page.getByRole('status');
    if ((await resetToast.count()) > 0) {
        await resetToast.first().getByRole('button').tap();
    }
    await expectNoHorizontalOverflow(page);

    await page.goto('/invitations/invalid-mobile-invitation-token');
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByText(/invitation link is invalid|undangan tidak valid/i).first()).toBeVisible();
    await expectNoHorizontalOverflow(page);
});

test('ID-04: mobile invitation token rejects mismatch, activates once, and permits login', async ({ page }) => {
    test.skip(
        process.env.RFIS_E2E_DEV_TARGET !== 'rf-is-dev',
        'Invitation email delivery is verified against the development Mailpit service only.',
    );
    test.setTimeout(120_000);

    const emailAddress = `e2e.mobile.invited.${Date.now()}@rfis.test`;
    const password = `Mobile-Invite-${Date.now()}-A7!`;
    const mailpit = await request.newContext({ baseURL: 'http://localhost:9204' });

    try {
        await loginAs(page, 'admin@rfis.test');
        await page.goto('/admin');
        await page.getByRole('button', { name: 'Add new user' }).tap();
        const dialog = page.getByRole('dialog').filter({ hasText: 'Create user account' });
        await dialog.getByLabel('Full name').fill(`E2E Mobile Invite ${Date.now()}`);
        await dialog.getByLabel('Email').fill(emailAddress);
        await dialog.getByRole('button', { name: 'Create account' }).tap();
        await expect(dialog).toBeHidden();

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
                                message.Subject?.includes('RF IS account invitation') &&
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
        await page.goto(invitationUrl!.toString());
        await expect(page.getByText(emailAddress)).toBeVisible();
        await page.locator('#password').fill(password);
        await page.locator('#password_confirmation').fill(`${password}-mismatch`);
        await page.getByRole('button', { name: /Activate account|Aktifkan akun/ }).tap();
        await expect(page.getByText(/confirmation does not match|konfirmasi kata sandi/i)).toBeVisible();

        await page.locator('#password_confirmation').fill(password);
        await page.getByRole('button', { name: /Activate account|Aktifkan akun/ }).tap();
        await page.waitForURL((url) => url.pathname === '/dashboard');

        const anonymousBrowser = await request.newContext({ baseURL: invitationUrl!.origin });
        try {
            const replay = await anonymousBrowser.get(invitationUrl!.pathname, {
                failOnStatusCode: false,
                maxRedirects: 0,
            });
            expect(replay.status(), 'Accepted invitation token cannot be reused').toBe(302);
            expect(replay.headers().location).toContain('/login');
        } finally {
            await anonymousBrowser.dispose();
        }

        await logout(page);
        await page.goto('/login');
        await page.getByLabel(/email address|alamat email/i).fill(emailAddress);
        await page.getByLabel(/password|kata sandi/i).fill(password);
        await page.getByTestId('login-button').tap();
        await page.waitForURL((url) => url.pathname === '/dashboard');
        await expectNoHorizontalOverflow(page);
    } finally {
        await mailpit.dispose();
    }
});

test('mobile settings overview, appearance toggle, password confirmation, 2FA setup modal, and profile milestone management', async ({
    page,
}) => {
    await loginAs(page, 'athlete@rfis.test');
    await page.goto('/settings');
    await expectNoHorizontalOverflow(page);
    await expect(page.getByRole('heading', { name: 'Adit Pratama' })).toBeVisible();

    await page.goto('/settings/appearance');
    await expectNoHorizontalOverflow(page);
    await page.getByRole('button', { name: /^(Dark|Gelap)$/ }).tap();
    await expect(page.locator('html')).toHaveClass(/dark/);
    await page.getByRole('button', { name: /^(Light|Terang)$/ }).tap();
    await expect(page.locator('html')).not.toHaveClass(/dark/);

    await page.goto('/user/confirm-password');
    await expectNoHorizontalOverflow(page);
    await page.locator('#password').fill(process.env.RFIS_DEMO_PASSWORD ?? 'password');
    await page.getByTestId('confirm-password-button').tap();
    await page.waitForURL((url) => url.pathname !== '/user/confirm-password');

    await page.goto('/settings/two-factor');
    await expectNoHorizontalOverflow(page);
    await page.getByRole('button', { name: /Enable 2FA|Aktifkan 2FA/i }).tap();
    const setupDialog = page.getByRole('dialog');
    await expect(setupDialog).toBeVisible();
    await expect(setupDialog.locator('input[readonly]')).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await page.keyboard.press('Escape');
    await expect(setupDialog).toBeHidden();
    const csrfCookie = (await page.context().cookies()).find(({ name }) => name === 'XSRF-TOKEN');
    if (csrfCookie) {
        await page.context().request.delete(new URL('/user/two-factor-authentication', page.url()).toString(), {
            failOnStatusCode: false,
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(csrfCookie.value),
            },
        });
    }

    await page.goto('/settings/profile');
    await expectNoHorizontalOverflow(page);
    const athleteSection = page.getByRole('heading', { name: 'Data atlet' }).locator('xpath=ancestor::section[1]');
    const guardianNameInput = athleteSection.locator('#profile-athlete-guardian-name');
    const originalGuardian = await guardianNameInput.inputValue();
    const mobileGuardian = `Wali Mobile ${Date.now()}`;
    await athleteSection.getByRole('button', { name: 'Ubah' }).tap();
    await guardianNameInput.fill(mobileGuardian);
    await athleteSection.getByRole('button', { name: 'Simpan data atlet' }).tap();
    await expect(guardianNameInput).toBeDisabled();
    await page.reload();
    await expect(page.locator('#profile-athlete-guardian-name')).toHaveValue(mobileGuardian);

    const reloadedAthleteSection = page
        .getByRole('heading', { name: 'Data atlet' })
        .locator('xpath=ancestor::section[1]');
    await reloadedAthleteSection.getByRole('button', { name: 'Ubah' }).tap();
    await page.locator('#profile-athlete-guardian-name').fill(originalGuardian);
    await reloadedAthleteSection.getByRole('button', { name: 'Simpan data atlet' }).tap();
    await expect(page.locator('#profile-athlete-guardian-name')).toBeDisabled();

    const achievements = page
        .getByRole('heading', { name: 'Prestasi', exact: true })
        .locator('xpath=ancestor::div[contains(@class,"rounded-xl")][1]');
    const mobileAchievement = `Prestasi Mobile ${Date.now()}`;
    await achievements.getByRole('button', { name: 'Tambah prestasi' }).tap();
    const dialog = page.getByRole('dialog').last();
    await dialog.getByLabel('Nama kejuaraan atau prestasi').fill(mobileAchievement);
    await dialog.getByRole('button', { name: 'Tambah prestasi' }).tap();
    await expect(dialog).toBeHidden();
    const card = achievements.locator('article').filter({ hasText: mobileAchievement });
    await expect(card).toBeVisible();
    await card.getByRole('button', { name: 'Hapus' }).tap();
    await page
        .getByRole('dialog')
        .filter({ hasText: /Hapus prestasi\?/ })
        .getByRole('button', { name: 'Hapus prestasi' })
        .tap();
    await expect(achievements.locator('article').filter({ hasText: mobileAchievement })).toHaveCount(0);
    await expectNoHorizontalOverflow(page);
});

test('mobile admin account management, user directory filtering, guardian profile edit, and class member modal', async ({
    page,
}) => {
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/admin');
    await expectNoHorizontalOverflow(page);

    const search = page.getByPlaceholder(/Cari pengguna berdasarkan nama|Search users by name/i);
    await search.fill('admin@rfis.test');
    const adminCard = page.locator('main article').filter({ hasText: 'admin@rfis.test' }).first();
    await expect(adminCard).toBeVisible();
    await adminCard.getByRole('button', { name: /Edit account|Ubah akun/i }).tap();
    const editDialog = page.getByRole('dialog').filter({ hasText: /Edit account|Ubah akun/i });
    await expect(editDialog).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await editDialog.getByRole('button', { name: /Batal|Cancel/i }).tap();
    await expect(editDialog).toBeHidden();

    await page.goto('/users');
    await expectNoHorizontalOverflow(page);
    await page.locator('#athlete-branch-filter').tap();
    await page.getByRole('button', { name: 'Central Dojang', exact: true }).tap();
    const aditCard = page.locator('main article').filter({ hasText: 'Adit Pratama' }).first();
    await expect(aditCard).toBeVisible();
    await aditCard.getByRole('button', { name: /^(View Profile|Lihat profil)$/i }).tap();
    await page.waitForURL((url) => /^\/users\/[0-9a-f-]{36}$/i.test(url.pathname));
    await expectNoHorizontalOverflow(page);
    await expect(page.locator('#guardian-name')).toBeVisible();

    await page.goto('/admin/classes');
    await expectNoHorizontalOverflow(page);
    const juniorCard = page.locator('main article').filter({ hasText: 'Junior Sparring' }).first();
    await expect(juniorCard).toBeVisible();
    await juniorCard.getByRole('button', { name: 'Lihat Anggota kelas' }).tap();
    const membersDialog = page.getByRole('dialog').filter({ hasText: 'Junior Sparring' });
    await expect(membersDialog).toBeVisible();
    await expect(membersDialog.getByText('Adit Pratama').first()).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await membersDialog
        .getByRole('button', { name: /Close modal|Tutup|Close/i })
        .first()
        .tap();
    await expect(membersDialog).toBeHidden();
});

test('mobile training locations, classes, weekly schedule, and sessions fit the viewport and work by touch', async ({
    page,
}) => {
    test.setTimeout(90_000);
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
    await expectNoHorizontalOverflow(page);
    await page.getByRole('button', { name: 'Tambah Lokasi' }).tap();
    const locationDialog = page.getByRole('dialog').last();
    await expect(locationDialog).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await locationDialog.getByRole('button', { name: /Batal|Cancel/i }).tap();
    await expect(locationDialog).toBeHidden();

    await page.goto('/admin/classes');
    await expectNoHorizontalOverflow(page);
    await page.getByRole('button', { name: 'Tambah Grup' }).tap();
    const classDialog = page.getByRole('dialog').last();
    await expect(classDialog).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await classDialog.getByRole('button', { name: 'Batal' }).tap();
    await expect(classDialog).toBeHidden();

    const juniorCard = page.locator('main article').filter({ hasText: 'Junior Sparring' }).first();
    await juniorCard.getByRole('button', { name: 'Lihat sesi kelas' }).tap();
    const sessionsDialog = page.getByRole('dialog').filter({ hasText: 'Riwayat Sesi Kelas' });
    await expect(sessionsDialog).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await sessionsDialog
        .getByRole('button', { name: /Close modal|Tutup|Close/i })
        .first()
        .tap();
    await expect(sessionsDialog).toBeHidden();

    await page.goto('/training-schedule');
    await expectNoHorizontalOverflow(page);
    await page.getByRole('button', { name: 'Tabel' }).tap();
    await expectNoHorizontalOverflow(page);
    await page.getByRole('button', { name: 'Kartu' }).tap();
    await expectNoHorizontalOverflow(page);

    await page.goto('/admin/daily-schedules');
    await expect(page.getByRole('heading', { name: 'Jadwal Latihan Harian' })).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page.goto('/sessions');
    await expectNoHorizontalOverflow(page);
    await page.getByRole('button', { name: /^Archived/i }).tap();
    await page.waitForURL((url) => url.searchParams.get('visibility') === 'archived');
    await expectNoHorizontalOverflow(page);
    await page.getByRole('button', { name: /^Upcoming/i }).tap();
    await page.waitForURL((url) => url.searchParams.get('visibility') !== 'archived');
    await expectNoHorizontalOverflow(page);
});

test('mobile session attendance sheet, coach check-in, offline QR queue and sync, and attendance reports work by touch', async ({
    page,
}) => {
    test.setTimeout(120_000);
    await page.route('**/favicon.svg', (route) => route.fulfill({ status: 204, body: '' }));

    await loginAs(page, 'admin@rfis.test');
    await page.goto('/sessions');
    const sessionCard = page.locator('main article').filter({ hasText: 'Junior Sparring' }).first();
    await expect(sessionCard).toBeVisible();
    const attendanceUrl = await sessionCard.getByRole('link', { name: /^(Edit|Ubah)$/ }).getAttribute('href');
    expect(attendanceUrl).toBeTruthy();

    await page.goto(new URL(attendanceUrl!, page.url()).toString());
    await expectNoHorizontalOverflow(page);

    const aditCard = page.locator('main article').filter({ hasText: 'Adit Pratama' }).first();
    await expect(aditCard).toBeVisible();
    await aditCard.getByRole('button', { name: 'Terlambat' }).tap();
    await expect(aditCard).toContainText(/Late|Terlambat/i);
    await aditCard.getByRole('button', { name: 'Hadir', exact: true }).tap();
    await expect(aditCard).toContainText(/Present|Hadir/i);

    const childCard = page.locator('main article').filter({ hasText: 'Anak Semua Peran' }).first();
    await childCard.getByRole('button', { name: 'Tidak hadir' }).tap();
    await expect(childCard).toContainText(/Absent|Tidak hadir/i);

    const qrToggle = page.getByRole('button', { name: /^(Buka QR cepat|Tutup QR)$/ }).first();
    if ((await qrToggle.innerText()).trim() === 'Buka QR cepat') {
        await qrToggle.tap();
    }
    const scanUrl = (
        await page
            .getByText(/\/attendance\/scan\//)
            .first()
            .innerText()
    ).trim();

    await page.getByTestId('user-menu-trigger').tap();
    await page.getByTestId('logout-button').tap();
    await page.waitForURL((url) => url.pathname === '/');

    await loginAs(page, 'allroles.child@rfis.test');
    await page.goto('/attendance');
    await expectNoHorizontalOverflow(page);

    await page.context().setOffline(true);
    const offlineToast = page.getByRole('status');
    if ((await offlineToast.count()) > 0) {
        await offlineToast.first().getByRole('button').tap();
    }

    await page.getByRole('textbox', { name: /\/attendance\/scan/i }).fill(scanUrl);
    await page.getByRole('button', { name: 'Use pasted link' }).tap();
    await expect(page.getByText(/1 pending|1 scan menunggu sinkronisasi/i).first()).toBeVisible();
    await expectNoHorizontalOverflow(page);
    const queuedToast = page.getByRole('status');
    if ((await queuedToast.count()) > 0) {
        await queuedToast.first().getByRole('button').tap();
    }

    const syncResponsePromise = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' && new URL(response.url()).pathname === new URL(scanUrl).pathname,
    );
    await page.context().setOffline(false);
    expect((await syncResponsePromise).status()).toBeLessThan(400);
    await expect(page.getByText(/1 pending|1 scan menunggu sinkronisasi/i)).toHaveCount(0);
    await expectNoHorizontalOverflow(page);

    await page.getByTestId('user-menu-trigger').tap();
    await page.getByTestId('logout-button').tap();
    await page.waitForURL((url) => url.pathname === '/');

    await loginAs(page, 'coach@rfis.test');
    await page.goto('/attendance');
    await expect(page.getByRole('heading', { name: 'Sesi mengajar yang tersedia' })).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page.getByTestId('user-menu-trigger').tap();
    await page.getByTestId('logout-button').tap();
    await page.waitForURL((url) => url.pathname === '/');

    await loginAs(page, 'admin@rfis.test');
    await page.goto(new URL(attendanceUrl!, page.url()).toString());
    const resetChildCard = page.locator('main article').filter({ hasText: 'Anak Semua Peran' }).first();
    await resetChildCard.getByRole('button', { name: 'Tidak hadir' }).tap();
    const closeQr = page.getByRole('button', { name: 'Tutup QR' }).first();
    if (await closeQr.isVisible()) {
        await closeQr.tap();
        await page.getByRole('dialog').getByRole('button', { name: 'Tutup QR' }).tap();
    }

    await page.goto('/admin/attendance');
    await expectNoHorizontalOverflow(page);
    await page.goto('/admin/instructor-attendance');
    await expectNoHorizontalOverflow(page);
});

test('mobile finance queue, invoice template, QRIS settings, payroll modal, and coach finance view fit the viewport', async ({
    page,
}) => {
    test.setTimeout(90_000);
    await loginAs(page, 'admin@rfis.test');

    await page.goto('/payments');
    await expectNoHorizontalOverflow(page);
    await page.getByRole('button', { name: 'Pengaturan invoice' }).tap();
    const invoiceTemplateDialog = page.getByRole('dialog').filter({ hasText: 'Pengaturan invoice' });
    await expect(invoiceTemplateDialog).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await invoiceTemplateDialog.getByRole('button', { name: 'Batal' }).tap();
    await expect(invoiceTemplateDialog).toBeHidden();

    await page.getByRole('button', { name: 'Buat tagihan', exact: true }).tap();
    const createInvoiceDialog = page.getByRole('dialog').last();
    await expect(createInvoiceDialog).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await createInvoiceDialog.getByRole('button', { name: 'Batal' }).tap();
    await expect(createInvoiceDialog).toBeHidden();

    await page.goto('/payments/qris');
    await expect(page.locator('#qris-label')).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page.goto('/admin/payroll');
    await expectNoHorizontalOverflow(page);
    await page.getByRole('button', { name: 'Buat payroll' }).tap();
    const payrollDialog = page.getByRole('dialog').last();
    await expect(payrollDialog).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await payrollDialog.getByRole('button', { name: 'Tutup dialog' }).tap();
    await expect(payrollDialog).toBeHidden();

    await page.getByTestId('user-menu-trigger').tap();
    await page.getByTestId('logout-button').tap();
    await page.waitForURL((url) => url.pathname === '/');

    await loginAs(page, 'coach@rfis.test');
    await page.goto('/payments');
    await expectNoHorizontalOverflow(page);
});

test('mobile championships, event history, announcements, WhatsApp settings, data export, and activity logs work by touch', async ({
    page,
}) => {
    test.setTimeout(90_000);
    await loginAs(page, 'admin@rfis.test');

    await page.goto('/championships');
    await expectNoHorizontalOverflow(page);
    await page.getByRole('button', { name: 'Tambah kejuaraan' }).tap();
    const eventDialog = page.getByRole('dialog').last();
    await expect(eventDialog).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await eventDialog.getByRole('button', { name: 'Batal' }).tap();
    await expect(eventDialog).toBeHidden();

    const firstDetailLink = page.getByRole('link', { name: 'Detail' }).first();
    await expect(firstDetailLink).toBeVisible();
    await firstDetailLink.tap();
    await page.waitForURL((url) => /^\/championships\/[^/]+$/.test(url.pathname));
    await expectNoHorizontalOverflow(page);

    await page.getByRole('button', { name: 'Tambah pelatih' }).tap();
    const coachModal = page.getByRole('dialog').last();
    await expect(coachModal).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await coachModal.getByRole('button', { name: 'Batal' }).tap();
    await expect(coachModal).toBeHidden();

    await page.goto('/admin/events/history');
    await expectNoHorizontalOverflow(page);
    const expandHistoryBtn = page.getByRole('button', { name: 'Lihat & edit hasil' }).first();
    if ((await expandHistoryBtn.count()) > 0) {
        await expandHistoryBtn.tap();
        await expectNoHorizontalOverflow(page);
    }

    await page.goto('/announcements');
    await expectNoHorizontalOverflow(page);
    await page.getByRole('button', { name: 'Buat pengumuman' }).tap();
    const announcementDialog = page.getByRole('dialog').last();
    await expect(announcementDialog).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await announcementDialog.getByRole('button', { name: 'Tutup dialog' }).tap();
    await expect(announcementDialog).toBeHidden();

    await page.goto('/admin/whatsapp-template');
    await expect(page.locator('#public-admin-whatsapp')).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page.goto('/admin/data-export');
    await expect(
        page.getByRole('heading', { name: /Build a custom Excel export|Buat ekspor Excel khusus/i }),
    ).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page.goto('/admin/activity-logs');
    await expect(page.locator('#log-search')).toBeVisible();
    await expectNoHorizontalOverflow(page);
});

test('ID-10: mobile soft-delete and permanent-delete confirmations, cancellation, and self-deletion safeguard', async ({
    page,
}) => {
    test.setTimeout(120_000);
    await loginAs(page, 'admin@rfis.test');
    await page.goto('/admin');

    const accountEmail = `e2e.mobile.lifecycle.${Date.now()}@rfis.test`;
    await page.getByRole('button', { name: 'Add new user' }).tap();
    const createDialog = page.getByRole('dialog').filter({ hasText: 'Create user account' });
    await createDialog.getByLabel('Full name').fill(`E2E Mobile Lifecycle ${Date.now()}`);
    await createDialog.getByLabel('Email').fill(accountEmail);
    await createDialog.locator('#admin-status').tap();
    await page.getByRole('button', { name: /Aktif|Active/, exact: true }).tap();
    await createDialog.getByRole('button', { name: 'Generate' }).tap();
    await createDialog.getByRole('button', { name: 'Create account' }).tap();
    await expect(createDialog).toBeHidden();

    await page.getByPlaceholder(/Cari pengguna berdasarkan nama|Search users by name/i).fill(accountEmail);
    const card = () => page.locator('main article').filter({ hasText: accountEmail }).first();
    await expect(card()).toBeVisible();

    await card()
        .getByRole('button', { name: /Hapus|Delete/ })
        .first()
        .tap();
    let confirmation = page.getByRole('dialog').filter({ hasText: 'Delete this account?' });
    await expect(confirmation).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await confirmation.getByRole('button', { name: /Batal|Cancel/i }).tap();
    await expect(confirmation).toBeHidden();
    await expect(card().getByRole('button', { name: 'Restore' })).toHaveCount(0);

    await card()
        .getByRole('button', { name: /Hapus|Delete/ })
        .first()
        .tap();
    confirmation = page.getByRole('dialog').filter({ hasText: 'Delete this account?' });
    await confirmation.getByRole('button', { name: 'Delete account' }).tap();
    await expect(confirmation).toBeHidden();
    await expect(card().getByRole('button', { name: 'Restore' })).toBeVisible();

    await card().getByRole('button', { name: 'Restore' }).tap();
    await expect(card().getByRole('button', { name: 'Edit account' })).toBeVisible();
    await card()
        .getByRole('button', { name: /Hapus|Delete/ })
        .first()
        .tap();
    await page
        .getByRole('dialog')
        .filter({ hasText: 'Delete this account?' })
        .getByRole('button', { name: 'Delete account' })
        .tap();
    await expect(card().getByRole('button', { name: 'Restore' })).toBeVisible();

    await card()
        .getByRole('button', { name: /Hapus|Delete/ })
        .last()
        .tap();
    confirmation = page.getByRole('dialog').filter({ hasText: 'Permanently delete this account?' });
    await expect(confirmation).toBeVisible();
    await confirmation.getByRole('button', { name: /Batal|Cancel/i }).tap();
    await expect(confirmation).toBeHidden();
    await expect(card()).toBeVisible();

    await card()
        .getByRole('button', { name: /Hapus|Delete/ })
        .last()
        .tap();
    await page
        .getByRole('dialog')
        .filter({ hasText: 'Permanently delete this account?' })
        .getByRole('button', { name: 'Permanently delete' })
        .tap();
    await expect(page.locator('main article').filter({ hasText: accountEmail })).toHaveCount(0);

    await page.getByPlaceholder(/Cari pengguna berdasarkan nama|Search users by name/i).fill('admin@rfis.test');
    const adminCard = page.locator('main article').filter({ hasText: 'admin@rfis.test' }).first();
    await adminCard
        .getByRole('button', { name: /Hapus|Delete/ })
        .first()
        .tap();
    await page
        .getByRole('dialog')
        .last()
        .getByRole('button', { name: /Delete account|Hapus akun/i })
        .tap();
    await expect(page.getByText(/You cannot delete your own account/i).first()).toBeVisible();
    await expect(adminCard).toBeVisible();
});

test('TR-04 and RP-04 and UI-01: mobile schedule detail modal, members and instructors summaries, and locale persistence', async ({
    page,
}) => {
    test.setTimeout(90_000);
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
    await page.locator('main article').first().tap();
    const detail = page.getByRole('dialog').filter({ hasText: 'Detail Jadwal' });
    await expect(detail).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await detail
        .getByRole('button', { name: /Close modal|Tutup|Close/i })
        .first()
        .tap();
    await expect(detail).toBeHidden();

    for (const path of ['/admin/members', '/admin/instructors']) {
        await page.goto(path);
        await expectNoHorizontalOverflow(page);
        await expect(page.locator('main')).not.toContainText('GEUP_');
        await expect(page.locator('main')).not.toContainText('\\n');
    }

    await page.goto('/locale/en');
    await page.goto('/training-schedule');
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expectNoHorizontalOverflow(page);
    await page.goto('/locale/id');
});

test('TR-05: mobile open-session join and history lock', async ({ page }) => {
    test.setTimeout(120_000);
    const sessionTitle = `E2E Mobile Open ${Date.now()}`;
    const sessionDate = new Date(Date.now() + 6 * 24 * 3600 * 1000).toISOString().slice(0, 10);

    const post = async (url: string, data?: Record<string, unknown>, method: 'post' | 'put' | 'delete' = 'post') => {
        const csrf = (await page.context().cookies()).find(({ name }) => name === 'XSRF-TOKEN');

        return page.context().request[method](new URL(url, page.url()).toString(), {
            failOnStatusCode: false,
            maxRedirects: 0,
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(csrf?.value ?? '') },
            data,
        });
    };

    await loginAs(page, 'admincoach@rfis.test');
    await page.goto('/sessions');
    const pageData = JSON.parse((await page.locator('#app').getAttribute('data-page')) ?? '{}') as {
        props?: { branches?: Array<{ value: string | number }> };
    };
    const created = await post('/sessions', {
        title: sessionTitle,
        branch_id: pageData.props?.branches?.[0]?.value,
        session_date: sessionDate,
        start_time: '07:00',
        end_time: '08:00',
        status: 'NEEDS_ASSISTANT',
    });
    expect(created.status()).toBeLessThan(400);

    await page.getByTestId('user-menu-trigger').tap();
    await page.getByTestId('logout-button').tap();
    await page.waitForURL((url) => url.pathname === '/');

    await loginAs(page, 'coach@rfis.test');
    await page.goto('/sessions?visibility=all');
    await expectNoHorizontalOverflow(page);
    const card = page.locator('main article').filter({ hasText: sessionTitle }).first();
    await expect(card).toBeVisible();
    await card.getByRole('button', { name: 'Join' }).tap();
    await expect(card.getByRole('button', { name: 'Join' })).toHaveCount(0);
    await expect(card.getByRole('link', { name: /^(Edit|Ubah)$/ })).toBeVisible();

    await page.getByTestId('user-menu-trigger').tap();
    await page.getByTestId('logout-button').tap();
    await page.waitForURL((url) => url.pathname === '/');

    await loginAs(page, 'admin@rfis.test');
    await page.goto('/sessions?visibility=all');
    await page.getByPlaceholder(/Search all session columns|Cari semua kolom sesi/i).fill(sessionTitle);
    const adminCard = page.locator('main article').filter({ hasText: sessionTitle }).first();
    await expect(adminCard).toBeVisible();
    await adminCard.getByRole('button', { name: 'Hapus', exact: true }).tap();
    await page
        .getByRole('dialog')
        .filter({ hasText: /Hapus sesi latihan\?/ })
        .getByRole('button', { name: 'Hapus sesi' })
        .tap();
    await expect(page.locator('main article').filter({ hasText: sessionTitle })).toHaveCount(0);
});
