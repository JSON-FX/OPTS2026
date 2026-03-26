import { AlertTriangle } from 'lucide-react';
import TimelineStep from './TimelineStep';
import ProgressBar from './ProgressBar';
import type { TransactionTimeline as TransactionTimelineType } from '@/types/models';

interface Props {
    timeline: TransactionTimelineType;
    delaySeverity?: string;
}

export default function TransactionTimeline({ timeline, delaySeverity = 'on_track' }: Props) {
    if (!timeline || timeline.total_steps === 0) {
        return (
            <p className="text-sm text-gray-500">No workflow assigned to this transaction.</p>
        );
    }

    return (
        <div>
            {/* Out of workflow warning */}
            {timeline.is_out_of_workflow && (
                <div className="mb-4 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                    <AlertTriangle className="h-4 w-4 text-amber-600 shrink-0" />
                    <p className="text-sm text-amber-700">
                        This transaction has deviated from the expected workflow path.
                    </p>
                </div>
            )}

            {/* Vertical timeline (always) */}
            <div>
                {timeline.steps.map((step, index) => (
                    <TimelineStep
                        key={step.step_order}
                        step={step}
                        isLast={index === timeline.steps.length - 1}
                        orientation="vertical"
                    />
                ))}
            </div>

            {/* Progress bar */}
            <div className="mt-4 pt-4 border-t border-gray-100">
                <ProgressBar
                    percentage={timeline.progress_percentage}
                    delaySeverity={delaySeverity}
                    label={`${timeline.completed_steps}/${timeline.total_steps} steps completed`}
                />
            </div>
        </div>
    );
}
