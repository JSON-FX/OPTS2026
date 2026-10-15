# Casebook document source sync

The owner authorized PR/PO/voucher page integration on 15 October 2026 with the six browser annotations recorded in acceptance.md. The source is the approved Storybook Hub working tree on feature/lgu-portal-storybook, not an inferred package release. App code is on codex/sso-directory-integration; neither branch was committed or pushed by this task.

Canonical proposal: Hub projects/opts/src/documents-prototype.tsx, documents.css, document-controls.tsx and documents-data.ts. Live counterparts: resources/js/Components/Documents, resources/css/documents.css, nine category page adapters and DashboardLayout/Profile Settings. Existing app shadcn React18/Tailwind3 primitives were reused after official registry inspection. Hub React19/Tailwind4 source is adapted deliberately; fixture permissions/data are never imported into live pages.

Latest user amendments take precedence over historical prototype notes: PR creation may explicitly override the full reference. Automatic annual allocation remains default. Automatic year is current and disabled. PR edit reference remains fixed. Office sits above account; Settings owns Help. The sidebar system-name footer is removed.

Exact source fingerprints are in source.sha256. Runtime app files were compared with the rebuilt healthy local image. Served documents.html and qr-handoffs.html were compared byte-for-byte by SHA256 with the final OPTS static build. Only OPTS catalog was published, preserving other hosted projects.

QR handoff stories remain a separate proposal. Local confirmation changes no app records. Real QR sample images encode their exact shown reference; a physical scanner gun was not available for acceptance.

The targeted catalog publish command includes `STORYBOOK_HUB_BASE=/opts/`. Omitting it builds root-relative iframe assets and stalls the embedded preview. Final publication verifies the embedded story as well as standalone HTML.
