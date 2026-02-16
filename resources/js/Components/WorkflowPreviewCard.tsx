import { useState } from 'react';

interface WorkflowStep {
    step_order: number;
    office_name: string;
    office_abbreviation: string;
    expected_days: number;
    is_final_step: boolean;
}

export interface WorkflowPreview {
    workflow_id: number;
    workflow_name: string;
    total_steps: number;
    total_expected_days: number;
    steps: WorkflowStep[];
}

interface Props {
    preview: WorkflowPreview;
}

export default function WorkflowPreviewCard({ preview }: Props) {
    const [expanded, setExpanded] = useState(false);

    return (
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div className="flex items-center justify-between">
                <div>
                    <h4 className="text-sm font-semibold text-blue-900">
                        Workflow: {preview.workflow_name}
                    </h4>
                    <p className="text-xs text-blue-700 mt-1">
                        {preview.total_steps} steps · ~{preview.total_expected_days} days total
                    </p>
                </div>
                <button
                    type="button"
                    onClick={() => setExpanded(!expanded)}
                    className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                >
                    {expanded ? 'Hide Steps' : 'Show Steps'}
                </button>
            </div>

            {/* Route preview - always visible */}
            <div className="mt-3 flex flex-wrap items-center gap-1 text-xs text-blue-800">
                {preview.steps.map((step, index) => (
                    <span key={step.step_order} className="flex items-center gap-1">
                        <span className="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-200 text-blue-800 font-semibold text-[10px]">
                            {step.step_order}
                        </span>
                        <span>{step.office_abbreviation}</span>
                        {index < preview.steps.length - 1 && (
                            <span className="text-blue-400 mx-1">&rarr;</span>
                        )}
                    </span>
                ))}
            </div>

            {/* Expanded step details */}
            {expanded && (
                <div className="mt-3 border-t border-blue-200 pt-3">
                    <table className="w-full text-xs">
                        <thead>
                            <tr className="text-left text-blue-700">
                                <th className="pb-1">#</th>
                                <th className="pb-1">Office</th>
                                <th className="pb-1 text-right">Expected Days</th>
                            </tr>
                        </thead>
                        <tbody className="text-blue-900">
                            {preview.steps.map((step) => (
                                <tr key={step.step_order} className="border-t border-blue-100">
                                    <td className="py-1">{step.step_order}</td>
                                    <td className="py-1">
                                        {step.office_name}
                                        {step.is_final_step && (
                                            <span className="ml-1 text-[10px] bg-blue-200 px-1 rounded">Final</span>
                                        )}
                                    </td>
                                    <td className="py-1 text-right">{step.expected_days}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
