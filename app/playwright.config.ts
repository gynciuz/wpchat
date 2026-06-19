import { defineConfig, devices } from "@playwright/test";

/**
 * Playwright visual/E2E config for the WPChat React app.
 *
 * Tests run against a Vite dev server serving e2e/harness.html — no
 * WordPress, MySQL, or Anthropic needed. Each test injects WPCHAT_BOOT and
 * mocks the wpchat/v1/* REST calls (see e2e/fixtures.ts), so the suite is
 * fast and deterministic. Visual snapshots live under e2e/__screenshots__/.
 */
export default defineConfig({
  testDir: "./e2e",
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? "github" : "list",
  snapshotPathTemplate: "{testDir}/__screenshots__/{testFilePath}/{arg}{ext}",

  use: {
    baseURL: "http://localhost:5173",
    trace: "on-first-retry",
  },

  // Animations (motion/react enter/exit) are rolled to their end frame and
  // frozen so snapshots are stable; a small pixel tolerance absorbs font
  // anti-aliasing differences across machines.
  expect: {
    toHaveScreenshot: { animations: "disabled", maxDiffPixelRatio: 0.02 },
  },

  projects: [
    { name: "chromium", use: { ...devices["Desktop Chrome"] } },
  ],

  webServer: {
    command: "pnpm vite --port 5173 --strictPort",
    url: "http://localhost:5173/e2e/harness.html",
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
});
