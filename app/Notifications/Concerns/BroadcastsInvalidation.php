<?php

namespace App\Notifications\Concerns;

trait BroadcastsInvalidation
{
    // Private socket subscriptions can outlive an SSO grant. Fetch protected data over HTTP.
    public function toBroadcast(object $notifiable): array
    {
        return ['refresh' => true];
    }

    public function broadcastWith(): array
    {
        return ['refresh' => true];
    }
}
