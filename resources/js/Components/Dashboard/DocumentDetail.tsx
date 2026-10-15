import { Link } from "@inertiajs/react";
import {
    ArrowUpRight,
    Check,
    CheckCircle2,
    Clock3,
    ShieldCheck,
    AlertTriangle,
} from "lucide-react";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import type {
    DashboardAttentionEntry,
    DashboardDocument,
} from "@/types/dashboard";

export const categoryLabels = {
    PR: "Purchase request",
    PO: "Purchase order",
    VCH: "Voucher",
};

export function DocumentStatus({ status }: { status: string }) {
    return (
        <Badge
            data-slot="badge"
            variant="outline"
            className={`op-badge op-status ${status === "Completed" ? "op-status-done" : status === "Cancelled" ? "op-status-cancelled" : "op-status-active"}`}
        >
            {status === "Completed" ? (
                <CheckCircle2 aria-hidden="true" />
            ) : (
                <Clock3 aria-hidden="true" />
            )}
            {status}
        </Badge>
    );
}

export function attentionDescription(entry: DashboardAttentionEntry) {
    return entry.delay_days > 0
        ? `${entry.delay_days} business ${entry.delay_days === 1 ? "day" : "days"} past the workflow target.`
        : `No movement for ${entry.idle_days} business ${entry.idle_days === 1 ? "day" : "days"}.`;
}

