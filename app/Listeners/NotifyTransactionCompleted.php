<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TransactionCompleted;
use App\Models\User;
use App\Notifications\TransactionCompletedNotification;
use Illuminate\Support\Facades\Notification;

class NotifyTransactionCompleted
{
    public function handle(TransactionCompleted $event): void
    {
        $transaction = $event->transaction;
        $completedBy = $event->completedBy;

        // Notify the transaction creator
        $creator = User::find($transaction->created_by_user_id);
        if ($creator) {
            Notification::send($creator, new TransactionCompletedNotification($transaction, $completedBy));
        }
    }
}
