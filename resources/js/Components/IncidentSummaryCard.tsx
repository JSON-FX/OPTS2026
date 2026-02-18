import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Minus } from 'lucide-react';
import type { IncidentSummary } from '@/types/models';

interface Props {
    data: IncidentSummary;
}

export default function IncidentSummaryCard({ data }: Props) {
    const { current_month, previous_month, trend_percentage } = data;

    const handleClick = () => {
        router.get(route('notifications.index'), { type: 'out_of_workflow' });
    };

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium">Out-of-Workflow Incidents</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="flex items-baseline gap-3">
                    <button
                        onClick={handleClick}
                        className="text-3xl font-bold text-blue-600 hover:underline"
                    >
                        {current_month}
                    </button>
                    <TrendIndicator
                        percentage={trend_percentage}
                        invertColor
                    />
                </div>
                <p className="mt-1 text-xs text-muted-foreground">
                    vs {previous_month} last month
                </p>
            </CardContent>
        </Card>
    );
}

function TrendIndicator({ percentage, invertColor }: { percentage: number; invertColor?: boolean }) {
    if (percentage === 0) {
        return (
            <span className="flex items-center text-sm text-muted-foreground">
                <Minus className="mr-0.5 h-3 w-3" />
                0%
            </span>
        );
    }

    const isUp = percentage > 0;
    // For incidents, up is bad (red), down is good (green)
    const colorClass = invertColor
        ? (isUp ? 'text-red-600' : 'text-green-600')
        : (isUp ? 'text-green-600' : 'text-red-600');

    const Icon = isUp ? ArrowUp : ArrowDown;

    return (
        <span className={`flex items-center text-sm font-medium ${colorClass}`}>
            <Icon className="mr-0.5 h-3 w-3" />
            {Math.abs(percentage)}%
        </span>
    );
}
