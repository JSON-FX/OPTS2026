# Annotation root cause and scoped fix

Owner's 15 October feedback covers Procurements status spacing, pagination, excess register height, office tooltip, checkbox sizing and timeline heading. Follow-up adds Dashboard whole-row selection. PR/PO/voucher creation-page redesigns are deferred. This is a local annotation issue, not a GitHub issue; no external comment or commit is authorized.

## Evidence and cause

- `ProcurementWorkspace.tsx` StateBadge lacks data-slot=badge, so `.pp-status[data-slot="badge"]` never applies its 4px gap. Grid stretches the badge in document overview.
- Installed SelectTrigger uses w-full. Intrinsic widths are required for pagination and timeline controls. Footer no-wrap correction exists in current source; verify loaded build and explicit control sizing.
- Register grid stretches the list to match the taller preview. White card background therefore continues beneath the footer. Independent card borders and intrinsic grid alignment remove that filler.
- ProcurementFacts renders full office name below the abbreviation. Quick-view autofocus lands on the first tooltip trigger. Remove duplicate name and focus the dialog title on initial quick-view opening; keyboard focus still shows the tooltip afterwards.
- Global minimum button height is 38px. Radix checkbox has 16px width but inherits 38px height. Scope a square 16px checkbox rule.
- Timeline SelectTrigger w-full squeezes heading copy and overflows. Give the heading flex child min-width:0 and filter intrinsic width; wrap on narrow screens.
- Dashboard DocumentRegister handles selection only on the reference button. Add row selection while excluding interactive descendants; keep arrow modal behavior and loading guards.

Severity medium; complexity low; confidence high. All causes are visible in current source/DOM measurements. No data, auth or workflow change is needed.

## Fix and verification

Modify domain JSX/CSS and corresponding Storybook Procurements source. Preserve installed official shadcn primitives. Check status gap/width in register, quick/detail/overview; footer, square checkbox, timeline control bounds, tooltip initial/keyboard behavior, Dashboard purpose/status/office cell clicks and arrow behavior. Verify desktop and phone. Run app build, focused existing feature tests, Hub typecheck/all catalog builds, diff review and local Docker deploy. Capture proof and sync reference hashes. No fixture mutation needed.
