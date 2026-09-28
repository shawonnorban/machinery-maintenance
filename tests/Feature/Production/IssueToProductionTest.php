<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
use App\Modules\ItemMaster\Services\ItemLedger;
use App\Modules\Production\Models\CuttingProgram;
use App\Modules\Production\Models\MaterialIssue;
use App\Modules\Production\Models\Order;
use App\Modules\Production\Models\Style;
use App\Modules\Production\Models\StyleBomLine;
use App\Modules\Settings\Actions\SetSetting;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\ItemFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Issuing material to a cut and the BOM variance it produces (Inventory
 * spec §9; Phase 3B).
 *
 * The rule the tolerance exists for: an issue past it does not move stock.
 * A store that hands the extra fabric over and asks afterwards has a
 * report, not a control — so the pending issue is tested by what is still
 * on the shelf, not only by its status.
 */
class IssueToProductionTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $storekeeper;

    private User $manager;

    private Item $fabric;

    private CuttingProgram $program;

    private ItemLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');
        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $this->ledger = app(ItemLedger::class);

        $this->fabric = ItemFixture::item($this->delta, 'FAB-SJ-180', 'Single jersey 180', [
            'item_type' => 'FABRIC', 'uom_id' => ItemFixture::uomId($this->delta, 'MTR'),
        ]);

        $order = Order::create([
            'company_id' => $this->delta->id, 'factory_id' => $this->dhaka->id,
            'order_no' => 'HM-4471-23', 'buyer' => 'H&M', 'order_qty' => '1000',
            'order_date' => '2026-09-01', 'status' => 'CONFIRMED',
        ]);

        $style = Style::create([
            'company_id' => $this->delta->id, 'production_order_id' => $order->id,
            'style_no' => 'ST-100', 'quantity' => '1000',
        ]);

        // 2 m/pc, no wastage: a 100-piece programme needs exactly 200 m,
        // which keeps the variance arithmetic readable.
        StyleBomLine::create([
            'company_id' => $this->delta->id, 'style_id' => $style->id,
            'item_id' => $this->fabric->id, 'item_code' => $this->fabric->item_code,
            'item_name' => $this->fabric->name, 'uom_id' => $this->fabric->uom_id,
            'consumption_per_unit' => '2', 'wastage_percent' => '0',
        ]);

        $this->program = CuttingProgram::create([
            'company_id' => $this->delta->id, 'factory_id' => $this->dhaka->id,
            'style_id' => $style->id, 'program_no' => 'CP-DHK-2026-0001',
            'program_qty' => '100', 'cutting_date' => '2026-10-10', 'status' => 'CONFIRMED',
        ]);

        $this->ledger->post($this->fabric, $this->bin, 'RECEIPT', '500', '250');
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(string $token, string $quantity, ?string $reason = null): array
    {
        return $this->withToken($token)->postJson("/api/v1/cutting-programs/{$this->program->id}/material-issues", [
            'item_id' => $this->fabric->id,
            'quantity' => $quantity,
            'bin_id' => $this->bin->id,
            'reason' => $reason,
        ])->assertCreated()->json('data');
    }

    /** A one-step chain for PRODUCTION_ISSUE, so the engine actually holds it. */
    private function varianceWorkflow(): void
    {
        $workflow = ApprovalWorkflow::create([
            'company_id' => $this->delta->id,
            'name' => 'BOM variance',
            'entity_type' => 'PRODUCTION_ISSUE',
            'active' => true,
        ]);

        ApprovalRule::create([
            'company_id' => $this->delta->id,
            'workflow_id' => $workflow->id,
            'name' => 'Factory manager',
            'sequence' => 1,
            'role_id' => Role::whereNull('company_id')->where('name', 'FACTORY_MANAGER')->firstOrFail()->id,
            'condition_json' => ['min_cost' => '0'],
        ]);
    }

    private function onHand(): string
    {
        return (string) ItemStockBalance::where('item_id', $this->fabric->id)
            ->where('bin_id', $this->bin->id)
            ->firstOrFail()->quantity_on_hand;
    }

    public function test_an_issue_within_tolerance_posts_immediately_and_starts_the_programme(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        $issue = $this->issue($token, '200');

        $this->assertSame('POSTED', $issue['status']);
        $this->assertTrue($issue['stock_moved']);
        $this->assertSame('200.0000', $issue['required_qty']);
        $this->assertSame('0.0000', $issue['variance_qty']);
        $this->assertSame('0.00', $issue['variance_percent']);
        $this->assertFalse($issue['is_extra']);

        $this->assertSame('300.0000', $this->onHand());
        $this->assertSame(1, ItemStockTransaction::where('transaction_type', 'ISSUE')->count());
        $this->assertSame('cutting_program', ItemStockTransaction::where('transaction_type', 'ISSUE')->first()->reference_type);

        // Issuing starts the cut.
        $this->assertSame('IN_PROGRESS', $this->program->fresh()->status);
    }

    public function test_the_variance_accumulates_across_issues(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        $this->issue($token, '150');
        $second = $this->issue($token, '55');

        // 205 issued against 200 required = +5, which is 2.5% and inside
        // the default 5% tolerance.
        $this->assertSame('POSTED', $second['status']);
        $this->assertSame('205.0000', $second['issued_to_date']);
        $this->assertSame('5.0000', $second['variance_qty']);
        $this->assertSame('2.50', $second['variance_percent']);
        $this->assertTrue($second['is_extra']);

        $this->assertSame('295.0000', $this->onHand());

        $variance = $second['variance'][0];
        $this->assertSame('205.0000', $variance['issued_qty']);
        $this->assertSame('2.50', $variance['variance_percent']);
    }

    public function test_an_issue_past_the_tolerance_moves_no_stock_until_it_is_approved(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        // A workflow so the engine actually holds the issue rather than
        // deciding NOT_REQUIRED and letting it through.
        $this->varianceWorkflow();

        $issue = $this->issue($token, '230', 'Shade change mid-cut');

        // 230 against 200 = +15%, past the 5% default.
        $this->assertSame('PENDING_APPROVAL', $issue['status']);
        $this->assertSame('15.00', $issue['variance_percent']);
        $this->assertFalse($issue['stock_moved']);
        $this->assertSame('Shade change mid-cut', $issue['reason']);

        // Nothing has left the store.
        $this->assertSame('500.0000', $this->onHand());
        $this->assertSame(0, ItemStockTransaction::where('transaction_type', 'ISSUE')->count());

        $request = ApprovalRequest::where('entity_type', 'PRODUCTION_ISSUE')->firstOrFail();
        $this->assertSame($issue['id'], $request->entity_id);

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/approval-requests/{$request->id}/approve", ['comment' => 'Agreed'])
            ->assertOk();

        // Now — and only now — the fabric moves.
        $this->assertSame('POSTED', MaterialIssue::findOrFail($issue['id'])->status);
        $this->assertSame('270.0000', $this->onHand());
        $this->assertSame(1, ItemStockTransaction::where('transaction_type', 'ISSUE')->count());
    }

    public function test_a_refused_variance_leaves_the_stock_where_it_was(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        $this->varianceWorkflow();

        $issue = $this->issue($token, '400');
        $request = ApprovalRequest::where('entity_type', 'PRODUCTION_ISSUE')->firstOrFail();

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/approval-requests/{$request->id}/reject", ['comment' => 'Re-cut the marker instead'])
            ->assertOk();

        $this->assertSame('REJECTED', MaterialIssue::findOrFail($issue['id'])->status);
        $this->assertSame('500.0000', $this->onHand());
        $this->assertSame(0, ItemStockTransaction::where('transaction_type', 'ISSUE')->count());

        // A refused issue does not count towards the variance either.
        $variance = $this->withToken($token)
            ->getJson("/api/v1/cutting-programs/{$this->program->id}/material-issues")
            ->assertOk()->json('data.variance.0');

        $this->assertSame('0.0000', $variance['issued_qty']);
    }

    public function test_the_tolerance_is_a_factory_setting(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        app(SetSetting::class)->handle('production.bom_variance_tolerance_percent', 20, factoryId: $this->dhaka->id);

        // 230 is 15% over — inside a 20% tolerance, so it posts.
        $issue = $this->issue($token, '230');

        $this->assertSame('POSTED', $issue['status']);
        $this->assertTrue($issue['stock_moved']);
        $this->assertSame('270.0000', $this->onHand());
    }

    public function test_an_item_outside_the_bill_of_materials_cannot_be_issued(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $button = ItemFixture::item($this->delta, 'TRM-BTN-18L', 'Button 18L');

        $this->withToken($token)->postJson("/api/v1/cutting-programs/{$this->program->id}/material-issues", [
            'item_id' => $button->id,
            'quantity' => '10',
            'bin_id' => $this->bin->id,
        ])->assertStatus(409);

        $this->assertSame(0, MaterialIssue::count());
    }
}
