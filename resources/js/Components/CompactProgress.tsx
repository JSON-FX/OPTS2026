import { cn } from '@/lib/utils';
import type { TransactionTimeline } from '@/types/models';

interface Props {
    timeline: TransactionTimeline;
    delaySeverity?: string;
}

const severityDotColors: Record<string, string> = {
    on_track: 'bg-green-500',
    warning: 'bg-yellow-500',
    overdue: 'bg-red-500',
};

export default function CompactProgress({ timeline, delaySeverity = 'on_track' }: Props) {
    if (!timeline || timeline.total_steps === 0) {
        return <span className="text-xs text-gray-400">No workflow</span>;
    }

    return (
        <div className="flex items-center gap-1.5">
            {timeline.steps.map((step) => (
                <div
                    key={step.step_order}
                    className={cn(
                        'h-2 w-2 rounded-full',
                        step.status === 'completed' && 'bg-green-500',
                        step.status === 'current' && cn(
                            'ring-2',
                            severityDotColors[delaySeverity] ?? 'bg-blue-500',
                            delaySeverity === 'on_track' && 'bg-blue-500 ring-blue-200',
                            delaySeverity === 'warning' && 'bg-yellow-500 ring-yellow-200',
                            delaySeverity === 'overdue' && 'bg-red-500 ring-red-200'
                        ),
                        step.status === 'upcoming' && 'bg-gray-200'
                    )}
                    title={`${step.office.name} (${step.status})`}
                />
            ))}
            <span className="ml-1 text-xs text-gray-500">
                {timeline.completed_steps}/{timeline.total_steps}
            </span>
        </div>
    );
}
