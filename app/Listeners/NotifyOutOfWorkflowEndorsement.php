<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OutOfWorkflowEndorsement;
use App\Notifications\OutOfWorkflowNotification;
use App\Services\SSOIdentityService;
use Illuminate\Support\Facades\Notification;

class NotifyOutOfWorkflowEndorsement
{
    public function __construct(private SSOIdentityService $identities) {}

    public function handle(OutOfWorkflowEndorsement $event): void
    {
        $action = $event->action;
        $transaction = $action->transaction;

        // Get expected office from the current workflow step's next step
        $expectedOffice = $transaction->currentStep?->getNextStep()?->office;

        // Notify all administrators
        $admins = $this->identities->eligibleRecipients(['administrator', 'super_administrator']);
        Notification::send($admins, new OutOfWorkflowNotification($action, $expectedOffice));

        // Notify users assigned to the expected office (if different from admins already notified)
        if ($expectedOffice) {
            $expectedUsers = $this->identities->eligibleRecipients(officeId: $expectedOffice->id)->except($admins->modelKeys());
            Notification::send($expectedUsers, new OutOfWorkflowNotification($action, $expectedOffice));
        }
    }
}
