# Workspace doorway source sync

Approved by the owner on 15 October 2026. Reference: https://storybook.lan/opts/?path=/story/proposals-access--workspace-doorway

The approved Dashboard supplies Source Sans 3, blue ink, paper surfaces and the existing seal. Official installed shadcn Button, Command and Popover primitives are reused. The prototype stays available alongside the two alternatives.

Application adaptation removes the preview strip, alternative layouts and simulated authentication. Both Welcome and Auth/Login render SSOEntry. The existing SSO redirect, dashboard route, callback errors and guest middleware remain authoritative. A pageshow handler restores the action after browser Back. The application shows actual callback text inside the approved error surface. No SSO office, role or workflow code changes.

## Exact source hashes (SHA-256)

- `/Users/jsonse/Documents/development/storybook-hub/projects/opts/src/login-prototype.tsx`: `a1b04515936cff49ac88b90c6aa1edfd39132f6400a22ebe04e8b1794c8d361c`
- `/Users/jsonse/Documents/development/storybook-hub/projects/opts/src/login.css`: `27bef6570ac94b7091736ddd9483b401d125c837c3d06f055e2d438b5a35a6bb`
- `/Users/jsonse/.codex/worktrees/opts-staging-release/opts2026/resources/js/Components/Auth/SSOEntry.tsx`: `578945ce6d3addd976854680f5226d37db5cbbeb7e7f8140a7eee8487faf14d5`
- `/Users/jsonse/.codex/worktrees/opts-staging-release/opts2026/resources/css/access.css`: `7f4eea97cda694f580409873e9266cd6d82461e97afa405fa2f2f179c7b1fd5c`
- `/Users/jsonse/.codex/worktrees/opts-staging-release/opts2026/resources/js/Components/Procurements/ProcurementWorkspace.tsx`: `abe9ebf3f8eae234d5e6df355be41f54715f1a0091b05daa17b2d487d7c648fe`

## Verification

- App TypeScript/Vite build passed in release and original checkouts.
- Full PHP suite: 816 tests, 4503 assertions; existing deprecations remain. Focused authentication tests: 27 tests, 189 assertions.
- Storybook workspace typecheck, all-catalog build, final OPTS build and Docker publication passed.
- Published browser regression stories passed without console errors: retained-scroll recovery, abbreviation priority, keyboard option ID selection and Particular mouse selection.
- Actual application UI checked through an isolated local PHP server: root and login use one entry surface, keyboard help expands, direct SSO redirect works, Back restores the action, invalid callback displays the real error. Desktop and 390px phone have no horizontal overflow.
- The isolated release worktree has placeholder SSO credentials. Its redirect reached the correct local portal, which rejected that placeholder registration. Successful end-to-end SSO authentication was not claimed or reconfigured. Signed-in presentation is covered by the backend identity contract and Storybook state.
- Application source is uncommitted and has not been deployed to staging. The active local Docker pilot image was preserved. Production activation remains pending separately.
