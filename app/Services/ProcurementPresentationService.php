<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Office;
use App\Models\Particular;
use App\Models\Procurement;
use App\Models\Transaction;
use App\Models\User;

/** Explicit procurement page data without serializing Transaction's computed model appends. */
class ProcurementPresentationService
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function documentRelations(): array
    {
        return [
            'transactions' => fn ($query) => $query->select([
                'id', 'procurement_id', 'category', 'reference_number', 'status', 'created_at',
                'current_office_id', 'current_step_id', 'workflow_id', 'received_at',
            ])->orderBy('id'),
            'transactions.currentOffice' => fn ($query) => $query->withTrashed()->select('id', 'name', 'abbreviation'),
            'transactions.currentStep',
            'transactions.latestAction',
            'transactions.workflow.steps.office' => fn ($query) => $query->withTrashed()->select('id', 'name', 'abbreviation'),
            'transactions.purchaseRequest:id,transaction_id',
            'transactions.purchaseOrder:id,transaction_id',
            'transactions.voucher:id,transaction_id',
        ];
    }

    public function formOptions(): array
    {
        return [
            'today' => today()->toDateString(),
            'offices' => Office::query()->where('is_active', true)->whereNotNull('sso_id')
                ->orderBy('abbreviation')->get(['id', 'name', 'abbreviation']),
            'particulars' => Particular::query()->where('is_active', true)
                ->orderBy('description')->get(['id', 'description']),
        ];
    }

    public function procurement(Procurement $procurement, User $user, bool $detail = false): array
    {
        $data = [
            ...$procurement->only(['id', 'end_user_id', 'particular_id', 'purpose', 'abc_amount', 'status', 'created_by_user_id', 'transactions_count']),
            'date_of_entry' => $procurement->date_of_entry?->toDateString(),
            'created_at' => $procurement->created_at?->toISOString(),
            'updated_at' => $procurement->updated_at?->toISOString(),
            'end_user' => $procurement->endUser?->only(['id', 'name', 'abbreviation']),
            'particular' => $procurement->particular?->only(['id', 'description']),
            'creator' => $procurement->creator?->only(['id', 'name']),
            'documents' => $procurement->transactions->map(function (Transaction $transaction) use ($procurement, $user) {
                $transaction->setRelation('procurement', $procurement);

                return $this->dashboard->mapDocument($transaction, $user);
            })->all(),
        ];

        if ($detail) {
            $data['purchase_request'] = $procurement->purchaseRequest ? [
                ...$procurement->purchaseRequest->only(['id', 'transaction_id', 'fund_type_id']),
                'fund_type' => $procurement->purchaseRequest->fundType?->only(['id', 'name', 'abbreviation']),
                'transaction' => $this->linkedTransaction($procurement->purchaseRequest->transaction),
            ] : null;
            $data['purchase_order'] = $procurement->purchaseOrder ? [
                ...$procurement->purchaseOrder->only(['id', 'transaction_id', 'supplier_id', 'supplier_address', 'contract_price']),
                'supplier' => $procurement->purchaseOrder->supplier?->only(['id', 'name']),
                'transaction' => $this->linkedTransaction($procurement->purchaseOrder->transaction),
            ] : null;
            $data['voucher'] = $procurement->voucher ? [
                ...$procurement->voucher->only(['id', 'transaction_id', 'payee']),
                'transaction' => $this->linkedTransaction($procurement->voucher->transaction),
            ] : null;
            $data['status_history'] = $procurement->statusHistory->map(fn ($history) => [
                ...$history->only(['id', 'old_status', 'new_status', 'changed_by_user_id', 'reason']),
                'created_at' => $history->created_at?->toISOString(),
                'changed_by' => $history->changedBy?->only(['id', 'name']),
            ])->all();
        }

        return $data;
    }

    private function linkedTransaction(?Transaction $transaction): ?array
    {
        return $transaction ? [
            ...$transaction->only(['id', 'reference_number', 'status', 'created_by_user_id']),
            'created_at' => $transaction->created_at?->toISOString(),
            'created_by' => $transaction->createdBy?->only(['id', 'name']),
        ] : null;
    }

    public function documentUrl(Transaction $transaction): ?string
    {
        [$name, $id] = match ($transaction->category) {
            'PR' => ['purchase-requests.show', $transaction->purchaseRequest?->id],
            'PO' => ['purchase-orders.show', $transaction->purchaseOrder?->id],
            'VCH' => ['vouchers.show', $transaction->voucher?->id],
            default => [null, null],
        };

        return $id ? route($name, $id) : null;
    }
}
