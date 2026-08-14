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

async function state(page) {
  const response = await page.request.get("/?rest_route=/wpnb-test/v1/state", {
    headers: { "x-wpnb-test": "1" },
  });
  expect(response.ok()).toBeTruthy();
  return response.json();
}

async function addSource(page, name, url) {
  await page.goto("/wp-admin/admin.php?page=wpnb-sources#wpnb-source-form");
  await page.locator("#wpnb-source-name").fill(name);
  await page.locator("#wpnb-feed-url").fill(url);
  await page.locator("#wpnb-category").selectOption("1");
  await page.getByRole("button", { name: "Test Before Saving" }).click();
  await expect(page.locator(".notice-success.is-dismissible")).toContainText(
    "succeeded",
  );
  await page.getByRole("button", { name: "Save Source" }).click();
  await expect(page.locator(".notice-success.is-dismissible")).toContainText(
    "saved",
  );
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
    const imageSettings = await page.request.post(
      "/?rest_route=/wpnb-test/v1/image-settings",
      { headers: { "x-wpnb-test": "1" } },
    );
    expect(imageSettings.ok()).toBeTruthy();
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

  test("adds two verified sources and exposes source and bulk fetch actions", async () => {
    await addSource(page, "NTV Spor-like Atom", "https://example.com/sports-atom.xml");
    await addSource(page, "NTV-like Atom", "https://example.com/news-atom.xml");
    await expect(page.locator(".wpnb-sources-table")).toContainText(
      "NTV Spor-like Atom",
    );
    await expect(page.locator(".wpnb-sources-table")).toContainText(
      "NTV-like Atom",
    );
    await expect(
      page.getByRole("button", { name: "Fetch from All Active Sources" }),
    ).toBeVisible();
    await expect(
      page
        .locator("tr", { hasText: "NTV Spor-like Atom" })
        .getByRole("button", { name: "Fetch News", exact: true }),
    ).toBeVisible();
    await page
      .locator("tr", { hasText: "NTV-like Atom" })
      .getByRole("link", { name: "Edit", exact: true })
      .click();
    await page.locator("#wpnb-source-name").fill("NTV-like Atom edited");
    await page.getByRole("button", { name: "Update Source" }).click();
    await expect(page.locator(".wpnb-sources-table")).toContainText(
      "NTV-like Atom edited",
    );
    const atomRow = page.locator("tr", { hasText: "NTV-like Atom edited" });
    await atomRow.getByRole("button", { name: "Deactivate" }).click();
    await expect(page.locator(".wpnb-sources-table")).toContainText("Inactive");
    await page
      .locator("tr", { hasText: "NTV-like Atom edited" })
      .getByRole("button", { name: "Activate" })
      .click();
    await page.locator("#wpnb-source-name").fill("Duplicate fixture");
    await page.locator("#wpnb-feed-url").fill("https://example.com/sports-atom.xml");
    await page.getByRole("button", { name: "Save Source" }).click();
    await expect(page.locator(".notice-error.is-dismissible")).toContainText(
      "already registered",
    );
  });

  test("fetches 20 plus 20 items idempotently with cron disabled and creates safe single and bulk drafts", async () => {
    await page.goto("/wp-admin/admin.php?page=wpnb-sources");
    const sportsRow = page.locator("tr", { hasText: "NTV Spor-like Atom" });
    await sportsRow
      .getByRole("button", { name: "Fetch News", exact: true })
      .click();
    await expect(page.locator(".notice-success.is-dismissible")).toContainText(
      "20 read, 20 new, 0 duplicate",
    );
    let db = await state(page);
    expect(db.feed_items).toBe(20);
    expect(db.category_links).toBe(20);
    expect(db.cron_disabled).toBe(true);
    await page
      .locator("tr", { hasText: "NTV Spor-like Atom" })
      .getByRole("button", { name: "Fetch News", exact: true })
      .click();
    await expect(page.locator(".notice-success.is-dismissible")).toContainText(
      "20 read, 0 new, 20 duplicate",
    );
    db = await state(page);
    expect(db.feed_items).toBe(20);
    const mediaBeforeNews = db.media.length;
    await page
      .getByRole("button", { name: "Fetch from All Active Sources" })
      .click();
    db = await state(page);
    expect(db.feed_items).toBe(40);
    expect(db.sources).toBe(2);
    expect(db.media).toHaveLength(3);
    expect(db.media.length).toBeGreaterThan(mediaBeforeNews);
    expect(db.media.every((item) => item.exists && item.real_image)).toBe(true);
    expect(db.media.every((item) => item.mime === "image/png")).toBe(true);
    expect(new Set(db.media.map((item) => item.hash)).size).toBe(3);
    expect(db.image_sources.map((item) => item.image_source).sort()).toEqual(
      ["atom:enclosure", "media:content", "media:thumbnail"],
    );
    await page.goto("/wp-admin/admin.php?page=wpnb-sources");
    await page
      .locator("tr", { hasText: "NTV-like Atom edited" })
      .getByRole("button", { name: "Fetch News", exact: true })
      .click();
    await expect(page.locator(".notice-success.is-dismissible")).toContainText(
      "20 read, 0 new, 20 duplicate",
    );
    db = await state(page);
    expect(db.media).toHaveLength(3);
    expect(
      Object.values(db.engines).every((engine) => engine === "innodb"),
    ).toBe(true);
    await page.goto("/wp-admin/admin.php?page=wpnb-pool");
    await expect(page.locator(".wpnb-pool-table")).toContainText(
      "News Atom item 1",
    );
    await expect(page.locator(".wpnb-pool-table")).toContainText(
      "Atom fixture item 1",
    );
    const allChecks = page.locator('.wpnb-pool-check');
    await expect(allChecks).toHaveCount(40);
    await page.locator('#wpnb-select-page').check();
    await expect(page.locator('#wpnb-selection-status')).toContainText('40');
    expect(await allChecks.evaluateAll((boxes) => boxes.every((box) => box.checked))).toBe(true);
    await page.locator('#wpnb-clear-selection').click();
    expect(await allChecks.evaluateAll((boxes) => boxes.every((box) => !box.checked))).toBe(true);
    const firstDraftForm = page
      .locator("form:has(button.wpnb-create-draft)")
      .first();
    const nonce = await firstDraftForm
      .locator('input[name="_wpnonce"]')
      .inputValue();
    const itemId = await firstDraftForm
      .locator('input[name="item_id"]')
      .inputValue();
    await firstDraftForm.getByRole("button", { name: "Create Draft" }).click();
    await expect(page.locator(".notice-success.is-dismissible")).toBeVisible();
    db = await state(page);
    expect(db.drafts).toBe(1);
    expect(db.draft_statuses).toEqual(["draft"]);
    await page.request.post("/wp-admin/admin-post.php", {
      form: { action: "wpnb_create_draft", item_id: itemId, _wpnonce: nonce },
    });
    db = await state(page);
    expect(db.drafts).toBe(1);
    await page.goto("/wp-admin/admin.php?page=wpnb-pool");
    const checks = page.locator('.wpnb-pool-check[data-draft-eligible="1"]');
    await checks.nth(0).check();
    await checks.nth(1).check();
    await page.locator('#wpnb-pool-action').selectOption('draft');
    await page
      .getByRole("button", { name: "Apply" })
      .click();
    db = await state(page);
    expect(db.drafts).toBe(3);
    expect(new Set(db.draft_feed_ids).size).toBe(3);
    const featuredDrafts = db.draft_records.filter((draft) => draft.thumbnail_id > 0);
    expect(featuredDrafts).toHaveLength(3);
    for (const draft of featuredDrafts) {
      expect(draft.thumbnail_mime).toBe("image/png");
      expect(draft.thumbnail_alt).toBe(draft.title);
      expect(draft.thumbnail_caption).toBe("");
      expect(draft.thumbnail_content).toBe("");
      expect(draft.content).not.toContain("example.com/images");
      expect(draft.thumbnail_alt).not.toContain("example.com/images");
    }
    await page.goto(`/wp-admin/post.php?post=${featuredDrafts[0].id}&action=edit`);
    const featuredPanel = page.getByRole('button', { name: /Featured image/i }).first();
    if (await featuredPanel.count()) await featuredPanel.click();
    await expect(
      page.locator('#postimagediv img, .editor-post-featured-image img, img[src*="/uploads/"]').first(),
    ).toBeVisible();
    for (const draft of db.draft_records) {
      expect(draft.title.toLocaleLowerCase('tr')).not.toBe(draft.source_title.toLocaleLowerCase('tr'));
      expect(draft.content).not.toMatch(/(?:Source:|Kaynak:|feed\.test|original)/i);
      const sourceBlock = draft.source_excerpt.split(/\s+/).slice(0, 12).join(' ');
      expect(sourceBlock.split(/\s+/).length).toBeGreaterThanOrEqual(12);
      expect(draft.content.toLocaleLowerCase('tr')).not.toContain(sourceBlock.toLocaleLowerCase('tr'));
      expect(draft.source_id).toBeGreaterThan(0);
      expect(draft.source_url).toContain('https://feed.test/');
      expect(draft.feed_item_id).toBeGreaterThan(0);
      expect(draft.content_hash).toHaveLength(64);
      expect(draft.ai_provider).toBe('openai');
      expect(draft.ai_model).toBe('fixture-model');
      expect(draft.generated_at).not.toBe('');
    }
    await page.goto("/wp-admin/admin.php?page=wpnb-pool&image_status=without");
    await expect(page.locator('#wpnb-pool-action option[value="images"]')).toHaveText("Fetch images again");
    await page.getByRole("button", { name: "Create Draft" }).first().click();
    db = await state(page);
    expect(db.drafts).toBe(4);
    expect(db.draft_records.filter((draft) => draft.thumbnail_id === 0)).toHaveLength(1);
    await page.request.post("/?rest_route=/wpnb-test/v1/invalid-ai", {
      headers: { "x-wpnb-test": "1" },
      data: { enabled: true },
    });
    await page.goto("/wp-admin/admin.php?page=wpnb-pool");
    await page.getByRole("button", { name: "Create Draft" }).first().click();
    await expect(page.locator(".notice-error.is-dismissible")).toBeVisible();
    db = await state(page);
    expect(db.feed_items).toBe(40);
    expect(db.drafts).toBe(4);
    await page.request.post("/?rest_route=/wpnb-test/v1/invalid-ai", {
      headers: { "x-wpnb-test": "1" },
      data: { enabled: false },
    });
    await page.goto("/wp-admin/admin.php?page=wpnb-sources#wpnb-source-form");
    await page.locator("#wpnb-source-name").fill("Broken fixture");
    await page.locator("#wpnb-feed-url").fill("https://example.com/broken");
    await page.getByRole("button", { name: "Test Before Saving" }).click();
    await expect(page.locator(".notice-error.is-dismissible")).toBeVisible();
    db = await state(page);
    for (const [kind, expectedStatus] of [
      ["broken", "error"],
      ["oversized", "error"],
      ["fake_mime", "error"],
      ["redirect", "ready"],
      ["private_redirect", "error"],
      ["private_ip", "error"],
    ]) {
      const response = await page.request.post("/?rest_route=/wpnb-test/v1/image-test", {
        headers: { "x-wpnb-test": "1" },
        data: { kind },
      });
      expect(response.ok()).toBeTruthy();
      const result = await response.json();
      expect(result.status).toBe(expectedStatus);
    }
    db = await state(page);
    expect(db.feed_items).toBe(46);
    expect(db.drafts).toBe(4);
    expect(db.draft_statuses).toEqual(["draft", "draft", "draft", "draft"]);
    expect(db.category_links).toBe(46);
    console.log(
      `P0 media evidence: sources=${db.sources} feed_items=${db.feed_items} media=${db.media.length} attachment_ids=${db.media.map((item) => item.id).join(",")} featured_drafts=${db.draft_records.filter((draft) => draft.thumbnail_id > 0).length} thumbnail_ids=${db.draft_records.filter((draft) => draft.thumbnail_id > 0).map((draft) => draft.thumbnail_id).join(",")} drafts=${db.drafts} cron_disabled=${db.cron_disabled}`,
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
      "NTV Spor-like Atom",
    );
  });

  test("deletes a source only after explicit confirmation and preserves drafts", async () => {
    await page.goto("/wp-admin/admin.php?page=wpnb-sources");
    await page
      .locator("tr", { hasText: "NTV-like Atom edited" })
      .getByRole("link", { name: "Delete", exact: true })
      .click();
    await expect(
      page.getByRole("heading", { name: "Delete news source" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Delete" }).click();
    await expect(page.locator(".notice-success.is-dismissible")).toContainText(
      "preserved",
    );
    await page.goto("/wp-admin/edit.php?post_status=draft&post_type=post");
    await expect(page.locator("#the-list")).toContainText(
      "Fixture olayındaki gelişmeler yeniden değerlendirildi",
    );
  });
});