export default function DocumentDetail({
    document,
    attention,
    viewer,
    canProcess,
    inOverview = false,
    onClose,
}: {
    document: DashboardDocument;
    attention?: DashboardAttentionEntry;
    viewer: boolean;
    canProcess: boolean;
    inOverview?: boolean;
    onClose?: () => void;
}) {
    const steps: (DashboardDocument["workflow_steps"][number] & {
        office_id?: number | null;
    })[] = [...document.workflow_steps].sort(
        (a, b) => a.step_order - b.step_order,
    );
    const currentStep = steps.find(
        (step) => step.id === document.current_step_id,
    );
    const complete = document.status === "Completed";
    const cancelled = document.status === "Cancelled";
    // Keep terminal and earlier-step presentation consistent with TimelineService.
    const terminal = complete || cancelled;
    const awaitingHandoff =
        document.status === "In Progress" &&
        !document.received_at &&
        currentStep?.office_id != null &&
        document.current_office_id != null &&
        currentStep.office_id !== document.current_office_id;
    const recipientStep = awaitingHandoff
        ? steps.find(
              (step) =>
                  step.office_id === document.current_office_id &&
                  step.step_order > currentStep.step_order,
          )
        : undefined;
    const nextAction =
        !viewer && canProcess && !terminal ? document.next_action : null;
    const created = new Date(document.created_at);

    return (
        <div className="op-record-detail">
            <div className="op-detail-top">
                <span>{categoryLabels[document.category]}</span>
                <DocumentStatus status={document.status} />
            </div>
            <h3>{document.reference_number}</h3>
            <p className="op-detail-purpose">{document.purpose}</p>
            <dl className="op-detail-facts">
                <div>
                    <dt>Procurement</dt>
                    <dd>
                        <Link
                            href={route(
                                "procurements.show",
                                document.procurement_id,
                            )}
                        >
                            #{document.procurement_id}
                        </Link>
                    </dd>
                </div>
                <div>
                    <dt>Current office</dt>
                    <dd>{document.current_office_name ?? "Not assigned"}</dd>
                </div>
                <div>
                    <dt>Created</dt>
                    <dd>
                        {Number.isNaN(created.getTime())
                            ? "Unavailable"
                            : new Intl.DateTimeFormat("en-PH", {
                                  day: "2-digit",
                                  month: "short",
                                  year: "numeric",
                              }).format(created)}
                    </dd>
                </div>
            </dl>
            {attention && (
                <div className="op-detail-warning">
                    <AlertTriangle aria-hidden="true" />
                    <div>
                        <strong>Needs attention</strong>
                        <p>
                            {attentionDescription(attention)}{" "}
                            {attention.awaiting_receipt
                                ? "Awaiting receipt; the workflow target has not started."
                                : attention.expected_days != null
                                  ? `Held ${attention.days_at_current_step} business days against a ${attention.expected_days}-day target.`
                                  : ""}
                        </p>
                    </div>
                </div>
            )}
            <div className="op-timeline-title">
                <h4>Document route</h4>
                <span>
                    {cancelled
                        ? "Route closed"
                        : complete
                          ? `${steps.length}/${steps.length} complete`
                          : awaitingHandoff
                            ? recipientStep
                                ? `Step ${recipientStep.step_order} of ${steps.length} · Awaiting receipt`
                                : "Awaiting receipt"
                            : currentStep
                              ? `Step ${currentStep.step_order} of ${steps.length}`
                              : steps.length
                                ? "Current step unavailable"
                                : "No assigned route"}
                </span>
            </div>
            <ol className="op-route">
                {steps.map((step) => {
                    const stepCompleted =
                        terminal ||
                        (currentStep != null &&
                            step.step_order < currentStep.step_order);
                    const isCurrent =
                        !terminal &&
                        (awaitingHandoff
                            ? step.id === recipientStep?.id
                            : step.id === document.current_step_id);
                    const endorsedStep =
                        awaitingHandoff && step.id === document.current_step_id;
                    return (
                        <li
                            key={step.id}
                            className={
                                stepCompleted
                                    ? "is-complete"
                                    : isCurrent
                                      ? "is-current"
                                      : ""
                            }
                        >
                            <span className="op-route-dot">
                                {stepCompleted ? (
                                    <Check aria-hidden="true" />
                                ) : (
                                    step.step_order
                                )}
                            </span>
                            <div>
                                <strong>{step.office_name}</strong>
                                <small>
                                    {cancelled
                                        ? "Route closed"
                                        : stepCompleted
                                          ? "Step completed"
                                          : endorsedStep
                                            ? `Endorsed · awaiting receipt at ${document.current_office_name ?? "the receiving office"}`
                                            : awaitingHandoff && isCurrent
                                              ? "Awaiting receipt"
                                              : isCurrent
                                                ? "Current office"
                                                : "Upcoming office"}
                                </small>
                            </div>
                        </li>
                    );
                })}
            </ol>
            {steps.length === 0 && (
                <p className="op-inline-empty">
                    No workflow is assigned to this document.
                </p>
            )}
            {inOverview ? (
                <Button
                    data-slot="button"
                    className="op-detail-button"
                    onClick={onClose}
                    disabled={!onClose}
                >
                    Close overview
                    <ArrowUpRight aria-hidden="true" />
                </Button>
            ) : document.url ? (
                <Button data-slot="button" asChild className="op-detail-button">
                    <Link href={document.url}>
                        View document
                        <ArrowUpRight aria-hidden="true" />
                    </Link>
                </Button>
            ) : (
                <p className="op-inline-empty">
                    Document details are unavailable.
                </p>
            )}
            {nextAction && (
                <div className="op-next-action">
                    <strong>Next action</strong>
                    <Button data-slot="button" asChild>
                        <Link href={nextAction.url}>{nextAction.label}</Link>
                    </Button>
                    <p>
                        Processing opens on the transaction page. Office
                        eligibility is checked before any action.
                    </p>
                </div>
            )}
            {viewer && (
                <p className="op-read-only">
                    <ShieldCheck aria-hidden="true" />
                    You have view-only access
                </p>
            )}
            {inOverview && document.url && (
                <Button
                    data-slot="button"
                    asChild
                    variant="link"
                    className="op-document-page-link"
                >
                    <Link href={document.url}>
                        Open document page
                        <ArrowUpRight aria-hidden="true" />
                    </Link>
                </Button>
            )}
        </div>
    );
}
