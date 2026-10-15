# Approved Workspace doorway integration

The owner selected Workspace doorway on 15 October 2026. Both / and /login will use one shared SSO entry component. The homepage action goes directly to the existing sso.redirect route. Authenticated users can open the dashboard. The existing callback error remains visible at /login. No authentication, office, role, workflow, callback, or session implementation changes.

## Step by step tasks

1. Adapt approved Storybook doorway component and scoped CSS into Components/Auth/SSOEntry.tsx and resources/css/access.css. Reuse installed Button, seal, Source Sans 3 and existing routes. Remove proposal controls and simulated authentication; show real navigation progress and recover on browser back. VALIDATE: npx tsc --noEmit.
2. Replace Welcome and Auth/Login page bodies with the shared entry component. Preserve Head metadata and existing ssoError prop. VALIDATE: npm run build.
3. Extend existing SSO authentication tests to verify anonymous homepage, callback-error presentation and authenticated login redirect. Keep existing authorization-code assertions. VALIDATE: php -d memory_limit=512M vendor/bin/phpunit tests/Feature/Auth/SSOAuthenticationTest.php.
4. Run final application build and full PHP suite. Verify the actual local anonymous / and /login pages in Browser, help expansion, SSO link destination, back navigation, desktop/phone layout and browser focus. Review the diff. Keep staging deployment and production acceptance status separate. VALIDATE: npm run build; php -d memory_limit=512M vendor/bin/phpunit; git diff --check.

Storybook source: projects/opts/src/login-prototype.tsx, login.css; approved story proposals-access--workspace-doorway. Existing backend routes and SSO controller are unchanged.
