import { expect } from '@playwright/test';
import { test, activateTheme } from '../fixtures/theme-fixture';
import { CALC } from '../helpers/selectors';
import { fillAndCalculate } from '../helpers/calculate';

test.beforeAll(async ({}, testInfo) => {
	const theme = (testInfo.project.metadata as { theme: string }).theme;
	activateTheme(theme);
});

test.describe('Calculator', () => {
	test('renders all form fields', async ({ page }) => {
		await page.goto('/calculator-test/');
		await expect(page.locator(CALC.root)).toBeVisible();
		await expect(page.locator(CALC.form)).toBeVisible();
		await expect(page.locator(CALC.bookSize)).toBeVisible();
		await expect(page.locator(CALC.pages)).toBeVisible();
		await expect(page.locator(CALC.copies)).toBeVisible();
		await expect(page.locator(CALC.interiorColor('bw'))).toBeVisible();
		await expect(page.locator(CALC.coverColor('color'))).toBeVisible();
		await expect(page.locator(CALC.binding)).toBeVisible();
		await expect(page.locator(CALC.paper)).toBeVisible();
		await expect(page.locator(CALC.country)).toBeVisible();
		await expect(page.locator(CALC.submit)).toBeVisible();
	});

	test('calculates price and shows results', async ({ page }) => {
		await fillAndCalculate(page);

		await expect(page.locator(CALC.results)).toBeVisible();
		await expect(page.locator(CALC.summary)).not.toBeEmpty();
		await expect(page.locator(CALC.breakdown)).toBeVisible();
		await expect(page.locator(`${CALC.breakdown} tbody tr`)).toHaveCount(6);
		await expect(page.locator(CALC.total)).not.toBeEmpty();
		await expect(page.locator(CALC.addToCart)).toBeEnabled();
	});

	test('auto-recalculates on field change', async ({ page }) => {
		await fillAndCalculate(page);

		const initialTotal = await page.locator(CALC.total).textContent();

		await page.fill(CALC.pages, '400');
		await expect(page.locator(CALC.total)).not.toHaveText(initialTotal!, { timeout: 5000 });

		const newTotal = await page.locator(CALC.total).textContent();
		expect(newTotal).not.toBe(initialTotal);
	});

	test('shows validation error for invalid pages', async ({ page }) => {
		await page.goto('/calculator-test/');
		await page.fill(CALC.pages, '3');
		await page.fill(CALC.copies, '100');
		await page.click(CALC.submit);

		await expect(page.locator(CALC.error)).toBeVisible();
		await expect(page.locator(CALC.results)).toBeHidden();
	});

	test('add-to-cart button sends signed offer', async ({ page }) => {
		await fillAndCalculate(page);
		await page.click(CALC.addToCart);

		await expect(page.locator(CALC.cartMessage)).toBeVisible({ timeout: 10000 });
		await expect(page.locator(CALC.cartMessage)).toHaveClass(/--success/);
		await expect(page.locator(CALC.cartMessage).locator('a')).toBeVisible();
	});
});
