import { defineConfig, devices } from '@playwright/test';

const themes = ['storefront', 'astra', 'generatepress'];

export default defineConfig({
	testDir: './tests',
	outputDir: '../test-results',
	use: {
		baseURL: 'http://localhost:8080',
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
		actionTimeout: 15000,
	},
	timeout: 60000,
	workers: 1,
	retries: 0,
	reporter: [['html', { outputFolder: '../playwright-report' }], ['list']],
	projects: themes.map((theme) => ({
		name: theme,
		metadata: { theme },
		use: { ...devices['Desktop Chrome'] },
	})),
});
