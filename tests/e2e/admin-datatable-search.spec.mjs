/**
 * Diagnóstico runtime: busca visual do <x-admin.datatable>.
 * Requer: php artisan serve (ou app em :44432) + usuário de teste.
 *
 * Uso:
 *   DEBUG_ADMIN_DATATABLE=1 npx playwright test tests/e2e/admin-datatable-search.spec.mjs
 */
import { test, expect } from '@playwright/test';

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8000';
const LOGIN_EMAIL = process.env.E2E_LOGIN_EMAIL || 'programador@example.test';
const LOGIN_PASSWORD = process.env.E2E_LOGIN_PASSWORD || 'password';

test.describe('Admin DataTable search (diagnóstico)', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(`${BASE_URL}/login`);
        await page.fill('input[name="email"]', LOGIN_EMAIL);
        await page.fill('input[name="password"]', LOGIN_PASSWORD);
        await page.click('button[type="submit"]');
        await page.waitForURL(/\/(admin|dashboard)/);
    });

    test('frutas: digitar na busca altera tbody e contagens DataTables', async ({ page }) => {
        await page.goto(`${BASE_URL}/admin/frutas`);
        await page.waitForSelector('#frutas-datatable');

        await page.evaluate(() => {
            window.DEBUG_ADMIN_DATATABLE = true;
        });

        // Re-dispara bootstrap se AdminDataTable já carregou
        await page.evaluate(() => {
            if (window.AdminDataTable?.bootstrap) {
                window.AdminDataTable.bootstrap();
            }
        });

        const before = await page.evaluate(() => window.__ADMIN_DT_LAST_SNAPSHOT__ || null);

        const searchInput = page.locator('#frutas-table-root .dataTables_filter input');
        await expect(searchInput).toHaveCount(1);

        const uniqueTerm = 'ZZZE2EUNICO';
        await searchInput.fill(uniqueTerm);
        await searchInput.dispatchEvent('keyup');
        await page.waitForTimeout(400);

        const after = await page.evaluate(() => window.__ADMIN_DT_LAST_SNAPSHOT__ || null);

        console.log('BEFORE', JSON.stringify(before, null, 2));
        console.log('AFTER', JSON.stringify(after, null, 2));

        expect(after, 'snapshot após busca deve existir (ative DEBUG no JS)').not.toBeNull();
        expect(after.searchTerm).toBe(uniqueTerm);
        expect(after.rowsAppliedCount).toBeLessThan(after.rowsTotalCount);
        expect(after.tbodyTrCount).toBe(after.rowsPageAppliedCount);
        expect(after.tbodyOwnedByDt).toBe(true);
        expect(after.dtInstanceCount).toBe(1);
    });
});
