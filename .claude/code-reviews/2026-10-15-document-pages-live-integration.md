# Review — document page integration

Scope: nine PR/PO/VCH adapters, five shared Documents source files, document CSS, shared shell/Settings, create reference policy, presentation props, timeline serialization and guarded local MMO-PO repair. Earlier unrelated dirty Dashboard/Procurement work is preserved and outside this review.

## Findings resolved

1. Missing default workflows disabled Create in the first UI draft. Existing backend permits documents without a workflow. Submission now remains available with an honest warning.
2. A mobile first-child flex selector stretched the lone overflow icon across the page. Limit flex to a processing action and keep overflow44px square/right aligned. Final phone capture and DOM measurement confirm44×44, no horizontal overflow.
3. Action history used timezone-free strings while creation timestamps used UTC ISO. Browser showed completion before creation and an8-hour display difference. Existing TimelineService now emits UTC ISO; regression test asserts exact offset. Live voucher history now sorts completion through creation correctly at9:23–9:24PM.
4. Final suite hit a random duplicate in the existing fund factory's three-letter abbreviation. Use Faker unique generation for its database-unique column. Final full suite passes.

5. Embedded Storybook accessibility scan found two unnamed complementary landmarks. Name workspace navigation and document context distinctly in live/shared prototype shells; no visual behavior changes.
6. Targeted catalog builds omitted the Hub base path and stalled embedded previews. Rebuild with STORYBOOK_HUB_BASE=/opts/ and verify served iframe/module paths.

## Technical review

Fresh independent review found no remaining actionable reference allocation, authorization, adapter, action error handling, route or real-data issues. Save-time allocation and uniqueness remain server-side. Receive/resume preserve validation errors and close only on success. Actual controller permission flags remain authoritative. No generic SSO auto-merge or schema changes added.

The operational repair was manually checked against the actual foreign-key schema and affected counts. It is restricted to local MySQL and reviewed identities, defaults dry run, writes a private selective backup before any mutation, updates under row locks, verifies no legacy references remain and is idempotent. Full database backup preceded apply. Full SSO sync and read-only re-run passed.

## Visual gate

Initial disposition: fix (mobile overflow control).
Final scored disposition: ship. No visible regression in the bounded fix pass.
Desktop1440×1000, phone390×844 and reported1683×1314 captures exist in .impeccable/review. The initial cached browser screenshot frames were rejected and replaced with verified full viewport captures before acceptance.

## Verification

765 PHPUnit tests /4,123 assertions pass. Existing 4 runtime and 37 PHPUnit deprecations remain. TypeScript/Vite app build, PHP formatting, full Hub typecheck/all 5 catalog builds, final OPTS rebuild, source/image checks and served HTML digest checks pass. No commit, push or production release requested.
