import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './e2e',
    timeout: 60_000,
    workers: 1,
    use: { baseURL: 'http://127.0.0.1:8123' },
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8123',
        url: 'http://127.0.0.1:8123/login',
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
    },
});
