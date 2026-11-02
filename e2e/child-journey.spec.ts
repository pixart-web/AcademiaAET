import { expect, test } from '@playwright/test';

// Full essential journey, repeated for each child shell (3–6, 7–13, 14–18):
// assign → device entry → perform all steps (choice, text, REAL voice
// recording via Chromium's fake media device) → submit → evaluate → feedback.
// Fictional demo accounts only (DemoDataSeeder: Matilde 3–6, Rodrigo 7–13, Bia 14–18).
test.use({
    launchOptions: { args: ['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream'] },
    permissions: ['microphone', 'camera'],
});

const CHILDREN = [
    { id: 1, shell: 'early' },
    { id: 2, shell: 'middle' },
    { id: 3, shell: 'teen' },
];

for (const { id, shell } of CHILDREN) {
    test(`journey in the ${shell} shell`, async ({ browser }) => {
        const staff = await (await browser.newContext()).newPage();
        await staff.goto('/login');
        await staff.getByLabel('Email').fill('terapeuta@academia-aet.test');
        await staff.getByLabel('Palavra-passe').fill('password');
        await staff.getByRole('button', { name: 'Entrar' }).click();
        await staff.waitForURL(/dashboard/);

        await staff.goto(`/children/${id}`);
        await staff.locator('select').first().selectOption({ index: 1 });
        await staff.getByRole('button', { name: 'Atribuir' }).click();
        await staff.getByRole('button', { name: 'Gerar novo acesso' }).click();
        const [, code, pin] = (await staff.getByText(/Código: \w+ · PIN: \d+/).innerText()).match(/Código: (\w+) · PIN: (\d+)/)!;

        const child = await (
            await browser.newContext({ permissions: ['microphone', 'camera'] })
        ).newPage();
        await child.goto('/crianca/entrar');
        await child.getByPlaceholder('Código do dispositivo').fill(code);
        await child.getByPlaceholder('PIN').fill(pin);
        await child.getByRole('button', { name: 'Associar dispositivo' }).click();
        await expect(child.locator(`[data-shell="${shell}"]`).first()).toBeVisible();

        await child.locator('main button').first().click();
        await child.waitForURL(/tentativas/);

        const next = child.locator('main').getByRole('button', { name: /^(Continuar|Seguinte|Concluir|Terminar)/ });
        // step 1: single choice
        await child.getByRole('radio').first().click();
        await expect(child.getByRole('radio', { checked: true })).toHaveCount(1);
        await next.click();
        // step 2: short text (saved on blur)
        await child.getByRole('textbox').fill('Gostei de ouvir os sons.');
        await child.getByRole('textbox').blur();
        await next.click();
        // step 3: real recording
        await child.getByRole('button', { name: 'Gravar' }).click();
        await child.waitForTimeout(1200);
        await child.getByRole('button', { name: 'Parar' }).click();
        await child.getByRole('button', { name: 'Enviar' }).click();
        await expect(child.getByText('Gravação enviada')).toBeVisible();
        await next.click();
        await child.waitForURL(/\/crianca$/);

        // therapist evaluates this child's submission
        await staff.goto('/evaluations');
        await staff.getByRole('link', { name: /Avaliar|Rever/ }).first().click();
        await staff.getByRole('spinbutton').first().fill('8');
        await staff.getByRole('textbox').first().fill('Muito bem, continua assim!');
        await staff.getByRole('button', { name: 'Guardar avaliação' }).click();

        // child reads the shared feedback
        await child.goto('/crianca');
        await child.getByRole('link', { name: /mensagem|Feedback|Reconhecer/i }).first().click();
        await expect(child.getByText('Muito bem, continua assim!')).toBeVisible();
    });
}
