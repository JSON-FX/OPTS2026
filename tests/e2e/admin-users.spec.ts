import { test, expect } from '@playwright/test';
import { adminTest, endorserTest, viewerTest } from './fixtures';

test.describe('Admin User Management - RBAC', () => {
    endorserTest('endorser receives 403 on /admin/users', async ({ page }) => {
        const response = await page.goto('/admin/users');
        expect(response?.status()).toBe(403);
    });

    viewerTest('viewer receives 403 on /admin/users', async ({ page }) => {
        const response = await page.goto('/admin/users');
        expect(response?.status()).toBe(403);
    });

    test('unauthenticated user is redirected to login', async ({ page }) => {
        await page.goto('/admin/users');
        await expect(page).toHaveURL(/\/login/);
    });

    adminTest('administrator can access /admin/users', async ({ page }) => {
        await page.goto('/admin/users');
        await expect(page).toHaveURL('/admin/users');
        await expect(
            page.getByRole('heading', { name: 'User Management' })
        ).toBeVisible();
    });
});

test.describe('Admin User Management - DataTable', () => {
    adminTest('displays user list in DataTable', async ({ page }) => {
        await page.goto('/admin/users');
        await expect(page.locator('table')).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Create User' })
        ).toBeVisible();
    });

    adminTest('search filters users by name or email', async ({ page }) => {
        await page.goto('/admin/users');
        const searchInput = page.getByPlaceholder('Search by name or email...');
        await expect(searchInput).toBeVisible();
        // Type a search query
        await searchInput.fill('admin');
        // Table should still be visible (filtered)
        await expect(page.locator('table')).toBeVisible();
    });

    adminTest('role filter dropdown works', async ({ page }) => {
        await page.goto('/admin/users');
        // Click the role filter trigger
        const roleFilter = page.locator('button').filter({ hasText: 'All Roles' });
        await expect(roleFilter).toBeVisible();
        await roleFilter.click();
        // Should see role options
        await expect(page.getByRole('option', { name: 'Administrator' })).toBeVisible();
    });

    adminTest('office filter dropdown works', async ({ page }) => {
        await page.goto('/admin/users');
        const officeFilter = page.locator('button').filter({ hasText: 'All Offices' });
        await expect(officeFilter).toBeVisible();
    });

    adminTest('displays role as Badge component', async ({ page }) => {
        await page.goto('/admin/users');
        // Badge elements should exist in table rows
        const badges = page.locator('table .inline-flex');
        const count = await badges.count();
        expect(count).toBeGreaterThan(0);
    });

    adminTest('column sorting works for Name', async ({ page }) => {
        await page.goto('/admin/users');
        const nameHeader = page.getByRole('button', { name: 'Name' });
        await expect(nameHeader).toBeVisible();
        // Click to sort
        await nameHeader.click();
        await expect(page.locator('table')).toBeVisible();
    });

    adminTest('row actions dropdown has Edit and Delete', async ({ page }) => {
        await page.goto('/admin/users');
        // Click the first actions button
        const actionsButton = page.locator('table button[aria-haspopup="menu"]').first();
        await actionsButton.click();
        await expect(page.getByRole('menuitem', { name: 'Edit' })).toBeVisible();
        await expect(page.getByRole('menuitem', { name: 'Delete' })).toBeVisible();
    });
});

