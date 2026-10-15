# Feature: printable document QR labels

## User story
An authenticated office user can print a QR label for an existing PR, PO or voucher and attach it to its paper document. The QR payload is the exact saved document reference consumed by the existing mandatory scan flow.

## Decisions and scope
No blocking product question remains. Reuse the existing read permission and Casebook controls. The shared purchase request (PR), purchase order (PO) and voucher (VCH) detail pages open a compact QR dialog. The dialog contains the code, saved reference, Download QR and Print label actions. It has a maximum width of 320px, a 132px displayed code, automatic height and a viewport height limit. It does not add a visible title, category header, procurement footer or tall card. The accessible title and description remain available to assistive technology.

Generation runs locally using pinned `qrcode.react` 4.2.0 canvas output. The canvas is 1024px square and downloads as a PNG containing only the QR. The existing official LGU seal is centered in an excavated 148/1024 region. Error correction is H, and a white four-module quiet zone surrounds black modules. Encode only the saved reference, without a URL, prefix, JSON, trimming or case conversion. Filename sanitization affects only the filename. Long references wrap below the displayed code without clipping.

Print label opens the authenticated read-only page. Native Print / Save as PDF prints a 35mm QR above the reference in a 60mm container on A4 paper at actual size. Its height grows with the reference. Controls, instructions, navigation and body-level widget elements do not print. Printer-specific roll templates and batch labels remain separate requests. Generation, download and printing require no external QR service or document/workflow writes.

## Context
- Shared detail/action source: resources/js/Components/Documents/DocumentDetail.tsx.
- Existing data/types: Documents/types.ts; Transaction model and TransactionPolicy::view.
- Read routes: routes/web.php authenticated group. Soft-deleted transactions stay unavailable.
- Entity return route: existing TransactionHandoffPresenter::entityShowRoute.
- Casebook authority: Storybook Hub projects/opts/DESIGN.md. Existing official shadcn Button was inspected through MCP and reused.
- Library official docs: https://github.com/zpao/qrcode.react#available-props (value, canvas, marginSize, level, imageSettings).

## Tasks, in order
1. Add and pin qrcode.react to app and OPTS catalog. Validate dependency metadata/React18 and19 compatibility.
2. Add DocumentQrLabelController and authenticated transactions.qr-label route. Authorize view; supply only persisted reference/category/procurement ID and the existing category return route. Tests cover all categories, Viewer read permission, guest/missing/archived records, exact override strings, and no document/action writes. Validate focused PHPUnit test before UI work.
3. Create pure shared DocumentQrLabel React component and label CSS. Copy exact source to the OPTS catalog with a source sync record. Compose the compact dialog and authenticated print page with existing Button and Dialog controls. Add the QR label action to the shared detail. Validate TypeScript/Vite.
4. Add real generated QR label stories for PR, PO, voucher, office-issued, long and Unicode references. Use fixtures only in Storybook. Validate pnpm typecheck and pnpm build:all. Publish only the OPTS static output.
5. Run full PHPUnit, scoped Pint, dependency audit, source review and one scoped design detector. Build/recreate only local OPTS. Browser check all category actions, desktop/phone layout, label and native print preview. Independently decode generated codes from actual rendered labels and compare exact references. Physical printer/scanner certification remains pending hardware.
6. Fresh technical/visual review and ordinary-extension documentation. Record runtime/source hashes and evidence. No commits, pushes, SSO changes or production release.

## Acceptance
- PR, PO and voucher saved detail pages expose a QR label action under existing read access.
- QR decoding yields the exact persisted reference for automatic and manual references, including case and Unicode.
- The compact dialog follows the owner's QR/reference sample without extra visible headers or footers. Download outputs a 1024px PNG containing the code, centered official seal and intact quiet zone.
- Printing outputs only the 35mm code and readable reference within a 60mm container. Navigation, controls, instructions and the body-level widget do not print.
- Label printing never renumbers or mutates documents, receipts, workflow, roles or audit actions.
- Existing mandatory scan flow accepts the generated reference without additional parsing.
- App/catalog validation, real Browser review, decode verification and local runtime readback pass.

## Verification commands
`php -d memory_limit=512M vendor/bin/phpunit --colors=never tests/Feature/DocumentQrLabelTest.php`

`php -d memory_limit=512M vendor/bin/phpunit --colors=never`

`npm run build`; scoped `vendor/bin/pint --test`; `git diff --check`.

Hub: `pnpm typecheck`, `pnpm build:all`; publish only projects/opts/storybook-static into the running catalog.

Browser is the primary E2E tool. Only a demonstrated missing print-emulation capability justifies a narrowly scoped fallback for automated print-to-PDF inspection.

## Amendments
- 2026-10-15 — Owner supplied the existing Storybook sample screenshot and rejected the larger label presentation. Replace the extra header/footer with a compact QR/reference dialog and a Download QR action. PNG download contains only the black/white code with its quiet zone, matching the catalog sample. Native print retains the reference below it. Use the library canvas renderer at high resolution for PNG output and the same compact source in Storybook.
- 2026-10-15 — Owner requested the existing LGU seal centered in each QR. Use a small excavated logo region and high error correction, retaining the four-module quiet zone. Verify branded downloads independently against exact references.
- 2026-10-15 — Documentation reconciliation: the decisions, task 3 and acceptance above now describe the final canvas, compact dialog and branded print/download implementation. The amendments retain the history of the earlier SVG/header/category presentation. Acceptance evidence is recorded in `docs/design/qr-labels/acceptance.md`.
