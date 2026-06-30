import { type Page } from '@playwright/test';
import { CALC } from './selectors';

interface CalcOptions {
	bookSize?: string;
	pages?: string;
	copies?: string;
	interiorColor?: string;
	coverColor?: string;
	binding?: string;
	paper?: string;
}

const DEFAULTS: Required<CalcOptions> = {
	bookSize: 'A5',
	pages: '200',
	copies: '100',
	interiorColor: 'bw',
	coverColor: 'color',
	binding: 'perfect',
	paper: '80gsm_offset',
};

export async function fillAndCalculate(page: Page, overrides: CalcOptions = {}) {
	const opts = { ...DEFAULTS, ...overrides };

	await page.goto('/calculator-test/');
	await page.waitForSelector(CALC.form);

	await page.selectOption(CALC.bookSize, opts.bookSize);
	await page.fill(CALC.pages, opts.pages);
	await page.fill(CALC.copies, opts.copies);
	await page.check(CALC.interiorColor(opts.interiorColor));
	await page.check(CALC.coverColor(opts.coverColor));
	await page.selectOption(CALC.binding, opts.binding);
	await page.selectOption(CALC.paper, opts.paper);

	await page.click(CALC.submit);
	await page.waitForSelector(CALC.results, { state: 'visible', timeout: 10000 });
}
