import { test, expect } from '@playwright/test';
import { endorserTest, viewerTest, adminTest } from './fixtures';

test.describe('Complete Action - RBAC', () => {
    viewerTest('viewer does not see Complete button on PR show page', async ({ page }) => {
        await page.goto('/transactions');
        // Navigate to a purchase request if available
        const prLink = page.locator('table a').first();
        const prLinkCount = await prLink.count();
        if (prLinkCount > 0) {
            await prLink.click();
            // Viewer should not see the Complete button
            await expect(page.getByRole('button', { name: 'Complete' })).not.toBeVisible();
        }
    });

    test('unauthenticated user cannot POST complete', async ({ page }) => {
        const response = await page.request.post('/transactions/1/complete', {
            data: { action_taken_id: 1 },
        });
        // Should redirect to login
        expect(response.status()).toBe(302);
        expect(response.headers()['location']).toContain('/login');
    });
});

test.describe('Complete Action - Button Visibility', () => {
    endorserTest('Complete button is visible on transaction detail page', async ({ page }) => {
        // Navigate to purchase requests list
        await page.goto('/transactions');
        await expect(page.locator('table')).toBeVisible();

        // Look for a purchase request link to navigate to
        const prLink = page.locator('table a').first();
        const prLinkCount = await prLink.count();
        if (prLinkCount > 0) {
            await prLink.click();
            // The Complete button should exist (either enabled or disabled)
            const completeButton = page.getByRole('button', { name: 'Complete' });
            await expect(completeButton).toBeVisible();
        }
    });

    endorserTest('Complete button is not shown on completed transactions', async ({ page }) => {
        // If we can find a completed transaction, Complete button should not be visible
        await page.goto('/transactions');
        await expect(page.locator('table')).toBeVisible();

        // Look for a row with "Completed" status
        const completedRow = page.locator('table tr').filter({ hasText: 'Completed' }).first();
        const completedCount = await completedRow.count();
        if (completedCount > 0) {
            const link = completedRow.locator('a').first();
            await link.click();
            // Complete button should not be visible
            await expect(page.getByRole('button', { name: 'Complete' })).not.toBeVisible();
            // Endorse button should not be visible either
            await expect(page.getByRole('button', { name: 'Endorse' })).not.toBeVisible();
            // Receive button should not be visible either
            await expect(page.getByRole('button', { name: 'Receive' })).not.toBeVisible();
            // "Completed" badge should be visible
            await expect(page.getByText('Completed')).toBeVisible();
        }
    });
});

test.describe('Complete Action - Modal', () => {
    endorserTest('clicking Complete opens confirmation modal', async ({ page }) => {
        await page.goto('/transactions');
        const prLink = page.locator('table a').first();
        const prLinkCount = await prLink.count();
        if (prLinkCount > 0) {
            await prLink.click();

            const completeButton = page.getByRole('button', { name: 'Complete' });
            // Only test modal if button is enabled (transaction is at final step)
            if (await completeButton.isEnabled()) {
                await completeButton.click();

                // Modal should appear
                await expect(page.getByText('Complete Transaction')).toBeVisible();
                await expect(page.getByText('This action will mark the transaction as completed and cannot be undone')).toBeVisible();

                // Should have Action Taken dropdown
                await expect(page.getByText('Action Taken')).toBeVisible();
                await expect(page.getByText('Select action taken...')).toBeVisible();

                // Should have Notes textarea
                await expect(page.getByLabel('Notes (optional)')).toBeVisible();

                // Should have Cancel and Complete Transaction buttons
                await expect(page.getByRole('button', { name: 'Cancel' })).toBeVisible();
                await expect(page.getByRole('button', { name: 'Complete Transaction' })).toBeVisible();
            }
        }
    });

    endorserTest('Cancel button closes the modal', async ({ page }) => {
        await page.goto('/transactions');
        const prLink = page.locator('table a').first();
        const prLinkCount = await prLink.count();
        if (prLinkCount > 0) {
            await prLink.click();

            const completeButton = page.getByRole('button', { name: 'Complete' });
            if (await completeButton.isEnabled()) {
                await completeButton.click();
                await expect(page.getByText('Complete Transaction')).toBeVisible();

                // Click Cancel
                await page.getByRole('button', { name: 'Cancel' }).click();

                // Modal should close
                await expect(page.getByRole('dialog')).not.toBeVisible();
            }
        }
    });

    endorserTest('modal shows transaction summary', async ({ page }) => {
        await page.goto('/transactions');
        const prLink = page.locator('table a').first();
        const prLinkCount = await prLink.count();
        if (prLinkCount > 0) {
            await prLink.click();

            const completeButton = page.getByRole('button', { name: 'Complete' });
            if (await completeButton.isEnabled()) {
                await completeButton.click();

                // Should display transaction details in the summary
                await expect(page.getByText('Reference Number')).toBeVisible();
                await expect(page.getByText('Category')).toBeVisible();
                await expect(page.getByText('Status')).toBeVisible();
            }
        }
    });
});

test.describe('Complete Action - Validation', () => {
    endorserTest('submitting without action taken shows error', async ({ page }) => {
        await page.goto('/transactions');
        const prLink = page.locator('table a').first();
        const prLinkCount = await prLink.count();
        if (prLinkCount > 0) {
            await prLink.click();

            const completeButton = page.getByRole('button', { name: 'Complete' });
            if (await completeButton.isEnabled()) {
                await completeButton.click();
                await expect(page.getByText('Complete Transaction')).toBeVisible();

                // Try to submit without selecting action taken
                await page.getByRole('button', { name: 'Complete Transaction' }).click();

                // Should show validation error
                await expect(page.getByText('Please select an action taken')).toBeVisible();
            }
        }
    });
});

test.describe('Complete Action - Administrator', () => {
    adminTest('administrator sees Complete button', async ({ page }) => {
        await page.goto('/transactions');
        const prLink = page.locator('table a').first();
        const prLinkCount = await prLink.count();
        if (prLinkCount > 0) {
            await prLink.click();
            // Admin should see the Complete button
            const completeButton = page.getByRole('button', { name: 'Complete' });
            await expect(completeButton).toBeVisible();
        }
    });
});
