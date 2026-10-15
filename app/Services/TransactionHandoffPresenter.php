<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActionTaken;
use App\Models\Office;
use App\Models\Transaction;
use App\Models\TransactionAction;

/** Real document and route context shared by both scan-before-confirm pages. */
class TransactionHandoffPresenter
{
    public function __construct(private readonly EndorsementService $endorsements) {}

    public function present(Transaction $transaction, string $mode): array
    {
        $transaction->load(['currentStep.office', 'workflow.steps.office', 'currentOffice', 'procurement.endUser']);
        $expectedOffice = $mode === 'endorse' ? $this->endorsements->getExpectedNextOffice($transaction) : null;
        $lastEndorsement = $transaction->actions()
            ->where('action_type', TransactionAction::TYPE_ENDORSE)
            ->where('to_office_id', $transaction->current_office_id)
            ->latest('id')->with('fromOffice')->first();

        return [
            'transaction' => [
                'id' => $transaction->id,
                'reference_number' => $transaction->reference_number,
                'category' => $transaction->category,
                'status' => $transaction->status,
                'received_at' => $transaction->received_at?->toIso8601String(),
                'endorsed_at' => $transaction->endorsed_at?->toIso8601String(),
                'current_office' => $this->office($transaction->currentOffice),
                'current_step_id' => $transaction->current_step_id,
                'current_step' => $transaction->currentStep ? [
                    'id' => $transaction->currentStep->id,
                    'step_order' => $transaction->currentStep->step_order,
                    'office' => $this->office($transaction->currentStep->office),
                ] : null,
                'procurement' => $transaction->procurement ? [
                    'id' => $transaction->procurement->id,
                    'purpose' => $transaction->procurement->purpose,
                    'end_user' => $this->office($transaction->procurement->endUser),
                ] : null,
            ],
            'workflowSteps' => $transaction->workflow?->steps->map(fn ($step) => [
                'id' => $step->id,
                'step_order' => $step->step_order,
                'office_id' => $step->office_id,
                'office_name' => $step->office?->name ?? 'Unavailable office',
                'office_abbreviation' => $step->office?->abbreviation,
                'is_final_step' => $step->is_final_step,
            ])->values()->all() ?? [],
            'entityShowRoute' => $this->entityShowRoute($transaction),
            'fromOffice' => $this->office($lastEndorsement?->fromOffice),
            'actionTakenOptions' => $mode === 'endorse' ? ActionTaken::query()->where('is_active', true)
                ->orderBy('description')->get(['id', 'description']) : [],
            'officeOptions' => $mode === 'endorse' ? Office::query()->where('is_active', true)->whereNotNull('sso_id')
                ->orderBy('name')->get(['id', 'name', 'abbreviation']) : [],
            'expectedNextOffice' => $this->office($expectedOffice),
            'defaultActionTakenId' => $mode === 'endorse' ? $transaction->currentStep?->action_taken_id : null,
        ];
    }

    public function entityShowRoute(Transaction $transaction): array
    {
        return match ($transaction->category) {
            'PO' => ['route' => 'purchase-orders.show', 'id' => $transaction->purchaseOrder?->id ?? $transaction->id],
            'VCH' => ['route' => 'vouchers.show', 'id' => $transaction->voucher?->id ?? $transaction->id],
            default => ['route' => 'purchase-requests.show', 'id' => $transaction->purchaseRequest?->id ?? $transaction->id],
        };
    }

    private function office(?Office $office): ?array
    {
        return $office ? ['id' => $office->id, 'name' => $office->name, 'abbreviation' => $office->abbreviation] : null;
    }
}
