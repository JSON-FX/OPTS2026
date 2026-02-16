import { test, expect } from '@playwright/test';
import { adminTest, endorserTest, viewerTest } from './fixtures';

test.describe('State Machine - RBAC for Admin Actions', () => {
    viewerTest('viewer does not see Hold/Cancel/Resume buttons', async ({ page }) => {
        await page.goto('/transactions');
        const prLink = page.locator('table a').first();
        const prLinkCount = await prLink.count();
        if (prLinkCount > 0) {
            await prLink.click();
            await expect(page.getByRole('button', { name: 'Hold' })).not.toBeVisible();
            await expect(page.getByRole('button', { name: 'Cancel' })).not.toBeVisible();
            await expect(page.getByRole('button', { name: 'Resume' })).not.toBeVisible();
        }
    });

    endorserTest('endorser does not see Hold/Cancel/Resume buttons', async ({ page }) => {
        await page.goto('/transactions');
        const prLink = page.locator('table a').first();
        const prLinkCount = await prLink.count();
        if (prLinkCount > 0) {
            await prLink.click();
            await expect(page.getByRole('button', { name: 'Hold' })).not.toBeVisible();
            await expect(page.getByRole('button', { name: 'Cancel' })).not.toBeVisible();
            await expect(page.getByRole('button', { name: 'Resume' })).not.toBeVisible();
        }
    });

    test('unauthenticated user cannot POST hold', async ({ page }) => {
        const response = await page.request.post('/transactions/1/hold', {
            data: { reason: 'test' },
        });
        expect(response.status()).toBe(302);
        expect(response.headers()['location']).toContain('/login');
    });

    test('unauthenticated user cannot POST cancel', async ({ page }) => {
        const response = await page.request.post('/transactions/1/cancel', {
            data: { reason: 'test' },
        });
        expect(response.status()).toBe(302);
        expect(response.headers()['location']).toContain('/login');
    });

    test('unauthenticated user cannot POST resume', async ({ page }) => {
        const response = await page.request.post('/transactions/1/resume', {
            data: { reason: 'test' },
        });
        expect(response.status()).toBe(302);
        expect(response.headers()['location']).toContain('/login');
    });
});

test.describe('State Machine - Admin Hold Button', () => {
    adminTest('admin sees Hold button on In Progress transaction', async ({ page }) => {
        await page.goto('/transactions');
        await expect(page.locator('table')).toBeVisible();

        // Look for an In Progress transaction
        const inProgressRow = page.locator('table tr').filter({ hasText: 'In Progress' }).first();
        const rowCount = await inProgressRow.count();
        if (rowCount > 0) {
            const link = inProgressRow.locator('a').first();
            await link.click();
            await expect(page.getByRole('button', { name: 'Hold' })).toBeVisible();
        }
    });

    adminTest('Hold button not visible on Completed transaction', async ({ page }) => {
        await page.goto('/transactions');
        await expect(page.locator('table')).toBeVisible();

        const completedRow = page.locator('table tr').filter({ hasText: 'Completed' }).first();
        const rowCount = await completedRow.count();
        if (rowCount > 0) {
            const link = completedRow.locator('a').first();
            await link.click();
            await expect(page.getByRole('button', { name: 'Hold' })).not.toBeVisible();
        }
    });

    adminTest('Hold button not visible on Cancelled transaction', async ({ page }) => {
        await page.goto('/transactions');
        await expect(page.locator('table')).toBeVisible();

        const cancelledRow = page.locator('table tr').filter({ hasText: 'Cancelled' }).first();
        const rowCount = await cancelledRow.count();
        if (rowCount > 0) {
            const link = cancelledRow.locator('a').first();
            await link.click();
            await expect(page.getByRole('button', { name: 'Hold' })).not.toBeVisible();
        }
    });
});

