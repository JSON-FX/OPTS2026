import { test, expect } from '@playwright/test';
import { adminTest } from './fixtures';

test('login page loads', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByLabel('Email')).toBeVisible();
    await expect(page.getByLabel('Password')).toBeVisible();
    await expect(page.getByRole('button', { name: /log in/i })).toBeVisible();
});

adminTest('dashboard accessible when authenticated', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page).toHaveURL('/dashboard');
});

adminTest('admin can access user management', async ({ page }) => {
    await page.goto('/admin/users');
    await expect(page).toHaveURL('/admin/users');
});
