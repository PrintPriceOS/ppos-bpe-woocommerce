import { test, expect } from '@playwright/test';
import { activateTheme } from '../fixtures/theme-fixture';

test.beforeAll(async ({}, testInfo) => {
	const theme = (testInfo.project.metadata as { theme: string }).theme;
	activateTheme(theme);
});

test('add-to-cart returns success and cart link', async ({ page }) => {
	await page.goto('/calculator-test/');
	await page.waitForSelector('#ppp-bpe-calc-form');
	await page.selectOption('#ppp-bpe-book-size', 'A5');
	await page.fill('#ppp-bpe-pages', '200');
	await page.fill('#ppp-bpe-copies', '50');
	await page.click('#ppp-bpe-calc-submit');
	await page.waitForSelector('#ppp-bpe-calc-results', { state: 'visible', timeout: 10000 });

	await page.click('#ppp-bpe-add-to-cart');
	await expect(page.locator('#ppp-bpe-cart-message')).toBeVisible({ timeout: 10000 });
	await expect(page.locator('#ppp-bpe-cart-message')).toHaveClass(/--success/);

	const cartLink = page.locator('#ppp-bpe-cart-message a');
	await expect(cartLink).toBeVisible();
	const href = await cartLink.getAttribute('href');
	expect(href).toContain('/cart');
});

test('cart page loads without errors', async ({ page }) => {
	await page.goto('/cart/', { waitUntil: 'domcontentloaded' });
	const title = await page.title();
	expect(title.length).toBeGreaterThan(0);

	const body = await page.locator('body').textContent();
	expect(body).not.toContain('Fatal error');
	expect(body).not.toContain('Warning:');
});

test('checkout page loads without errors', async ({ page }) => {
	await page.goto('/checkout/', { waitUntil: 'domcontentloaded' });
	const title = await page.title();
	expect(title.length).toBeGreaterThan(0);

	const body = await page.locator('body').textContent();
	expect(body).not.toContain('Fatal error');
	expect(body).not.toContain('Warning:');
});
