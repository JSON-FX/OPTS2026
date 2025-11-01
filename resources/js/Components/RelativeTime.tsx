import { formatDistanceToNow, format } from 'date-fns';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/Components/ui/tooltip';

interface RelativeTimeProps {
    timestamp: string;
}

export default function RelativeTime({ timestamp }: RelativeTimeProps) {
    const date = new Date(timestamp);
    const relativeTime = formatDistanceToNow(date, { addSuffix: true });
    const absoluteTime = format(date, "MMMM d, yyyy 'at' h:mm a");

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className="cursor-help">{relativeTime}</span>
                </TooltipTrigger>
                <TooltipContent>
                    <p>{absoluteTime}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
