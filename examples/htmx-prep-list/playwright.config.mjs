import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  workers: 1,
  retries: 0,
  outputDir: '.tmp/test-results',
  reporter: 'line',
  use: {
    browserName: 'chromium',
    baseURL: 'http://127.0.0.1:4173',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      testIgnore: /webmcp\.spec\.mjs$/,
    },
    {
      // Chromium's native WebMCP (document.modelContext) is behind a Blink runtime
      // feature outside the origin trial; this project turns it on for the live proof.
      name: 'chromium-webmcp',
      testMatch: /webmcp\.spec\.mjs$/,
      use: {
        launchOptions: { args: ['--enable-blink-features=WebMCP'] },
      },
    },
  ],
  webServer: {
    command: 'npm run start',
    url: 'http://127.0.0.1:4173/',
    reuseExistingServer: false,
    timeout: 15_000,
    stdout: 'pipe',
    stderr: 'pipe',
  },
});
