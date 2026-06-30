import { expect } from '@playwright/test';
import { test, activateTheme } from '../fixtures/theme-fixture';
import { CALC } from '../helpers/selectors';
import { fillAndCalculate } from '../helpers/calculate';
import { loginAsAdmin } from '../helpers/login';

test.beforeAll(async ({}, testInfo) => {
	const theme = (testInfo.project.metadata as { theme: string }).theme;
	activateTheme(theme);
});

test.describe('Visual Regression', () => {
	test('calculator form empty state', async ({ page }) => {
		await page.goto('/calculator-test/');
		await page.waitForSelector(CALC.form);

		await expect(page.locator(CALC.root)).toHaveScreenshot('calculator-form.png', {
			maxDiffPixelRatio: 0.01,
			animations: 'disabled',
		});
	});

	test('calculator with results', async ({ page }) => {
		await fillAndCalculate(page);

		await expect(page.locator(CALC.root)).toHaveScreenshot('calculator-results.png', {
			maxDiffPixelRatio: 0.01,
			animations: 'disabled',
		});
	});

	test('calculator mobile viewport', async ({ page }) => {
		await page.setViewportSize({ width: 375, height: 812 });
		await fillAndCalculate(page);

		await expect(page.locator(CALC.root)).toHaveScreenshot('calculator-mobile.png', {
			maxDiffPixelRatio: 0.01,
			animations: 'disabled',
		});
	});

	test('cart page with item', async ({ page }) => {
		await fillAndCalculate(page);
		await page.click(CALC.addToCart);
		await expect(page.locator(CALC.cartMessage)).toBeVisible({ timeout: 10000 });

		await page.goto('/cart/');
		await page.waitForLoadState('networkidle');

		const cartForm = page.locator('.woocommerce-cart-form');
		if (await cartForm.isVisible()) {
			await expect(cartForm).toHaveScreenshot('cart-with-item.png', {
				maxDiffPixelRatio: 0.01,
				animations: 'disabled',
			});
		}
	});

	test('admin settings page', async ({ page }) => {
		await loginAsAdmin(page);
		await page.goto('/wp-admin/admin.php?page=printpricepro-bpe');
		await page.waitForSelector('.wrap');

		await expect(page.locator('.wrap')).toHaveScreenshot('admin-settings.png', {
			maxDiffPixelRatio: 0.01,
			animations: 'disabled',
		});
	});
});