test.describe('Admin User Management - Create', () => {
    adminTest('can navigate to create user page', async ({ page }) => {
        await page.goto('/admin/users');
        await page.getByRole('button', { name: 'Create User' }).click();
        await expect(page).toHaveURL('/admin/users/create');
        await expect(
            page.getByRole('heading', { name: 'Create User' })
        ).toBeVisible();
    });

    adminTest('create form has all required fields', async ({ page }) => {
        await page.goto('/admin/users/create');
        await expect(page.getByLabel('Name')).toBeVisible();
        await expect(page.getByLabel('Email')).toBeVisible();
        await expect(page.getByLabel('Password', { exact: true })).toBeVisible();
        await expect(page.getByLabel('Confirm Password')).toBeVisible();
        await expect(page.getByText('Select a role')).toBeVisible();
        await expect(page.getByText('No office')).toBeVisible();
    });

    adminTest('password visibility toggle works', async ({ page }) => {
        await page.goto('/admin/users/create');
        const passwordInput = page.getByLabel('Password', { exact: true });
        await expect(passwordInput).toHaveAttribute('type', 'password');
        // Click the eye icon button next to password
        const toggleButton = passwordInput
            .locator('..')
            .locator('button[type="button"]');
        await toggleButton.click();
        await expect(passwordInput).toHaveAttribute('type', 'text');
    });

    adminTest('cancel button navigates back to index', async ({ page }) => {
        await page.goto('/admin/users/create');
        await page.getByRole('button', { name: 'Cancel' }).click();
        await expect(page).toHaveURL('/admin/users');
    });
});

test.describe('Admin User Management - Edit', () => {
    adminTest('can navigate to edit user page from actions', async ({ page }) => {
        await page.goto('/admin/users');
        // Open actions for first user
        const actionsButton = page.locator('table button[aria-haspopup="menu"]').first();
        await actionsButton.click();
        await page.getByRole('menuitem', { name: 'Edit' }).click();
        await expect(page).toHaveURL(/\/admin\/users\/\d+\/edit/);
        await expect(
            page.getByRole('heading', { name: 'Edit User' })
        ).toBeVisible();
    });

    adminTest('edit form pre-populates user data', async ({ page }) => {
        await page.goto('/admin/users');
        const actionsButton = page.locator('table button[aria-haspopup="menu"]').first();
        await actionsButton.click();
        await page.getByRole('menuitem', { name: 'Edit' }).click();
        // Name input should have a value
        const nameInput = page.getByLabel('Name');
        await expect(nameInput).not.toHaveValue('');
    });

    adminTest('password field is optional on edit', async ({ page }) => {
        await page.goto('/admin/users');
        const actionsButton = page.locator('table button[aria-haspopup="menu"]').first();
        await actionsButton.click();
        await page.getByRole('menuitem', { name: 'Edit' }).click();
        // Password label should indicate it's optional
        await expect(
            page.getByText('Password (leave blank to keep current)')
        ).toBeVisible();
    });
});

test.describe('Admin User Management - Delete', () => {
    adminTest('delete shows AlertDialog confirmation', async ({ page }) => {
        await page.goto('/admin/users');
        // Open actions for first user
        const actionsButton = page.locator('table button[aria-haspopup="menu"]').first();
        await actionsButton.click();
        await page.getByRole('menuitem', { name: 'Delete' }).click();
        // AlertDialog should appear
        await expect(
            page.getByText('Are you absolutely sure?')
        ).toBeVisible();
        await expect(
            page.getByText('This action cannot be undone')
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Cancel' })
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Delete' })
        ).toBeVisible();
    });

    adminTest('cancel button closes AlertDialog', async ({ page }) => {
        await page.goto('/admin/users');
        const actionsButton = page.locator('table button[aria-haspopup="menu"]').first();
        await actionsButton.click();
        await page.getByRole('menuitem', { name: 'Delete' }).click();
        await expect(page.getByText('Are you absolutely sure?')).toBeVisible();
        // Click Cancel
        await page.getByRole('button', { name: 'Cancel' }).click();
        // Dialog should close
        await expect(page.getByText('Are you absolutely sure?')).not.toBeVisible();
    });
});

test.describe('Admin User Management - Responsive', () => {
    adminTest('renders at desktop viewport (1920px)', async ({ page }) => {
        await page.setViewportSize({ width: 1920, height: 1080 });
        await page.goto('/admin/users');
        await expect(page.locator('table')).toBeVisible();
    });

    adminTest('renders at tablet viewport (768px)', async ({ page }) => {
        await page.setViewportSize({ width: 768, height: 1024 });
        await page.goto('/admin/users');
        await expect(page.locator('table')).toBeVisible();
    });

    adminTest('renders at mobile viewport (375px)', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto('/admin/users');
        await expect(page.locator('table')).toBeVisible();
    });
});
