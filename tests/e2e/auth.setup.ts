import { test as setup, expect } from '@playwright/test';
import path from 'path';

const authFile = (role: string) =>
    path.join(__dirname, `.auth/${role}.json`);

/**
 * Authenticate as the admin user and save storage state.
 */
setup('authenticate as admin', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('admin@example.com');
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: /log in/i }).click();
    await page.waitForURL('/dashboard');
    await expect(page).toHaveURL('/dashboard');
    await page.context().storageState({ path: authFile('admin') });
});

/**
 * Authenticate as the endorser user and save storage state.
 */
setup('authenticate as endorser', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('endorser@example.com');
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: /log in/i }).click();
    await page.waitForURL('/dashboard');
    await expect(page).toHaveURL('/dashboard');
    await page.context().storageState({ path: authFile('endorser') });
});

/**
 * Authenticate as the viewer user and save storage state.
 */
setup('authenticate as viewer', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('viewer@example.com');
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: /log in/i }).click();
    await page.waitForURL('/dashboard');
    await expect(page).toHaveURL('/dashboard');
    await page.context().storageState({ path: authFile('viewer') });
});
