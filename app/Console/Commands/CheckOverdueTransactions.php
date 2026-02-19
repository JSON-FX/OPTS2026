<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\User;
use App\Notifications\TransactionOverdueNotification;
use App\Services\EtaCalculationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckOverdueTransactions extends Command
{
    protected $signature = 'opts:check-overdue';

    protected $description = 'Check for overdue transactions and send notifications';

    public function handle(EtaCalculationService $etaService): int
    {
        $transactions = Transaction::where('status', 'In Progress')
            ->whereNotNull('current_step_id')
            ->where(function ($query) {
                $query->whereNull('last_overdue_notified_at')
                    ->orWhere('last_overdue_notified_at', '<=', now()->subHours(24));
            })
            ->with(['currentStep.office', 'workflow.steps'])
            ->get();

        $notifiedCount = 0;

        foreach ($transactions as $transaction) {
            $delayDays = $etaService->getDelayDays($transaction);

            if ($delayDays <= 0) {
                continue;
            }

            // Send to current holder
            $recipients = collect();
            if ($transaction->current_user_id) {
                $currentUser = User::find($transaction->current_user_id);
                if ($currentUser) {
                    $recipients->push($currentUser);
                }
            }

            // Send to all administrators
            $admins = User::role('Administrator')->get();
            $recipients = $recipients->merge($admins)->unique('id');

            Notification::send($recipients, new TransactionOverdueNotification($transaction, $delayDays));

            $transaction->update(['last_overdue_notified_at' => now()]);
            $notifiedCount++;
        }

        $this->info("Sent overdue notifications for {$notifiedCount} transaction(s).");

        return self::SUCCESS;
    }
}
