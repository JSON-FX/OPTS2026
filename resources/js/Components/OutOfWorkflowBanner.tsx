import { AlertTriangle } from 'lucide-react';

interface OutOfWorkflowInfo {
    is_out_of_workflow: boolean;
    expected_office_name: string | null;
    actual_office_name: string | null;
}

interface Props {
    outOfWorkflowInfo: OutOfWorkflowInfo | null;
}

export default function OutOfWorkflowBanner({ outOfWorkflowInfo }: Props) {
    if (!outOfWorkflowInfo?.is_out_of_workflow) {
        return null;
    }

    return (
        <div className="rounded-lg border border-amber-300 bg-amber-50 p-4">
            <div className="flex items-start gap-3">
                <AlertTriangle className="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
                <div>
                    <h4 className="text-sm font-semibold text-amber-800">
                        Out-of-Workflow Routing Detected
                    </h4>
                    <p className="text-sm text-amber-700 mt-1">
                        This transaction was routed outside the expected workflow.
                    </p>
                    {(outOfWorkflowInfo.expected_office_name || outOfWorkflowInfo.actual_office_name) && (
                        <div className="mt-2 text-sm text-amber-700 space-y-1">
                            {outOfWorkflowInfo.expected_office_name && (
                                <p>
                                    <span className="font-medium">Expected office:</span>{' '}
                                    {outOfWorkflowInfo.expected_office_name}
                                </p>
                            )}
                            {outOfWorkflowInfo.actual_office_name && (
                                <p>
                                    <span className="font-medium">Actual office:</span>{' '}
                                    {outOfWorkflowInfo.actual_office_name}
                                </p>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
