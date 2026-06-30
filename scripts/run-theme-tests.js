const { spawn } = require('child_process');
const fs = require('fs');

const updateSnapshots = process.argv.includes('--update-snapshots');
const args = [
	'node_modules/@playwright/test/cli.js', 'test',
	'--config=e2e/playwright.config.ts',
	'--reporter=list',
	'--timeout=60000',
];
if (updateSnapshots) {
	args.push('--update-snapshots');
}

fs.mkdirSync('playwright-report', { recursive: true });
const outStream = fs.createWriteStream('playwright-report/last-run.txt');

const child = spawn('node', args, { stdio: ['ignore', 'pipe', 'pipe'] });

child.stdout.on('data', (d) => { process.stdout.write(d); outStream.write(d); });
child.stderr.on('data', (d) => { process.stderr.write(d); outStream.write(d); });

child.on('close', (code) => {
	const line = '\nEXIT: ' + code + '\n';
	process.stdout.write(line);
	outStream.write(line);
	outStream.end();
	process.exit(code);
});
