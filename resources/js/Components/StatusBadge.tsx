import { Badge } from '@/Components/ui/badge';
import { cn } from '@/lib/utils';

interface StatusBadgeProps {
    status: string;
    className?: string;
}

const statusColors: Record<string, string> = {
    'Created': 'bg-gray-100 text-gray-800',
    'In Progress': 'bg-blue-100 text-blue-800',
    'Completed': 'bg-green-100 text-green-800',
    'On Hold': 'bg-yellow-100 text-yellow-800',
    'Cancelled': 'bg-red-100 text-red-800',
};

export default function StatusBadge({ status, className }: StatusBadgeProps) {
    return (
        <Badge
            variant="outline"
            className={cn(statusColors[status] || 'bg-gray-100 text-gray-800', className)}
        >
            {status}
        </Badge>
    );
}
