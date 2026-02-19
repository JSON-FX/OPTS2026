<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\TransactionAction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TransactionReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected TransactionAction $action
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $transaction = $this->action->transaction;
        $fromOffice = $this->action->fromOffice;
        $toOffice = $this->action->toOffice;
        $receivedBy = $this->action->toUser;

        return [
            'type' => 'received',
            'transaction_id' => $transaction->id,
            'reference_number' => $transaction->reference_number,
            'category' => $transaction->category,
            'from_office_name' => $fromOffice?->name ?? 'Unknown',
            'to_office_name' => $toOffice?->name ?? 'Unknown',
            'received_by_name' => $receivedBy?->name ?? 'Unknown',
            'message' => "Transaction {$transaction->reference_number} has been received at ".($toOffice?->name ?? 'Unknown'),
        ];
    }
}
