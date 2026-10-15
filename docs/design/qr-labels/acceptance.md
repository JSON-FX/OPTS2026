# QR label local acceptance

15 October 2026. The compact branded QR feature passes the recorded local software checks. Physical scanner/printer acceptance and production release remain untested.

## Contract and evidence

| Requirement | Result | Evidence |
| --- | --- | --- |
| Existing PR, PO and VCH detail exposes QR | Pass | Shared `DocumentDetail.tsx` action; parent Browser checked existing read-only documents across all categories. |
| Existing read access governs print route | Pass | Authenticated route and `DocumentQrLabelController` view policy; tests include Viewer, guest, missing and archived records. |
| Exact persisted reference is encoded | Pass | Six branded synthetic PNGs decode exactly, including long, Unicode and office-issued references. See [decode results](branded-decode-results.json). |
| Compact approved composition | Pass | [Desktop](desktop.png), [phone](mobile.png) and [1683px viewport](user-1683.png) show the compact code/reference/actions dialog. No extra visible header/footer or viewport-height card. |
| Centered existing official seal | Pass | Shared renderer specifies 148/1024 excavated logo, H correction and four-module quiet zone. [Downloaded sample](download-sample.png) is 1024px square. |
| Existing scan payload remains compatible | Pass for payload | Exact reference is preserved; no URL, prefix, JSON, trimming or case conversion. This feature does not claim a new hardware scan test. |
| Actual app QR decodes | Pass | Real PR-GF-2026-10-008 decoded from app evidence; see [app/print decode results](print-and-app-decode-results.json). |
| Print contains only QR/reference | Pass for automated proof | [One-page PDF](print-proof.pdf) and [rendered proof](print-proof.png) show synthetic PR-GF-2026-10-022. The proof's code independently decoded exactly. |
| Print size and exclusion | Pass for source/proof | 35mm QR, 60mm container, A4 with 12mm margins. Controls, instructions, notifications and body-level widget are hidden. Long text wraps. |
| No document/workflow writes | Pass | Six feature tests cover unchanged transaction data and absent action/status-history writes. Controller is read-only. |
| Application validation | Pass with existing warnings | 807 tests / 4,434 assertions, final TypeScript/Vite, scoped Pint. Deprecations remain in suite output. |
| Hub validation | Pass with existing warnings | Final typecheck and all five project builds completed in `/tmp/qr-label-hub-final-*.log`. |
| Technical/visual review | Pass / ship | [Technical review](../../../.claude/code-reviews/2026-10-15-document-qr-labels.md) and [visual finish](../../../.claude/code-reviews/2026-10-15-qr-labels-visual-finish.md). |

## Evidence boundaries

The parent used Browser for the authenticated application and public catalog. Native print was initiated, then the owner closed the panel. The in-app browser lacks the required PDF/export download capture capability. Playwright was used only for the public catalog download and a static PDF fixture using final app DOM/CSS and the shared PR-022 PNG. The PDF proof is synthetic; it does not represent a newly created application document or a completed native save.

Apple Vision decoded six synthetic branded images and the separate real-app/print images. This proves the recorded raster payloads under that decoder. It does not certify scanner hardware, printer resolution or label stock.

No documents were created or processed for this label check. No SSO implementation, role, workflow, numbering or production data changed. Only local OPTS runtime/catalog publication is recorded; source hashes and runtime readback remain in the parent's delivery record.

The existing Casebook DESIGN.md and sidecar remain preserved. Documentation records this feature's compact dimensions without promoting them into global system rules.
