<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\TransactionCompleted;
use App\Events\TransactionReceived;
use App\Listeners\NotifyTransactionCompleted;
use App\Listeners\NotifyTransactionReceived;
use App\Models\ActionTaken;
use App\Models\Office;
use App\Models\Procurement;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Notifications\TransactionCompletedNotification;
use App\Notifications\TransactionOverdueNotification;
use App\Notifications\TransactionReceivedNotification;
use App\Services\EndorsementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdditionalNotificationTypesTest extends TestCase
{
    use RefreshDatabase;

    private EndorsementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->service = app(EndorsementService::class);
    }

    /**
     * Create a standard test setup with a 2-step workflow.
     */
    protected function createTestSetup(): array
    {
        $office1 = Office::factory()->create(['is_active' => true, 'name' => 'Budget Office']);
        $office2 = Office::factory()->create(['is_active' => true, 'name' => 'Accounting Office']);

        $workflow = Workflow::factory()->create([
            'category' => 'PR',
            'is_active' => true,
        ]);

        $step1 = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office1->id,
            'step_order' => 1,
            'is_final_step' => false,
            'expected_days' => 3,
        ]);

        $step2 = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office2->id,
            'step_order' => 2,
            'is_final_step' => true,
            'expected_days' => 3,
        ]);

        $creator = User::factory()->create(['office_id' => $office1->id]);
        $creator->assignRole('Viewer');

        $endorser = User::factory()->create(['office_id' => $office1->id]);
        $endorser->assignRole('Endorser');

        $receiver = User::factory()->create(['office_id' => $office2->id]);
        $receiver->assignRole('Endorser');

        $admin = User::factory()->create(['office_id' => $office1->id]);
        $admin->assignRole('Administrator');

        $actionTaken = ActionTaken::factory()->create(['is_active' => true]);

        $procurement = Procurement::factory()->create();
        $transaction = Transaction::factory()->create([
            'procurement_id' => $procurement->id,
            'category' => 'PR',
            'status' => 'In Progress',
            'workflow_id' => $workflow->id,
            'current_office_id' => $office1->id,
            'current_step_id' => $step1->id,
            'received_at' => now(),
            'created_by_user_id' => $creator->id,
        ]);

        return compact(
            'office1', 'office2',
            'workflow', 'step1', 'step2',
            'creator', 'endorser', 'receiver', 'admin',
            'actionTaken', 'procurement', 'transaction'
        );
    }

    // -------------------------------------------------------
    // TransactionReceivedNotification tests
    // -------------------------------------------------------

    public function test_transaction_received_notification_to_array(): void
    {
        $setup = $this->createTestSetup();

        $action = TransactionAction::create([
            'transaction_id' => $setup['transaction']->id,
            'action_type' => TransactionAction::TYPE_RECEIVE,
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office2']->id,
            'from_user_id' => $setup['endorser']->id,
            'to_user_id' => $setup['receiver']->id,
            'workflow_step_id' => $setup['step1']->id,
        ]);

        $action->load(['transaction', 'fromOffice', 'toOffice', 'toUser']);

        $notification = new TransactionReceivedNotification($action);
        $data = $notification->toArray($setup['receiver']);

        $this->assertEquals('received', $data['type']);
        $this->assertEquals($setup['transaction']->id, $data['transaction_id']);
        $this->assertEquals($setup['transaction']->reference_number, $data['reference_number']);
        $this->assertEquals('PR', $data['category']);
        $this->assertEquals('Budget Office', $data['from_office_name']);
        $this->assertEquals('Accounting Office', $data['to_office_name']);
        $this->assertEquals($setup['receiver']->name, $data['received_by_name']);
        $this->assertStringContainsString($setup['transaction']->reference_number, $data['message']);
        $this->assertStringContainsString('Accounting Office', $data['message']);
    }

    public function test_transaction_received_event_fires_on_receive(): void
    {
        Event::fake([TransactionReceived::class]);

        $setup = $this->createTestSetup();

        // First endorse to office2
        $this->service->endorse(
            $setup['transaction'],
            $setup['endorser'],
            $setup['actionTaken']->id,
            $setup['office2']->id
        );

        // Update transaction state for receive
        $setup['transaction']->refresh();

        // Receive at office2
        $this->service->receive(
            $setup['transaction'],
            $setup['receiver']
        );

        Event::assertDispatched(TransactionReceived::class);
    }

    public function test_transaction_received_listener_sends_notifications(): void
    {
        Notification::fake();

        $setup = $this->createTestSetup();

        $action = TransactionAction::create([
            'transaction_id' => $setup['transaction']->id,
            'action_type' => TransactionAction::TYPE_RECEIVE,
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office2']->id,
            'from_user_id' => $setup['endorser']->id,
            'to_user_id' => $setup['receiver']->id,
            'workflow_step_id' => $setup['step1']->id,
        ]);

        $action->load(['transaction', 'fromOffice', 'toOffice', 'toUser']);

        $listener = new NotifyTransactionReceived;
        $listener->handle(new TransactionReceived($action));

        // Receiver is in office2, should be notified
        Notification::assertSentTo($setup['receiver'], TransactionReceivedNotification::class);
    }

    // -------------------------------------------------------
    // TransactionCompletedNotification tests
    // -------------------------------------------------------

    public function test_transaction_completed_notification_to_array(): void
    {
        $setup = $this->createTestSetup();

        $notification = new TransactionCompletedNotification($setup['transaction'], $setup['receiver']);
        $data = $notification->toArray($setup['creator']);

        $this->assertEquals('completed', $data['type']);
        $this->assertEquals($setup['transaction']->id, $data['transaction_id']);
        $this->assertEquals($setup['transaction']->reference_number, $data['reference_number']);
        $this->assertEquals('PR', $data['category']);
        $this->assertEquals($setup['receiver']->name, $data['completed_by_name']);
        $this->assertStringContainsString($setup['transaction']->reference_number, $data['message']);
        $this->assertStringContainsString('PR', $data['message']);
        $this->assertStringContainsString('completed', $data['message']);
    }

    public function test_transaction_completed_event_fires_on_complete(): void
    {
        Event::fake([TransactionCompleted::class]);

        $setup = $this->createTestSetup();

        // Move transaction to final step so it can be completed
        $setup['transaction']->update([
            'current_office_id' => $setup['office2']->id,
            'current_step_id' => $setup['step2']->id,
            'received_at' => now(),
        ]);

        $this->service->complete(
            $setup['transaction'],
            $setup['receiver'],
            $setup['actionTaken']->id
        );

        Event::assertDispatched(TransactionCompleted::class);
    }

    public function test_transaction_completed_listener_sends_notification_to_creator(): void
    {
        Notification::fake();

        $setup = $this->createTestSetup();

        $listener = new NotifyTransactionCompleted;
        $listener->handle(new TransactionCompleted($setup['transaction'], $setup['receiver']));

        Notification::assertSentTo($setup['creator'], TransactionCompletedNotification::class);
    }

    // -------------------------------------------------------
    // TransactionOverdueNotification tests
    // -------------------------------------------------------

    public function test_transaction_overdue_notification_to_array(): void
    {
        $setup = $this->createTestSetup();
        $setup['transaction']->load('currentStep.office');

        $notification = new TransactionOverdueNotification($setup['transaction'], 5);
        $data = $notification->toArray($setup['admin']);

        $this->assertEquals('overdue', $data['type']);
        $this->assertEquals($setup['transaction']->id, $data['transaction_id']);
        $this->assertEquals($setup['transaction']->reference_number, $data['reference_number']);
        $this->assertEquals('PR', $data['category']);
        $this->assertEquals(5, $data['delay_days']);
        $this->assertStringContainsString($setup['transaction']->reference_number, $data['message']);
        $this->assertStringContainsString('5 business day(s)', $data['message']);
    }

    // -------------------------------------------------------
    // opts:check-overdue command tests
    // -------------------------------------------------------

    public function test_check_overdue_command_sends_notifications_for_overdue_transactions(): void
    {
        Notification::fake();

        $setup = $this->createTestSetup();

        // Set received_at far in the past so it's overdue
        $setup['transaction']->update([
            'received_at' => now()->subDays(30),
            'current_user_id' => $setup['endorser']->id,
        ]);

        $this->artisan('opts:check-overdue')->assertSuccessful();

        // Admin should receive overdue notification
        Notification::assertSentTo($setup['admin'], TransactionOverdueNotification::class);

        // Current holder should also receive notification
        Notification::assertSentTo($setup['endorser'], TransactionOverdueNotification::class);
    }

    public function test_check_overdue_command_skips_recently_notified(): void
    {
        Notification::fake();

        $setup = $this->createTestSetup();

        // Set received_at far in the past so it's overdue, but recently notified
        $setup['transaction']->update([
            'received_at' => now()->subDays(30),
            'current_user_id' => $setup['endorser']->id,
            'last_overdue_notified_at' => now()->subHours(12), // Within 24h
        ]);

        $this->artisan('opts:check-overdue')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_check_overdue_command_skips_non_overdue_transactions(): void
    {
        Notification::fake();

        $setup = $this->createTestSetup();

        // Transaction received today — not overdue (expected_days = 3)
        $setup['transaction']->update([
            'received_at' => now(),
            'current_user_id' => $setup['endorser']->id,
        ]);

        $this->artisan('opts:check-overdue')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_check_overdue_command_updates_last_overdue_notified_at(): void
    {
        Notification::fake();

        $setup = $this->createTestSetup();

        $setup['transaction']->update([
            'received_at' => now()->subDays(30),
            'current_user_id' => $setup['endorser']->id,
        ]);

        $this->assertNull($setup['transaction']->last_overdue_notified_at);

        $this->artisan('opts:check-overdue')->assertSuccessful();

        $setup['transaction']->refresh();
        $this->assertNotNull($setup['transaction']->last_overdue_notified_at);
    }

    // -------------------------------------------------------
    // Notifications Index filter tests
    // -------------------------------------------------------

    public function test_notifications_index_filters_by_received_type(): void
    {
        $this->withoutVite();
        $setup = $this->createTestSetup();

        $this->actingAs($setup['admin'])
            ->get(route('notifications.index', ['type' => 'received']))
            ->assertStatus(200);
    }

    public function test_notifications_index_filters_by_overdue_type(): void
    {
        $this->withoutVite();
        $setup = $this->createTestSetup();

        $this->actingAs($setup['admin'])
            ->get(route('notifications.index', ['type' => 'overdue']))
            ->assertStatus(200);
    }

    public function test_notifications_index_filters_by_completed_type(): void
    {
        $this->withoutVite();
        $setup = $this->createTestSetup();

        $this->actingAs($setup['admin'])
            ->get(route('notifications.index', ['type' => 'completed']))
            ->assertStatus(200);
    }

    // -------------------------------------------------------
    // Shared props / bell count tests
    // -------------------------------------------------------

    public function test_new_notification_types_appear_in_bell_count(): void
    {
        $setup = $this->createTestSetup();

        // Create a received notification
        $setup['admin']->notify(new TransactionReceivedNotification(
            TransactionAction::create([
                'transaction_id' => $setup['transaction']->id,
                'action_type' => TransactionAction::TYPE_RECEIVE,
                'from_office_id' => $setup['office1']->id,
                'to_office_id' => $setup['office2']->id,
                'from_user_id' => $setup['endorser']->id,
                'to_user_id' => $setup['receiver']->id,
            ])
        ));

        // Create an overdue notification
        $setup['transaction']->load('currentStep.office');
        $setup['admin']->notify(new TransactionOverdueNotification($setup['transaction'], 3));

        // Create a completed notification
        $setup['admin']->notify(new TransactionCompletedNotification($setup['transaction'], $setup['receiver']));

        $this->assertEquals(3, $setup['admin']->unreadNotifications()->count());

        $response = $this->actingAs($setup['admin'])
            ->get(route('dashboard'));

        $response->assertStatus(200);
    }
}
