<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Transaction $transaction,
        public User $completedBy
    ) {}
}
