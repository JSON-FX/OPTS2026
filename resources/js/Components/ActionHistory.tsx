import { useState } from 'react';
import { Badge } from '@/Components/ui/badge';
import { cn } from '@/lib/utils';
import type { ActionHistoryEntry, ActionType } from '@/types/models';

interface Props {
    actions: ActionHistoryEntry[];
    initialLimit?: number;
}

const actionTypeConfig: Record<ActionType, { label: string; dotColor: string; badgeClass: string }> = {
    endorse: { label: 'Endorsed', dotColor: 'bg-green-500', badgeClass: 'bg-green-100 text-green-800' },
    receive: { label: 'Received', dotColor: 'bg-blue-500', badgeClass: 'bg-blue-100 text-blue-800' },
    complete: { label: 'Completed', dotColor: 'bg-emerald-500', badgeClass: 'bg-emerald-100 text-emerald-800' },
    hold: { label: 'On Hold', dotColor: 'bg-yellow-500', badgeClass: 'bg-yellow-100 text-yellow-800' },
    cancel: { label: 'Cancelled', dotColor: 'bg-red-500', badgeClass: 'bg-red-100 text-red-800' },
    bypass: { label: 'Bypassed', dotColor: 'bg-orange-500', badgeClass: 'bg-orange-100 text-orange-800' },
};

function describeAction(entry: ActionHistoryEntry): string {
    const user = entry.from_user?.name ?? 'Unknown';
    const fromOffice = entry.from_office?.abbreviation;
    const toOffice = entry.to_office?.abbreviation;

    switch (entry.action_type) {
        case 'endorse':
            return toOffice
                ? `${user} endorsed from ${fromOffice ?? '--'} to ${toOffice}`
                : `${user} endorsed from ${fromOffice ?? '--'}`;
        case 'receive':
            return `${user} received at ${fromOffice ?? '--'}`;
        case 'complete':
            return `${user} completed at ${fromOffice ?? '--'}`;
        case 'hold':
            return `${user} placed on hold at ${fromOffice ?? '--'}`;
        case 'cancel':
            return `${user} cancelled at ${fromOffice ?? '--'}`;
        case 'bypass':
            return toOffice
                ? `${user} bypassed from ${fromOffice ?? '--'} to ${toOffice}`
                : `${user} bypassed from ${fromOffice ?? '--'}`;
        default:
            return `${user} performed action`;
    }
}

function formatDateTime(dateString: string): string {
    return new Date(dateString).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export default function ActionHistory({ actions, initialLimit = 10 }: Props) {
    const [expanded, setExpanded] = useState(false);
    const [showAll, setShowAll] = useState(false);

    if (!actions || actions.length === 0) {
        return (
            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div className="p-6">
                    <h3 className="text-lg font-medium text-gray-900">Action History</h3>
                    <p className="mt-2 text-sm text-gray-500">No actions recorded for this transaction.</p>
                </div>
            </div>
        );
    }

    const visibleActions = showAll ? actions : actions.slice(0, initialLimit);
    const hasMore = actions.length > initialLimit;

    return (
        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <button
                type="button"
                onClick={() => setExpanded(!expanded)}
                className="w-full border-b border-gray-200 p-6 text-left hover:bg-gray-50 transition-colors"
            >
                <div className="flex items-center justify-between">
                    <h3 className="text-lg font-medium text-gray-900">Action History</h3>
                    <div className="flex items-center gap-3">
                        <span className="text-sm text-gray-500">
                            {actions.length} action{actions.length !== 1 ? 's' : ''}
                        </span>
                        <ChevronIcon expanded={expanded} />
                    </div>
                </div>
            </button>

            {expanded && (
                <div className="p-6">
                    <div className="relative">
                        {visibleActions.map((action, index) => {
                            const config = actionTypeConfig[action.action_type];
                            const isLast = index === visibleActions.length - 1;

                            return (
                                <div key={action.id} className="relative flex gap-4 pb-6 last:pb-0">
                                    {/* Vertical line */}
                                    {!isLast && (
                                        <div className="absolute left-[7px] top-[18px] bottom-0 w-px bg-gray-200" />
                                    )}
                                    {/* Dot */}
                                    <div className="relative z-10 flex-shrink-0 mt-1.5">
                                        <div
                                            className={cn(
                                                'h-[14px] w-[14px] rounded-full border-2 border-white ring-2',
                                                config.dotColor,
                                                {
                                                    'ring-green-200': action.action_type === 'endorse' || action.action_type === 'complete',
                                                    'ring-blue-200': action.action_type === 'receive',
                                                    'ring-yellow-200': action.action_type === 'hold',
                                                    'ring-red-200': action.action_type === 'cancel',
                                                    'ring-orange-200': action.action_type === 'bypass',
                                                }
                                            )}
                                        />
                                    </div>
                                    {/* Content */}
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge className={cn('border-0 text-xs font-medium', config.badgeClass)}>
                                                {config.label}
                                            </Badge>
                                            {action.is_out_of_workflow && (
                                                <Badge className="border-0 bg-amber-100 text-amber-700 text-xs font-medium">
                                                    Out of Workflow
                                                </Badge>
                                            )}
                                            {action.workflow_step_order && (
                                                <span className="text-xs text-gray-400">
                                                    Step {action.workflow_step_order}
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-1 text-sm text-gray-900">
                                            {describeAction(action)}
                                        </p>
                                        {action.action_taken && (
                                            <p className="mt-0.5 text-sm text-gray-600">
                                                Action: {action.action_taken}
                                            </p>
                                        )}
                                        {action.notes && (
                                            <p className="mt-0.5 text-sm text-gray-500">
                                                Note: {action.notes}
                                            </p>
                                        )}
                                        {action.reason && (
                                            <p className="mt-0.5 text-sm text-gray-500">
                                                Reason: {action.reason}
                                            </p>
                                        )}
                                        <p className="mt-1 text-xs text-gray-400">
                                            {formatDateTime(action.created_at)}
                                        </p>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                    {hasMore && (
                        <div className="mt-4 text-center">
                            <button
                                type="button"
                                onClick={() => setShowAll(!showAll)}
                                className="text-sm text-blue-600 hover:underline"
                            >
                                {showAll ? 'Show Less' : `View All (${actions.length})`}
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

function ChevronIcon({ expanded }: { expanded: boolean }) {
    return (
        <svg
            className={cn('h-5 w-5 text-gray-400 transition-transform', expanded && 'rotate-180')}
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth={1.5}
            stroke="currentColor"
        >
            <path strokeLinecap="round" strokeLinejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    );
}
