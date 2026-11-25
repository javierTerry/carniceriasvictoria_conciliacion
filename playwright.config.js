// @ts-check
const { defineConfig, devices } = require('@playwright/test');

const isHeaded = process.argv.includes('--headed');

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: isHeaded ? 60 * 1000 : 30 * 1000,
  expect: {
    timeout: 5000
  },
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [['list'], ['html', { outputFolder: 'playwright-report', open: 'never' }]],
  use: {
    baseURL: process.env.BASE_URL || 'http://172.17.0.2/syspv/conciliacion/',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    launchOptions: {
      slowMo: isHeaded ? 500 : 0,
    },
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
