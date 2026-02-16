import { test, expect } from '@playwright/test';
import { endorserTest, adminTest } from './fixtures';

test.describe('Workflow Assignment - PR Create Page', () => {
    endorserTest('PR create page shows workflow dropdown', async ({ page }) => {
        await page.goto('/procurements');
        await expect(page.locator('table')).toBeVisible();

        const procurementLink = page.locator('table tbody tr:first-child a').first();
        const linkCount = await procurementLink.count();
        if (linkCount > 0) {
            await procurementLink.click();

            const createPR = page.getByRole('link', { name: /Create Purchase Request/i });
            if (await createPR.isVisible()) {
                await createPR.click();

                const workflowSelect = page.locator('#workflow_id');
                await expect(workflowSelect).toBeVisible();
                await expect(workflowSelect).not.toBeDisabled();
            }
        }
    });

    endorserTest('selecting workflow shows route preview on PR create', async ({ page }) => {
        await page.goto('/procurements');
        await expect(page.locator('table')).toBeVisible();

        const procurementLink = page.locator('table tbody tr:first-child a').first();
        const linkCount = await procurementLink.count();
        if (linkCount > 0) {
            await procurementLink.click();

            const createPR = page.getByRole('link', { name: /Create Purchase Request/i });
            if (await createPR.isVisible()) {
                await createPR.click();

                const workflowSelect = page.locator('#workflow_id');
                const options = await workflowSelect.locator('option').all();
                if (options.length > 1) {
                    await workflowSelect.selectOption({ index: 1 });
                    await expect(page.locator('text=Route:')).toBeVisible();
                }
            }
        }
    });
});

test.describe('Workflow Assignment - PO Create Page', () => {
    endorserTest('PO create page shows workflow dropdown', async ({ page }) => {
        await page.goto('/procurements');
        await expect(page.locator('table')).toBeVisible();

        const procurementLink = page.locator('table tbody tr:first-child a').first();
        const linkCount = await procurementLink.count();
        if (linkCount > 0) {
            await procurementLink.click();

            const createPO = page.getByRole('link', { name: /Create Purchase Order/i });
            if (await createPO.isVisible()) {
                await createPO.click();

                const workflowSelect = page.locator('#workflow_id');
                await expect(workflowSelect).toBeVisible();
                await expect(workflowSelect).not.toBeDisabled();
            }
        }
    });

    endorserTest('selecting workflow shows route preview on PO create', async ({ page }) => {
        await page.goto('/procurements');
        await expect(page.locator('table')).toBeVisible();

        const procurementLink = page.locator('table tbody tr:first-child a').first();
        const linkCount = await procurementLink.count();
        if (linkCount > 0) {
            await procurementLink.click();

            const createPO = page.getByRole('link', { name: /Create Purchase Order/i });
            if (await createPO.isVisible()) {
                await createPO.click();

                const workflowSelect = page.locator('#workflow_id');
                const options = await workflowSelect.locator('option').all();
                if (options.length > 1) {
                    await workflowSelect.selectOption({ index: 1 });
                    await expect(page.locator('text=Route:')).toBeVisible();
                }
            }
        }
    });
});

test.describe('Workflow Assignment - VCH Create Page', () => {
    endorserTest('VCH create page shows workflow dropdown', async ({ page }) => {
        await page.goto('/procurements');
        await expect(page.locator('table')).toBeVisible();

        const procurementLink = page.locator('table tbody tr:first-child a').first();
        const linkCount = await procurementLink.count();
        if (linkCount > 0) {
            await procurementLink.click();

            const createVCH = page.getByRole('link', { name: /Create Voucher/i });
            if (await createVCH.isVisible()) {
                await createVCH.click();

                const workflowSelect = page.locator('#workflow_id');
                await expect(workflowSelect).toBeVisible();
                await expect(workflowSelect).not.toBeDisabled();
            }
        }
    });

    endorserTest('selecting workflow shows route preview on VCH create', async ({ page }) => {
        await page.goto('/procurements');
        await expect(page.locator('table')).toBeVisible();

        const procurementLink = page.locator('table tbody tr:first-child a').first();
        const linkCount = await procurementLink.count();
        if (linkCount > 0) {
            await procurementLink.click();

            const createVCH = page.getByRole('link', { name: /Create Voucher/i });
            if (await createVCH.isVisible()) {
                await createVCH.click();

                const workflowSelect = page.locator('#workflow_id');
                const options = await workflowSelect.locator('option').all();
                if (options.length > 1) {
                    await workflowSelect.selectOption({ index: 1 });
                    await expect(page.locator('text=Route:')).toBeVisible();
                }
            }
        }
    });
});

