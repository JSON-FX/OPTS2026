# Procurement search review

Scope: the private SearchChoice composition, its four procurement consumers, and the corresponding Storybook source and regression stories. Login proposals reviewed separately against the approved Casebook system.

Code review passed. No technical issues detected in the scoped changes.

- Option IDs remain unchanged; display labels and existing SSO keywords drive search only.
- A query change resets scroll independently of cmdk selection. Keyboard navigation and Popover focus handling remain upstream behavior.
- Abbreviation/label matches rank ahead of full-name matches. All query tokens must occur contiguously somewhere in the searchable text.
- Explicit Command label fixes the accessible name that cmdk otherwise supplies through aria-labelledby.
- Empty configuration and a nonmatching query have different messages. No synthetic database rows are added.
- No permission, office mapping, workflow, numbering, route, dependency, or backend changes.
- App source is synchronized exactly between the release worktree and original checkout. Unrelated original checkout changes are preserved.

Validation: application TypeScript/Vite build passed. PHP suite passed with 816 tests and 4503 assertions using a 512 MB PHP process; existing PHP/PHPUnit deprecations remain. Default artisan test runner exhausted the local 128 MB child-process limit. Storybook typecheck and all-catalog build passed; final OPTS build also passed after scoped refinements. Browser regression stories confirm retained-scroll recovery, abbreviation priority, keyboard ID selection and mouse Particular ID selection. Full form checks cover abbreviation, full office name, no-match state, and focus outline.

Publication: local Storybook is the review artifact. Application changes remain uncommitted and are not yet deployed to staging. The owner selected Workspace doorway; it is integrated locally through one shared entry component. Production acceptance/activation remains a separate pending delivery gate.
