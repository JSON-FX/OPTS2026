# Procurements live UI implementation report

Date: 15 October 2026. Status: implemented and verified locally. No commit, push or remote deployment requested or performed.

## Delivered

The approved Storybook register, adjacent preview, quick view, full procurement detail, document overview, creation/editing, archive and help interactions now use real Inertia data and existing Laravel resource routes. Filters, sorting, pagination and global selected-year status counts are server driven. Columns and sidebar collapse persist per user. All dialogs are centered and have short enter/exit motion with reduced-motion overrides.

Office and particular selectors are searchable comboboxes. New choices use active SSO offices with abbreviation labels and full-name search keywords. Historical office references are preserved for filters and records. The transaction timeline uses actual actions, actors, timestamps, reasons and full office names. Creation events use actual recorded document timestamps and creators. SSO eligibility and Viewer/Endorser/Administrator policies remain server authoritative. PR existence gates PO, and PO existence gates voucher; completion requirements were not added.

The shared Dashboard shell now wraps every authenticated route. The old 469-line navigation was replaced by a compatibility wrapper. Unused old NavLink/ResponsiveNavLink and two procurement backup pages were removed. Global paper/blue tokens, Source Sans, button primitives and navigation are shared. Reporting-year selection moved into Settings. Existing transaction/admin page contents retain their functionality under this shell; their individual page redesigns are separate work.

Storybook now includes permanent Office/Particular focused-search stories. Both were checked in Browser after publication. The fixed 44px search row uses one inset 2px ring, without a clipped inner input outline. The final source snapshot and hashes are in `docs/design/procurements-reference-sync.md`.

## Validation

| Gate | Result |
| --- | --- |
| Final complete PHPUnit suite | 717 tests, 3623 assertions; zero failures/errors |
| Independent final focused backend review | 8 tests, 200 assertions; no remaining blocker |
| Host TypeScript + Vite build | Pass |
| Local Docker build, including frontend build | Pass |
| Scoped PHP Pint and git diff --check | Pass |
| Hub workspace typecheck | Pass |
| Hub all five catalogs build | Pass on final corrected source |
| Final corrected OPTS catalog build | Pass |
| Local container / HTTPS health | Healthy / HTTP success with trusted local CA |

The default 128 MB Composer test subprocess exhausted memory. Direct PHPUnit with `php -d memory_limit=512M vendor/bin/phpunit` completed. Four PHP runtime deprecations and 37 PHPUnit deprecations remain. They did not fail tests. Vite reports existing dependency-data/chunk/font resolution warnings; local font files load successfully.

## Browser acceptance evidence

Browser was the primary E2E tool; no Playwright connector fallback was used. Tested the running local container as the existing SSO Administrator account. No accounts, credentials or grants changed.

- Register: real reference search found only procurement #3; historical office filter found only #1. Particular and created-by-me filters, Cancelled empty state, reset, highest-budget ordering and columns worked.
- Pagination: four clearly labeled QA procurements produced seven records. Page two contained one record; 12-per-page showed all seven. Column choice survived reload and was restored after testing.
- Row selection: clicking the budget cell selected the matching adjacent preview. Arrow opened quick view; full detail navigation retained register context.
- Real create/edit: IDs 4–7 created through UI. ID4 purpose and amount were updated and persisted. Empty-field validation displayed correctly. Saving disables submit/cancel. Server validation was preserved.
- Browser found and fixed a UTC/Manila default-date mismatch. Form date now comes from the same server clock as its validation. A regression covers the boundary.
- Browser found missing success feedback from controller redirects. Shared lazy flash props now reach workspace confirmations; successful archive confirmation was observed and dismissed.
- Delayed filter response: Edit and Archive for ID4 stayed on ID4 after the background list became empty. Form data-procurement-id and archive heading remained fixed. No accidental target change.
- Completed procurement #3: all linked records show actual completed states. Document overview shows actual full-name route and valid PR link. Timeline type filter limits events correctly; actual notes/actors/full office names and expanded history are visible.
- Existing linked procurement office and particular are disabled in Edit. New procurement offers Create PR, while PO/voucher remain disabled with dependency explanations. The PR creation route loads under the shared shell.
- Archive: cancellation retained the record. IDs4–7 were soft-deleted through the UI after testing. Independent read-only DB verification confirmed IDs1–3 and original transaction IDs1–7 remain active, linked and intact. Procurement #3 and all three documents remain completed.
- Shared shell: Transactions and Dashboard load without horizontal overflow. Sidebar collapse survives reload. Settings shows actual SSO profile and year. Only 2026 exists in this local dataset, so changing to another year was not exercised in Browser; server selected-year tests pass.
- Responsive checks: 1683×1314 desktop, 1024×768 tablet, 390×844 phone. No page/modal horizontal overflow in checked states. Quick-view/dialog centers measured exactly at viewport center. Mobile drawer → Help and Settings transitions close the drawer and retain usable focus. Viewport override reset afterwards.
- Computed motion: quick view uses `pp-dialog-enter`. Reduced-motion CSS reviewed in source; Browser media emulation unavailable. Not a claim of OS reduced-motion runtime verification.
- Notification bell opens actual dropdown with real notification rows. Zero unread is truthful. No new notifications were generated solely for this UI test.
- No Browser console errors recorded in the final live tab.

## Proof files

- `docs/design/procurements-live-register.png`
- `docs/design/procurements-live-detail.png`
- `docs/design/procurements-live-document-modal.png`
- `docs/design/procurements-live-combobox.png`
- `docs/design/procurements-live-phone.png`
- `docs/design/procurements-live-phone-combobox.png`
- `docs/design/procurements-storybook-office-focus.png`
- `docs/design/procurements-storybook-particular-focus.png`

Phone screenshot output is downsampled by the Browser screenshot service; DOM viewport checks used 390×844. PNG files were normalized from the returned screenshot bytes.

## Local readback

Only OPTS was rebuilt/recreated. Image: `sha256:b31e33b521f5a8c5a3034842fbf713ea3edd593239a7207086412f1b4bac3e05`, healthy.

Workspace/container SHA-256 matches:

- ProcurementPresentationService: `e81db51932d7ebef794c7c55c6bbf1dba808007385e01f5674f007e04be88e06`
- HandleInertiaRequests: `5f643b9ef48d50ecaf40b522425e601917dbd6ebd9663b3787f4afc2d0077478`
- ProcurementWorkspace TSX: `21ea7be690a32274760a1e08b2fd3dc754147c62700932cc567346879921ed2d`
- Procurement CSS: `c45ae9b10d0308a35b81f751c6f33ec4a5930bd56c88dfd8ab0db618042c0773`

Served `ProcurementWorkspace-DMX4JvR-.js` matches container hash `44de2bc79b9a4d9153044523486a9a7807860ece6119c0e514d27d9a470650f6`. Served `ProcurementWorkspace-BR914PtT.css` matches container hash `1779a838453f2a2d8ea059ae022c746effd49aff73f399e1178edf37b9a5660b`. Docker's manifest identifies its own built chunks; host build hashes are not used as deployment proof. Trusted-CA HTTPS prototype matches the final saved snapshot.

## Limits

Viewer denials, SSO eligibility, duplicate document prevention and workflow dependencies are covered by the existing/presentation regression suites. Browser checks used one actual Administrator session. This was not a new multi-account, full PR→PO→voucher endorsement lifecycle test. No workflow rule, SSO source record, legacy import or database structure changed. MMO-PO/MMO-PSMD remain for the owner to add in SSO. No production release occurred.
