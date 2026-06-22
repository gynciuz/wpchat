import { test, expect } from "@playwright/test";
import { BOOT, ORDERS_CHAT_RESPONSE, installRoutes, gotoApp } from "./fixtures";

test.describe("Chat", () => {
  test("empty state renders the header and outcome hero", async ({ page }) => {
    await installRoutes(page);
    await gotoApp(page, BOOT);

    await expect(page.getByRole("heading", { name: "WPChat" })).toBeVisible();
    await expect(
      page.getByRole("heading", { name: "What outcome do you want?" })
    ).toBeVisible();
    await expect(page.getByPlaceholder("Type or speak…")).toBeVisible();

    await expect(page).toHaveScreenshot("chat-empty.png", { fullPage: true });
  });

  test("renders an interactive orders table from a chat response", async ({ page }) => {
    await installRoutes(page, { chat: ORDERS_CHAT_RESPONSE });
    await gotoApp(page, BOOT);

    const input = page.getByPlaceholder("Type or speak…");
    await input.fill("show me recent orders");
    await input.press("Enter");

    // The assistant turn renders the structured table above its prose.
    await expect(page.getByRole("table")).toBeVisible();
    await expect(page.getByRole("columnheader", { name: "Statusas" })).toBeVisible();
    await expect(page.getByText("Jonas Jonaitis")).toBeVisible();
    await expect(page.getByText("Petras Petraitis")).toBeVisible();
    await expect(page.getByText("Here are your 2 most recent orders.")).toBeVisible();

    await expect(page).toHaveScreenshot("chat-orders-table.png", { fullPage: true });
  });

  test("attaches multiple images and shows a chip per file, then thumbnails", async ({ page }) => {
    await installRoutes(page, {
      chat: { text: "Created a draft post with your images.", tool_calls: [], conversation_id: "c1" },
    });
    await gotoApp(page, BOOT);

    const png = Buffer.from(
      "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==",
      "base64"
    );
    await page.locator('input[type="file"]').first().setInputFiles([
      { name: "first.png", mimeType: "image/png", buffer: png },
      { name: "second.png", mimeType: "image/png", buffer: png },
    ]);

    // One chip per attached file.
    await expect(page.getByText("first.png")).toBeVisible();
    await expect(page.getByText("second.png")).toBeVisible();

    const input = page.getByPlaceholder("Type or speak…");
    await input.fill("Create a post with these photos");
    await input.press("Enter");

    // Both images render as thumbnails in the user bubble after upload.
    await expect(page.getByRole("img", { name: "uploaded" })).toHaveCount(2);
    await expect(page.getByText("Created a draft post with your images.")).toBeVisible();
  });

  test("surfaces a backend error in the chat", async ({ page }) => {
    await installRoutes(page);
    // Override chat with a 500 so the error banner renders.
    await page.route("**/wpchat/v1/chat", (route) =>
      route.fulfill({
        status: 500,
        contentType: "application/json",
        body: JSON.stringify({ error: "Anthropic API error: overloaded" }),
      })
    );
    await gotoApp(page, BOOT);

    const input = page.getByPlaceholder("Type or speak…");
    await input.fill("hello");
    await input.press("Enter");

    await expect(page.getByText("Anthropic API error: overloaded")).toBeVisible();
  });
});
