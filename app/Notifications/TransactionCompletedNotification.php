<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TransactionCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Transaction $transaction,
        protected User $completedBy
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
        return [
            'type' => 'completed',
            'transaction_id' => $this->transaction->id,
            'reference_number' => $this->transaction->reference_number,
            'category' => $this->transaction->category,
            'completed_by_name' => $this->completedBy->name,
            'message' => "Transaction {$this->transaction->reference_number} ({$this->transaction->category}) has been completed",
        ];
    }
}
