import { expect } from '@playwright/test';
import { test, activateTheme } from '../fixtures/theme-fixture';
import { ADMIN_PAGES } from '../helpers/selectors';
import { loginAsAdmin } from '../helpers/login';

test.beforeAll(async ({}, testInfo) => {
	const theme = (testInfo.project.metadata as { theme: string }).theme;
	activateTheme(theme);
});

test.describe('Admin Pages', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
	});

	for (const adminPage of ADMIN_PAGES) {
		test(`${adminPage.name} page loads without errors`, async ({ page }) => {
			const response = await page.goto(
				`/wp-admin/admin.php?page=${adminPage.slug}`
			);

			expect(response?.status()).toBe(200);

			await expect(page.locator('.wrap')).toBeVisible();

			const body = await page.locator('body').textContent();
			expect(body).not.toContain('Fatal error');
			expect(body).not.toContain('Warning:');
			expect(body).not.toContain('Parse error');
		});
	}
});
