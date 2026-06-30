import { expect } from '@playwright/test';
import { test, activateTheme } from '../fixtures/theme-fixture';
import { CALC, ADMIN_PAGES } from '../helpers/selectors';
import { loginAsAdmin } from '../helpers/login';

test.beforeAll(async ({}, testInfo) => {
	const theme = (testInfo.project.metadata as { theme: string }).theme;
	activateTheme(theme);
});

test.describe('Console Errors', () => {
	test('calculator page has zero JS errors', async ({ page }) => {
		const errors: string[] = [];
		page.on('console', (msg) => {
			if (msg.type() === 'error') {
				errors.push(msg.text());
			}
		});

		await page.goto('/calculator-test/');
		await page.waitForSelector(CALC.form);

		await page.fill(CALC.pages, '200');
		await page.fill(CALC.copies, '100');
		await page.click(CALC.submit);
		await page.waitForSelector(CALC.results, { state: 'visible', timeout: 10000 });

		expect(errors).toHaveLength(0);
	});

	test('cart page has zero JS errors', async ({ page }) => {
		const errors: string[] = [];
		page.on('console', (msg) => {
			if (msg.type() === 'error') {
				errors.push(msg.text());
			}
		});

		await page.goto('/cart/');
		await page.waitForLoadState('networkidle');

		expect(errors).toHaveLength(0);
	});

	test('admin pages have zero JS errors', async ({ page }) => {
		await loginAsAdmin(page);

		const errors: string[] = [];
		page.on('console', (msg) => {
			if (msg.type() === 'error') {
				errors.push(msg.text());
			}
		});

		for (const adminPage of ADMIN_PAGES) {
			await page.goto(`/wp-admin/admin.php?page=${adminPage.slug}`);
			await page.waitForLoadState('networkidle');
		}

		expect(errors).toHaveLength(0);
	});
});
