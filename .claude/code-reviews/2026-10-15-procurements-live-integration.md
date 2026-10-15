# Procurements live integration review

Scope: approved prototype → live Inertia resource pages; shared shell/theme retirement; selected-year Settings; fixed combobox Storybook references. Existing dirty Dashboard changes are prior authorized work and remain preserved.

## Findings addressed before live mutation QA

- P1: a delayed register response could change the selected fallback row while Edit/Archive was open. Dialogs now pin their opened ProcurementRecord; edit receives that snapshot and archive uses its fixed ID. Background results only affect register selection.
- P2: numeric cmdk values did not match particular descriptions. Every option includes its label plus directory names in search keywords.
- P2: stale responses could overwrite a newer draft query before debounce. Response synchronization preserves drafts made after submission; explicit browser history is handled separately.
- P2: history serialization omitted reasons. Include the existing reason field and assert it in the full-history regression.
- Visual adaptation: installed Tailwind 3 SelectTrigger fills width; constrain compact sort control to prototype width. Restore prototype automatic table layout.

## Reviewed boundaries

All mutations use existing resource routes/FormRequests. Viewer processing remains denied server-side. SSO office values use local IDs and only active mapped offices are offered for new choices. Historical names remain readable. PR existence permits PO; PO existence permits voucher. Explicit document serialization avoids unrelated audit fields and model append queries. Source is pinned from the latest Hub artifact; live app uses database data, never prototype fixtures.

## Validation status

Backend focused tests after review: 16 tests / 280 assertions pass. First full suite: 715 tests / 3597 assertions pass with 512 MB PHP memory; default 128 MB composer invocation exhausted memory. Four existing runtime and 37 PHPUnit deprecations remain. Frontend TypeScript/Vite build passes. Final Browser parity and final-source tests pending in implementation report.

## Final review and verification

Browser testing found two additional integration gaps, both fixed and covered by regression tests:

- The browser Manila date was ahead of server UTC before midnight. Form defaults, maximum and client validation now use the server's explicit `formOptions.today`; server rules are unchanged.
- Controller success redirects were not shared to Inertia. Shared lazy `flash.success/error` now reaches the inline confirmation and shell toast consumers.

Independent focused final review passed, 8 tests / 200 assertions. Final full suite passes: 717 tests / 3623 assertions. Existing deprecations only. Browser create/edit/archive, pagination, selection, filtering, dialogs, real timeline/routes, Settings and responsive shell checks pass. See `.claude/reports/procurements-live-integration-report.md` for exact boundaries and source/deployment readback.

Final screenshot review found the Tailwind 3 full-width page-size trigger squeezed the page counter onto three lines. Its width is now intrinsic and the counter does not shrink or wrap. Frontend/build and live desktop/phone footer checks were repeated after this scoped CSS adaptation.
