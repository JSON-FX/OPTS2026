import { useState } from 'react';
import { AlertTriangle, ChevronDown, ChevronUp } from 'lucide-react';
import { cn } from '@/lib/utils';
import TimelineStep from './TimelineStep';
import ProgressBar from './ProgressBar';
import type { TransactionTimeline as TransactionTimelineType } from '@/types/models';

interface Props {
    timeline: TransactionTimelineType;
    delaySeverity?: string;
}

export default function TransactionTimeline({ timeline, delaySeverity = 'on_track' }: Props) {
    const [expanded, setExpanded] = useState(true);

    if (!timeline || timeline.total_steps === 0) {
        return (
            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div className="p-6">
                    <h3 className="text-lg font-medium text-gray-900">Transaction Timeline</h3>
                    <p className="mt-2 text-sm text-gray-500">No workflow assigned to this transaction.</p>
                </div>
            </div>
        );
    }

    return (
        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            {/* Header */}
            <button
                type="button"
                onClick={() => setExpanded(!expanded)}
                className="w-full border-b border-gray-200 p-6 text-left hover:bg-gray-50 transition-colors"
            >
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h3 className="text-lg font-medium text-gray-900">Transaction Timeline</h3>
                        <span className="text-sm text-gray-500">
                            {timeline.completed_steps}/{timeline.total_steps} steps
                        </span>
                    </div>
                    <div className="flex items-center gap-3">
                        <ProgressBar
                            percentage={timeline.progress_percentage}
                            delaySeverity={delaySeverity}
                            compact
                        />
                        {expanded ? (
                            <ChevronUp className="h-5 w-5 text-gray-400" />
                        ) : (
                            <ChevronDown className="h-5 w-5 text-gray-400" />
                        )}
                    </div>
                </div>
            </button>

            {expanded && (
                <div className="p-6">
                    {/* Out of workflow warning */}
                    {timeline.is_out_of_workflow && (
                        <div className="mb-4 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                            <AlertTriangle className="h-4 w-4 text-amber-600 shrink-0" />
                            <p className="text-sm text-amber-700">
                                This transaction has deviated from the expected workflow path.
                            </p>
                        </div>
                    )}

                    {/* Desktop horizontal timeline */}
                    <div className="hidden md:block">
                        <div className="flex">
                            {timeline.steps.map((step, index) => (
                                <TimelineStep
                                    key={step.step_order}
                                    step={step}
                                    isLast={index === timeline.steps.length - 1}
                                    orientation="horizontal"
                                />
                            ))}
                        </div>
                    </div>

                    {/* Mobile vertical timeline */}
                    <div className="block md:hidden">
                        {timeline.steps.map((step, index) => (
                            <TimelineStep
                                key={step.step_order}
                                step={step}
                                isLast={index === timeline.steps.length - 1}
                                orientation="vertical"
                            />
                        ))}
                    </div>

                    {/* Full width progress bar */}
                    <div className="mt-6 pt-4 border-t border-gray-100">
                        <ProgressBar
                            percentage={timeline.progress_percentage}
                            delaySeverity={delaySeverity}
                            label={`${timeline.completed_steps}/${timeline.total_steps} steps completed`}
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
