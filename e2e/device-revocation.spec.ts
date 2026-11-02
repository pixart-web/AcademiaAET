import { expect, test } from '@playwright/test';

// Essential journey (AET-RC01 findings 2/3): staff issues a device code, the
// child activates it, the code cannot be reused, and revoking the device ends
// the child's session on its very next request. Uses only the fictional demo
// accounts created by DemoDataSeeder (local/testing environments only).
test('staff issues a device, child activates it once, revocation cuts access', async ({ page, browser }) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('admin@academia-aet.test');
    await page.getByLabel('Palavra-passe').fill('password');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/children');
    await page.getByRole('link', { name: 'Ver' }).first().click();
    await page.getByRole('button', { name: 'Gerar novo acesso' }).click();
    const text = await page.getByText(/Código: \w+ · PIN: \d+/).innerText();
    const [, code, pin] = text.match(/Código: (\w+) · PIN: (\d+)/)!;

    const child = await (await browser.newContext()).newPage();
    await child.goto('/crianca/entrar');
    await child.getByPlaceholder('Código do dispositivo').fill(code);
    await child.getByPlaceholder('PIN').fill(pin);
    await child.getByRole('button', { name: 'Associar dispositivo' }).click();
    await expect(child).toHaveURL(/\/crianca$/);

    const replay = await (await browser.newContext()).newPage();
    await replay.goto('/crianca/entrar');
    await replay.getByPlaceholder('Código do dispositivo').fill(code);
    await replay.getByPlaceholder('PIN').fill(pin);
    await replay.getByRole('button', { name: 'Associar dispositivo' }).click();
    await expect(replay).not.toHaveURL(/\/crianca$/);

    await page.getByRole('button', { name: 'Revogar' }).first().click();
    await child.goto('/crianca');
    await expect(child).toHaveURL(/crianca\/entrar/);
});
