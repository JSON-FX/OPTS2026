# Implementation report — access and procurement search annotations

Plan: docs/design/workspace-access-plan.md. Branch: codex/opts-staging-ui. Implementation: complete locally. Delivery: uncommitted; staging not deployed.

## Changes

- One shared approved Workspace doorway entry for / and /login, with direct SSO action, real callback error, keyboard help, progress and browser Back recovery.
- Procurement office and Particular searches now reset retained scroll, match contiguous tokens and prioritize visible labels. Explicit Command labels preserve the intended accessible search names.
- Empty Particular configuration has a clear administrator setup message. Staging's empty catalog was not filled with sample data.
- Storybook preserves three login alternatives, ready/error/expired/signed-in states, and automated search regression stories.

## Validation

Application build passed. All 816 PHP tests passed with 4503 assertions. Existing PHP/PHPUnit deprecations remain. PHP's default 128 MB test child limit was insufficient; direct PHPUnit with 512 MB completed. Focused authentication tests and Pint passed. Storybook typecheck, full build, final scoped build and Docker publication passed. Published search regression stories passed without browser console errors. Actual integrated page was verified at desktop and 390px phone width, including help, Back and callback recovery.

## Review and limits

Code review passed. No technical findings in the scoped diff. No controller, route, directory, office mapping, permissions or workflow changes. No dependency changes. Unrelated working-tree and local pilot changes were preserved. Exact source hashes and browser verification limits are in docs/design/workspace-access-reference-sync.md.

Deviation: no GitHub issue existed; browser comments were documented as a local RCA. The approved UI required no server route changes. Browser verification used an isolated local PHP server because the existing local Docker app uses an immutable pilot image. Its placeholder SSO registration prevents a full fresh sign-in check; existing authentication contracts passed.
