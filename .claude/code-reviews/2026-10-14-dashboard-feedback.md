# Dashboard browser feedback review

Date: 14 October 2026. Scope: the existing OPTS Dashboard prototype and its Storybook stories.

## Delivered

- Office workload/Turnaround and Recent activity now appear together before the document register. Document volume remains below it. The DOM and reading order match the visual order.
- A prominent official shadcn Alert appears before the Dashboard heading. Update, scheduled maintenance, emergency maintenance, reminder, and no-announcement states have dedicated stories. Ordinary notices support dismissal; emergency notices remain visible. Details and simulated incoming notices work locally.
- User name, role, account menu, and office now occupy the desktop sidebar footer and mobile navigation footer. The Casebook header retains notifications. The horizontal-navigation alternative retains its desktop account entry.
- Design reference, product boundary, standalone HTML, and served catalog were refreshed.

## Validation

- `pnpm typecheck`: PASS across all workspace packages/projects.
- `pnpm build:all`: PASS for all five catalogs; OPTS has 77 stories.
- `docker compose up -d --build`: PASS; refreshed served HTML verified through Browser.
- Browser: annotated 1637×1314 viewport, intermediate 1024×1000, and phone 390×844. Workload/activity precede the register, with volume below it. No horizontal overflow at phone/tablet widths.
- Tested four notice types, no-notice state, normal dismissal, nondismissible emergency, incoming-notice restoration, notice details and Escape. Mobile navigation contains the account footer; its dialog bounds are x=0 and width=292.5 at the 390px viewport.
- Account menu, Viewer display, MBO office filtering and matching totals/activity, and section customization passed. Browser reported no runtime errors.
- Mechanical detector ran once: zero primary findings. It returned 23 advisories for literal sizes/colors outside the compact DESIGN.md frontmatter. Existing density values and notice-specific surfaces remain deliberate local values; no blanket suppression was added.

## Code review

Reviewed the four runtime/story files changed for this revision, plus matching design documentation. No shared primitive source, dependency, backend, or application UI file changed. Announcement state remains local. Notices do not grant access or perform document processing. No unresolved technical issue was found in the scoped revision. The initial missing metrics binding was caught by TypeScript and corrected before final verification.

## Boundaries

Realtime transport, notice publishing, scheduling, persistence, and permissions are not connected. Announcement copy is explicitly labeled demo data. This is a prototype revision, not an application release or an MVP retest. Existing inherited overlay timing remains documented separately.
