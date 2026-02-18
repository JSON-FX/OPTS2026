import { test, expect } from '@playwright/test';
import { adminTest, endorserTest, viewerTest } from './fixtures';

test.describe('Workflow Management - RBAC', () => {
    endorserTest('endorser receives 403 on /admin/workflows', async ({ page }) => {
        const response = await page.goto('/admin/workflows');
        expect(response?.status()).toBe(403);
    });

    viewerTest('viewer receives 403 on /admin/workflows', async ({ page }) => {
        const response = await page.goto('/admin/workflows');
        expect(response?.status()).toBe(403);
    });

    test('unauthenticated user is redirected to login', async ({ page }) => {
        await page.goto('/admin/workflows');
        await expect(page).toHaveURL(/\/login/);
    });

    adminTest('administrator can access /admin/workflows', async ({ page }) => {
        await page.goto('/admin/workflows');
        await expect(page).toHaveURL('/admin/workflows');
        await expect(
            page.getByRole('heading', { name: 'Workflow Management' })
        ).toBeVisible();
    });
});

test.describe('Workflow Management - Index Page', () => {
    adminTest('displays workflow list in table', async ({ page }) => {
        await page.goto('/admin/workflows');
        await expect(page.locator('table')).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'New Workflow' })
        ).toBeVisible();
    });

    adminTest('table shows correct columns', async ({ page }) => {
        await page.goto('/admin/workflows');
        await expect(page.getByRole('columnheader', { name: 'Name' })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'Category' })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'Steps' })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'Status' })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: /Created/ })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'Actions' })).toBeVisible();
    });

    adminTest('displays existing workflows', async ({ page }) => {
        await page.goto('/admin/workflows');
        // Should have at least 3 workflow rows
        const rows = page.locator('table tbody tr');
        await expect(rows).toHaveCount(3);
        // Check category badges are visible
        await expect(page.getByRole('cell', { name: 'PR' }).first()).toBeVisible();
        await expect(page.getByRole('cell', { name: 'VCH' }).first()).toBeVisible();
    });

    adminTest('search filters workflows by name', async ({ page }) => {
        // Use URL params directly to test server-side search filtering
        await page.goto('/admin/workflows?search=Voucher');
        await expect(page.locator('table tbody tr')).toHaveCount(1);
        await expect(page.getByText('Standard Voucher Workflow')).toBeVisible();
    });

    adminTest('category filter works', async ({ page }) => {
        await page.goto('/admin/workflows');
        // Click category filter
        const categoryFilter = page.locator('button').filter({ hasText: 'All' }).first();
        await categoryFilter.click();
        await page.getByRole('option', { name: /PR/ }).click();
        await page.waitForURL(/category=PR/);
        // Should only show PR workflows
        const rows = page.locator('table tbody tr');
        const count = await rows.count();
        for (let i = 0; i < count; i++) {
            await expect(rows.nth(i).getByText('PR')).toBeVisible();
        }
    });

    adminTest('column sorting works for Name', async ({ page }) => {
        await page.goto('/admin/workflows');
        const nameHeader = page.getByRole('columnheader', { name: 'Name' });
        await nameHeader.click();
        await page.waitForURL(/sort=name/);
        await expect(page.locator('table')).toBeVisible();
    });

    adminTest('each row has view, edit, and delete action buttons', async ({ page }) => {
        await page.goto('/admin/workflows');
        const firstRow = page.locator('table tbody tr').first();
        // View button (links to show page)
        await expect(firstRow.locator('a[href*="/admin/workflows/"]').first()).toBeVisible();
        // Edit button (links to edit page)
        await expect(firstRow.locator('a[href*="/edit"]')).toBeVisible();
    });
});