test.describe('State Machine - Hold Modal', () => {
    adminTest('clicking Hold opens modal with reason field', async ({ page }) => {
        await page.goto('/transactions');
        const inProgressRow = page.locator('table tr').filter({ hasText: 'In Progress' }).first();
        const rowCount = await inProgressRow.count();
        if (rowCount > 0) {
            const link = inProgressRow.locator('a').first();
            await link.click();

            const holdButton = page.getByRole('button', { name: 'Hold' });
            if (await holdButton.isVisible()) {
                await holdButton.click();

                // Modal should appear
                await expect(page.getByText('Hold Transaction')).toBeVisible();
                await expect(page.getByText('Reason')).toBeVisible();
                await expect(page.getByPlaceholder('Enter reason for placing this transaction on hold...')).toBeVisible();

                // Should have Cancel and Place on Hold buttons
                await expect(page.getByRole('button', { name: 'Cancel' })).toBeVisible();
                await expect(page.getByRole('button', { name: 'Place on Hold' })).toBeVisible();
            }
        }
    });

    adminTest('Hold modal Cancel button closes modal', async ({ page }) => {
        await page.goto('/transactions');
        const inProgressRow = page.locator('table tr').filter({ hasText: 'In Progress' }).first();
        const rowCount = await inProgressRow.count();
        if (rowCount > 0) {
            const link = inProgressRow.locator('a').first();
            await link.click();

            const holdButton = page.getByRole('button', { name: 'Hold' });
            if (await holdButton.isVisible()) {
                await holdButton.click();
                await expect(page.getByText('Hold Transaction')).toBeVisible();

                await page.getByRole('button', { name: 'Cancel' }).click();
                await expect(page.getByRole('dialog')).not.toBeVisible();
            }
        }
    });

    adminTest('Hold modal requires reason', async ({ page }) => {
        await page.goto('/transactions');
        const inProgressRow = page.locator('table tr').filter({ hasText: 'In Progress' }).first();
        const rowCount = await inProgressRow.count();
        if (rowCount > 0) {
            const link = inProgressRow.locator('a').first();
            await link.click();

            const holdButton = page.getByRole('button', { name: 'Hold' });
            if (await holdButton.isVisible()) {
                await holdButton.click();
                await expect(page.getByText('Hold Transaction')).toBeVisible();

                // Try to submit without reason
                await page.getByRole('button', { name: 'Place on Hold' }).click();

                // Should show validation error
                await expect(page.getByText('A reason is required')).toBeVisible();
            }
        }
    });

    adminTest('Hold modal shows character count', async ({ page }) => {
        await page.goto('/transactions');
        const inProgressRow = page.locator('table tr').filter({ hasText: 'In Progress' }).first();
        const rowCount = await inProgressRow.count();
        if (rowCount > 0) {
            const link = inProgressRow.locator('a').first();
            await link.click();

            const holdButton = page.getByRole('button', { name: 'Hold' });
            if (await holdButton.isVisible()) {
                await holdButton.click();
                await expect(page.getByText('0/1000')).toBeVisible();

                await page.getByPlaceholder('Enter reason for placing this transaction on hold...').fill('Test reason');
                await expect(page.getByText('11/1000')).toBeVisible();
            }
        }
    });
});

test.describe('State Machine - Admin Cancel Button', () => {
    adminTest('admin sees Cancel button on In Progress transaction', async ({ page }) => {
        await page.goto('/transactions');
        await expect(page.locator('table')).toBeVisible();

        const inProgressRow = page.locator('table tr').filter({ hasText: 'In Progress' }).first();
        const rowCount = await inProgressRow.count();
        if (rowCount > 0) {
            const link = inProgressRow.locator('a').first();
            await link.click();
            await expect(page.getByRole('button', { name: 'Cancel' })).toBeVisible();
        }
    });

    adminTest('Cancel button not visible on Completed transaction', async ({ page }) => {
        await page.goto('/transactions');
        await expect(page.locator('table')).toBeVisible();

        const completedRow = page.locator('table tr').filter({ hasText: 'Completed' }).first();
        const rowCount = await completedRow.count();
        if (rowCount > 0) {
            const link = completedRow.locator('a').first();
            await link.click();
            await expect(page.getByRole('button', { name: /^Cancel$/ })).not.toBeVisible();
        }
    });
});

test.describe('State Machine - Cancel Modal', () => {
    adminTest('clicking Cancel opens modal with warning and reason field', async ({ page }) => {
        await page.goto('/transactions');
        const inProgressRow = page.locator('table tr').filter({ hasText: 'In Progress' }).first();
        const rowCount = await inProgressRow.count();
        if (rowCount > 0) {
            const link = inProgressRow.locator('a').first();
            await link.click();

            const cancelButton = page.getByRole('button', { name: 'Cancel' });
            if (await cancelButton.isVisible()) {
                await cancelButton.click();

                // Modal should appear with warning
                await expect(page.getByText('Cancel Transaction')).toBeVisible();
                await expect(page.getByText('Cancelling a transaction is permanent and cannot be undone')).toBeVisible();
                await expect(page.getByPlaceholder('Enter reason for cancelling this transaction...')).toBeVisible();

                // Should have Keep Transaction and Cancel Transaction buttons
                await expect(page.getByRole('button', { name: 'Keep Transaction' })).toBeVisible();
                await expect(page.getByRole('button', { name: 'Cancel Transaction' })).toBeVisible();
            }
        }
    });

    adminTest('Cancel modal Keep Transaction button closes modal', async ({ page }) => {
        await page.goto('/transactions');
        const inProgressRow = page.locator('table tr').filter({ hasText: 'In Progress' }).first();
        const rowCount = await inProgressRow.count();
        if (rowCount > 0) {
            const link = inProgressRow.locator('a').first();
            await link.click();

            const cancelButton = page.getByRole('button', { name: 'Cancel' });
            if (await cancelButton.isVisible()) {
                await cancelButton.click();
                await expect(page.getByText('Cancel Transaction')).toBeVisible();

                await page.getByRole('button', { name: 'Keep Transaction' }).click();
                await expect(page.getByRole('dialog')).not.toBeVisible();
            }
        }
    });
});

