<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Procurement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentWorkspacePresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public static function documents(): array
    {
        return [
            ['PR', 'purchase-requests', 'purchaseRequest'],
            ['PO', 'purchase-orders', 'purchaseOrder'],
            ['VCH', 'vouchers', 'voucher'],
        ];
    }

    #[DataProvider('documents')]
    public function test_detail_and_edit_pages_keep_real_office_and_linked_document_context(string $category, string $resource, string $prop): void
    {
        $office = Office::factory()->create(['abbreviation' => 'MBO', 'name' => 'Municipal Budget Office']);
        $user = User::factory()->create(['office_id' => $office->id]);
        $user->assignRole('Endorser');
        $procurement = Procurement::factory()->create(['end_user_id' => $office->id, 'abc_amount' => '1250.75']);
        $workflow = Workflow::factory()->create(['category' => $category]);
        $step = WorkflowStep::factory()->create(['workflow_id' => $workflow->id, 'office_id' => $office->id]);
        $models = [];
        foreach (['PR' => PurchaseRequest::class, 'PO' => PurchaseOrder::class, 'VCH' => Voucher::class] as $kind => $model) {
            $transaction = Transaction::factory()->create([
                'procurement_id' => $procurement->id,
                'category' => $kind,
                'reference_number' => 'DEMO-'.$kind.'-REFERENCE',
                'current_office_id' => $office->id,
                'created_by_user_id' => $user->id,
                'workflow_id' => $kind === $category ? $workflow->id : null,
                'current_step_id' => $kind === $category ? $step->id : null,
            ]);
            $models[$kind] = $model::factory()->create(['transaction_id' => $transaction->id]);
        }

        foreach (['show', 'edit'] as $view) {
            $this->actingAs($user)->get(route($resource.'.'.$view, $models[$category]))->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where($prop.'.transaction.current_office.abbreviation', 'MBO')
                    ->where($prop.'.transaction.created_by.id', $user->id)
                    ->where($prop.'.transaction.procurement.end_user.name', 'Municipal Budget Office')
                    ->where($prop.'.transaction.procurement.end_user.abbreviation', 'MBO')
                    ->where($prop.'.transaction.procurement.abc_amount', '1250.75')
                    ->where($prop.'.transaction.procurement.purchase_request.transaction.reference_number', 'DEMO-PR-REFERENCE')
                    ->where($prop.'.transaction.procurement.purchase_order.transaction.reference_number', 'DEMO-PO-REFERENCE')
                    ->where($prop.'.transaction.procurement.voucher.transaction.reference_number', 'DEMO-VCH-REFERENCE'));
        }

        $this->actingAs($user)->get(route($resource.'.edit', $models[$category]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where($prop.'.transaction.workflow.steps.0.id', $step->id)
                ->where($prop.'.transaction.workflow.steps.0.office.abbreviation', 'MBO'));

        $viewer = User::factory()->create(['office_id' => $office->id]);
        $viewer->assignRole('Viewer');
        $this->actingAs($viewer)->get(route($resource.'.show', $models[$category]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canEdit', false)->where('canReceive', false)->where('canEndorse', false));
        $this->get(route($resource.'.edit', $models[$category]))->assertForbidden();
    }
}
