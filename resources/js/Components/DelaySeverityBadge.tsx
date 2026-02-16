import { Badge } from '@/Components/ui/badge';
import { cn } from '@/lib/utils';
import type { DelaySeverity } from '@/types/models';

const severityStyles: Record<DelaySeverity, string> = {
    on_track: 'bg-green-100 text-green-800 border-green-200',
    warning: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    overdue: 'bg-red-100 text-red-800 border-red-200',
};

const severityLabels: Record<DelaySeverity, string> = {
    on_track: 'On Track',
    warning: 'Warning',
    overdue: 'Overdue',
};

interface DelaySeverityBadgeProps {
    severity: DelaySeverity;
    delayDays?: number;
    className?: string;
}

export default function DelaySeverityBadge({ severity, delayDays, className }: DelaySeverityBadgeProps) {
    return (
        <Badge
            variant="outline"
            className={cn(severityStyles[severity], className)}
        >
            {severityLabels[severity]}
            {delayDays !== undefined && delayDays > 0 && ` (${delayDays}d)`}
        </Badge>
    );
}