test.describe('Workflow Assignment - WorkflowPreviewCard', () => {
    endorserTest('workflow preview card shows steps when available', async ({ page }) => {
        await page.goto('/procurements');
        await expect(page.locator('table')).toBeVisible();

        const procurementLink = page.locator('table tbody tr:first-child a').first();
        const linkCount = await procurementLink.count();
        if (linkCount > 0) {
            await procurementLink.click();

            const createPR = page.getByRole('link', { name: /Create Purchase Request/i });
            if (await createPR.isVisible()) {
                await createPR.click();

                // Check for WorkflowPreviewCard - it shows when workflowPreview prop is provided
                const previewCard = page.locator('text=Workflow:');
                if (await previewCard.isVisible()) {
                    await expect(page.locator('text=steps')).toBeVisible();
                    await expect(page.getByRole('button', { name: /Show Steps/i })).toBeVisible();
                }
            }
        }
    });

    endorserTest('workflow preview card expands to show step details', async ({ page }) => {
        await page.goto('/procurements');
        await expect(page.locator('table')).toBeVisible();

        const procurementLink = page.locator('table tbody tr:first-child a').first();
        const linkCount = await procurementLink.count();
        if (linkCount > 0) {
            await procurementLink.click();

            const createPR = page.getByRole('link', { name: /Create Purchase Request/i });
            if (await createPR.isVisible()) {
                await createPR.click();

                const showStepsButton = page.getByRole('button', { name: /Show Steps/i });
                if (await showStepsButton.isVisible()) {
                    await showStepsButton.click();

                    // After expanding, should show step details table
                    await expect(page.locator('text=Office')).toBeVisible();
                    await expect(page.locator('text=Expected Days')).toBeVisible();

                    // Button should now say "Hide Steps"
                    await expect(page.getByRole('button', { name: /Hide Steps/i })).toBeVisible();
                }
            }
        }
    });
});

test.describe('Workflow Assignment - Dropdown Default', () => {
    endorserTest('workflow dropdown defaults to empty selection', async ({ page }) => {
        await page.goto('/procurements');
        await expect(page.locator('table')).toBeVisible();

        const procurementLink = page.locator('table tbody tr:first-child a').first();
        const linkCount = await procurementLink.count();
        if (linkCount > 0) {
            await procurementLink.click();

            const createPR = page.getByRole('link', { name: /Create Purchase Request/i });
            if (await createPR.isVisible()) {
                await createPR.click();

                const workflowSelect = page.locator('#workflow_id');
                await expect(workflowSelect).toHaveValue('');
            }
        }
    });

    adminTest('admin can see workflow dropdown on PR create', async ({ page }) => {
        await page.goto('/procurements');
        await expect(page.locator('table')).toBeVisible();

        const procurementLink = page.locator('table tbody tr:first-child a').first();
        const linkCount = await procurementLink.count();
        if (linkCount > 0) {
            await procurementLink.click();

            const createPR = page.getByRole('link', { name: /Create Purchase Request/i });
            if (await createPR.isVisible()) {
                await createPR.click();

                const workflowSelect = page.locator('#workflow_id');
                await expect(workflowSelect).toBeVisible();
                await expect(workflowSelect).not.toBeDisabled();
            }
        }
    });
});
