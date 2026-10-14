<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Events\TransactionCompleted;
use App\Exceptions\SSOUnavailableException;
use App\Listeners\NotifyTransactionCompleted;
use App\Models\Office;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SSOIdentityService;
use App\Services\SSOService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class SSONotificationEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_actor_without_sso_identity_cannot_receive_live_data(): void
    {
        $this->mock(SSOService::class)->shouldNotReceive('directoryUser');
        $user = User::factory()->create(['sso_uuid' => null]);
        $this->assertFalse(app(SSOIdentityService::class)->eligibleRecipient($user));
    }

    public function test_notification_checks_current_role_and_office_not_local_assignment(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create(['sso_uuid' => (string) Str::uuid(), 'office_id' => $office->id]);
        $this->mock(SSOService::class)->shouldReceive('directoryUser')->times(3)->with($user->sso_uuid)->andReturn(
            ['uuid' => $user->sso_uuid, 'role' => 'guest', 'office_id' => $office->sso_id],
            ['uuid' => $user->sso_uuid, 'role' => 'standard', 'office_id' => $office->sso_id + 1],
            ['uuid' => $user->sso_uuid, 'role' => 'standard', 'office_id' => $office->sso_id],
        );
        $identities = app(SSOIdentityService::class);
        $this->assertFalse($identities->eligibleRecipient($user, ['administrator', 'super_administrator']));
        $this->assertFalse($identities->eligibleRecipient($user, officeId: $office->id));
        $this->assertTrue($identities->eligibleRecipient($user, ['standard'], $office->id));
    }

    public function test_idle_office_transfer_and_admin_promotion_use_live_directory_across_pages(): void
    {
        $oldOffice = Office::factory()->create();
        $newOffice = Office::factory()->create();
        $transferred = User::factory()->create(['sso_uuid' => (string) Str::uuid(), 'office_id' => $oldOffice->id]);
        $promoted = User::factory()->create(['sso_uuid' => (string) Str::uuid()]);
        $api = $this->mock(SSOService::class);
        $api->shouldReceive('directory')->with(['page' => 1, 'per_page' => 50])->twice()->andReturn([
            'data' => [['uuid' => $transferred->sso_uuid, 'role' => 'standard', 'office_id' => $newOffice->sso_id]],
            'meta' => ['page' => 1, 'has_more' => true],
        ]);
        $api->shouldReceive('directory')->with(['page' => 2, 'per_page' => 50])->twice()->andReturn([
            'data' => [
                ['uuid' => $promoted->sso_uuid, 'role' => 'administrator', 'office_id' => null],
                ['uuid' => (string) Str::uuid(), 'role' => 'administrator', 'office_id' => null],
            ],
            'meta' => ['page' => 2, 'has_more' => false],
        ]);
        $identities = app(SSOIdentityService::class);
        $this->assertSame([$transferred->id], $identities->eligibleRecipients(officeId: $newOffice->id)->modelKeys());
        $this->assertSame([$promoted->id], $identities->eligibleRecipients(['administrator', 'super_administrator'])->modelKeys());
        $this->assertSame($oldOffice->id, $transferred->fresh()->office_id);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_later_directory_page_outage_never_returns_partial_recipients(): void
    {
        $user = User::factory()->create(['sso_uuid' => (string) Str::uuid()]);
        $api = $this->mock(SSOService::class);
        $api->shouldReceive('directory')->with(['page' => 1, 'per_page' => 50])->once()->andReturn([
            'data' => [['uuid' => $user->sso_uuid, 'role' => 'administrator', 'office_id' => null]],
            'meta' => ['page' => 1, 'has_more' => true],
        ]);
        $api->shouldReceive('directory')->with(['page' => 2, 'per_page' => 50])->once()
            ->andThrow(new SSOUnavailableException('Unavailable'));
        $this->assertTrue(app(SSOIdentityService::class)->eligibleRecipients()->isEmpty());
    }

    public function test_revoked_creator_receives_no_completion_notification(): void
    {
        Notification::fake();
        $creator = User::factory()->create(['sso_uuid' => (string) Str::uuid()]);
        $transaction = Transaction::factory()->create(['created_by_user_id' => $creator->id]);
        $this->mock(SSOService::class)->shouldReceive('directoryUser')->once()->with($creator->sso_uuid)->andReturnNull();
        app(NotifyTransactionCompleted::class)->handle(new TransactionCompleted($transaction, $creator));
        Notification::assertNothingSent();
    }

    public function test_directory_outage_withholds_notification_without_failing_completed_business_action(): void
    {
        Notification::fake();
        $creator = User::factory()->create(['sso_uuid' => (string) Str::uuid()]);
        $transaction = Transaction::factory()->create(['created_by_user_id' => $creator->id]);
        $this->mock(SSOService::class)->shouldReceive('directoryUser')->once()->andThrow(new SSOUnavailableException('Unavailable'));
        app(NotifyTransactionCompleted::class)->handle(new TransactionCompleted($transaction, $creator));
        Notification::assertNothingSent();
    }
}
