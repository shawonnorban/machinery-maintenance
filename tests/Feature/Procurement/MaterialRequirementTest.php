<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Actions\ReserveItemStock;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Services\ItemLedger;
use App\Modules\Procurement\Models\PurchaseRequisition;
use App\Modules\Production\Models\CuttingProgram;
use App\Modules\Production\Models\Order;
use App\Modules\Production\Models\Style;
use App\Modules\Production\Models\StyleBomLine;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\ItemFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The material requirement and the draft requisition it raises
 * (Procurement spec §4, §5.3; Phase 3B).
 *
 *     Net = BOM required − free stock − booked − on order
 *
 * The two rules worth a test of their own are the ones a factory notices
 * when they are wrong: stock held for somebody else is not free, and the
 * automatic requisition arrives as a draft rather than in front of an
 * approver.
 */
class MaterialRequirementTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $storeManager;

    private Item $fabric;

    private Item $button;

    private Style $style;

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
        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');
        $this->ledger = app(ItemLedger::class);

        $this->fabric = ItemFixture::item($this->delta, 'FAB-SJ-180', 'Single jersey 180', [
            'item_type' => 'FABRIC', 'uom_id' => ItemFixture::uomId($this->delta, 'MTR'),
        ]);
        $this->button = ItemFixture::item($this->delta, 'TRM-BTN-18L', 'Button 18L');

        $order = Order::create([
            'company_id' => $this->delta->id, 'factory_id' => $this->dhaka->id,
            'order_no' => 'HM-4471-23', 'buyer' => 'H&M', 'order_qty' => '1000',
            'order_date' => '2026-09-01', 'status' => 'CONFIRMED',
        ]);

        $this->style = Style::create([
            'company_id' => $this->delta->id, 'production_order_id' => $order->id,
            'style_no' => 'ST-100', 'quantity' => '1000',
        ]);

        // 1.85 m/pc + 8% = 1998 m for 1000 pieces; 500 m for a 250-piece cut
        // (1.85 x 250 = 462.5, + 8% = 499.5).
        StyleBomLine::create([
            'company_id' => $this->delta->id, 'style_id' => $this->style->id,
            'item_id' => $this->fabric->id, 'item_code' => $this->fabric->item_code,
            'item_name' => $this->fabric->name, 'uom_id' => $this->fabric->uom_id,
            'consumption_per_unit' => '1.85', 'wastage_percent' => '8',
        ]);

        StyleBomLine::create([
            'company_id' => $this->delta->id, 'style_id' => $this->style->id,
            'item_id' => $this->button->id, 'item_code' => $this->button->item_code,
            'item_name' => $this->button->name, 'uom_id' => $this->button->uom_id,
            'consumption_per_unit' => '6', 'wastage_percent' => '0',
        ]);

        $this->program = CuttingProgram::create([
            'company_id' => $this->delta->id, 'factory_id' => $this->dhaka->id,
            'style_id' => $this->style->id, 'program_no' => 'CP-DHK-2026-0001',
            'program_qty' => '250', 'cutting_date' => '2026-10-10', 'status' => 'CONFIRMED',
        ]);
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
    private function requirement(string $token): array
    {
        return $this->withToken($token)
            ->getJson("/api/v1/cutting-programs/{$this->program->id}/material-requirement")
            ->assertOk()->json('data');
    }

    private function lineFor(array $requirement, Item $item): array
    {
        foreach ($requirement['lines'] as $line) {
            if ($line['item_id'] === $item->id) {
                return $line;
            }
        }

        $this->fail("No requirement line for {$item->item_code}");
    }

    public function test_the_requirement_comes_from_the_bill_of_materials_and_includes_wastage(): void
    {
        $token = $this->tokenFor($this->storeManager);

        $requirement = $this->requirement($token);
        $fabric = $this->lineFor($requirement, $this->fabric);

        // 1.85 x 250 = 462.5, plus 8% = 499.5.
        $this->assertSame('499.5000', $fabric['required_qty']);
        $this->assertSame('0.0000', $fabric['stock_available']);
        $this->assertSame('499.5000', $fabric['net_requirement']);

        $this->assertSame('1500.0000', $this->lineFor($requirement, $this->button)['required_qty']);
        $this->assertTrue($requirement['has_shortfall']);
    }

    public function test_stock_held_for_somebody_else_is_not_free_stock(): void
    {
        $token = $this->tokenFor($this->storeManager);

        $this->ledger->post($this->fabric, $this->bin, 'RECEIPT', '400', '250');

        $this->assertSame('99.5000', $this->lineFor($this->requirement($token), $this->fabric)['net_requirement']);

        // Somebody holds 300 of the 400: only 100 is genuinely available,
        // so the shortfall goes back up.
        app(ReserveItemStock::class)->reserve($this->fabric, $this->bin, '300', [
            'reserved_by' => $this->storeManager->id,
        ]);

        $line = $this->lineFor($this->requirement($token), $this->fabric);

        $this->assertSame('100.0000', $line['stock_available']);
        $this->assertSame('399.5000', $line['net_requirement']);
    }

    public function test_a_confirmed_booking_counts_against_what_still_has_to_be_bought(): void
    {
        $token = $this->tokenFor($this->storeManager);

        $mill = Vendor::create([
            'company_id' => $this->delta->id, 'name' => 'Dhaka Fabrics Ltd', 'code' => 'DF2',
            'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE', 'default_currency' => 'BDT',
        ]);

        $booking = $this->withToken($token)->postJson('/api/v1/fabric-bookings', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $mill->id,
            'style_id' => $this->style->id,
            'lines' => [['item_id' => $this->fabric->id, 'booked_qty' => '300']],
        ])->assertCreated()->json('data');

        // A draft booking is not a commitment and does not count.
        $this->assertSame('0.0000', $this->lineFor($this->requirement($token), $this->fabric)['already_booked']);

        $this->withToken($token)->postJson("/api/v1/fabric-bookings/{$booking['id']}/confirm")->assertOk();

        $line = $this->lineFor($this->requirement($token), $this->fabric);

        $this->assertSame('300.0000', $line['already_booked']);
        $this->assertSame('199.5000', $line['net_requirement']);
    }

    public function test_the_automatic_requisition_is_a_draft_and_is_raised_only_once(): void
    {
        $token = $this->tokenFor($this->storeManager);

        $this->ledger->post($this->button, $this->bin, 'RECEIPT', '1500', '2');

        $raised = $this->withToken($token)
            ->postJson("/api/v1/cutting-programs/{$this->program->id}/material-requirement/requisition")
            ->assertCreated()->json('data');

        $requisition = PurchaseRequisition::findOrFail($raised['id']);

        // Not sent to an approver: the requester reviews it first (§5.3).
        $this->assertSame('DRAFT', $requisition->status);
        $this->assertSame('AUTO', $requisition->pr_type);
        $this->assertSame($this->program->id, $requisition->source_reference);

        // Only the fabric is short — the buttons are covered by stock.
        $this->assertSame(1, $raised['line_count']);
        $line = $requisition->lines()->firstOrFail();
        $this->assertSame($this->fabric->id, $line->item_id);
        $this->assertSame('499.5000', (string) $line->requested_qty);
        $this->assertSame('2026-10-10', $line->required_date?->toDateString());

        // A second attempt is refused rather than duplicating the order.
        $this->withToken($token)
            ->postJson("/api/v1/cutting-programs/{$this->program->id}/material-requirement/requisition")
            ->assertStatus(409);
    }

    public function test_nothing_is_raised_when_the_programme_is_already_covered(): void
    {
        $token = $this->tokenFor($this->storeManager);

        $this->ledger->post($this->fabric, $this->bin, 'RECEIPT', '600', '250');
        $this->ledger->post($this->button, $this->bin, 'RECEIPT', '2000', '2');

        $requirement = $this->requirement($token);

        $this->assertFalse($requirement['has_shortfall']);
        $this->assertSame('0.0000', $this->lineFor($requirement, $this->fabric)['net_requirement']);

        $this->withToken($token)
            ->postJson("/api/v1/cutting-programs/{$this->program->id}/material-requirement/requisition")
            ->assertStatus(409);

        $this->assertSame(0, PurchaseRequisition::count());
    }
}
