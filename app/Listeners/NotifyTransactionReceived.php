<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TransactionReceived;
use App\Models\User;
use App\Notifications\TransactionReceivedNotification;
use Illuminate\Support\Facades\Notification;

class NotifyTransactionReceived
{
    public function handle(TransactionReceived $event): void
    {
        $action = $event->action;
        $toOfficeId = $action->to_office_id;

        // Notify all users assigned to the receiving office
        $officeUsers = User::where('office_id', $toOfficeId)->get();
        Notification::send($officeUsers, new TransactionReceivedNotification($action));
    }
}
