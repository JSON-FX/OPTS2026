<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TransactionOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Transaction $transaction,
        protected int $delayDays
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
        $currentOffice = $this->transaction->currentStep?->office;

        return [
            'type' => 'overdue',
            'transaction_id' => $this->transaction->id,
            'reference_number' => $this->transaction->reference_number,
            'category' => $this->transaction->category,
            'current_office_name' => $currentOffice?->name ?? 'Unknown',
            'delay_days' => $this->delayDays,
            'message' => "Transaction {$this->transaction->reference_number} is overdue by {$this->delayDays} business day(s) at ".($currentOffice?->name ?? 'Unknown'),
        ];
    }
}
