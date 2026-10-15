<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PurchaseRequest;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardNotificationLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_links_resolve_entity_ids_and_preserve_read_ownership(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create(['category' => 'PR']);
        $pr = PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);
        $notice = $user->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => 'test',
            'data' => ['transaction_id' => $transaction->id, 'message' => 'Ready for review', 'document_url' => 'https://invalid.example'],
        ]);
        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unread_count', 1)
            ->where('notifications.recent.0.data.document_url', route('purchase-requests.show', $pr)));

        $this->actingAs(User::factory()->create())->post(route('notifications.markAsRead', $notice->id))->assertNotFound();
        $this->assertNull($notice->fresh()->read_at);
        $this->actingAs($user)->post(route('notifications.markAsRead', $notice->id))->assertRedirect();
        $this->assertNotNull($notice->fresh()->read_at);

        $transaction->delete();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('notifications.recent.0.data.document_url', null));
    }
}
