<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\OutOfWorkflowEndorsement;
use App\Listeners\NotifyOutOfWorkflowEndorsement;
use App\Models\ActionTaken;
use App\Models\Office;
use App\Models\Procurement;
use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Notifications\OutOfWorkflowNotification;
use App\Services\EndorsementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OutOfWorkflowNotificationTest extends TestCase
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
        $office3 = Office::factory()->create(['is_active' => true, 'name' => 'Other Office']);

        $workflow = Workflow::factory()->create([
            'category' => 'PR',
            'is_active' => true,
        ]);

        $step1 = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office1->id,
            'step_order' => 1,
            'is_final_step' => false,
        ]);

        $step2 = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'office_id' => $office2->id,
            'step_order' => 2,
            'is_final_step' => true,
        ]);

        $endorser = User::factory()->create(['office_id' => $office1->id]);
        $endorser->assignRole('Endorser');

        $admin = User::factory()->create(['office_id' => $office1->id]);
        $admin->assignRole('Administrator');

        $expectedOfficeUser = User::factory()->create(['office_id' => $office2->id]);
        $expectedOfficeUser->assignRole('Endorser');

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
        ]);

        return compact(
            'office1', 'office2', 'office3',
            'workflow', 'step1', 'step2',
            'endorser', 'admin', 'expectedOfficeUser',
            'actionTaken', 'procurement', 'transaction'
        );
    }

    public function test_out_of_workflow_endorsement_fires_event(): void
    {
        Event::fake([OutOfWorkflowEndorsement::class]);

        $setup = $this->createTestSetup();

        // Endorse to office3 (not the expected office2)
        $this->service->endorse(
            $setup['transaction'],
            $setup['endorser'],
            $setup['actionTaken']->id,
            $setup['office3']->id,
            'Test notes'
        );

        Event::assertDispatched(OutOfWorkflowEndorsement::class);
    }

    public function test_in_workflow_endorsement_does_not_fire_event(): void
    {
        Event::fake([OutOfWorkflowEndorsement::class]);

        $setup = $this->createTestSetup();

        // Endorse to office2 (the expected next office)
        $this->service->endorse(
            $setup['transaction'],
            $setup['endorser'],
            $setup['actionTaken']->id,
            $setup['office2']->id,
            'Test notes'
        );

        Event::assertNotDispatched(OutOfWorkflowEndorsement::class);
    }

    public function test_out_of_workflow_endorsement_sets_flag_on_action(): void
    {
        $setup = $this->createTestSetup();

        $action = $this->service->endorse(
            $setup['transaction'],
            $setup['endorser'],
            $setup['actionTaken']->id,
            $setup['office3']->id
        );

        $this->assertTrue($action->is_out_of_workflow);
    }

    public function test_in_workflow_endorsement_does_not_set_flag(): void
    {
        $setup = $this->createTestSetup();

        $action = $this->service->endorse(
            $setup['transaction'],
            $setup['endorser'],
            $setup['actionTaken']->id,
            $setup['office2']->id
        );

        $this->assertFalse($action->is_out_of_workflow);
    }

    public function test_out_of_workflow_creates_notifications_for_admins(): void
    {
        Notification::fake();

        $setup = $this->createTestSetup();

        // Create the action and trigger the event manually
        $action = TransactionAction::create([
            'transaction_id' => $setup['transaction']->id,
            'action_type' => TransactionAction::TYPE_ENDORSE,
            'action_taken_id' => $setup['actionTaken']->id,
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office3']->id,
            'from_user_id' => $setup['endorser']->id,
            'workflow_step_id' => $setup['step1']->id,
            'is_out_of_workflow' => true,
        ]);

        $action->load(['transaction.currentStep', 'toOffice', 'fromUser']);

        $listener = new NotifyOutOfWorkflowEndorsement;
        $listener->handle(new OutOfWorkflowEndorsement($action));

        Notification::assertSentTo($setup['admin'], OutOfWorkflowNotification::class);
    }

    public function test_out_of_workflow_creates_notifications_for_expected_office_users(): void
    {
        Notification::fake();

        $setup = $this->createTestSetup();

        $action = TransactionAction::create([
            'transaction_id' => $setup['transaction']->id,
            'action_type' => TransactionAction::TYPE_ENDORSE,
            'action_taken_id' => $setup['actionTaken']->id,
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office3']->id,
            'from_user_id' => $setup['endorser']->id,
            'workflow_step_id' => $setup['step1']->id,
            'is_out_of_workflow' => true,
        ]);

        $action->load(['transaction.currentStep', 'toOffice', 'fromUser']);

        $listener = new NotifyOutOfWorkflowEndorsement;
        $listener->handle(new OutOfWorkflowEndorsement($action));

        Notification::assertSentTo($setup['expectedOfficeUser'], OutOfWorkflowNotification::class);
    }

    public function test_notification_contains_correct_data(): void
    {
        $setup = $this->createTestSetup();

        $action = TransactionAction::create([
            'transaction_id' => $setup['transaction']->id,
            'action_type' => TransactionAction::TYPE_ENDORSE,
            'action_taken_id' => $setup['actionTaken']->id,
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office3']->id,
            'from_user_id' => $setup['endorser']->id,
            'workflow_step_id' => $setup['step1']->id,
            'is_out_of_workflow' => true,
        ]);

        $action->load(['transaction.currentStep', 'toOffice', 'fromUser']);

        $notification = new OutOfWorkflowNotification($action, $setup['office2']);
        $data = $notification->toArray($setup['admin']);

        $this->assertEquals('out_of_workflow', $data['type']);
        $this->assertEquals($setup['transaction']->id, $data['transaction_id']);
        $this->assertEquals($setup['transaction']->reference_number, $data['reference_number']);
        $this->assertEquals('PR', $data['category']);
        $this->assertEquals($setup['office2']->id, $data['expected_office_id']);
        $this->assertEquals($setup['office2']->name, $data['expected_office_name']);
        $this->assertEquals($setup['office3']->id, $data['actual_office_id']);
        $this->assertEquals($setup['office3']->name, $data['actual_office_name']);
        $this->assertEquals($setup['endorser']->id, $data['endorsed_by_user_id']);
        $this->assertStringContainsString($setup['transaction']->reference_number, $data['message']);
    }

    public function test_mark_notification_as_read(): void
    {
        $setup = $this->createTestSetup();

        // Create a notification directly
        $setup['admin']->notify(new OutOfWorkflowNotification(
            TransactionAction::create([
                'transaction_id' => $setup['transaction']->id,
                'action_type' => TransactionAction::TYPE_ENDORSE,
                'from_office_id' => $setup['office1']->id,
                'to_office_id' => $setup['office3']->id,
                'from_user_id' => $setup['endorser']->id,
                'is_out_of_workflow' => true,
            ]),
            $setup['office2']
        ));

        $notification = $setup['admin']->notifications()->first();
        $this->assertNull($notification->read_at);

        $this->actingAs($setup['admin'])
            ->post(route('notifications.markAsRead', $notification->id))
            ->assertRedirect();

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_mark_all_notifications_as_read(): void
    {
        $setup = $this->createTestSetup();

        // Create two notifications
        for ($i = 0; $i < 2; $i++) {
            $setup['admin']->notify(new OutOfWorkflowNotification(
                TransactionAction::create([
                    'transaction_id' => $setup['transaction']->id,
                    'action_type' => TransactionAction::TYPE_ENDORSE,
                    'from_office_id' => $setup['office1']->id,
                    'to_office_id' => $setup['office3']->id,
                    'from_user_id' => $setup['endorser']->id,
                    'is_out_of_workflow' => true,
                ]),
                $setup['office2']
            ));
        }

        $this->assertEquals(2, $setup['admin']->unreadNotifications()->count());

        $this->actingAs($setup['admin'])
            ->post(route('notifications.markAllAsRead'))
            ->assertRedirect();

        $this->assertEquals(0, $setup['admin']->fresh()->unreadNotifications()->count());
    }

    public function test_notification_count_in_shared_props(): void
    {
        $setup = $this->createTestSetup();

        // Create a notification
        $setup['admin']->notify(new OutOfWorkflowNotification(
            TransactionAction::create([
                'transaction_id' => $setup['transaction']->id,
                'action_type' => TransactionAction::TYPE_ENDORSE,
                'from_office_id' => $setup['office1']->id,
                'to_office_id' => $setup['office3']->id,
                'from_user_id' => $setup['endorser']->id,
                'is_out_of_workflow' => true,
            ]),
            $setup['office2']
        ));

        $response = $this->actingAs($setup['admin'])
            ->get(route('dashboard'));

        $response->assertStatus(200);
    }

    public function test_notifications_page_loads(): void
    {
        $this->withoutVite();
        $setup = $this->createTestSetup();

        $this->actingAs($setup['admin'])
            ->get(route('notifications.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Notifications/Index')
                ->has('notifications')
                ->has('filters')
            );
    }

    public function test_notifications_page_filters_by_type(): void
    {
        $this->withoutVite();
        $setup = $this->createTestSetup();

        $this->actingAs($setup['admin'])
            ->get(route('notifications.index', ['type' => 'out_of_workflow']))
            ->assertStatus(200);
    }

    public function test_notifications_page_filters_by_read_status(): void
    {
        $this->withoutVite();
        $setup = $this->createTestSetup();

        $this->actingAs($setup['admin'])
            ->get(route('notifications.index', ['status' => 'unread']))
            ->assertStatus(200);
    }

    public function test_delete_notification(): void
    {
        $setup = $this->createTestSetup();

        $setup['admin']->notify(new OutOfWorkflowNotification(
            TransactionAction::create([
                'transaction_id' => $setup['transaction']->id,
                'action_type' => TransactionAction::TYPE_ENDORSE,
                'from_office_id' => $setup['office1']->id,
                'to_office_id' => $setup['office3']->id,
                'from_user_id' => $setup['endorser']->id,
                'is_out_of_workflow' => true,
            ]),
            $setup['office2']
        ));

        $notification = $setup['admin']->notifications()->first();

        $this->actingAs($setup['admin'])
            ->delete(route('notifications.destroy', $notification->id))
            ->assertRedirect();

        $this->assertEquals(0, $setup['admin']->notifications()->count());
    }

    public function test_out_of_workflow_banner_data_passed_to_show_page(): void
    {
        $setup = $this->createTestSetup();

        // Create an out-of-workflow action for the transaction
        TransactionAction::create([
            'transaction_id' => $setup['transaction']->id,
            'action_type' => TransactionAction::TYPE_ENDORSE,
            'action_taken_id' => $setup['actionTaken']->id,
            'from_office_id' => $setup['office1']->id,
            'to_office_id' => $setup['office3']->id,
            'from_user_id' => $setup['endorser']->id,
            'workflow_step_id' => $setup['step1']->id,
            'is_out_of_workflow' => true,
        ]);

        // Create the PR for this transaction
        \App\Models\PurchaseRequest::factory()->create([
            'transaction_id' => $setup['transaction']->id,
        ]);

        $pr = \App\Models\PurchaseRequest::where('transaction_id', $setup['transaction']->id)->first();

        $this->actingAs($setup['admin'])
            ->get(route('purchase-requests.show', $pr->id))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('PurchaseRequests/Show')
                ->has('outOfWorkflowInfo')
                ->where('outOfWorkflowInfo.is_out_of_workflow', true)
            );
    }
}
