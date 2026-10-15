import { useRef, useState } from "react";
import { Bell, AlertTriangle, Info, Megaphone, Wrench, X } from "lucide-react";
import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert";
import { Button } from "@/Components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from "@/Components/ui/dialog";
import type { DashboardNotice } from "@/types/dashboard";

// A revised notice must reappear even when its previous version was dismissed.
const noticeVersion = (notice: DashboardNotice) =>
    JSON.stringify([
        notice.id,
        notice.kind,
        notice.title,
        notice.message,
        notice.detail,
        notice.audience,
        notice.publisher,
    ]);

export default function Announcements({
    notices,
}: {
    notices: DashboardNotice[];
}) {
    const [dismissed, setDismissed] = useState<string[]>(() => {
        try {
            const stored: unknown = JSON.parse(
                sessionStorage.getItem("opts_dismissed_notices") ?? "[]",
            );
            return Array.isArray(stored)
                ? stored.filter(
                      (item): item is string => typeof item === "string",
                  )
                : [];
        } catch {
            return [];
        }
    });
    const [detailId, setDetailId] = useState<string | null>(null);
    const detail = notices.find((notice) => notice.id === detailId);
    const lastDetail = useRef(detail);
    if (detail) lastDetail.current = detail;
    const renderedDetail = detail ?? lastDetail.current;
    const dismiss = (notice: DashboardNotice) => {
        const next = [...dismissed, noticeVersion(notice)];
        setDismissed(next);
        try {
            sessionStorage.setItem(
                "opts_dismissed_notices",
                JSON.stringify(next),
            );
        } catch {
            /* Dismissal lasts for this view when storage is unavailable. */
        }
    };
    const icons = {
        update: Megaphone,
        maintenance: Wrench,
        emergency: AlertTriangle,
        reminder: Bell,
    };
    return (
        <>
            {notices
                .filter(
                    (notice) =>
                        notice.kind === "emergency" ||
                        !dismissed.includes(noticeVersion(notice)),
                )
                .map((notice) => {
                    const Icon = icons[notice.kind];
                    const urgent = notice.kind === "emergency";
                    const metadata = [notice.audience, notice.publisher]
                        .filter(Boolean)
                        .join(" · ");
                    return (
                        <Alert
                            key={notice.id}
                            className={`op-announcement op-announcement-${notice.kind}`}
                            role={urgent ? "alert" : "status"}
                            aria-live={urgent ? "assertive" : "polite"}
                            aria-atomic="true"
                        >
                            <Icon aria-hidden="true" />
                            <AlertTitle data-slot="alert-title">
                                {notice.title}
                            </AlertTitle>
                            <AlertDescription data-slot="alert-description">
                                <p>{notice.message}</p>
                                {metadata && (
                                    <span className="op-announcement-meta">
                                        {metadata}
                                    </span>
                                )}
                            </AlertDescription>
                            <div className="op-announcement-actions">
                                {notice.detail && (
                                    <Button
                                        data-slot="button"
                                        variant="ghost"
                                        onClick={() => setDetailId(notice.id)}
                                    >
                                        <Info aria-hidden="true" />
                                        Details
                                    </Button>
                                )}
                                {!urgent && (
                                    <Button
                                        data-slot="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Dismiss announcement"
                                        onClick={() => dismiss(notice)}
                                    >
                                        <X aria-hidden="true" />
                                    </Button>
                                )}
                            </div>
                        </Alert>
                    );
                })}
            <Dialog
                open={!!detail}
                onOpenChange={(open) => {
                    if (!open) setDetailId(null);
                }}
            >
                <DialogContent
                    overlayClassName="op-modal-overlay"
                    className="op-live op-live-overlay op-announcement-dialog"
                >
                    <DialogHeader>
                        <DialogTitle>{renderedDetail?.title}</DialogTitle>
                        <DialogDescription>
                            {[
                                renderedDetail?.audience,
                                renderedDetail?.publisher,
                            ]
                                .filter(Boolean)
                                .join(" · ") || "System announcement"}
                        </DialogDescription>
                    </DialogHeader>
                    <p>{renderedDetail?.message}</p>
                    <p className="whitespace-pre-line">
                        {renderedDetail?.detail}
                    </p>
                </DialogContent>
            </Dialog>
        </>
    );
}
