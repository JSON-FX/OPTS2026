import type { DocumentKind, Office } from './types';

export interface HandoffProps {
    transaction: {
        id: number;
        reference_number: string;
        category: DocumentKind;
        status: string;
        current_step_id: number | null;
        current_office?: Office | null;
        received_at?: string | null;
        current_step?: {
            id: number;
            step_order: number;
            office: Office | null;
        } | null;
        procurement: {
            id: number;
            purpose: string | null;
            end_user: Office | null;
        } | null;
    };
    workflowSteps: {
        id: number;
        step_order: number;
        office_name: string;
        office_id?: number;
        office_abbreviation?: string;
        is_final_step: boolean;
    }[];
    actionTakenOptions?: { id: number; description: string }[];
    officeOptions?: (Office & { id: number })[];
    expectedNextOffice?: (Office & { id: number }) | null;
    fromOffice?: Office | null;
    entityShowRoute: { route: string; id: number };
    defaultActionTakenId?: number | null;
}
