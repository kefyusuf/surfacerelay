import { defineConfig } from '@playwright/test';

const php = process.env.PHP_BINARY ?? 'php';
const testbench = `${php} vendor/bin/testbench`;

export default defineConfig({
  testDir: './tests',
  workers: 1,
  retries: 0,
  timeout: 60_000,
  outputDir: '.tmp/test-results',
  reporter: 'line',
  use: {
    browserName: 'chromium',
    baseURL: 'http://127.0.0.1:4180',
    trace: 'retain-on-failure',
    // Chromium's native WebMCP (document.modelContext) is behind a Blink runtime
    // feature outside the origin trial.
    launchOptions: { args: ['--enable-blink-features=WebMCP'] },
  },
  webServer: {
    command: [
      `${testbench} workbench:build`,
      `${testbench} filament:assets`,
      `${testbench} serve --host=127.0.0.1 --port=4180 --no-reload`,
    ].join(' && '),
    cwd: '../../packages/laravel',
    url: 'http://127.0.0.1:4180/admin/orders',
    reuseExistingServer: false,
    timeout: 120_000,
    stdout: 'ignore',
    stderr: 'pipe',
  },
});

