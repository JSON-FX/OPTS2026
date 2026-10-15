# Annotation fix review

Scope: small Procurements UI corrections, Dashboard row selection and matching OPTS references. Reviewed against `docs/issues/issue-procurements-ui-annotations.md` and final source. Existing unrelated dirty changes preserved.

No outstanding findings. Domain CSS changes are scoped. Badge anatomy uses installed primitive props. Modal title focus prevents accidental tooltip opening while retaining keyboard tooltip access and existing close-focus restoration. Dashboard row handler excludes interactive descendants and preserves loading guard, reference button and arrow/modal behavior. No backend mutation, authorization or workflow change.

Browser caught a specificity issue in the first checkbox correction. The final `.op-root .pp-filter-panel [role="checkbox"]` rule wins against the shared :is selector; desktop and phone measurements both confirm 16×16. Final Docker TypeScript/Vite build, 39 regressions / 926 assertions, Hub typecheck/all five builds and scoped diff checks pass. Verification details and source hashes: `.claude/reports/2026-10-15-ui-annotations.md`.
