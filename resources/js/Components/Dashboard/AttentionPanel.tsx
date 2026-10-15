import { useState } from "react";
import {
    AlertTriangle,
    ChevronDown,
    ChevronRight,
    CheckCircle2,
    Search,
    X,
} from "lucide-react";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from "@/Components/ui/collapsible";
import {
    Table,
    TableHeader,
    TableHead,
    TableBody,
    TableRow,
    TableCell,
} from "@/Components/ui/table";
import type {
    DashboardAttentionEntry,
    DashboardProps,
} from "@/types/dashboard";
import { attentionDescription } from "./DocumentDetail";

export default function AttentionPanel({
    entries,
    meta,
    onView,
}: {
    entries: DashboardAttentionEntry[];
    meta: DashboardProps["attentionMeta"];
    onView: (id: number) => void;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState("");
    const rows = entries
        .filter((entry) =>
            `${entry.reference_number} ${entry.current_office_name} ${entry.current_office_abbreviation}`
                .toLowerCase()
                .includes(search.toLowerCase()),
        )
        .sort(
            (a, b) => b.delay_days - a.delay_days || b.idle_days - a.idle_days,
        );
    const officeCount = new Set(entries.map((entry) => entry.current_office_id))
        .size;
    const firstEntry = entries[0];

    if (!meta.total)
        return (
            <div className="op-all-clear">
                <CheckCircle2 />
                <span>No documents need attention in this view.</span>
            </div>
        );

    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className="op-attention-group"
            aria-label="Documents needing attention"
        >
            <div className="op-attention">
                <span className="op-attention-icon">
                    <AlertTriangle aria-hidden="true" />
                </span>
                <div>
                    <strong>
                        {meta.total === 1
                            ? "One document needs attention."
                            : `${meta.total} documents need attention.`}
                    </strong>
                    <p>
                        {meta.total === 1 && firstEntry ? (
                            <>
                                <span>{firstEntry.reference_number}</span> ·{" "}
                                {firstEntry.current_office_abbreviation} ·{" "}
                                {attentionDescription(firstEntry)}
                            </>
                        ) : (
                            <>
                                {meta.overdue_count} past workflow targets ·{" "}
                                {meta.idle_count} idle · {officeCount}{" "}
                                {officeCount === 1 ? "office" : "offices"}
                            </>
                        )}
                    </p>
                </div>
                <CollapsibleTrigger asChild>
                    <Button data-slot="button" variant="ghost">
                        {open
                            ? "Hide attention list"
                            : `Review ${meta.total === 1 ? "document" : `${meta.total} documents`}`}
                        <ChevronDown
                            className={open ? "op-chevron-open" : ""}
                            aria-hidden="true"
                        />
                    </Button>
                </CollapsibleTrigger>
            </div>
            <CollapsibleContent className="op-attention-content">
                <div className="op-attention-explainer">
                    <h2>Documents needing attention</h2>
                    <p>
                        Business days exclude weekends. Each received step uses
                        its own workflow target. Idle notices use a separate
                        threshold.
                    </p>
                    <small>
                        Idle threshold: {meta.idle_threshold_days} business days
                        · delay means days beyond the step target.
                    </small>
                </div>
                <div className="op-attention-search op-search">
                    <Search aria-hidden="true" />
                    <Input
                        aria-label="Search attention documents"
                        placeholder="Search reference or office…"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    {search && (
                        <Button
                            data-slot="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Clear attention search"
                            onClick={() => setSearch("")}
                        >
                            <X aria-hidden="true" />
                        </Button>
                    )}
                </div>
                <div className="op-attention-list-body">
                    <Table className="op-attention-table">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Document</TableHead>
                                <TableHead>Current office / step</TableHead>
                                <TableHead>Days held</TableHead>
                                <TableHead>Target</TableHead>
                                <TableHead>Delay / reason</TableHead>
                                <TableHead>
                                    <span className="sr-only">
                                        View document
                                    </span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((entry) => (
                                <TableRow key={entry.id}>
                                    <TableCell>
                                        <Button
                                            data-slot="button"
                                            variant="link"
                                            className="op-reference"
                                            onClick={() => onView(entry.id)}
                                        >
                                            {entry.reference_number}
                                        </Button>
                                        <small className="op-row-purpose">
                                            {entry.category}
                                            {entry.status
                                                ? ` · ${entry.status}`
                                                : ""}
                                        </small>
                                    </TableCell>
                                    <TableCell>
                                        <span className="op-mobile-cell-label">
                                            Current office / step
                                        </span>
                                        <strong>
                                            {entry.current_office_abbreviation}
                                        </strong>
                                        <small>
                                            {entry.current_office_name}
                                        </small>
                                        {entry.current_step_order != null &&
                                            entry.total_steps != null && (
                                                <small>
                                                    Step{" "}
                                                    {entry.current_step_order}{" "}
                                                    of {entry.total_steps}
                                                </small>
                                            )}
                                    </TableCell>
                                    <TableCell>
                                        <span className="op-mobile-cell-label">
                                            Days held
                                        </span>
                                        {entry.awaiting_receipt
                                            ? "Awaiting receipt"
                                            : `${entry.days_at_current_step} days`}
                                    </TableCell>
                                    <TableCell>
                                        <span className="op-mobile-cell-label">
                                            Workflow target
                                        </span>
                                        {entry.awaiting_receipt
                                            ? "Not started"
                                            : entry.expected_days == null
                                              ? "No target"
                                              : `${entry.expected_days} days`}
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            className={`op-attention-reason ${entry.delay_days > 0 ? "is-overdue" : "is-idle"}`}
                                        >
                                            {entry.delay_days > 0
                                                ? `${entry.delay_days} days overdue`
                                                : `Idle ${entry.idle_days} days`}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            data-slot="button"
                                            size="icon"
                                            variant="ghost"
                                            aria-label={`Review attention document ${entry.reference_number}`}
                                            onClick={() => onView(entry.id)}
                                        >
                                            <ChevronRight aria-hidden="true" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                {rows.length === 0 && (
                    <p className="op-inline-empty">
                        No matching attention documents. Try another reference
                        or office.
                    </p>
                )}
                <div className="op-table-footer">
                    <span>
                        {rows.length} of {meta.total} documents
                    </span>
                    <span>Office scope applies</span>
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
