import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { ArrowDown, ArrowUp, Minus } from 'lucide-react';
import type { VolumeSummary } from '@/types/models';

const categoryLabels: Record<string, string> = {
    PR: 'Purchase Requests',
    PO: 'Purchase Orders',
    VCH: 'Vouchers',
};

interface Props {
    data: VolumeSummary[];
}

export default function VolumeSummaryCard({ data }: Props) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium">Transaction Volume</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="space-y-3">
                    {data.map((item) => (
                        <div key={item.category} className="flex items-center justify-between">
                            <div>
                                <span className="text-sm font-medium">
                                    {categoryLabels[item.category] ?? item.category}
                                </span>
                                <p className="text-xs text-muted-foreground">
                                    {item.current_month} vs {item.previous_month}
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-lg font-bold">{item.current_month}</span>
                                <TrendIndicator percentage={item.trend_percentage} />
                            </div>
                        </div>
                    ))}
                    {data.length === 0 && (
                        <p className="text-sm text-muted-foreground">No transaction data</p>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function TrendIndicator({ percentage }: { percentage: number }) {
    if (percentage === 0) {
        return (
            <span className="flex items-center text-sm text-muted-foreground">
                <Minus className="mr-0.5 h-3 w-3" />
                0%
            </span>
        );
    }

    const isUp = percentage > 0;
    const Icon = isUp ? ArrowUp : ArrowDown;

    return (
        <span className={`flex items-center text-sm font-medium ${isUp ? 'text-blue-600' : 'text-orange-600'}`}>
            <Icon className="mr-0.5 h-3 w-3" />
            {Math.abs(percentage)}%
        </span>
    );
}
