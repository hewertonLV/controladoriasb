/**
 * Diagnóstico isolado (sem login): public/diagnostics/datatable-search-test.html
 */
import { test, expect } from '@playwright/test';

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8000';

test('diagnóstico HTML: busca reduz linhas no tbody', async ({ page }) => {
    const logs = [];
    page.on('console', (msg) => {
        if (msg.text().includes('[AdminDataTable]')) {
            logs.push(msg.text());
        }
    });

    await page.goto(`${BASE_URL}/diagnostics/datatable-search-test.html`);
    await page.waitForSelector('#diag-datatable.dataTable');

    const before = await page.evaluate(() => window.__ADMIN_DT_LAST_SNAPSHOT__);
    expect(before.rowsTotalCount).toBe(8);

    const input = page.locator('#diag-table-root .dataTables_filter input');
    await input.fill('ZZZE2EUNICO');
    await input.dispatchEvent('keyup');
    await page.waitForTimeout(350);

    const after = await page.evaluate(() => window.__ADMIN_DT_LAST_SNAPSHOT__);

    console.log('--- LOGS ---');
    logs.forEach((l) => console.log(l));
    console.log('BEFORE', JSON.stringify(before, null, 2));
    console.log('AFTER', JSON.stringify(after, null, 2));

    expect(after.searchTerm).toBe('ZZZE2EUNICO');
    expect(after.rowsAppliedCount).toBe(1);
    expect(after.tbodyTrCount).toBe(1);
    expect(after.rowsPageAppliedCount).toBe(1);
    expect(after.dtInstanceCount).toBe(1);
    expect(after.tbodyOwnedByDt).toBe(true);
});
