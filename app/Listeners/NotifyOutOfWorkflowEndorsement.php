<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OutOfWorkflowEndorsement;
use App\Models\User;
use App\Notifications\OutOfWorkflowNotification;
use Illuminate\Support\Facades\Notification;

class NotifyOutOfWorkflowEndorsement
{
    public function handle(OutOfWorkflowEndorsement $event): void
    {
        $action = $event->action;
        $transaction = $action->transaction;

        // Get expected office from the current workflow step's next step
        $expectedOffice = $transaction->currentStep?->getNextStep()?->office;

        // Notify all administrators
        $admins = User::role('Administrator')->get();
        Notification::send($admins, new OutOfWorkflowNotification($action, $expectedOffice));

        // Notify users assigned to the expected office (if different from admins already notified)
        if ($expectedOffice) {
            $expectedUsers = User::where('office_id', $expectedOffice->id)
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Administrator'))
                ->get();
            Notification::send($expectedUsers, new OutOfWorkflowNotification($action, $expectedOffice));
        }
    }
}
