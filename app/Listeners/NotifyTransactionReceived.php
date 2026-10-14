<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TransactionReceived;
use App\Notifications\TransactionReceivedNotification;
use App\Services\SSOIdentityService;
use Illuminate\Support\Facades\Notification;

class NotifyTransactionReceived
{
    public function __construct(private SSOIdentityService $identities) {}

    public function handle(TransactionReceived $event): void
    {
        $action = $event->action;
        $toOfficeId = $action->to_office_id;

        // Notify all users assigned to the receiving office
        $officeUsers = $this->identities->eligibleRecipients(officeId: $toOfficeId);
        Notification::send($officeUsers, new TransactionReceivedNotification($action));
    }
}
