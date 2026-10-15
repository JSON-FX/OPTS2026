# Automatic references review

Scope: yearly automatic PR/PO/VCH numbering and manual PO/VCH override; existing procurement ID retained. Read service, controller/request changes, shared form fields and current create/edit wiring. Preserve unrelated dirty Dashboard/Procurements work.

P1 cross-category manual-prefix collision: fixed. Historical/deleted reference scan uses recognizable prefix across all owning categories. Manual references lock and advance their actual prefix counter, including lowercase. Verified via focused regressions, full PHPUnit and real MySQL mixed-prefix contention.

Initial contention probe found shared-lock upgrade deadlocks from INSERT IGNORE. No-op upsert corrected exclusive row acquisition; final probe passed. Transaction rollback, counter limit, manual duplicate field errors, immutable PR update, stale client suggestions, and role/prerequisite behavior have executable coverage.

Fresh read-only reviewer final: no remaining actionable findings. React review: existing controls reused, stable IDs/labels, explicit manual mode, numeric fields read-only, no render-side writes or new data fetching. Prototype-only fixture counters are explicitly simulated; backend is allocation authority.

Final results in `.claude/reports/automatic-document-references-report.md`. No commit/push or production release.
