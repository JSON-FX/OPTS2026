# QR handoff source sync

15 October 2026. The owner requested live endorsement and receiving after approving the QR prototype. This record supersedes the earlier document-page report's deferred QR implementation boundary. Neither repository was committed or pushed.

## Authority and adaptations

Source authority is Storybook Hub `projects/opts/src/qr-handoff-prototype.tsx`, `qr-handoff.css`, `qr-handoff.stories.tsx` and its existing `DESIGN.md`. Exact proposal fingerprints are in `prototype-source.sha256`. The catalog remains a synthetic interaction reference; app data, permission flags and workflow behavior come from the existing backend.

App source fingerprints are in `source.sha256`. The live workspace reuses existing React18/Tailwind3 shadcn primitives and Casebook navigation. Prototype colors are adapted to the app's HSL token format. The app omits simulator and demo QR panels. Actual active SSO destinations, workflow warnings, action choices and notes replace fixed prototype examples.

## Local runtime readback

The final local image is `sha256:8253d046a7a549e74f9565949eca186e4f6e37d3eaf5ccfb42bb135206ec646a`. Docker reported healthy. All19 runtime source files in this scope match the host SHA256 values; see `runtime-readback.json`. Only the OPTS service was rebuilt and recreated. Shared SSO services were not restarted or modified.

Commands: `npm run build`, `docker compose build opts`, then `docker compose up -d --no-build --wait --wait-timeout 120 opts`. Existing advisory Vite/Browserslist/font warnings remain; TypeScript and Vite completed successfully.

Hub changes for this integration are documentation only. The15 existing QR stories, prototype source, tokens and served catalog are unchanged; no catalog runtime publish was needed.

## Browser and data evidence

CUA Browser exercised the actual app through its normal LGU SSO login. One labeled local QA PR was seeded with a synthetic prior endorsement using the existing workflow and existing SSO account. A private full MySQL backup was saved before setup. No existing procurement, document, office, workflow, role or identity was altered.

Wrong reference and wrong case remained blocked. Matching keyboard input plus Enter unlocked details. Scan again relocked them. Receipt confirmation could be cancelled. Final receipt removed the record from the pending queue and advanced step15 to16 exactly once. A fresh scan was required on the endorsement page. The real office combobox showed the out-of-workflow warning; restoring the expected destination and confirming sent the document to office5, cleared receipt and left step16 until the next receipt. The old receive URL then returned403. No browser console errors were observed.

The linked detail label was corrected to name `awaiting_office` after endorsement. Its final image is `saved-handoff-desktop.png`. Other images show1440x1000,1683x1314 and390x844 layouts, locked/matched states and explicit confirmation dialogs. No horizontal overflow was observed. The independent visual review returned `ship`; see `finish-review.md`.

Fixture procurement9, transaction9 and PR5 were soft archived after readback. Its three test notifications were marked read. Audit actions remain preserved and clearly labeled QA. `local-qa-readback.json` records the final archived state. No automatic reference sequence was consumed.

Physical scanner hardware was unavailable. Browser testing verifies keyboard input and Enter behavior, not the identity of a scanner or physical possession of paper. Final confirmation is an explicit office attestation. Camera scanning and QR generation/printing were not added.
