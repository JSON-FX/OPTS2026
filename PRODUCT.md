# OPTS product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Municipal office endorsers receive, endorse, and complete assigned procurement documents. View-only officials, including the Mayor when granted SSO guest access, monitor general progress. Administrators manage workflows and reference configuration. The owner confirmed a balanced Dashboard adapted to each role.

## Product Purpose

Track a procurement through one Purchase Request (PR), one Purchase Order (PO), and one voucher, with office workflows, receipts, endorsements, timelines, and turnaround monitoring. A user should understand current progress and find the next permitted action.

## Operating Context

OPTS serves the Local Government of Quezon, Bukidnon. Office staff use a browser during daily processing; officials use the same data for oversight. Workflows route documents between offices. Duplicate references are prohibited. SSO owns users, roles, office assignments, and operational offices.

## Capabilities and Constraints

Dashboard exposes document/status totals, office workloads with document drill-down, recent activity, stagnant documents, office turnaround, out-of-workflow incidents, and document volume. A selected year scopes data. User settings control visible sections. Viewer access never grants processing controls. Standard SSO users map to Endorser; administrator and super_administrator map to Administrator. Legacy imports are retired.

Current application: Laravel12, React18, TypeScript, Inertia, shadcn new-york, Tailwind3. The approved Casebook Dashboard, Procurements and PR/PO/voucher workspaces are integrated in the local application. QR endorsement and receipt remain separate Storybook proposals pending review. The owner requested a complete official shadcn component catalog. The hub uses its existing React19/Storybook10/Tailwind4 stack; live integration requires an explicit source sync and compatibility check.

## Brand Commitments

Use the OPTS name, the municipality's name, and the existing official seal. The owner approved Casebook after prototype review. Source Sans 3, cool paper, white panels, blue ink and compact office controls define the shared direction. Document page integration was authorized on 15 October 2026.

## Evidence on Hand

The local MVP acceptance created procurement#3 with completed PR-GF-2026-10-003, PO-2026-10-002, and VCH-SSO-2026-10-002. That historical acceptance snapshot had3 procurements,3 PRs,2 POs,2 vouchers; it is not a current record count. The prototype may use clearly labeled fixtures; no demonstration value is a production claim. Current application source and local Browser observations define supported functions.

## Product Principles

- Make next actions and office ownership clear.
- Preserve role eligibility and source-of-truth boundaries.
- Keep references, statuses, and timestamps readable and consistent.
- Show useful empty, loading, and unavailable states.
- Preserve full office names in transaction timelines; show abbreviations in office selectors.
