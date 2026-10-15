import { useEffect, useRef, type MutableRefObject } from "react";
import { Link } from "@inertiajs/react";
import { ArrowUpRight, CheckCheck, ChevronRight } from "lucide-react";
import { Button } from "@/Components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from "@/Components/ui/dialog";
import DocumentDetail from "./DocumentDetail";
import DocumentRegister from "./DocumentRegister";
import type {
    DashboardDocument,
    DashboardFilters,
    DashboardProps,
} from "@/types/dashboard";

export type DashboardModal = "detail" | "queue" | "pending" | "help" | null;

export default function DashboardDialogs({
    modal,
    onClose,
    selected,
    viewer,
    canProcess,
    attention,
    attentionMeta,
    pendingDocuments,
    pendingCount,
    queueDocuments,
    queueFilters,
    queueLoading,
    queueChangingOffice,
    queueError,
    onQueueFilters,
    onQueuePage,
    queueOfficeLabel,
    selectedYear,
    selectedId,
    onSelect,
    onView,
    returnFocus,
}: {
    modal: DashboardModal;
    onClose: () => void;
    selected?: DashboardDocument;
    viewer: boolean;
    canProcess: boolean;
    attention: DashboardProps["stagnantTransactions"];
    attentionMeta: DashboardProps["attentionMeta"];
    pendingDocuments: DashboardDocument[];
    pendingCount: number;
    queueDocuments: DashboardProps["queueDocuments"];
    queueFilters: DashboardFilters | null;
    queueLoading: boolean;
    queueChangingOffice: boolean;
    queueError: string | null;
    onQueueFilters: (filters: Partial<DashboardFilters>) => void;
    onQueuePage: (page: number) => void;
    queueOfficeLabel: string;
    selectedYear: number;
    selectedId: number | null;
    onSelect: (document: DashboardDocument) => void;
    onView: (id: number) => void;
    returnFocus: MutableRefObject<HTMLElement | null>;
}) {
    const heading = useRef<HTMLHeadingElement>(null);
    // Preserve the current content while Radix plays its exit animation.
    const lastModal = useRef(modal);
    if (modal) lastModal.current = modal;
    const renderedModal = modal ?? lastModal.current;
    useEffect(() => {
        if (modal) heading.current?.focus();
    }, [modal]);
    return (
        <Dialog
            open={modal !== null}
            onOpenChange={(open) => {
                if (!open) onClose();
            }}
        >
            <DialogContent
                overlayClassName="op-modal-overlay"
                className={`op-root op-live op-dashboard-dialog ${renderedModal === "queue" || renderedModal === "pending" ? "op-queue-dialog" : renderedModal === "detail" ? "op-document-dialog" : "op-help-dialog"}`}
                onCloseAutoFocus={(event) => {
                    const target = returnFocus.current?.isConnected
                        ? returnFocus.current
                        : document.querySelector<HTMLButtonElement>(
                              window.matchMedia("(max-width:800px)").matches
                                  ? ".op-mobile-navigation button"
                                  : ".op-desktop-collapse button",
                          );
                    if (target) {
                        event.preventDefault();
                        target.focus();
                    }
                }}
            >
                <DialogHeader>
                    <DialogTitle ref={heading} tabIndex={-1}>
                        {renderedModal === "detail"
                            ? "Document overview"
                            : renderedModal === "queue"
                              ? "Office document queue"
                              : renderedModal === "pending"
                                ? "Pending receipts"
                                : "Help & reference"}
                    </DialogTitle>
                    <DialogDescription>
                        {renderedModal === "detail"
                            ? "Current office and workflow progress."
                            : renderedModal === "queue"
                              ? `Documents at ${queueOfficeLabel}. Select a document to inspect its route.`
                              : renderedModal === "pending"
                                ? "Documents endorsed to your office and awaiting receipt, across all reporting years."
                                : "How to use your procurement Dashboard."}
                    </DialogDescription>
                </DialogHeader>
                {renderedModal === "detail" && !selected && (
                    <div className="op-empty">
                        <h3>Document unavailable</h3>
                        <p>
                            This document may have been removed. Close the
                            overview and refresh the register.
                        </p>
                        <Button onClick={onClose} variant="outline">
                            Close overview
                        </Button>
                    </div>
                )}
                {renderedModal === "detail" && selected && (
                    <DocumentDetail
                        document={selected}
                        attention={
                            selected.attention ??
                            attention.find((entry) => entry.id === selected.id)
                        }
                        viewer={viewer}
                        canProcess={canProcess}
                        inOverview
                        onClose={onClose}
                    />
                )}
                {renderedModal === "queue" && (
                    <div aria-busy={queueLoading}>
                        {queueError && (
                            <p role="alert" className="op-inline-empty">
                                {queueError}
                            </p>
                        )}
                        {queueDocuments &&
                        queueFilters &&
                        !queueChangingOffice ? (
                            <DocumentRegister
                                documents={queueDocuments}
                                filters={queueFilters}
                                selectedYear={selectedYear}
                                loading={queueLoading}
                                selectedId={selectedId}
                                onFilters={onQueueFilters}
                                onPage={onQueuePage}
                                onSelect={onSelect}
                            />
                        ) : !queueError ? (
                            <p className="op-inline-empty" role="status">
                                Loading office documents…
                            </p>
                        ) : null}
                    </div>
                )}
                {renderedModal === "pending" && (
                    <>
                        {pendingDocuments.length ? (
                            <div className="op-pending-documents">
                                {pendingDocuments.map((document) => (
                                    <Button
                                        variant="ghost"
                                        className="op-pending-document"
                                        key={document.id}
                                        onClick={() => onView(document.id)}
                                    >
                                        <span>
                                            <strong>
                                                {document.reference_number}
                                            </strong>
                                            <small>
                                                {document.current_office_name}
                                            </small>
                                        </span>
                                        <ChevronRight />
                                    </Button>
                                ))}
                                {pendingCount > pendingDocuments.length && (
                                    <p className="op-inline-empty">
                                        Showing {pendingDocuments.length} of{" "}
                                        {pendingCount} pending receipts. Open
                                        receipt processing to see all documents.
                                    </p>
                                )}
                            </div>
                        ) : (
                            <div className="op-empty">
                                <CheckCheck />
                                <h3>You're up to date</h3>
                                <p>
                                    New receipts appear here when another office
                                    endorses a document to you.
                                </p>
                            </div>
                        )}
                        {canProcess && pendingDocuments.length > 0 && (
                            <Button asChild variant="outline">
                                <Link href={route("transactions.pending")}>
                                    Open receipt processing
                                    <ArrowUpRight />
                                </Link>
                            </Button>
                        )}
                    </>
                )}
                {renderedModal === "help" && (
                    <div className="op-help-content">
                        <section>
                            <h3>Find a document</h3>
                            <p>
                                Choose a reporting year and office. Search by
                                reference or office, then select a row to
                                inspect its document route. Open the document
                                for its full timeline and endorsements.
                            </p>
                        </section>
                        <section>
                            <h3>Understand office scope</h3>
                            <p>
                                Workload shows active documents at the selected
                                office. The register includes completed
                                documents. Procurement counts include records
                                created in the selected year with a document
                                currently at that office. Activity follows the
                                offices involved in each action.
                            </p>
                        </section>
                        <section>
                            <h3>Review attention notices</h3>
                            <p>
                                Received documents use the expected business
                                days for their workflow step. Idle notices use
                                the configured threshold of{" "}
                                {attentionMeta.idle_threshold_days} business
                                days without an action. Weekends are excluded.
                            </p>
                        </section>
                        <section>
                            <h3>Your access</h3>
                            <p>
                                Viewers can inspect information. Eligible
                                Endorsers and Administrators can process
                                documents at their assigned office. SSO controls
                                roles and office assignment. Each processing
                                page checks eligibility again.
                            </p>
                        </section>
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link href={route("procurements.index")}>
                                    Browse procurements
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={route("transactions.index")}>
                                    Browse transactions
                                </Link>
                            </Button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
