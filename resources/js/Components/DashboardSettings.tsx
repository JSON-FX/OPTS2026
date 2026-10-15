import {
    DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent,
    DropdownMenuLabel, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
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
    performanceMetrics: true,
    outOfWorkflow: true,
    transactionVolume: true,
};

const CARD_LABELS = {
    officeWorkload: 'Office workload',
    recentActivity: 'Recent activity',
    transactionVolume: 'Document volume',
    performanceMetrics: 'Office turnaround',
} satisfies Partial<Record<keyof DashboardCardVisibility, string>>;

function getStorageKey(userId: number): string {
    return `dashboard_settings_${userId}`;
}

export function loadDashboardSettings(userId: number): DashboardCardVisibility {
    try {
        const stored = localStorage.getItem(getStorageKey(userId));
        if (stored) {
            const parsed: unknown = JSON.parse(stored);
            if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                const settings = { ...DEFAULT_VISIBILITY };
                for (const key of Object.keys(settings) as Array<keyof DashboardCardVisibility>) {
                    const value = (parsed as Record<string, unknown>)[key];
                    if (typeof value === 'boolean') settings[key] = value;
                }
                return settings;
            }
        }
    } catch {
        // ignore parse errors
    }
    return { ...DEFAULT_VISIBILITY };
}

function saveDashboardSettings(userId: number, settings: DashboardCardVisibility): void {
    try {
        localStorage.setItem(getStorageKey(userId), JSON.stringify(settings));
    } catch {
        // Keep the current session usable when browser storage is unavailable.
    }
}

interface Props {
    userId: number;
    visibility: DashboardCardVisibility;
    onChange: (visibility: DashboardCardVisibility) => void;
}

export default function DashboardSettings({ userId, visibility, onChange }: Props) {
    const handleToggle = (key: keyof DashboardCardVisibility, checked: boolean) => {
        const updated = { ...visibility, [key]: checked };
        saveDashboardSettings(userId, updated);
        onChange(updated);
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="op-settings gap-1.5" aria-label="Customize Dashboard sections" data-slot="button">
                    <Settings2 className="h-4 w-4" />
                    Customize
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="op-live op-live-overlay w-64 max-w-[calc(100vw-24px)]">
                <DropdownMenuLabel>Show on Dashboard</DropdownMenuLabel>
                {(Object.keys(CARD_LABELS) as Array<keyof typeof CARD_LABELS>).map((key) => (
                    <DropdownMenuCheckboxItem key={key} checked={visibility[key]} onCheckedChange={(checked) => handleToggle(key, checked === true)}>
                        {CARD_LABELS[key]}
                    </DropdownMenuCheckboxItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
