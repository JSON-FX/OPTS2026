# Implementation report — document pages

Plan: `.claude/plans/document-pages-live-integration.md`
Branch: `codex/sso-directory-integration`
Status: COMPLETE locally

## Delivered

- PR, PO and voucher Create/Edit/Show now compose shared Casebook document components. Real funds, suppliers, money, procurement context, linked documents, workflow steps, action history and server eligibility replace prototype fixtures.
- Searchable supplier/fund/workflow controls, PHP amount grouping, route disclosure, activity filters/action log, empty states and existing permitted processing actions work through Inertia.
- Current year is disabled for automatic creation and enforced server-side. Every category defaults annual automatic numbering. Explicit full office-issued overrides now include PR creation. Persisted PR references remain fixed on edit. Atomic allocation/uniqueness and prerequisite rules remain intact.
- Preview spacing, office-above-account, Settings Help and sidebar footer annotations applied in app/document reference stories. Mobile overflow action corrected after finishing review.
- Separate QR handoff proposal saved in Storybook: 15 states, endorsement/receipt, exact scan match, explicit confirmation, wrong reference/office, duplicate receipt, Viewer and retry states. Three genuine QR sample images were encoded and decoded with CoreImage/Apple Vision. All actions are synthetic local preview state.
- Owner additionally authorized retiring legacy MMO-PO. Backed-up local repair moved 13 office references to canonical local80/SSO54, archived legacy6 without identifier conflicts, preserved records/workflow step IDs and restored synchronization of 85 active SSO offices. See docs/issues/mmo-po-legacy-mapping.md.

## Validation

- Final PHPUnit: 765 tests /4,123 assertions, zero failures/errors. Existing 4 runtime and 37 PHPUnit deprecations remain.
- App TypeScript/Vite build and scoped PHP formatting pass. Local Docker OPTS build/recreate is healthy.
- Hub full typecheck and all 5 catalogs built. Final targeted OPTS rebuild published only OPTS;164 catalog entries include 15 QR stories. Served documents/QR HTML hashes match final static artifacts.
- 21 runtime document/shell/controller source files match the local image. Explicit app/Hub source manifest: docs/design/document-pages/source.sha256.
- Browser: real PR detail/edit, PO unsaved creation and details, voucher details, actual routes, grouped PHP amounts, manual PO override, disabled year, supplier search, activity empty/filter/log, full-name routes and Settings Help. Existing wrong-office and completed document restrictions visible. Phone390px and reported1683px verified; no horizontal overflow. Live records were inspected but no new procurement/document QA records saved.
- QR Browser: wrong-document rejection, exact Enter match, confirmation and local success for receive/endorse, required endorsement action, wrong office/Viewer blocking, connection retry, desktop/phone layout. Hardware compatibility not tested.
- Mechanical detector completed without actionable findings on app document scope. Independent technical review clear after optional-workflow fix. Finishing review final disposition: ship after compact mobile action correction.

## Intentional adaptations and limits

Live values and permissions supersede fixture defaults. Existing optional-workflow rule remains available. Existing receive/hold/cancel/resume/complete endpoints remain; endorsement continues through its current route. QR enforcement and actual scanner integration await separate review/implementation. No SSO server/portal changes, migrations, commit, push or production deployment.

## References

- docs/design/document-pages/acceptance.md
- docs/design/document-pages/ (desktop/phone/QR proof captures)
- Hub projects/opts/docs/document-pages-integration.md
- .claude/code-reviews/2026-10-15-document-pages-live-integration.md

Final catalog packaging correction: targeted builds explicitly set STORYBOOK_HUB_BASE=/opts/. The full Hub build already provides this value. The final embedded QR story was inspected after republishing its correctly based assets.

The embedded accessibility scan also identified unnamed repeated complementary landmarks. Workspace navigation and document context now have distinct accessible names in the app and relevant prototypes.

Final embedded QR receipt scan:0 accessibility violations /35 passes /0 inconclusive. Initial scanner focus keeps scroll position at the page heading. Final app image21-file source comparison and served iframe/module/prototype byte checks pass after these semantic/focus refinements.