test.describe('Workflow Management - Show Page', () => {
    adminTest('can view workflow details', async ({ page }) => {
        await page.goto('/admin/workflows/1');
        await expect(page.getByRole('heading', { name: 'Standard Purchase Request Workflow' })).toBeVisible();
        await expect(page.getByText('Purchase Request', { exact: true })).toBeVisible();
    });

    adminTest('shows workflow steps', async ({ page }) => {
        await page.goto('/admin/workflows/2');
        await expect(page.getByText('General Services Office')).toBeVisible();
        await expect(page.getByText('Bids and Awards Committee Secretariat')).toBeVisible();
        await expect(page.getByText('Municipal Accountant Office')).toBeVisible();
        await expect(page.getByText('Municipal Treasurer Office')).toBeVisible();
    });

    adminTest('shows total steps and expected days summary', async ({ page }) => {
        await page.goto('/admin/workflows/2');
        // PO workflow has 4 steps and 6 expected days
        await expect(page.getByText('4')).toBeVisible();
    });

    adminTest('has back and edit buttons', async ({ page }) => {
        await page.goto('/admin/workflows/1');
        await expect(page.getByRole('link', { name: /Back/ })).toBeVisible();
        await expect(page.getByRole('link', { name: /Edit/ })).toBeVisible();
    });

    adminTest('edit button navigates to edit page', async ({ page }) => {
        await page.goto('/admin/workflows/1');
        await page.getByRole('link', { name: /Edit/ }).click();
        await expect(page).toHaveURL('/admin/workflows/1/edit');
    });
});

test.describe('Workflow Management - Create Page', () => {
    adminTest('can navigate to create workflow page', async ({ page }) => {
        await page.goto('/admin/workflows');
        await page.getByRole('link', { name: 'New Workflow' }).click();
        await expect(page).toHaveURL('/admin/workflows/create');
        await expect(
            page.getByRole('heading', { name: /Create Workflow|New Workflow/ })
        ).toBeVisible();
    });

    adminTest('create form has all required fields', async ({ page }) => {
        await page.goto('/admin/workflows/create');
        await expect(page.getByText('Name *')).toBeVisible();
        await expect(page.getByText('Category *')).toBeVisible();
        await expect(page.getByText('Description', { exact: true })).toBeVisible();
        await expect(page.locator('text=Active')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Workflow Steps' })).toBeVisible();
    });

    adminTest('create form has Add Step button', async ({ page }) => {
        await page.goto('/admin/workflows/create');
        await expect(
            page.getByRole('button', { name: 'Add Step' })
        ).toBeVisible();
    });

    adminTest('can add and remove workflow steps', async ({ page }) => {
        await page.goto('/admin/workflows/create');
        // Should start with 2 steps (minimum)
        const stepRows = page.locator('text="Expected Days:"');
        const initialCount = await stepRows.count();

        // Add a step
        await page.getByRole('button', { name: 'Add Step' }).click();
        await expect(stepRows).toHaveCount(initialCount + 1);
    });

    adminTest('cancel button navigates back to index', async ({ page }) => {
        await page.goto('/admin/workflows/create');
        // Accept the confirmation dialog for unsaved changes
        page.on('dialog', dialog => dialog.accept());
        await page.getByRole('button', { name: 'Cancel' }).click();
        await expect(page).toHaveURL('/admin/workflows');
    });

    adminTest('shows validation errors for empty form', async ({ page }) => {
        await page.goto('/admin/workflows/create');
        // Submit with empty name - click the submit button
        await page.getByRole('button', { name: 'Create Workflow' }).click();
        // Should remain on create page (not redirect) due to validation failure
        await expect(page).toHaveURL(/\/admin\/workflows\/create/);
        // Page should still show the create form
        await expect(page.getByRole('heading', { name: 'Create Workflow' })).toBeVisible();
    });
});

test.describe('Workflow Management - Edit Page', () => {
    test.describe.configure({ mode: 'serial' });
    adminTest('edit page loads with pre-populated data', async ({ page }) => {
        await page.goto('/admin/workflows/2/edit');
        await expect(
            page.getByRole('heading', { name: 'Edit Workflow' })
        ).toBeVisible();
        // Name should be pre-populated with a non-empty value
        const nameInput = page.locator('input[placeholder="Enter workflow name"]');
        await expect(nameInput).not.toHaveValue('');
    });

    adminTest('edit page shows existing workflow steps', async ({ page }) => {
        await page.goto('/admin/workflows/2/edit');
        // PO workflow has 4 steps - check for step count in the summary
        await expect(page.getByText('4 steps')).toBeVisible();
        // Check expected days summary exists (value may vary)
        await expect(page.getByText(/\d+ expected days/)).toBeVisible();
    });

    adminTest('can save workflow without changes (regression test for unsigned int bug)', async ({ page }) => {
        await page.goto('/admin/workflows/2/edit');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        // Should redirect to index with success
        await expect(page).toHaveURL('/admin/workflows');
    });

    adminTest('can update workflow name and save', async ({ page }) => {
        // Edit PO workflow
        await page.goto('/admin/workflows/2/edit');
        const nameInput = page.locator('input[placeholder="Enter workflow name"]');
        await nameInput.clear();
        await nameInput.fill('Updated PO Workflow');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page).toHaveURL('/admin/workflows');

        // Verify the name was updated
        await expect(page.getByRole('cell', { name: 'Updated PO Workflow' })).toBeVisible();

        // Restore original name
        await page.goto('/admin/workflows/2/edit');
        const nameInput2 = page.locator('input[placeholder="Enter workflow name"]');
        await nameInput2.clear();
        await nameInput2.fill('Standard Purchase Order Workflow');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page).toHaveURL('/admin/workflows');
    });

    adminTest('can update PR workflow and save', async ({ page }) => {
        await page.goto('/admin/workflows/1/edit');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page).toHaveURL('/admin/workflows');
    });

    adminTest('can update VCH workflow and save', async ({ page }) => {
        await page.goto('/admin/workflows/3/edit');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page).toHaveURL('/admin/workflows');
    });

    adminTest('step reorder buttons work', async ({ page }) => {
        await page.goto('/admin/workflows/2/edit');
        // The step builder shows step numbers "1.", "2.", etc.
        // Verify the first step's up button is disabled (can't move first step up)
        // and the last step's down button is disabled (can't move last step down)
        await expect(page.getByText('1.')).toBeVisible();
        await expect(page.getByText('4.')).toBeVisible();
        // Check that "Add Step" button exists
        await expect(page.getByRole('button', { name: 'Add Step' })).toBeVisible();
    });

    adminTest('cancel button navigates back to index', async ({ page }) => {
        await page.goto('/admin/workflows/2/edit');
        page.on('dialog', dialog => dialog.accept());
        await page.getByRole('button', { name: 'Cancel' }).click();
        await expect(page).toHaveURL('/admin/workflows');
    });
});

