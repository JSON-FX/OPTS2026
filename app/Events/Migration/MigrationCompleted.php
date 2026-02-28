<?php

declare(strict_types=1);

namespace App\Events\Migration;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MigrationCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $importId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("migration.{$this->importId}")];
    }
}
