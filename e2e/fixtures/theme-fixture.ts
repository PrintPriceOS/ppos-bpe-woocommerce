import { test as base } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

export function activateTheme(slug: string) {
	const projectRoot = path.resolve(__dirname, '..', '..');
	execSync(`docker compose run --rm wpcli theme activate ${slug}`, {
		cwd: projectRoot,
		stdio: 'pipe',
		timeout: 30000,
	});
	try {
		execSync('docker compose run --rm wpcli cache flush', {
			cwd: projectRoot,
			stdio: 'pipe',
			timeout: 15000,
		});
	} catch {
		// Cache flush may fail if no object cache is configured — safe to ignore.
	}
}

export const test = base;
