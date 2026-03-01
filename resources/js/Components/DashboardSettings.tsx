import { useState, useEffect } from 'react';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Switch } from '@/Components/ui/switch';
import { Label } from '@/Components/ui/label';
import { Button } from '@/Components/ui/button';
import { Settings2 } from 'lucide-react';

export interface DashboardCardVisibility {
    officeWorkload: boolean;
    recentActivity: boolean;
    needsAttention: boolean;
    performanceMetrics: boolean;
    outOfWorkflow: boolean;
    transactionVolume: boolean;
}

const DEFAULT_VISIBILITY: DashboardCardVisibility = {
    officeWorkload: true,
    recentActivity: true,
    needsAttention: true,
    performanceMetrics: false,
    outOfWorkflow: false,
    transactionVolume: false,
};

const CARD_LABELS: Record<keyof DashboardCardVisibility, string> = {
    officeWorkload: 'Office Workload',
    recentActivity: 'Recent Activity',
    needsAttention: 'Needs Attention',
    performanceMetrics: 'Performance Metrics',
    outOfWorkflow: 'Out of Workflow Incidents',
    transactionVolume: 'Transaction Volume',
};

function getStorageKey(userId: number): string {
    return `dashboard_settings_${userId}`;
}

export function loadDashboardSettings(userId: number): DashboardCardVisibility {
    try {
        const stored = localStorage.getItem(getStorageKey(userId));
        if (stored) {
            return { ...DEFAULT_VISIBILITY, ...JSON.parse(stored) };
        }
    } catch {
        // ignore parse errors
    }
    return { ...DEFAULT_VISIBILITY };
}

function saveDashboardSettings(userId: number, settings: DashboardCardVisibility): void {
    localStorage.setItem(getStorageKey(userId), JSON.stringify(settings));
}

interface Props {
    userId: number;
    visibility: DashboardCardVisibility;
    onChange: (visibility: DashboardCardVisibility) => void;
}

export default function DashboardSettings({ userId, visibility, onChange }: Props) {
    const handleToggle = (key: keyof DashboardCardVisibility) => {
        const updated = { ...visibility, [key]: !visibility[key] };
        saveDashboardSettings(userId, updated);
        onChange(updated);
    };

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button variant="outline" size="sm" className="gap-1.5">
                    <Settings2 className="h-4 w-4" />
                    Settings
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-64">
                <div className="space-y-1 pb-2">
                    <h4 className="text-sm font-semibold">Visible Cards</h4>
                    <p className="text-xs text-muted-foreground">
                        Toggle which sections appear on the dashboard.
                    </p>
                </div>
                <div className="space-y-3">
                    {(Object.keys(CARD_LABELS) as Array<keyof DashboardCardVisibility>).map(
                        (key) => (
                            <div key={key} className="flex items-center justify-between">
                                <Label
                                    htmlFor={`dashboard-${key}`}
                                    className="text-sm font-normal cursor-pointer"
                                >
                                    {CARD_LABELS[key]}
                                </Label>
                                <Switch
                                    id={`dashboard-${key}`}
                                    checked={visibility[key]}
                                    onCheckedChange={() => handleToggle(key)}
                                />
                            </div>
                        )
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}
