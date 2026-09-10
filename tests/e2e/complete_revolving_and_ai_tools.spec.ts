import { test, expect, type Page } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:56586';

async function loginAs(page: Page, username: string) {
    await page.context().clearCookies();
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    const userIn = page.locator('input[autocomplete="username"]').first();
    await userIn.waitFor({ state: 'visible', timeout: 15000 });
    await userIn.fill('');
    await userIn.pressSequentially(username, { delay: 20 });

    const passIn = page.locator('input[autocomplete="current-password"]').first();
    await passIn.fill('');
    await passIn.pressSequentially('password', { delay: 20 });

    await page.getByRole('button', { name: /Masuk/i }).first().click();
    await page.waitForFunction(() => !window.location.pathname.includes('/login'), { timeout: 20000 });
    await page.waitForTimeout(400);
}

    test('2. AI Assistant Tool Execution & Streaming Chat API Audit', async ({ page }) => {
        test.setTimeout(120000);

        await loginAs(page, 'superadmin');

        await page.goto(`${BASE}/admin/integrations/orchestrator`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();

        const csrfToken = await page.evaluate(() => {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        });

        const personasRes = await page.request.get(`${BASE}/admin/integrations/orchestrator/personas`);
        expect(personasRes.status()).toBe(200);

        const toolsRes = await page.request.get(`${BASE}/admin/integrations/orchestrator/tools`);
        expect(toolsRes.status()).toBe(200);

        const convRes = await page.request.get(`${BASE}/admin/integrations/orchestrator/conversations`);
        expect(convRes.status()).toBe(200);

        const logsRes = await page.request.get(`${BASE}/admin/integrations/orchestrator/audit-logs`);
        expect(logsRes.status()).toBe(200);

        const chatRes = await page.request.post(`${BASE}/admin/integrations/orchestrator/chat`, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'text/event-stream, application/json',
            },
            data: {
                message: 'Berapa total saldo kas saat ini?',
                persona_slug: 'default'
            }
        });
        expect(chatRes.status()).toBeLessThan(500);
    });
});
