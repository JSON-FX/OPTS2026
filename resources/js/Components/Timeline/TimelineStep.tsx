import { Check, Circle, Clock, AlertTriangle } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { cn } from '@/lib/utils';
import type { TimelineStep as TimelineStepType } from '@/types/models';

interface Props {
    step: TimelineStepType;
    isLast: boolean;
    orientation: 'horizontal' | 'vertical';
}

function formatDate(dateString?: string | null): string {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatDateTime(dateString?: string | null): string {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export default function TimelineStep({ step, isLast, orientation }: Props) {
    const isVertical = orientation === 'vertical';

    return (
        <div
            className={cn(
                'relative flex',
                isVertical ? 'flex-row gap-3' : 'flex-col items-center gap-2 flex-1 min-w-0'
            )}
        >
            {/* Step indicator and connector */}
            <div
                className={cn(
                    'flex flex-shrink-0',
                    isVertical ? 'flex-col items-center' : 'flex-row items-center w-full'
                )}
            >
                {/* Connector before (horizontal only) */}
                {!isVertical && (
                    <div
                        className={cn(
                            'flex-1 h-0.5',
                            step.step_order === 1 ? 'bg-transparent' : '',
                            step.status === 'completed' ? 'bg-green-400' : '',
                            step.status === 'current' ? 'bg-green-400' : '',
                            step.status === 'upcoming' && step.step_order > 1 ? 'bg-gray-200' : ''
                        )}
                    />
                )}

                {/* Icon */}
                <StepIcon status={step.status} />

                {/* Connector after (horizontal only) */}
                {!isVertical && (
                    <div
                        className={cn(
                            'flex-1 h-0.5',
                            isLast ? 'bg-transparent' : '',
                            step.status === 'completed' && !isLast ? 'bg-green-400' : '',
                            step.status === 'current' && !isLast ? 'bg-gray-200' : '',
                            step.status === 'upcoming' && !isLast ? 'bg-gray-200' : ''
                        )}
                    />
                )}

                {/* Vertical connector line */}
                {isVertical && !isLast && (
                    <div
                        className={cn(
                            'w-0.5 flex-1 min-h-[2rem]',
                            step.status === 'completed' ? 'bg-green-400' : 'bg-gray-200'
                        )}
                    />
                )}
            </div>

            {/* Content */}
            <div className={cn('min-w-0', isVertical ? 'pb-6' : 'text-center mt-1')}>
                <p
                    className={cn(
                        'text-sm font-medium truncate',
                        step.status === 'completed' && 'text-green-700',
                        step.status === 'current' && 'text-blue-700',
                        step.status === 'upcoming' && 'text-gray-400'
                    )}
                >
                    {step.office.name}
                </p>

                {step.status === 'completed' && (
                    <div className={cn('text-xs text-gray-500 mt-0.5', !isVertical && 'space-y-0.5')}>
                        {step.completed_by && (
                            <p className="truncate">{step.completed_by.name}</p>
                        )}
                        <p>{formatDateTime(step.completed_at)}</p>
                        {step.actual_days !== null && step.actual_days !== undefined && (
                            <p>
                                {step.actual_days}d
                                <span className="text-gray-400"> / {step.expected_days}d exp</span>
                            </p>
                        )}
                    </div>
                )}

                {step.status === 'current' && (
                    <div className={cn('text-xs mt-0.5', !isVertical && 'space-y-0.5')}>
                        <p className="text-blue-600 truncate">
                            {step.current_holder?.name ?? 'Awaiting receipt'}
                        </p>
                        <p className="text-gray-500">
                            {step.days_at_step}d at step
                        </p>
                        {step.eta && (
                            <p className="text-gray-500">
                                ETA: {formatDate(step.eta)}
                            </p>
                        )}
                        {step.is_overdue && (
                            <Badge variant="outline" className="mt-1 border-red-200 bg-red-50 text-red-700 text-xs px-1.5 py-0">
                                Overdue
                            </Badge>
                        )}
                    </div>
                )}

                {step.status === 'upcoming' && (
                    <div className={cn('text-xs text-gray-400 mt-0.5', !isVertical && 'space-y-0.5')}>
                        <p>{step.expected_days}d expected</p>
                        {step.estimated_arrival && (
                            <p>ETA: {formatDate(step.estimated_arrival)}</p>
                        )}
                    </div>
                )}

                {step.is_final_step && (
                    <p className="text-xs text-gray-400 mt-0.5 italic">Final step</p>
                )}
            </div>
        </div>
    );
}

function StepIcon({ status }: { status: TimelineStepType['status'] }) {
    const baseClasses = 'flex h-8 w-8 items-center justify-center rounded-full flex-shrink-0';

    if (status === 'completed') {
        return (
            <div className={cn(baseClasses, 'bg-green-500 text-white')}>
                <Check className="h-4 w-4" />
            </div>
        );
    }

    if (status === 'current') {
        return (
            <div className={cn(baseClasses, 'bg-blue-500 text-white ring-4 ring-blue-100')}>
                <Circle className="h-3 w-3 fill-current" />
            </div>
        );
    }

    return (
        <div className={cn(baseClasses, 'bg-gray-200 text-gray-400')}>
            <Circle className="h-3 w-3" />
        </div>
    );
}
