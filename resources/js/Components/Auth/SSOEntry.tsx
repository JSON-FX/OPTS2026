import { useEffect, useId, useState } from "react";
import {
    ArrowRight,
    Building2,
    ChevronDown,
    CircleHelp,
    FileText,
    Loader2,
    ShieldCheck,
} from "lucide-react";
import { Button } from "@/Components/ui/button";
import "../../../css/access.css";

export default function SSOEntry({
    signedIn = false,
    ssoError,
}: {
    signedIn?: boolean;
    ssoError?: string;
}) {
    const [connecting, setConnecting] = useState(false);
    const [help, setHelp] = useState(false);
    const helpId = useId();
    useEffect(() => {
        const restore = () => setConnecting(false);
        window.addEventListener("pageshow", restore);
        return () => window.removeEventListener("pageshow", restore);
    }, []);
    return (
        <div className="access-page access-doorway">
            <div className="access-body">
                <header className="access-identity">
                    <div className="access-municipality">
                        <img
                            src="/lgu-seal.png"
                            alt="Municipality of Quezon seal"
                        />
                        <div>
                            <strong>Municipality of Quezon</strong>
                            <span>Province of Bukidnon</span>
                        </div>
                    </div>
                    <div className="access-product">
                        <span className="access-wordmark">OPTS</span>
                        <p>
                            Online Procurement
                            <br />
                            Tracking System
                        </p>
                    </div>
                    <p className="access-context">
                        A shared workspace for the offices that move procurement
                        forward.
                    </p>
                    <div
                        className="access-paper-route"
                        aria-label="Linked procurement documents"
                    >
                        <span>
                            <FileText aria-hidden="true" />
                            Purchase request
                        </span>
                        <span>
                            <FileText aria-hidden="true" />
                            Purchase order
                        </span>
                        <span>
                            <FileText aria-hidden="true" />
                            Voucher
                        </span>
                    </div>
                    <span className="access-locality">
                        <Building2 aria-hidden="true" />
                        Quezon, Bukidnon
                    </span>
                </header>
                <main className="access-entry">
                    <div className="access-entry-copy">
                        <span className="access-eyebrow">EMPLOYEE ACCESS</span>
                        <h1>
                            {signedIn
                                ? "Your workspace is ready."
                                : "Sign in to your workspace."}
                        </h1>
                        <p>
                            {signedIn
                                ? "Continue with your current LGU account."
                                : "Use your LGU account to track procurement documents and manage your office’s handoffs."}
                        </p>
                        {ssoError && (
                            <div
                                className="access-notice access-error"
                                role="alert"
                            >
                                <strong>Sign-in could not be completed.</strong>
                                <span>{ssoError}</span>
                            </div>
                        )}
                        <Button
                            className="access-cta"
                            disabled={connecting}
                            onClick={() => {
                                setConnecting(true);
                                window.location.assign(
                                    route(
                                        signedIn ? "dashboard" : "sso.redirect",
                                    ),
                                );
                            }}
                        >
                            {connecting ? (
                                <>
                                    <Loader2
                                        className="access-spinner"
                                        aria-hidden="true"
                                    />
                                    {signedIn
                                        ? "Opening workspace…"
                                        : "Connecting to LGU SSO…"}
                                </>
                            ) : (
                                <>
                                    {signedIn
                                        ? "Open workspace"
                                        : ssoError
                                          ? "Try LGU SSO again"
                                          : "Continue with LGU SSO"}
                                    <ArrowRight aria-hidden="true" />
                                </>
                            )}
                        </Button>
                        <p className="access-auth-note">
                            <ShieldCheck aria-hidden="true" />
                            {signedIn
                                ? "Your current office and role apply."
                                : "You’ll sign in through LGU Single Sign-On (SSO)."}
                        </p>
                        <div className="access-help">
                            <button
                                type="button"
                                aria-expanded={help}
                                aria-controls={helpId}
                                onClick={() => setHelp(!help)}
                            >
                                <CircleHelp aria-hidden="true" />
                                Need help signing in?
                                <ChevronDown
                                    className={help ? "access-rotated" : ""}
                                    aria-hidden="true"
                                />
                            </button>
                            <div id={helpId} hidden={!help}>
                                <p>
                                    Use the same account you use for LGU
                                    services. Your SSO office and assigned role
                                    determine your access.
                                </p>
                                <p>
                                    For account or access issues, contact your
                                    office’s SSO administrator.
                                </p>
                            </div>
                        </div>
                    </div>
                    <footer className="access-footer">
                        <span>Local Government of Quezon, Bukidnon</span>
                        <span>OPTS · Official office workspace</span>
                    </footer>
                </main>
            </div>
        </div>
    );
}
