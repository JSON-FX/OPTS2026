import { useState } from 'react';

interface ExpandableTextProps {
    text: string;
    maxLength?: number;
}

export default function ExpandableText({ text, maxLength = 500 }: ExpandableTextProps) {
    const [isExpanded, setIsExpanded] = useState(false);

    if (text.length <= maxLength) {
        return <span>{text}</span>;
    }

    const truncatedText = text.substring(0, maxLength);

    return (
        <span>
            {isExpanded ? text : `${truncatedText}...`}
            {' '}
            <button
                type="button"
                onClick={() => setIsExpanded(!isExpanded)}
                className="text-blue-600 hover:underline focus:outline-none"
            >
                {isExpanded ? 'Show less' : 'Show more'}
            </button>
        </span>
    );
}