test.describe('State Machine - Admin Resume Button', () => {
    adminTest('admin sees Resume button on On Hold transaction', async ({ page }) => {
        await page.goto('/transactions');
        await expect(page.locator('table')).toBeVisible();

        const onHoldRow = page.locator('table tr').filter({ hasText: 'On Hold' }).first();
        const rowCount = await onHoldRow.count();
        if (rowCount > 0) {
            const link = onHoldRow.locator('a').first();
            await link.click();
            await expect(page.getByRole('button', { name: 'Resume' })).toBeVisible();
        }
    });

    adminTest('Resume button not visible on In Progress transaction', async ({ page }) => {
        await page.goto('/transactions');
        await expect(page.locator('table')).toBeVisible();

        const inProgressRow = page.locator('table tr').filter({ hasText: 'In Progress' }).first();
        const rowCount = await inProgressRow.count();
        if (rowCount > 0) {
            const link = inProgressRow.locator('a').first();
            await link.click();
            await expect(page.getByRole('button', { name: 'Resume' })).not.toBeVisible();
        }
    });
});

test.describe('State Machine - Resume Modal', () => {
    adminTest('clicking Resume opens modal with optional reason field', async ({ page }) => {
        await page.goto('/transactions');
        const onHoldRow = page.locator('table tr').filter({ hasText: 'On Hold' }).first();
        const rowCount = await onHoldRow.count();
        if (rowCount > 0) {
            const link = onHoldRow.locator('a').first();
            await link.click();

            const resumeButton = page.getByRole('button', { name: 'Resume' });
            if (await resumeButton.isVisible()) {
                await resumeButton.click();

                // Modal should appear
                await expect(page.getByText('Resume Transaction')).toBeVisible();
                await expect(page.getByText('Reason (optional)')).toBeVisible();
                await expect(page.getByPlaceholder('Enter reason for resuming this transaction...')).toBeVisible();

                // Should have Cancel and Resume Transaction buttons
                await expect(page.getByRole('button', { name: 'Cancel' })).toBeVisible();
                await expect(page.getByRole('button', { name: 'Resume Transaction' })).toBeVisible();
            }
        }
    });
});

test.describe('State Machine - Status Badge', () => {
    adminTest('status badge shows correct colors', async ({ page }) => {
        await page.goto('/transactions');
        await expect(page.locator('table')).toBeVisible();

        // Navigate to any transaction to check status badge
        const prLink = page.locator('table a').first();
        const prLinkCount = await prLink.count();
        if (prLinkCount > 0) {
            await prLink.click();
            // Status badge should be visible on the detail page
            const statusText = page.locator('text=Created, text=In Progress, text=Completed, text=On Hold, text=Cancelled').first();
            await expect(statusText).toBeVisible();
        }
    });
});

test.describe('State Machine - Terminal States Hide Workflow Buttons', () => {
    adminTest('Cancelled transaction hides workflow buttons', async ({ page }) => {
        await page.goto('/transactions');
        await expect(page.locator('table')).toBeVisible();

        const cancelledRow = page.locator('table tr').filter({ hasText: 'Cancelled' }).first();
        const rowCount = await cancelledRow.count();
        if (rowCount > 0) {
            const link = cancelledRow.locator('a').first();
            await link.click();
            await expect(page.getByRole('button', { name: 'Receive' })).not.toBeVisible();
            await expect(page.getByRole('button', { name: 'Endorse' })).not.toBeVisible();
            await expect(page.getByRole('button', { name: 'Complete' })).not.toBeVisible();
            await expect(page.getByRole('button', { name: 'Hold' })).not.toBeVisible();
            await expect(page.getByRole('button', { name: 'Resume' })).not.toBeVisible();
        }
    });

    adminTest('Completed transaction hides workflow and admin action buttons', async ({ page }) => {
        await page.goto('/transactions');
        await expect(page.locator('table')).toBeVisible();

        const completedRow = page.locator('table tr').filter({ hasText: 'Completed' }).first();
        const rowCount = await completedRow.count();
        if (rowCount > 0) {
            const link = completedRow.locator('a').first();
            await link.click();
            await expect(page.getByRole('button', { name: 'Receive' })).not.toBeVisible();
            await expect(page.getByRole('button', { name: 'Endorse' })).not.toBeVisible();
            await expect(page.getByRole('button', { name: 'Complete' })).not.toBeVisible();
            await expect(page.getByRole('button', { name: 'Hold' })).not.toBeVisible();
            await expect(page.getByRole('button', { name: 'Resume' })).not.toBeVisible();
        }
    });
});
