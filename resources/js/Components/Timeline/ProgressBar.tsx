import { cn } from '@/lib/utils';

interface Props {
    percentage: number;
    delaySeverity?: string;
    label?: string;
    compact?: boolean;
}

const severityColors: Record<string, { bar: string; bg: string }> = {
    on_track: { bar: 'bg-green-500', bg: 'bg-green-100' },
    warning: { bar: 'bg-yellow-500', bg: 'bg-yellow-100' },
    overdue: { bar: 'bg-red-500', bg: 'bg-red-100' },
};

export default function ProgressBar({ percentage, delaySeverity = 'on_track', label, compact = false }: Props) {
    const colors = severityColors[delaySeverity] ?? severityColors.on_track;

    if (compact) {
        return (
            <div className="flex items-center gap-2">
                <div className={cn('h-2 w-20 rounded-full', colors.bg)}>
                    <div
                        className={cn('h-full rounded-full transition-all', colors.bar)}
                        style={{ width: `${Math.min(percentage, 100)}%` }}
                    />
                </div>
                <span className="text-xs text-gray-500">{percentage}%</span>
            </div>
        );
    }

    return (
        <div>
            <div className="flex items-center justify-between mb-1">
                {label && <span className="text-sm text-gray-600">{label}</span>}
                <span className="text-sm font-medium text-gray-700">{percentage}%</span>
            </div>
            <div className={cn('h-2.5 w-full rounded-full', colors.bg)}>
                <div
                    className={cn('h-full rounded-full transition-all', colors.bar)}
                    style={{ width: `${Math.min(percentage, 100)}%` }}
                />
            </div>
        </div>
    );
}
