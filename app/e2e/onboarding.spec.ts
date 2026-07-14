import { test, expect } from "@playwright/test";
import { BOOT, ONBOARDING_STATUS, installRoutes, gotoApp } from "./fixtures";

test.describe("Onboarding wizard", () => {
  test("renders the welcome step from a loaded status", async ({ page }) => {
    await installRoutes(page, { onboardingStatus: ONBOARDING_STATUS });
    await gotoApp(page, { ...BOOT, mode: "onboarding" });

    // WelcomeCard greets the user by first name and names their site.
    await expect(page.getByRole("heading", { name: "Hi, Test." })).toBeVisible();
    await expect(
      page.getByText("Let's get ChatAdmin ready for Gentleman's Empire", { exact: false })
    ).toBeVisible();

    await expect(page).toHaveScreenshot("onboarding-welcome.png", { fullPage: true });
  });
});
