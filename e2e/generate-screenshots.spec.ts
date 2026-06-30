import { test } from '@playwright/test';
import { loginAsAdmin } from '../helpers/login';
import path from 'path';
import fs from 'fs';

const assetsDir = path.join(process.cwd(), 'assets');

test.use({ viewport: { width: 1280, height: 900 } });

test('screenshot 1 - calculator form', async ({ page }) => {
	await page.goto('/calculator-test/');
	await page.waitForSelector('#ppp-bpe-calc-form');
	await page.waitForLoadState('networkidle');
	await page.screenshot({
		path: path.join(assetsDir, 'screenshot-1.png'),
		clip: { x: 0, y: 0, width: 1280, height: 900 },
	});
});

test('screenshot 2 - calculator with results', async ({ page }) => {
	await page.goto('/calculator-test/');
	await page.waitForSelector('#ppp-bpe-calc-form');
	await page.selectOption('#ppp-bpe-book-size', 'A5');
	await page.fill('#ppp-bpe-pages', '200');
	await page.fill('#ppp-bpe-copies', '100');
	await page.click('#ppp-bpe-calc-submit');
	await page.waitForSelector('#ppp-bpe-calc-results', { state: 'visible', timeout: 10000 });
	await page.screenshot({
		path: path.join(assetsDir, 'screenshot-2.png'),
		clip: { x: 0, y: 0, width: 1280, height: 900 },
	});
});

test('screenshot 3 - settings page', async ({ page }) => {
	await loginAsAdmin(page);
	await page.goto('/wp-admin/admin.php?page=printpricepro-bpe');
	await page.waitForSelector('.wrap');
	await page.waitForLoadState('networkidle');
	await page.screenshot({
		path: path.join(assetsDir, 'screenshot-3.png'),
	});
});

test('screenshot 4 - production queue', async ({ page }) => {
	await loginAsAdmin(page);
	await page.goto('/wp-admin/admin.php?page=printpricepro-bpe-orders');
	await page.waitForSelector('.wrap');
	await page.waitForLoadState('networkidle');
	await page.screenshot({
		path: path.join(assetsDir, 'screenshot-4.png'),
	});
});

test('screenshot 5 - license page', async ({ page }) => {
	await loginAsAdmin(page);
	await page.goto('/wp-admin/admin.php?page=printpricepro-bpe-license');
	await page.waitForSelector('.wrap');
	await page.waitForLoadState('networkidle');
	await page.screenshot({
		path: path.join(assetsDir, 'screenshot-5.png'),
	});
});
