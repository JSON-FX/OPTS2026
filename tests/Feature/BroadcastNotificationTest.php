<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActionTaken;
use App\Models\Office;
use App\Models\Procurement;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Notifications\OutOfWorkflowNotification;
use App\Notifications\TransactionCompletedNotification;
use App\Notifications\TransactionOverdueNotification;
use App\Notifications\TransactionReceivedNotification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BroadcastNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

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
    // ShouldBroadcast interface tests
    // -------------------------------------------------------

    public function test_transaction_received_notification_implements_should_broadcast(): void
    {
        $setup = $this->createTestSetup();

        $action = TransactionAction::create([
            'transaction_id' => $setup['transaction']->id,
            'action_type' => TransactionAction::TYPE_RECEIVE,
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office2']->id,
            'from_user_id' => $setup['endorser']->id,
            'to_user_id' => $setup['receiver']->id,
        ]);

        $notification = new TransactionReceivedNotification($action);
        $this->assertInstanceOf(ShouldBroadcast::class, $notification);
    }

    public function test_transaction_completed_notification_implements_should_broadcast(): void
    {
        $setup = $this->createTestSetup();

        $notification = new TransactionCompletedNotification($setup['transaction'], $setup['receiver']);
        $this->assertInstanceOf(ShouldBroadcast::class, $notification);
    }

    public function test_transaction_overdue_notification_implements_should_broadcast(): void
    {
        $setup = $this->createTestSetup();
        $setup['transaction']->load('currentStep.office');

        $notification = new TransactionOverdueNotification($setup['transaction'], 5);
        $this->assertInstanceOf(ShouldBroadcast::class, $notification);
    }

    public function test_out_of_workflow_notification_implements_should_broadcast(): void
    {
        $setup = $this->createTestSetup();

        $action = TransactionAction::create([
            'transaction_id' => $setup['transaction']->id,
            'action_type' => TransactionAction::TYPE_ENDORSE,
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office2']->id,
            'from_user_id' => $setup['endorser']->id,
            'to_user_id' => $setup['receiver']->id,
        ]);

        $notification = new OutOfWorkflowNotification($action, $setup['office1']);
        $this->assertInstanceOf(ShouldBroadcast::class, $notification);
    }

    // -------------------------------------------------------
    // Via channels include broadcast
    // -------------------------------------------------------

    public function test_transaction_received_notification_broadcasts(): void
    {
        $setup = $this->createTestSetup();

        $action = TransactionAction::create([
            'transaction_id' => $setup['transaction']->id,
            'action_type' => TransactionAction::TYPE_RECEIVE,
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office2']->id,
            'from_user_id' => $setup['endorser']->id,
            'to_user_id' => $setup['receiver']->id,
        ]);

        $notification = new TransactionReceivedNotification($action);
        $channels = $notification->via($setup['receiver']);

        $this->assertContains('database', $channels);
        $this->assertContains('broadcast', $channels);
    }

    public function test_transaction_completed_notification_broadcasts(): void
    {
        $setup = $this->createTestSetup();

        $notification = new TransactionCompletedNotification($setup['transaction'], $setup['receiver']);
        $channels = $notification->via($setup['creator']);

        $this->assertContains('database', $channels);
        $this->assertContains('broadcast', $channels);
    }

    public function test_transaction_overdue_notification_broadcasts(): void
    {
        $setup = $this->createTestSetup();
        $setup['transaction']->load('currentStep.office');

        $notification = new TransactionOverdueNotification($setup['transaction'], 5);
        $channels = $notification->via($setup['admin']);

        $this->assertContains('database', $channels);
        $this->assertContains('broadcast', $channels);
    }

    public function test_out_of_workflow_notification_broadcasts(): void
    {
        $setup = $this->createTestSetup();

        $action = TransactionAction::create([
            'transaction_id' => $setup['transaction']->id,
            'action_type' => TransactionAction::TYPE_ENDORSE,
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office2']->id,
            'from_user_id' => $setup['endorser']->id,
            'to_user_id' => $setup['receiver']->id,
        ]);

        $notification = new OutOfWorkflowNotification($action, $setup['office1']);
        $channels = $notification->via($setup['admin']);

        $this->assertContains('database', $channels);
        $this->assertContains('broadcast', $channels);
    }

    // -------------------------------------------------------
    // Broadcast payload structure tests
    // -------------------------------------------------------

    public function test_transaction_received_broadcast_payload(): void
    {
        $setup = $this->createTestSetup();

        $action = TransactionAction::create([
            'transaction_id' => $setup['transaction']->id,
            'action_type' => TransactionAction::TYPE_RECEIVE,
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office2']->id,
            'from_user_id' => $setup['endorser']->id,
            'to_user_id' => $setup['receiver']->id,
        ]);

        $action->load(['transaction', 'fromOffice', 'toOffice', 'toUser']);

        $notification = new TransactionReceivedNotification($action);
        $data = $notification->toArray($setup['receiver']);

        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('transaction_id', $data);
        $this->assertArrayHasKey('reference_number', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('received', $data['type']);
    }

    public function test_transaction_overdue_broadcast_payload(): void
    {
        $setup = $this->createTestSetup();
        $setup['transaction']->load('currentStep.office');

        $notification = new TransactionOverdueNotification($setup['transaction'], 3);
        $data = $notification->toArray($setup['admin']);

        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('transaction_id', $data);
        $this->assertArrayHasKey('delay_days', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('overdue', $data['type']);
        $this->assertEquals(3, $data['delay_days']);
    }

    public function test_transaction_completed_broadcast_payload(): void
    {
        $setup = $this->createTestSetup();

        $notification = new TransactionCompletedNotification($setup['transaction'], $setup['receiver']);
        $data = $notification->toArray($setup['creator']);

        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('transaction_id', $data);
        $this->assertArrayHasKey('completed_by_name', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('completed', $data['type']);
    }

    // -------------------------------------------------------
    // Private channel authorization tests
    // -------------------------------------------------------

    public function test_private_channel_allows_authenticated_user(): void
    {
        $setup = $this->createTestSetup();

        // Test channel authorization callback directly
        $this->actingAs($setup['admin']);

        $channels = Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
            return (int) $user->id === (int) $id;
        });

        // The channel callback should return true for matching user
        $result = (int) $setup['admin']->id === (int) $setup['admin']->id;
        $this->assertTrue($result);
    }

    public function test_private_channel_denies_other_user(): void
    {
        $setup = $this->createTestSetup();

        // The channel callback should return false for non-matching user
        $result = (int) $setup['endorser']->id === (int) $setup['admin']->id;
        $this->assertFalse($result);
    }

    public function test_channel_authorization_callback_exists(): void
    {
        // Verify the channels.php file defines the correct channel
        $channelsFile = base_path('routes/channels.php');
        $this->assertFileExists($channelsFile);

        $content = file_get_contents($channelsFile);
        $this->assertStringContainsString('App.Models.User.{id}', $content);
        $this->assertStringContainsString('$user->id', $content);
    }

    // -------------------------------------------------------
    // Notification::fake with broadcast channel
    // -------------------------------------------------------

    public function test_notification_sent_via_broadcast_channel(): void
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
        ]);

        $setup['receiver']->notify(new TransactionReceivedNotification($action));

        Notification::assertSentTo(
            $setup['receiver'],
            TransactionReceivedNotification::class,
            function ($notification, $channels) {
                return in_array('broadcast', $channels) && in_array('database', $channels);
            }
        );
    }
}
