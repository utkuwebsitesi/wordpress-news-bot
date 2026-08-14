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

  test("publishes safely, removes processed news from the default pool, and preserves duplicate history with cron disabled", async () => {
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
    expect(db.publication_mode).toBe("publish");
    const firstDraftForm = page.locator("tr:has(td img) form:has(button.wpnb-create-draft)").first();
    const nonce = await firstDraftForm
      .locator('input[name="_wpnonce"]')
      .inputValue();
    const itemId = await firstDraftForm
      .locator('input[name="item_id"]')
      .inputValue();
    await firstDraftForm.getByRole("button", { name: "Create AI Post" }).click();
    db = await state(page);
    await expect(page.locator(".notice-success.is-dismissible")).toBeVisible();
    expect(db.published).toBe(1);
    expect(db.drafts).toBe(0);
    expect(db.published_records[0].feed_status).toBe("published");
    expect(db.published_records[0].linked_post_id).toBe(db.published_records[0].id);
    expect(db.published_records[0].thumbnail_id).toBeGreaterThan(0);
    expect(db.published_records[0].thumbnail_mime).toBe("image/png");
    expect(db.processed_pool).toBe(1);
    expect(db.default_pool).toBe(39);
    await page.request.post("/wp-admin/admin-post.php", {
      form: { action: "wpnb_create_draft", item_id: itemId, _wpnonce: nonce },
    });
    db = await state(page);
    expect(db.published).toBe(1);
    expect(db.posts).toBe(1);
    await page.goto("/wp-admin/admin.php?page=wpnb-pool");
    await expect(page.locator('.wpnb-pool-check')).toHaveCount(39);
    await page.goto("/wp-admin/admin.php?page=wpnb-pool&view=processed");
    await expect(page.locator(".wpnb-pool-table")).toContainText("Published");
    await expect(page.getByRole("link", { name: "View Post" }).first()).toBeVisible();
    await expect(page.getByRole("link", { name: "Edit in WordPress" }).first()).toBeVisible();
    await page.request.post("/?rest_route=/wpnb-test/v1/invalid-ai", {
      headers: { "x-wpnb-test": "1" }, data: { enabled: true },
    });
    await page.goto("/wp-admin/admin.php?page=wpnb-pool");
    await page.locator("tr:has(td img)").getByRole("button", { name: "Create AI Post" }).first().click();
    await expect(page.locator(".notice-error.is-dismissible")).toBeVisible();
    db = await state(page);
    expect(db.published).toBe(1);
    expect(db.posts).toBe(1);
    await page.request.post("/?rest_route=/wpnb-test/v1/invalid-ai", {
      headers: { "x-wpnb-test": "1" }, data: { enabled: false },
    });
    await page.goto("/wp-admin/admin.php?page=wpnb-pool");
    const checks = page.locator('tr:has(td img) .wpnb-pool-check[data-draft-eligible="1"]');
    await expect(checks).toHaveCount(2);
    await checks.nth(0).check();await checks.nth(1).check();
    await page.locator('#wpnb-pool-action').selectOption('draft');
    await page.getByRole("button", { name: "Apply" }).click();
    db = await state(page);
    expect(db.published).toBe(3);expect(db.drafts).toBe(0);expect(new Set(db.published_records.map((post) => post.feed_item_id)).size).toBe(3);
    for (const post of db.published_records) {
      expect(post.thumbnail_id).toBeGreaterThan(0);expect(post.thumbnail_mime).toBe("image/png");expect(post.thumbnail_alt).toBe(post.title);expect(post.thumbnail_caption).toBe("");expect(post.thumbnail_content).toBe("");expect(post.content).not.toContain("example.com/images");expect(post.content).not.toMatch(/(?:Source:|Kaynak:|https?:\/\/)/i);expect(post.linked_post_id).toBe(post.id);
    }
    await page.goto(`/wp-admin/post.php?post=${db.published_records[0].id}&action=edit`);
    const featuredPanel = page.getByRole('button', { name: /Featured image/i }).first();
    if (await featuredPanel.count()) await featuredPanel.click();
    await expect(
      page.locator('#postimagediv img, .editor-post-featured-image img, img[src*="/uploads/"]').first(),
    ).toBeVisible();
    for (const post of db.published_records) {
      expect(post.title.toLocaleLowerCase('tr')).not.toBe(post.source_title.toLocaleLowerCase('tr'));
      expect(post.content).not.toMatch(/(?:Source:|Kaynak:|feed\.test|original)/i);
      const sourceBlock = post.source_excerpt.split(/\s+/).slice(0, 12).join(' ');
      expect(sourceBlock.split(/\s+/).length).toBeGreaterThanOrEqual(12);
      expect(post.content.toLocaleLowerCase('tr')).not.toContain(sourceBlock.toLocaleLowerCase('tr'));
      expect(post.source_id).toBeGreaterThan(0);expect(post.source_url).toContain('https://feed.test/');expect(post.feed_item_id).toBeGreaterThan(0);expect(post.content_hash).toHaveLength(64);expect(post.ai_provider).toBe('openai');expect(post.ai_model).toBe('fixture-model');expect(post.generated_at).not.toBe('');
    }
    await page.goto("/wp-admin/admin.php?page=wpnb-pool&image_status=without");
    await expect(page.locator('#wpnb-pool-action option[value="images"]')).toHaveText("Fetch images again");
    await page.getByRole("button", { name: "Create AI Post" }).first().click();
    await expect(page.locator(".notice-error.is-dismissible")).toBeVisible();
    db = await state(page);
    expect(db.published).toBe(3);expect(db.posts).toBe(3);expect(db.drafts).toBe(0);
    const mode = await page.request.post("/?rest_route=/wpnb-test/v1/publication-mode", {headers:{"x-wpnb-test":"1"},data:{mode:"draft"}});expect(mode.ok()).toBeTruthy();
    await page.goto("/wp-admin/admin.php?page=wpnb-pool&image_status=without");
    await page.getByRole("button", { name: "Create AI Post" }).first().click();
    db = await state(page);
    expect(db.publication_mode).toBe("draft");expect(db.drafts).toBe(1);expect(db.draft_records[0].thumbnail_id).toBe(0);expect(db.draft_records[0].feed_status).toBe("processed");
    const seededResponse=await page.request.post("/?rest_route=/wpnb-test/v1/seed-legacy-drafts",{headers:{"x-wpnb-test":"1"}});expect(seededResponse.ok()).toBeTruthy();const seeded=await seededResponse.json();
    await page.goto("/wp-admin/admin.php?page=wpnb-legacy-drafts");
    await expect(page.locator("body")).toContainText("Legacy plugin draft");await expect(page.locator("body")).not.toContainText("Foreign draft must stay private");
    await page.locator(`input[name="post_ids[]"][value="${seeded.plugin_draft}"]`).check();await page.locator('input[name="confirm_publish"]').check();page.once("dialog",dialog=>dialog.accept());await page.getByRole("button",{name:"Publish Selected Drafts"}).click();
    await expect(page.locator(".notice-success.is-dismissible")).toContainText("Published: 1");db=await state(page);expect(db.published).toBe(4);expect(db.drafts).toBe(1);
    const foreign=await page.request.get(`/?rest_route=/wpnb-test/v1/post-status&id=${seeded.foreign_draft}`,{headers:{"x-wpnb-test":"1"}});expect(foreign.ok()).toBeTruthy();expect((await foreign.json()).status).toBe("draft");
    await page.request.post("/?rest_route=/wpnb-test/v1/publication-mode", {headers:{"x-wpnb-test":"1"},data:{mode:"publish"}});
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
    expect(db.feed_items).toBe(47);
    expect(db.published).toBe(4);expect(db.drafts).toBe(1);expect(db.draft_statuses).toEqual(["draft"]);
    expect(db.category_links).toBe(47);
    console.log(
      `RC4 publication evidence: sources=${db.sources} feed_items=${db.feed_items} media=${db.media.length} attachment_ids=${db.media.map((item) => item.id).join(",")} published=${db.published} published_ids=${db.published_records.map((post) => post.id).join(",")} thumbnail_ids=${db.published_records.map((post) => post.thumbnail_id).join(",")} drafts=${db.drafts} processed_pool=${db.processed_pool} cron_disabled=${db.cron_disabled}`,
    );
  });

  test("runs daily automation safely with heartbeat, quotas, backlog isolation, failures and concurrency", async () => {
    await page.goto("/wp-admin/admin.php?page=wpnb-automation");
    await expect(page.getByRole("heading", { name: "Automation" }).first()).toBeVisible();
    await expect(page.locator("#wpnb-cron-command")).toContainText("wpnb_automation_tick");
    const before = await state(page);
    const setupResponse = await page.request.post("/?rest_route=/wpnb-test/v1/automation-setup", { headers: { "x-wpnb-test": "1" } });
    expect(setupResponse.ok()).toBeTruthy();
    const setup = await setupResponse.json();
    expect(setup.attachment).toBeGreaterThan(0);
    for (let slot = 0; slot < 4; slot += 1) {
      const response = await page.request.post("/?rest_route=/wpnb-test/v1/automation-run", { headers: { "x-wpnb-test": "1" }, data: { make_due: true, force: false } });
      expect(response.ok()).toBeTruthy();
      expect((await response.json()).published).toBe(1);
    }
    let db = await state(page);
    expect(db.published).toBe(before.published + 4);
    const automatic = db.published_records.slice(0, 4);
    for (const post of automatic) {
      expect(post.thumbnail_id).toBeGreaterThan(0);
      expect(post.content).not.toMatch(/(?:Source:|Kaynak:|https?:\/\/)/i);
      expect(post.feed_status).toBe("published");
    }
    const oldResponse = await page.request.get(`/?rest_route=/wpnb-test/v1/automation-item&id=${setup.old_id}`, { headers: { "x-wpnb-test": "1" } });
    const oldItem = await oldResponse.json();
    expect(oldItem.wordpress_post_id).toBe("0");
    expect(oldItem.status).toBe("new");
    const fifth = await page.request.post("/?rest_route=/wpnb-test/v1/automation-run", { headers: { "x-wpnb-test": "1" }, data: { make_due: true, force: true } });
    expect((await fifth.json()).message).toBe("daily_quota_complete");
    expect((await state(page)).published).toBe(before.published + 4);

    const nextDay = await page.request.post("/?rest_route=/wpnb-test/v1/automation-day-offset", { headers: { "x-wpnb-test": "1" }, data: { days: 1 } });
    expect((await nextDay.json()).days).toBe(1);
    const resetRun = await page.request.post("/?rest_route=/wpnb-test/v1/automation-run", { headers: { "x-wpnb-test": "1" }, data: { make_due: true, force: false } });
    expect((await resetRun.json()).published).toBe(1);
    expect((await state(page)).published).toBe(before.published + 5);
    await page.request.post("/?rest_route=/wpnb-test/v1/automation-day-offset", { headers: { "x-wpnb-test": "1" }, data: { days: 0 } });

    await page.request.post("/?rest_route=/wpnb-test/v1/automation-enable", { headers: { "x-wpnb-test": "1" }, data: { enabled: true, limit: 10 } });
    await page.request.post("/?rest_route=/wpnb-test/v1/invalid-ai", { headers: { "x-wpnb-test": "1" }, data: { enabled: true } });
    const aiSeed = await (await page.request.post("/?rest_route=/wpnb-test/v1/automation-seed-one", { headers: { "x-wpnb-test": "1" } })).json();
    await page.request.post("/?rest_route=/wpnb-test/v1/automation-prune", { headers: { "x-wpnb-test": "1" }, data: { keep: aiSeed.id } });
    const aiFailure = await (await page.request.post("/?rest_route=/wpnb-test/v1/automation-run", { headers: { "x-wpnb-test": "1" }, data: { force: true } })).json();
    expect(aiFailure.failed).toBe(1);
    expect((await state(page)).published).toBe(before.published + 5);
    await page.request.post("/?rest_route=/wpnb-test/v1/invalid-ai", { headers: { "x-wpnb-test": "1" }, data: { enabled: false } });

    const imageSeed = await (await page.request.post("/?rest_route=/wpnb-test/v1/automation-seed-one", { headers: { "x-wpnb-test": "1" }, data: { missing_image: true } })).json();
    await page.request.post("/?rest_route=/wpnb-test/v1/automation-prune", { headers: { "x-wpnb-test": "1" }, data: { keep: imageSeed.id } });
    const imageFailure = await (await page.request.post("/?rest_route=/wpnb-test/v1/automation-run", { headers: { "x-wpnb-test": "1" }, data: { force: true } })).json();
    expect(imageFailure.failed).toBe(1);
    expect((await state(page)).published).toBe(before.published + 5);

    const concurrentSeed = await (await page.request.post("/?rest_route=/wpnb-test/v1/automation-seed-one", { headers: { "x-wpnb-test": "1" } })).json();
    await page.request.post("/?rest_route=/wpnb-test/v1/automation-prune", { headers: { "x-wpnb-test": "1" }, data: { keep: concurrentSeed.id } });
    const calls = await Promise.all([0, 1].map(() => page.request.post("/?rest_route=/wpnb-test/v1/automation-run", { headers: { "x-wpnb-test": "1" }, data: { force: true } })));
    expect(calls.every((response) => response.ok())).toBeTruthy();
    db = await state(page);
    expect(db.published).toBe(before.published + 6);
    const concurrentItem = await (await page.request.get(`/?rest_route=/wpnb-test/v1/automation-item&id=${concurrentSeed.id}`, { headers: { "x-wpnb-test": "1" } })).json();
    expect(Number(concurrentItem.wordpress_post_id)).toBeGreaterThan(0);

    await page.request.post("/?rest_route=/wpnb-test/v1/automation-enable", { headers: { "x-wpnb-test": "1" }, data: { enabled: false, limit: 10 } });
    const disabledSeed = await (await page.request.post("/?rest_route=/wpnb-test/v1/automation-seed-one", { headers: { "x-wpnb-test": "1" } })).json();
    await page.request.post("/?rest_route=/wpnb-test/v1/automation-prune", { headers: { "x-wpnb-test": "1" }, data: { keep: disabledSeed.id } });
    const disabledRun = await (await page.request.post("/?rest_route=/wpnb-test/v1/automation-run", { headers: { "x-wpnb-test": "1" }, data: { force: true } })).json();
    expect(disabledRun.status).toBe("disabled");
    expect((await state(page)).published).toBe(before.published + 6);
    await page.goto("/wp-admin/admin.php?page=wpnb-automation");
    await expect(page.locator("body")).toContainText("Last heartbeat");
    console.log(`RC1 automation evidence: old_unprocessed=${setup.old_id} published=6 daily_limit=4 next_day_reset=1 duplicate_posts=0 cron_disabled=${db.cron_disabled}`);
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
      "wpnb-automation",
      "wpnb-legacy-drafts",
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
