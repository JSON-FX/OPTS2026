# Document QR label technical review

Review date: 2026-10-15. Scope: the document QR label plan and both amendments, including the compact owner-supplied reference and centered existing seal. This review excludes unrelated changes already present in the working tree.

## Result

Code review passed. No technical issues detected in the final reviewed source.

One medium finding was corrected during review. The initial print rules left the existing Connect launcher, unread badge, and any open conversation panel printable. The widget appends these elements directly to `body`, outside `#app`. The final print rule at `resources/css/document-qr-label.css:36` hides those body siblings only while `.ql-page` exists. The application and Hub CSS copies match exactly.

The parent's Browser check later found that shared `.op-root { min-height:100dvh }` expanded the compact dialog to the viewport height. The updated `.op-root.ql-dialog` rule at `resources/css/document-qr-label.css:5` overrides that minimum with `min-height:0`, retains automatic height, and limits height to the viewport minus 32px with vertical scrolling. Its specificity exceeds the shared root rule in both the app and Hub. This CSS correction passes source review; visual acceptance remains pending the parent's rebuilt Browser check.

## Scope and statistics

- Files modified: seven existing feature paths across the app and Hub, including dependency manifests and lockfiles.
- Files added: nine feature files, containing 368 lines across the app and Hub.
- Files deleted: zero.
- New lines: 368 in added files; changes inside existing files cannot be attributed accurately against HEAD because earlier work remains uncommitted.
- Deleted lines: zero in added files; existing-file counts are likewise not isolated from earlier work.

The reviewed application paths are the new controller, authenticated GET route, QR renderer/helpers, compact dialog, shared detail action, printable page, label CSS, feature tests, and exact `qrcode.react` dependency entries. Hub scope includes the synchronized renderer/CSS, label stories, sample renderer/download integration, package entry, and lockfile entry. Existing authorization, scan validation, presenter routing, application initialization, and widget sources were read only to trace integration.

## Evidence and assessment

- The controller authorizes the existing `view` policy before returning the persisted reference. It performs no document, workflow, numbering, receipt, or audit write. Route model binding excludes soft-deleted transactions.
- The shared detail action is available to readers, including Viewer. The print page returns through the presenter's real category-specific entity route.
- The payload is the unchanged saved reference. Filename sanitization affects only the downloaded filename. React escapes displayed reference text, and the PNG uses a trusted same-origin seal rather than a user-provided remote image.
- The installed `qrcode.react` implementation merges the explicit 132px canvas style over its 1024px default display size. High error correction, four quiet-zone modules, and the excavated 148/1024 logo region are explicit. The canvas retains high pixel resolution for PNG output.
- The image preparation helper locates the library's hidden sibling image. It waits for image decoding and two animation frames when the image is incomplete. Download and print failures display application error feedback. No demonstrated logo-readiness defect was found in source review.
- The print rule overrides the inline canvas display dimensions with 35mm dimensions. The 60mm label grows vertically, references wrap with `overflow-wrap:anywhere`, and controls/instructions are hidden. The corrected body-sibling rule removes the widget and portals from this page's print output.
- Renderer and CSS source copies compare byte-for-byte between the app and Hub. The dependency is pinned to 4.2.0 in both projects, with installed React 18/19 peer compatibility.
- Scoped `git diff --check` passes. The parent supplied successful backend results: 807 tests and 4,434 assertions, including six new tests and 75 assertions. Those results were not rerun by this reviewer.

This is an independent source review. Browser rendering, actual downloaded PNG decoding, print preview, runtime readback, and physical scanner/printer acceptance are outside this review and remain separately owned by the parent task. No browser, subagent, implementation edit, or dependency mutation was used here.

## Final source hashes

SHA-256; Hub paths below are relative to `/Users/jsonse/Documents/development/storybook-hub`.

```text
d24f484f0ce2c4ff5cb06d01912b94c92b3b41b62ada11a43b88f1e7dd2541fb  app/Http/Controllers/DocumentQrLabelController.php
4a335915c7ddc670d0af88896d4ebad5e507a958b5f60087269ea013d2d1f475  routes/web.php
648262c9fbbc29a0af107929a328fc10ad7813900eff06a3cec7d1a91e1d8022  resources/js/Components/Documents/DocumentQrLabel.tsx
609c9d539b17171db2d47c6989b1a30d89540e70596becac40dd8a26b060004d  resources/js/Components/Documents/DocumentQrDialog.tsx
cf9d065c0aa68d0fc58eb03841f6839cb5cf8421a3566940e45ca4d6eb83a2a9  resources/js/Components/Documents/DocumentDetail.tsx
eedf8ef0bd9f51720b31ac8a41f7344b17b32192b5f81aedcc7f45acf2a465f8  resources/js/Pages/Transactions/QrLabel.tsx
07a2a93cc807b660b1dc426e0548ec2552ac93d8a183322a771ce65b7587c8e4  resources/css/document-qr-label.css
b117271092572c34cb95e0edfc3b4f031125656cfae4e76ea56ba4dd89da982a  tests/Feature/DocumentQrLabelTest.php
78ce867e0c1cea4e1b1db6bb3e8e96a5759802559c9c9012fee8311ac58d8785  package.json
496a457e11e5ca20ce2c3e0ec7e6e61a86b7b1cf8a8edb39b318d7d24c8d3279  package-lock.json
648262c9fbbc29a0af107929a328fc10ad7813900eff06a3cec7d1a91e1d8022  HUB/projects/opts/src/document-qr-label.tsx
07a2a93cc807b660b1dc426e0548ec2552ac93d8a183322a771ce65b7587c8e4  HUB/projects/opts/src/document-qr-label.css
db051b92c48b54ab0c7401abb227a508a48710973ffc25ad2d21c0d590844555  HUB/projects/opts/src/document-qr-label.stories.tsx
8e5fa75e8100caed544ab98eea1e2af710045525724fc064d5edee9440713ada  HUB/projects/opts/src/qr-handoff-prototype.tsx
a2a4fac422232843853dadcae4ba9911fb7ea678da4d2f84fb0ab9ecee897c40  HUB/projects/opts/package.json
590cf1e01471a6191e40dc284dc272f4f5506d5c38a9f831890f845c2757b14d  HUB/pnpm-lock.yaml
```
