# Document QR labels delivery report

15 October 2026. Local OPTS now exposes a shared QR action for existing purchase requests (PR), purchase orders (PO) and vouchers (VCH). This report covers the compact dialog, branded PNG download and printable reference label. Production deployment and physical hardware acceptance are not established.

## Delivered behavior

`DocumentDetail.tsx` opens `DocumentQrDialog.tsx` for all three document categories. The dialog uses existing controls, a maximum width of 320px and a 132px code. Its height follows the content and stays within the viewport. The owner's compact sample governs the presentation: code, reference and actions, with no extra visible header or footer. The existing Dialog supplies accessible naming and close behavior.

The pure `DocumentQrLabel.tsx` renderer uses pinned `qrcode.react` 4.2.0. It encodes the saved reference exactly and renders a 1024px square canvas. The existing official LGU seal occupies a centered, excavated 148/1024 region. H error correction and a four-module white quiet zone remain explicit. Download QR produces a PNG containing only that branded code. Sanitization changes the filename only.

`GET /transactions/{transaction}/qr-label` uses the authenticated route group and existing `view` policy. It supplies the saved reference and the category-specific return route. The print page shows the same code with its reference and native Print / Save as PDF. Print CSS produces a 35mm code in a 60mm label container on A4, with text allowed to wrap. Controls, instructions and body-level Connect widget elements are hidden in print.

The controller performs no numbering, document, receipt, workflow or audit write. Existing endorsement and receipt scan fields consume the exact reference without a new payload parser. No SSO behavior changed for this feature.

## Verification

| Check | Evidence and result |
| --- | --- |
| Application suite | `/tmp/qr-label-full-tests.log`: 807 tests and 4,434 assertions passed; four deprecations and 37 PHPUnit deprecations remain. Six new label tests contain 75 assertions. |
| Application build | `/tmp/qr-label-app-final-build.log`: TypeScript and Vite completed. Existing unresolved-at-build font URL warnings remain. |
| Formatting | Scoped Pint passed, supplied by the parent execution. |
| Hub validation | `/tmp/qr-label-hub-final-typecheck.log` completed all project checks; `/tmp/qr-label-hub-final-build.log` ends with all five project libraries built successfully. Existing bundle and directive warnings remain. |
| Exact payload decoding | Apple Vision independently decoded six synthetic branded PNGs, including office-issued, long and Unicode references. See `docs/design/qr-labels/branded-decode-results.json`. |
| Real app and print decoding | `print-and-app-decode-results.json` records real-app PR-GF-2026-10-008 and synthetic print-proof PR-GF-2026-10-022. Both decoded exactly. |
| Browser | Parent checked existing read-only PR, PO and VCH documents, download/print entry, desktop, 390px phone and 1683px owner viewport. Captures are in `docs/design/qr-labels/`. |
| Print proof | `print-proof.pdf` is one A4 page with only the code and reference. `print-proof.png` is its rendered proof. |
| Technical review | `.claude/code-reviews/2026-10-15-document-qr-labels.md`: pass after correcting printable widget leakage and dialog minimum height. |
| Visual finish | `.claude/code-reviews/2026-10-15-qr-labels-visual-finish.md`: ship within the recorded source/capture scope. The single supplied detector result was `[]`; this pass did not rerun it. |

Browser was the primary tool. The native print panel was initiated and later closed by the owner; no native save completion is claimed. The in-app browser could not capture PDF/export download events. A narrow Playwright fallback covered the public Storybook PNG download and a static print fixture made from the final app DOM/CSS with the actual shared synthetic PR-022 PNG. This establishes the recorded PDF artifact, not a completed native print job.

The new QR dependency has no reported advisory in the supplied audit. The application's 28 pre-existing dependency advisories remain unchanged. Hardware scanner readability, paper stock and printer behavior remain untested.

Only local OPTS was rebuilt, and only the OPTS static catalog was published locally. Runtime readback and source-sync hashes are recorded by the parent separately. A transient SSO rate limit exposed a root-owned local log; the parent restored only that existing log's `www-data` ownership, after which reload worked. This was local environment recovery, not an SSO implementation change. No commit, push or production release is recorded.

## Preserved design system

This is an ordinary Casebook extension. The checked authority is the Hub's `projects/opts/DESIGN.md`, with its existing `.impeccable/design.json`, app `PRODUCT.md` and `docs/design/dashboard-direction.md`. The feature CSS and renderer retain Source Sans 3, cool paper, blue ink, existing controls and the official seal. Neither DESIGN.md nor its sidecar was changed or regenerated.

The fresh documenter used `reference/document.md` and `reference/degraded/documenter.md` because the shipped documenter role was unavailable. This pass checked source, recorded reviews, decode JSON and completed build/test logs. It performed no browser action or implementation edit.

Five-line system summary:

1. Palette: cool paper `#f4f6f8`, blue ink `#1c2c3b`, existing blue actions and white QR ground.
2. Type: Source Sans 3; reference 16px/650/1.3 with tabular numerals, print reference 10pt.
3. Composition: compact 320px maximum dialog, 132px displayed code and existing 44px action targets.
4. Feature rule: preserve the exact saved payload, white quiet zone and official centered seal.
5. Print rule: keep only the 35mm code and reference in a 60mm container; let long references wrap.

Not canonized or repaired: the sidecar still calls the approved world provisional, and older product/direction text retains historical proposal language. Those pre-existing records are outside this narrow extension. Feature dimensions remain local rules rather than new global design tokens.
