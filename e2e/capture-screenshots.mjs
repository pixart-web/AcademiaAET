// Captures REAL screenshots of the running app (not AI renders) for docs/.
// Usage: php artisan serve --port=8123 & node e2e/capture-screenshots.mjs
import { chromium } from '@playwright/test';

const BASE = 'http://127.0.0.1:8123';
const OUT = 'docs/screenshots';
const sizes = { desktop: [1440, 900], tablet: [768, 1024], mobile: [390, 844], small: [360, 740] };

const browser = await chromium.launch();

// let the short entrance animation settle before every capture
async function shot(page, path) {
    await page.waitForTimeout(500);
    await page.screenshot({ path, fullPage: true });
}

// One-time activation codes: every child capture gets a fresh device, issued
// through the real staff UI exactly like a therapist would.
let staffPage;
async function issueDevice(childId) {
    if (!staffPage) {
        staffPage = await (await browser.newContext()).newPage();
        await staffPage.goto(`${BASE}/login`);
        await staffPage.getByLabel('Email').fill('terapeuta@academia-aet.test');
        await staffPage.getByLabel('Palavra-passe').fill('password');
        await staffPage.getByRole('button', { name: 'Entrar' }).click();
        await staffPage.waitForURL(/dashboard/);
    }
    await staffPage.goto(`${BASE}/children/${childId}`);
    await staffPage.getByRole('button', { name: 'Gerar novo acesso' }).click();
    const t = await staffPage.getByText(/Código: \w+ · PIN: \d+/).innerText();
    const [, code, pin] = t.match(/Código: (\w+) · PIN: (\d+)/);
    return { code, pin };
}

async function childSession(childId, ctxOpts, attempt = 1) {
    // the activation route is throttled to 10/min per IP — pace the captures
    await new Promise((r) => setTimeout(r, 6500));
    const { code, pin } = await issueDevice(childId);
    const ctx = await browser.newContext(ctxOpts);
    const page = await ctx.newPage();
    await page.goto(`${BASE}/crianca/entrar`);
    await page.getByPlaceholder('Código do dispositivo').fill(code);
    await page.getByPlaceholder('PIN').fill(pin);
    await page.getByRole('button', { name: 'Associar dispositivo' }).click();
    try { await page.waitForURL(/\/crianca$/, { timeout: 8000 }); } catch { console.log('ACTIVATION FAILED', page.url(), JSON.stringify((await page.locator('body').innerText()).slice(0, 400)), code, pin); if (attempt >= 3) throw new Error('activation'); await ctx.close(); return childSession(childId, ctxOpts, attempt + 1); }
    return page;
}

if (!process.env.ONLY) {
// Professional portal
for (const [name, [w, h]] of Object.entries(sizes)) {
    const ctx = await browser.newContext({ viewport: { width: w, height: h } });
    const page = await ctx.newPage();
    await page.goto(`${BASE}/login`);
    if (name === 'desktop') await shot(page, `${OUT}/pro-login-${name}.png`);
    await page.getByLabel('Email').fill('terapeuta@academia-aet.test');
    await page.getByLabel('Palavra-passe').fill('password');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await page.waitForURL(/dashboard/);
    await shot(page, `${OUT}/pro-dashboard-${name}.png`);
    if (name === 'desktop' || name === 'mobile') {
        await page.goto(`${BASE}/children`);
        await shot(page, `${OUT}/pro-children-${name}.png`);
        await page.goto(`${BASE}/activities/1/edit`);
        await shot(page, `${OUT}/pro-editor-${name}.png`);
    }
    await ctx.close();
}

}
// Child shells: child 1 = 3-6, child 2 = 7-13, child 3 = 14-18 (demo seed)
const shells = Object.fromEntries(Object.entries({ early: 1, middle: 2, teen: 3 }).filter(([k]) => !process.env.ONLY || k === process.env.ONLY));
for (const [shell, childId] of Object.entries(shells)) {
    for (const [name, [w, h]] of Object.entries(sizes)) {
        const page = await childSession(childId, { viewport: { width: w, height: h } });
        await shot(page, `${OUT}/${shell}-home-${name}.png`);
        if (name === 'desktop' || name === 'mobile') {
            // open the first activity -> first step
            const open = page.locator('main button').first();
            await open.click();
            await page.waitForURL(/tentativas/);
            await shot(page, `${OUT}/${shell}-step-${name}.png`);
        }
        await page.context().close();
    }
}
await browser.close();
console.log('done');
