<?php

declare(strict_types=1);

namespace Tests\Feature\ItemMaster;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Actions\RecalculateRollCosts;
use App\Modules\ItemMaster\Actions\ReserveItemStock;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemFabricAttributes;
use App\Modules\ItemMaster\Models\ItemReservation;
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
 * Cutting rolls up and joining them back together (Inventory spec §5, §6),
 * and the landed-cost correction that has to follow them afterwards (§10).
 *
 * Three rules a factory notices when they are missing: the pieces add up to
 * the roll, a merge costs the weighted average rather than the mean, and a
 * price corrected weeks later reaches the children the roll was cut into.
 */
class RollSplitMergeTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $storekeeper;

    private Item $fabric;

    private Vendor $mill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');

        $this->mill = Vendor::create([
            'company_id' => $this->delta->id, 'name' => 'Dhaka Fabrics Ltd', 'code' => 'DF2',
            'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE', 'default_currency' => 'BDT',
        ]);

        $this->fabric = ItemFixture::item($this->delta, 'FAB-SJ-180', 'Single jersey 180', [
            'item_type' => 'FABRIC', 'tracking_mode' => 'ROLL', 'uom_id' => ItemFixture::uomId($this->delta, 'MTR'),
        ]);
        ItemFabricAttributes::create([
            'company_id' => $this->delta->id, 'item_id' => $this->fabric->id,
            'fabric_type' => 'KNIT', 'gsm' => '180', 'width' => '72', 'composition' => '100% cotton',
        ]);
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    /**
     * One inspected, passed roll — created the way a real one is, since
     * `fabric_rolls` cannot exist without a goods receipt behind it.
     */
    private function roll(string $rollNo, string $netLength, string $rate = '250'): FabricRoll
    {
        $token = $this->tokenFor($this->storekeeper);

        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'vendor_id' => $this->mill->id,
            'lines' => [['item_id' => $this->fabric->id, 'received_qty' => $netLength, 'rate' => $rate]],
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete", [
            'rolls' => [$grn['lines'][0]['id'] => [[
                'roll_no' => $rollNo, 'bin_id' => $this->bin->id,
                'net_length' => $netLength, 'gross_length' => $netLength,
                'shade' => 'NAVY-01', 'lot_no' => 'L-1',
            ]]],
        ])->assertOk();

        $roll = FabricRoll::where('roll_no', $rollNo)->firstOrFail();

        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$roll->id}/inspect", [
            'grade' => 'A', 'shade_match' => true,
        ])->assertOk();

        return $roll->fresh();
    }

    public function test_the_pieces_must_add_up_to_the_roll(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $roll = $this->roll('R-100', '100');

        // 60 + 30 is not 100.
        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$roll->id}/split", [
            'children' => [
                ['roll_no' => 'R-100-A', 'net_length' => '60'],
                ['roll_no' => 'R-100-B', 'net_length' => '30'],
            ],
        ])->assertStatus(409);

        $this->assertSame('AVAILABLE', $roll->fresh()->status);
        $this->assertSame(1, FabricRoll::count());

        // A split into one piece is a rename, not a split.
        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$roll->id}/split", [
            'children' => [['roll_no' => 'R-100-A', 'net_length' => '100']],
        ])->assertStatus(422);
    }

    public function test_a_split_closes_the_parent_and_carries_the_cost_per_metre(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $roll = $this->roll('R-100', '100', '250');

        $result = $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$roll->id}/split", [
            'children' => [
                ['roll_no' => 'R-100-A', 'net_length' => '60'],
                ['roll_no' => 'R-100-B', 'net_length' => '40'],
            ],
        ])->assertCreated()->json('data');

        // The parent stops being a roll; its length now lives in the pieces.
        $this->assertSame('SPLIT', $roll->fresh()->status);
        $this->assertCount(2, $result['children']);

        $a = FabricRoll::where('roll_no', 'R-100-A')->firstOrFail();
        $b = FabricRoll::where('roll_no', 'R-100-B')->firstOrFail();

        $this->assertSame('60.00', (string) $a->net_length);
        $this->assertSame('40.00', (string) $b->net_length);
        $this->assertSame($roll->id, $a->parent_roll_id);
        $this->assertSame('AVAILABLE', $a->status);

        // A metre costs what a metre cost: the value divides itself.
        $this->assertSame('250.0000', (string) $a->unit_cost);
        $this->assertSame('15000.0000', $a->totalValue());
        $this->assertSame('10000.0000', $b->totalValue());

        // Shade, lot and bin are the parent's — a split moves nothing.
        $this->assertSame('NAVY-01', $a->shade);
        $this->assertSame('L-1', $a->lot_no);
        $this->assertSame($this->bin->id, $a->bin_id);

        // And the parent cannot be split twice.
        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$roll->id}/split", [
            'children' => [
                ['roll_no' => 'R-100-C', 'net_length' => '50'],
                ['roll_no' => 'R-100-D', 'net_length' => '50'],
            ],
        ])->assertStatus(409);
    }

    public function test_splitting_a_held_roll_moves_the_hold_to_the_pieces(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $roll = $this->roll('R-200', '100');

        app(ReserveItemStock::class)->allocateRoll($roll, [
            'reservation_type' => 'FLOOR_REQUISITION',
            'reference_type' => 'floor_requisition_line',
            'reference_id' => $roll->id,
            'reserved_by' => $this->storekeeper->id,
        ]);

        $this->assertSame('RESERVED', $roll->fresh()->status);

        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$roll->id}/split", [
            'children' => [
                ['roll_no' => 'R-200-A', 'net_length' => '70'],
                ['roll_no' => 'R-200-B', 'net_length' => '30'],
            ],
        ])->assertCreated();

        // Both pieces are still spoken for, for the same requisition.
        foreach (['R-200-A', 'R-200-B'] as $rollNo) {
            $child = FabricRoll::where('roll_no', $rollNo)->firstOrFail();
            $this->assertSame('RESERVED', $child->status);

            $claim = ItemReservation::where('fabric_roll_id', $child->id)->firstOrFail();
            $this->assertSame('ACTIVE', $claim->status);
            $this->assertSame($roll->id, $claim->reference_id);
        }

        // The parent's own claim is closed, not deleted.
        $parentClaim = ItemReservation::where('fabric_roll_id', $roll->id)->firstOrFail();
        $this->assertSame('RELEASED', $parentClaim->status);
        $this->assertNotNull($parentClaim->override_reason);
    }

    public function test_a_merge_takes_the_weighted_average_not_the_mean(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        $first = $this->roll('R-300', '200', '250');
        $second = $this->roll('R-301', '50', '300');

        $merged = $this->withToken($token)->postJson('/api/v1/fabric-rolls/merge', [
            'roll_ids' => [$first->id, $second->id],
            'roll_no' => 'R-300-M',
        ])->assertCreated()->json('data');

        $this->assertSame('250.00', $merged['net_length']);
        // (200 x 250 + 50 x 300) / 250 = 260, not the mean of 275.
        $this->assertSame('260.0000', $merged['unit_cost']);

        $this->assertSame('MERGED', $first->fresh()->status);
        $this->assertSame('MERGED', $second->fresh()->status);
        $this->assertSame($merged['id'], $first->fresh()->merged_into_roll_id);
    }

    public function test_only_interchangeable_rolls_can_be_merged(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        $navy = $this->roll('R-400', '100');
        $other = $this->roll('R-401', '100');
        $other->forceFill(['shade' => 'RED-02'])->save();

        $this->withToken($token)->postJson('/api/v1/fabric-rolls/merge', [
            'roll_ids' => [$navy->id, $other->id],
            'roll_no' => 'R-400-M',
        ])->assertStatus(409);

        // A held roll is somebody's; merging it would move their cloth.
        $held = $this->roll('R-402', '100');
        app(ReserveItemStock::class)->allocateRoll($held, ['reserved_by' => $this->storekeeper->id]);

        $this->withToken($token)->postJson('/api/v1/fabric-rolls/merge', [
            'roll_ids' => [$navy->id, $held->id],
            'roll_no' => 'R-400-M',
        ])->assertStatus(409);

        $this->assertSame(0, FabricRoll::where('roll_no', 'R-400-M')->count());
    }

    public function test_a_landed_cost_correction_follows_the_roll_into_its_children(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $roll = $this->roll('R-500', '100', '250');

        $this->withToken($token)->postJson("/api/v1/fabric-rolls/{$roll->id}/split", [
            'children' => [
                ['roll_no' => 'R-500-A', 'net_length' => '60'],
                ['roll_no' => 'R-500-B', 'net_length' => '40'],
            ],
        ])->assertCreated();

        // The freight and duty land weeks later: 250 was provisional, 280
        // is what the cloth actually cost.
        $touched = app(RecalculateRollCosts::class)->forGoodsReceipt(
            (string) $roll->goods_receipt_id,
            [$this->fabric->id => '280'],
        );

        // Parent plus both children.
        $this->assertSame(3, $touched);
        $this->assertSame('280.0000', (string) FabricRoll::where('roll_no', 'R-500-A')->firstOrFail()->unit_cost);
        $this->assertSame('280.0000', (string) FabricRoll::where('roll_no', 'R-500-B')->firstOrFail()->unit_cost);

        // The parent is closed, so its correction is declared (§10.5); the
        // children are still on the shelf and need no such warning.
        $this->assertTrue((bool) $roll->fresh()->cost_adjusted_after_issue);
        $this->assertFalse((bool) FabricRoll::where('roll_no', 'R-500-A')->firstOrFail()->cost_adjusted_after_issue);
    }

    public function test_a_correction_re_weights_a_merged_roll_from_its_sources(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        $first = $this->roll('R-600', '200', '250');
        $second = $this->roll('R-601', '50', '300');

        $merged = $this->withToken($token)->postJson('/api/v1/fabric-rolls/merge', [
            'roll_ids' => [$first->id, $second->id],
            'roll_no' => 'R-600-M',
        ])->assertCreated()->json('data');

        // Only the first receipt is re-priced; the other source is untouched.
        app(RecalculateRollCosts::class)->forGoodsReceipt(
            (string) $first->goods_receipt_id,
            [$this->fabric->id => '270'],
        );

        // (200 x 270 + 50 x 300) / 250 = 276.
        $this->assertSame('276.0000', (string) FabricRoll::findOrFail($merged['id'])->unit_cost);
        $this->assertSame('300.0000', (string) $second->fresh()->unit_cost);
    }

    public function test_splitting_needs_its_own_permission(): void
    {
        $roll = $this->roll('R-700', '100');
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->withToken($this->tokenFor($technician))->postJson("/api/v1/fabric-rolls/{$roll->id}/split", [
            'children' => [
                ['roll_no' => 'R-700-A', 'net_length' => '50'],
                ['roll_no' => 'R-700-B', 'net_length' => '50'],
            ],
        ])->assertForbidden();
    }
}
