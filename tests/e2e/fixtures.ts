import { test as base, expect } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const authFile = (role: string) =>
    path.join(__dirname, `.auth/${role}.json`);

/**
 * Test fixtures for different authenticated roles.
 * Usage:
 *   import { adminTest, endorserTest, viewerTest } from './fixtures';
 *   adminTest('can access admin pages', async ({ page }) => { ... });
 */
export const adminTest = base.extend({
    storageState: authFile('admin'),
});

export const endorserTest = base.extend({
    storageState: authFile('endorser'),
});

export const viewerTest = base.extend({
    storageState: authFile('viewer'),
});

export { expect };