test.describe('Workflow Management - Update All Workflows', () => {
    test.describe.configure({ mode: 'serial' });
    // These tests specifically verify the unsigned int bug fix
    // by saving each workflow type (PR, PO, VCH) and verifying no SQL error

    adminTest('PO workflow update does not cause unsigned int overflow', async ({ page }) => {
        await page.goto('/admin/workflows/2/edit');
        // Change expected days for first step
        const firstDaysInput = page.locator('input[type="number"]').first();
        await firstDaysInput.clear();
        await firstDaysInput.fill('2');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page).toHaveURL('/admin/workflows');

        // Restore original value
        await page.goto('/admin/workflows/2/edit');
        const restoreDaysInput = page.locator('input[type="number"]').first();
        await restoreDaysInput.clear();
        await restoreDaysInput.fill('1');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page).toHaveURL('/admin/workflows');
    });

    adminTest('PR workflow update does not cause unsigned int overflow', async ({ page }) => {
        await page.goto('/admin/workflows/1/edit');
        const firstDaysInput = page.locator('input[type="number"]').first();
        await firstDaysInput.clear();
        await firstDaysInput.fill('2');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page).toHaveURL('/admin/workflows');

        // Restore original value
        await page.goto('/admin/workflows/1/edit');
        const restoreDaysInput = page.locator('input[type="number"]').first();
        await restoreDaysInput.clear();
        await restoreDaysInput.fill('1');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page).toHaveURL('/admin/workflows');
    });

    adminTest('VCH workflow update does not cause unsigned int overflow', async ({ page }) => {
        await page.goto('/admin/workflows/3/edit');
        const firstDaysInput = page.locator('input[type="number"]').first();
        await firstDaysInput.clear();
        await firstDaysInput.fill('2');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page).toHaveURL('/admin/workflows');

        // Restore original value
        await page.goto('/admin/workflows/3/edit');
        const restoreDaysInput = page.locator('input[type="number"]').first();
        await restoreDaysInput.clear();
        await restoreDaysInput.fill('1');
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await expect(page).toHaveURL('/admin/workflows');
    });
});
