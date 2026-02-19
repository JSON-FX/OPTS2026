<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Office;
use App\Models\TransactionAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class OutOfWorkflowNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        protected TransactionAction $action,
        protected ?Office $expectedOffice
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $transaction = $this->action->transaction;
        $actualOffice = $this->action->toOffice;

        return [
            'type' => 'out_of_workflow',
            'transaction_id' => $transaction->id,
            'reference_number' => $transaction->reference_number,
            'category' => $transaction->category,
            'expected_office_id' => $this->expectedOffice?->id,
            'expected_office_name' => $this->expectedOffice?->name ?? 'Unknown',
            'actual_office_id' => $actualOffice?->id,
            'actual_office_name' => $actualOffice?->name ?? 'Unknown',
            'endorsed_by_user_id' => $this->action->from_user_id,
            'endorsed_by_name' => $this->action->fromUser?->name ?? 'Unknown',
            'message' => "Transaction {$transaction->reference_number} was sent to ".
                ($actualOffice?->name ?? 'Unknown').
                ' instead of expected '.
                ($this->expectedOffice?->name ?? 'Unknown'),
        ];
    }
}
