# Procurements annotations and Dashboard row selection

Implemented locally, 15 October 2026. Scope: Procurements annotations 1–9 and follow-up Dashboard document-register row selection. PR, PO and voucher creation-page redesigns, supplier combobox, workflow preview and reference preview are deferred together as requested. No procurement data, workflow, SSO role, endpoint or schema change. No commit/push or production release.

## Root cause and changes

See `docs/issues/issue-procurements-ui-annotations.md` for the scoped investigation. Missing badge data-slot prevented the intended 4px gap and padding. Grid stretched badges; shared full-width SelectTrigger squeezed timeline headings. Register grid stretched its left white surface to the taller preview. Office facts duplicated full names, and modal autofocus opened the tooltip immediately. Shared button minimums overrode checkbox height. Dashboard selection existed only on the reference button.

- Added the domain badge's expected attribute and intrinsic sizing. All register/quick/detail/document-overview statuses share the fix.
- Made pagination controls intrinsic and nonshrinking, with an explicitly labeled single-line page counter.
- Gave register and contextual preview independent content heights and panel boundaries. The list ends immediately after its footer.
- Removed duplicate full-name office text. Full names remain available through the office tooltip. Quick-view initial focus lands on its title; keyboard Tab still reaches and opens the office tooltip.
- Scoped 16px square checkbox dimensions above the specificity of `.op-root :is(button, a.inline-flex)`. That :is selector inherits its most specific argument, explaining why the first two-class correction still lost.
- Gave timeline copy a shrink-safe flex basis and the filter intrinsic width. Phone filter wraps below the copy.
- Dashboard non-action row cells now call the existing selection handler. Interactive descendants retain their own behavior, with loading guard preserved. Existing reference button remains keyboard accessible; arrow still opens modal.
- Updated canonical OPTS Storybook Procurements and Dashboard sources and saved standalone HTML references. Existing 19 Procurements / 102 total stories remain.

## Review and validation

Focused review found no outstanding logic, security or ownership issue. No new dependency or primitive replacement. Official shadcn badge/checkbox/tooltip source and tooltip example were inspected through MCP; installed primitives were reused.

- TypeScript/Vite application build: pass in final Docker build.
- Existing focused PHPUnit regressions: 39 tests / 926 assertions; no failures/errors, four existing runtime deprecations.
- Hub workspace typecheck: pass. All five catalog builds: pass on final source. Existing bundler warnings remain nonblocking.
- App and scoped Hub `git diff --check`: pass.
- Only OPTS application container rebuilt/recreated. Healthy; trusted-CA HTTPS `/up` succeeds.
- Only OPTS catalog copied to running Storybook container, preserving unrelated catalogs.

## Browser verification

Primary Browser tool used; no alternative E2E connector. Actual local Administrator session; no records created or changed.

Desktop 1683×1314 and phone 390×844 checks:

- Badge icon gap: 4px. Created overview badge approximately 63px wide inside a 570px container, rather than stretching across it.
- Page counter: `1 / 1`, single line (17.4px height). The live three-record dataset correctly disables previous/next. Refreshed Storybook next/previous pagination exercised both pages.
- Register surface extends only its 1px border below footer; no white filler to match preview height.
- Checkbox: 16×16 on desktop and phone. No horizontal page overflow.
- Timeline filter approximately 126px wide on desktop, inside its heading. Phone heading copy gets 299px and filter wraps below it without overlap/overflow.
- Quick view initially focuses heading and has zero visible tooltips. Keyboard Tab opens the full historical office name tooltip. Standalone reference matches this behavior.
- Dashboard purpose cell selects PO-2026-10-002, status cell selects PR-GF-2026-10-003, office cell selects VCH-SSO-2026-10-002. Adjacent preview heading matches each selection. Arrow opens exactly one overview dialog.
- Storybook checkbox is also 16×16. Phone quick view has no horizontal overflow. Viewport override reset after checks.

Proof PNGs: `docs/design/annotations-procurements-register.png`, `annotations-procurements-timeline.png`, `annotations-phone-timeline.png`, `annotations-dashboard-row-selection.png`.

## Source / local readback

Final image `sha256:838e34618d1372d451c471b3fa9009e940cd0429029869307b4e9d2e0690fe82`, healthy. Workspace/container file hashes match:

| File | SHA-256 |
| --- | --- |
| Procurement CSS | `785f723df28ee4f8ee055c4c20bcdedc6cf8b3ab88fab3118edbfa0cacfc21ed` |
| ProcurementWorkspace | `b7c44fafdc6fccc1ce42d9688d8755258442ee710a255c341bd80e00750254a8` |
| Dashboard DocumentRegister | `c77a5175c6ce63b5a6f52e56834832ab7543a0b757cef99292d8497b0eca79d9` |
| Hub Procurement TSX | `26e8f82a27b979a1fd17173cb56c7f54200470b81a8cc0d6589daeef9d47fa17` |
| Hub Procurement CSS | `622d6c4b417258e6cab3080f61327ad54c1ce09d51b7571ef1cbb6befbb97558` |
| Procurements HTML: source/built/served/app snapshot | `18675130205d0a5b8733cf0f49e86e7fa2342926f7996c4378e742f70a14d4bd` |
| Dashboard HTML | `aad98c1ad78b245ee04090d30ae7f9a8ae5cf243bf8bf6b86e81131eb4b73f62` |

Reduced-motion CSS remains unchanged. This pass does not claim another full procurement endorsement lifecycle or new multi-role Browser coverage.
