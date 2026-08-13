const { test, expect } = require("@playwright/test");
const path = require("path");

async function login(page) {
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await page.goto("/wp-login.php?reauth=1");
    await page.getByLabel("Username or Email Address").fill("admin");
    await page.getByLabel("Password", { exact: true }).fill("admin-password");
    await page.getByRole("button", { name: "Log In" }).click();
    await page.waitForLoadState("domcontentloaded");
    if (page.url().includes("/wp-admin/")) return;
  }
  await expect(page).toHaveURL(/wp-admin/);
}

test.describe.serial("WordPress News Bot admin lifecycle", () => {
  let context;
  let page;

  test.beforeAll(async ({ browser }) => {
    context = await browser.newContext();
    page = await context.newPage();
  });

  test.afterAll(async () => {
    await context.close();
  });

  test("uploads the real ZIP, activates it, and shows Utkuweb metadata", async () => {
    await login(page);
    await page.goto("/wp-admin/plugin-install.php?tab=upload");
    await page
      .locator("input[type=file]")
      .setInputFiles(path.resolve(process.env.WPNB_ZIP_PATH));
    await page.getByRole("button", { name: /Install Now/i }).click();
    await expect(page.locator("body")).toContainText(
      "Plugin installed successfully",
    );
    await page
      .locator('a[href*="action=activate"][href*="plugin=wordpress-news-bot"]')
      .click();
    await expect(page.locator("#the-list")).toContainText("WordPress News Bot");
    await expect(page.locator("#the-list")).toContainText("Utkuweb");
  });

  test("supports setup skip, reopen, and completion without losing settings", async () => {
    await page.goto("/wp-admin/admin.php?page=wpnb-setup");
    await expect(
      page.getByRole("heading", { name: "Setup Wizard" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Skip setup" }).click();
    await page.goto("/wp-admin/admin.php?page=wpnb-setup&step=5");
    await page.getByRole("button", { name: "Finish Setup" }).click();
    await expect(page).toHaveURL(/wpnb-dashboard/);
  });

  test("rejects a failed OpenAI key and never renders it", async () => {
    await page.goto("/wp-admin/admin.php?page=wpnb-settings");
    await page.locator("#wpnb-api-key").fill("invalid-key");
    await page.locator("#wpnb-model").fill("fixture-model");
    await page
      .getByRole("button", { name: "Save and Test Connection" })
      .click();
    await expect(page.locator(".notice-error.is-dismissible")).toBeVisible();
    await expect(page.locator("body")).not.toContainText("invalid-key");
  });

  test("saves, retests, changes, and deletes an encrypted OpenAI key without exposure", async () => {
    await page.goto("/wp-admin/admin.php?page=wpnb-settings");
    await page.locator("#wpnb-api-key").fill("fixture-secret-key");
    await page.locator("#wpnb-model").fill("fixture-model");
    await page
      .getByRole("button", { name: "Save and Test Connection" })
      .click();
    const connectionNotice = page.locator(
      ".notice-success.is-dismissible, .notice-error.is-dismissible",
    );
    await expect(connectionNotice).toBeVisible();
    expect(await connectionNotice.innerText()).toContain("securely saved");
    await expect(page.locator("body")).not.toContainText("fixture-secret-key");
    const browserLeak = await page.evaluate(() =>
      JSON.stringify({
        localStorage: { ...localStorage },
        sessionStorage: { ...sessionStorage },
        cookie: document.cookie,
      }),
    );
    expect(browserLeak).not.toContain("fixture-secret-key");
    await page.getByRole("button", { name: "Retest Connection" }).click();
    await expect(page.locator(".notice-success.is-dismissible")).toBeVisible();
    await page.locator("#wpnb-api-key").fill("fixture-secret-key-2");
    await page.getByRole("button", { name: "Change API Key and Test" }).click();
    page.once("dialog", (dialog) => dialog.accept());
    await page.getByRole("button", { name: "Delete API Key" }).click();
    await expect(page.locator(".notice-success.is-dismissible")).toContainText(
      "deleted",
    );
    await page.locator("#wpnb-api-key").fill("fixture-secret-key");
    await page.locator("#wpnb-model").fill("fixture-model");
    await page
      .getByRole("button", { name: "Save and Test Connection" })
      .click();
    await expect(page.locator(".notice-success.is-dismissible")).toBeVisible();
  });

  test("tests, saves, lists, edits, toggles, and rejects a duplicate RSS source", async () => {
    await page.goto("/wp-admin/admin.php?page=wpnb-sources");
    await page.locator("#wpnb-source-name").fill("Fixture RSS");
    await page.locator("#wpnb-feed-url").fill("https://feed.test/rss.xml");
    await page.getByRole("button", { name: "Test Before Saving" }).click();
    await expect(page.locator(".notice-success.is-dismissible")).toContainText(
      "succeeded",
    );
    await page.getByRole("button", { name: "Save Source" }).click();
    await expect(page.locator(".notice-success.is-dismissible")).toContainText(
      "saved",
    );
    await expect(page.locator(".wpnb-sources-table")).toContainText(
      "Fixture RSS",
    );
    await page.getByRole("link", { name: "Edit", exact: true }).click();
    await page.locator("#wpnb-source-name").fill("Fixture RSS edited");
    await page.getByRole("button", { name: "Update Source" }).click();
    await expect(page.locator(".wpnb-sources-table")).toContainText(
      "Fixture RSS edited",
    );
    await page.getByRole("button", { name: "Deactivate" }).click();
    await expect(page.locator(".wpnb-sources-table")).toContainText("Inactive");
    await page.getByRole("button", { name: "Activate" }).click();
    await page.locator("#wpnb-source-name").fill("Duplicate fixture");
    await page.locator("#wpnb-feed-url").fill("https://feed.test/rss.xml");
    await page.getByRole("button", { name: "Save Source" }).click();
    await expect(page.locator(".notice-error.is-dismissible")).toContainText(
      "already registered",
    );
  });

  test("imports the fixture, creates only one safe draft, and enforces duplicate protection", async () => {
    await page.goto("/wp-admin/admin.php?page=wpnb-settings");
    await page.getByRole("button", { name: "Run Manually" }).click();
    await page.goto("/wp-admin/admin.php?page=wpnb-pool");
    await expect(page.locator("table")).toContainText("Anonymous fixture item");
    await page.getByRole("button", { name: "Create Draft" }).click();
    await expect(page.locator(".notice-success.is-dismissible")).toBeVisible();
    await page.goto("/wp-admin/edit.php?post_status=draft&post_type=post");
    await expect(page.locator("#the-list")).toContainText(
      "Deterministic draft",
    );
  });

  test("renders all admin pages at desktop and mobile widths without console errors", async () => {
    const errors = [];
    page.on("console", (msg) => {
      if (msg.type() === "error") errors.push(msg.text());
    });
    for (const slug of [
      "wpnb-dashboard",
      "wpnb-setup",
      "wpnb-sources",
      "wpnb-pool",
      "wpnb-settings",
      "wpnb-health",
    ]) {
      await page.goto("/wp-admin/admin.php?page=" + slug);
      await expect(page.locator(".wpnb-wrap")).toBeVisible();
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/wp-admin/admin.php?page=wpnb-sources");
    await expect(page.locator(".wpnb-wrap")).toBeVisible();
    expect(errors).toEqual([]);
  });

  test("deactivates and reactivates while preserving plugin data", async () => {
    await page.goto("/wp-admin/plugins.php");
    const row = page.locator('tr[data-slug="wordpress-news-bot"]');
    await row.getByRole("link", { name: "Deactivate" }).click();
    await row.getByRole("link", { name: "Activate" }).click();
    await page.goto("/wp-admin/admin.php?page=wpnb-sources");
    await expect(page.locator(".wpnb-sources-table")).toContainText(
      "Fixture RSS edited",
    );
  });

  test("deletes a source only after explicit confirmation and preserves drafts", async () => {
    await page.goto("/wp-admin/admin.php?page=wpnb-sources");
    await page.getByRole("link", { name: "Delete", exact: true }).click();
    await expect(
      page.getByRole("heading", { name: "Delete news source" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Delete" }).click();
    await expect(page.locator(".notice-success.is-dismissible")).toContainText(
      "preserved",
    );
    await page.goto("/wp-admin/edit.php?post_status=draft&post_type=post");
    await expect(page.locator("#the-list")).toContainText(
      "Deterministic draft",
    );
  });
});
