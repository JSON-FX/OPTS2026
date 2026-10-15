import DashboardLayout from '@/Layouts/DashboardLayout';
import { PropsWithChildren, ReactNode } from 'react';

/** Existing pages keep their content while sharing the approved application shell. */
export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    return (
        <DashboardLayout mainId="application-main">
            <main id="application-main" className="op-main op-compat-main" tabIndex={-1}>
                {header && <div className="op-page-head op-compat-heading">{header}</div>}
                <div className="op-compat-content">{children}</div>
                <footer className="op-compat-footer">Local Government of Quezon, Bukidnon</footer>
            </main>
        </DashboardLayout>
    );
}
