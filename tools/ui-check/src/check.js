const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.UI_BASE_URL || 'http://127.0.0.1:8000';
const USER = process.env.UI_USER || 'admin';
const PASS = process.env.UI_PASS || 'admin';
const OUT_DIR = path.join(__dirname, '..', 'artifacts');

const PAGES = {
  dashboard: '/public/index.php',
  olt_mac_table: '/public/olt_mac_table.php',
};

function ensureOutDir() {
  fs.mkdirSync(OUT_DIR, { recursive: true });
}

function parseTargets() {
  const idx = process.argv.indexOf('--page');
  if (idx !== -1 && process.argv[idx + 1]) {
    const target = process.argv[idx + 1];
    if (!PAGES[target]) {
      throw new Error(`Unknown page "${target}". Valid: ${Object.keys(PAGES).join(', ')}`);
    }
    return [target];
  }
  return Object.keys(PAGES);
}

async function loginAndGetState(browser) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await ctx.newPage();
  await page.goto(`${BASE}/public/login.php`, { waitUntil: 'networkidle' });
  await page.fill('input[name="username"]', USER);
  await page.fill('input[name="password"]', PASS);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }),
    page.click('button[type="submit"]'),
  ]);
  const state = await ctx.storageState();
  await ctx.close();
  return state;
}

async function openPage(context, relPath) {
  const page = await context.newPage();
  await page.goto(`${BASE}${relPath}`, { waitUntil: 'networkidle' });
  await page.waitForSelector('.content-area', { timeout: 8000 }).catch(() => {});
  return page;
}

async function screenshotPage(browser, storageState, name, relPath) {
  // Desktop
  const desktopCtx = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    storageState,
  });
  const desktopPage = await openPage(desktopCtx, relPath);
  await desktopPage.waitForTimeout(300);
  await desktopPage.screenshot({ path: path.join(OUT_DIR, `${name}-desktop.png`), fullPage: true });
  await desktopCtx.close();

  // Mobile
  const mobileCtx = await browser.newContext({
    viewport: { width: 390, height: 844 },
    isMobile: true,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.5 Mobile/15E148 Safari/604.1',
    deviceScaleFactor: 3,
    storageState,
  });
  const mobilePage = await openPage(mobileCtx, relPath);
  await mobilePage.waitForTimeout(300);
  await mobilePage.screenshot({ path: path.join(OUT_DIR, `${name}-mobile.png`), fullPage: true });

  // Mobile sidebar open (if toggle exists)
  const toggle = await mobilePage.$('[data-bs-target="#sidebarOffcanvas"]');
  if (toggle) {
    await toggle.click().catch(() => {});
    await mobilePage.waitForSelector('#sidebarOffcanvas.show', { timeout: 4000 }).catch(() => {});
    await mobilePage.waitForTimeout(300);
    await mobilePage.screenshot({ path: path.join(OUT_DIR, `${name}-mobile-sidebar.png`), fullPage: true });
  }
  await mobileCtx.close();
}

async function main() {
  ensureOutDir();
  const targets = parseTargets();
  const browser = await chromium.launch({ headless: true });
  const state = await loginAndGetState(browser);

  for (const key of targets) {
    const relPath = PAGES[key];
    await screenshotPage(browser, state, key, relPath);
    console.log(`Captured ${key}`);
  }

  await browser.close();
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
