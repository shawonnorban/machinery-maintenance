<?php

declare(strict_types=1);

namespace Tests\Feature\ItemMaster;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\FabricDefect;
use App\Modules\ItemMaster\Models\FabricInspection;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemFabricAttributes;
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
 * The four-point system (Inventory spec §4.3).
 *
 * The figures in these tests are taken off a real inspection sheet —
 * Foshan indigo, 33 rolls, four inspected — so the arithmetic is pinned to
 * what an inspector actually wrote rather than to what the formula looked
 * like it should produce. Four rolls, four percentages, and a pooled lot
 * average that is deliberately *not* the mean of them.
 */
class FourPointInspectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Roll no. => [points, yards, width], with the percentage the mill's
     * own sheet recorded against each.
     */
    private const SHEET = [
        '199' => ['points' => 34, 'yards' => '124', 'percent' => '16.45'],
        '186' => ['points' => 38, 'yards' => '108', 'percent' => '21.11'],
        '197' => ['points' => 42, 'yards' => '113', 'percent' => '22.30'],
        '193' => ['points' => 39, 'yards' => '121', 'percent' => '19.33'],
    ];

    private const WIDTH = '60';

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $storekeeper;

    private User $storeManager;

    private Vendor $vendor;

    private Item $fabric;

    /** Set by `receiveLot()`, which every test starts with. */
    private string $grnId = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka, 'DHK-A1');
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');
        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');

        $this->vendor = Vendor::create([
            'company_id' => $this->delta->id, 'name' => 'Foshan Textiles', 'code' => 'FOSHAN',
            'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE', 'default_currency' => 'BDT',
        ]);

        $this->fabric = ItemFixture::item($this->delta, 'FAB-DNM-IND', 'Denim indigo', [
            'item_type' => 'FABRIC', 'tracking_mode' => 'ROLL', 'uom_id' => ItemFixture::uomId($this->delta, 'MTR'),
        ]);
        ItemFabricAttributes::create([
            'company_id' => $this->delta->id, 'item_id' => $this->fabric->id,
            'fabric_type' => 'WOVEN', 'gsm' => '340', 'width' => self::WIDTH, 'composition' => '100% cotton',
        ]);
    }

    public function test_a_roll_scores_what_the_inspector_wrote(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $rolls = $this->receiveLot($token);
        $inspection = $this->openSheet($token);

        foreach (self::SHEET as $rollNo => $expected) {
            $data = $this->recordRoll($token, $inspection['id'], $rolls[$rollNo], $expected['points'], $expected['yards']);

            $row = collect($data['rolls'])->firstWhere('roll_no', (string) $rollNo);

            $this->assertSame(
                $expected['percent'],
                $row['penalty_percent'],
                "Roll {$rollNo} scored {$row['penalty_percent']}, the sheet says {$expected['percent']}.",
            );
            $this->assertSame((string) $expected['points'].'.00', $row['inspection_points']);
        }
    }

    public function test_the_lot_figure_is_pooled_rather_than_averaged(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $rolls = $this->receiveLot($token);
        $inspection = $this->openSheet($token);
        $data = $this->recordWholeSheet($token, $inspection['id'], $rolls);

        // 153 points over 466 yards at 60": 153 × 3600 ÷ 27,960 = 19.699…,
        // truncated to 19.69 — which is the figure on the mill's sheet.
        // Rounding would have produced 19.70 and quietly disagreed with it.
        $this->assertSame('153.00', $data['total_points']);
        $this->assertSame('466.00', $data['yards_inspected']);
        $this->assertSame('19.69', $data['penalty_percent']);

        // The mean of the four roll percentages is 19.79. Pooling is what
        // keeps a short bad roll from outweighing a long clean one.
        $this->assertNotSame('19.79', $data['penalty_percent']);
    }

    public function test_a_lot_inside_the_limit_passes_and_its_rolls_reach_the_shelf(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $rolls = $this->receiveLot($token);
        $inspection = $this->openSheet($token);
        $this->recordWholeSheet($token, $inspection['id'], $rolls);

        // 19.69 against the default bar of 28.
        $submitted = $this->withToken($token)
            ->postJson("/api/v1/fabric-inspections/{$inspection['id']}/submit")
            ->assertOk()->json('data');

        $this->assertSame('PASS', $submitted['result']);
        $this->assertSame('SUBMITTED', $submitted['status']);

        // Accepting the lot is the store manager's, not the storekeeper's.
        $this->withToken($token)
            ->postJson("/api/v1/fabric-inspections/{$inspection['id']}/approve")
            ->assertForbidden();

        $approved = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/fabric-inspections/{$inspection['id']}/approve")
            ->assertOk()->json('data');

        $this->assertSame('APPROVED', $approved['status']);

        foreach (array_keys(self::SHEET) as $rollNo) {
            $roll = FabricRoll::where('roll_no', (string) $rollNo)->firstOrFail();
            $this->assertSame('AVAILABLE', $roll->status, "Roll {$rollNo} did not reach the shelf.");
            // Every roll here is under the bar, so every one is an A.
            $this->assertSame('A', $roll->inspection_grade);
        }
    }

    public function test_a_failed_pass_escalates_to_a_wider_sample_instead_of_being_rewritten(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $rolls = $this->receiveLot($token);
        $inspection = $this->openSheet($token);

        // One filthy roll: 300 points over 124 yards at 60" is 145.16.
        $this->recordRoll($token, $inspection['id'], $rolls['199'], 300, '124');
        $submitted = $this->withToken($token)
            ->postJson("/api/v1/fabric-inspections/{$inspection['id']}/submit")
            ->assertOk()->json('data');

        $this->assertSame('FAIL', $submitted['result']);
        $this->assertSame('FIRST', $submitted['stage']);

        $rejected = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/fabric-inspections/{$inspection['id']}/reject", ['reason' => 'Shade and points both out'])
            ->assertOk()->json('data');

        $this->assertSame('REJECTED', $rejected['status']);
        $this->assertTrue($rejected['can_escalate']);
        // Nothing was graded: a refused sample is a reason to look wider,
        // not a verdict on each roll.
        $this->assertSame('UNDER_INSPECTION', FabricRoll::where('roll_no', '199')->firstOrFail()->status);

        $wider = $this->withToken($token)
            ->postJson("/api/v1/fabric-inspections/{$inspection['id']}/escalate")
            ->assertCreated()->json('data');

        $this->assertSame('SECOND', $wider['stage']);
        $this->assertSame('15.00', $wider['sampling_percent']);
        $this->assertSame($inspection['id'], $wider['supersedes']['id']);
        $this->assertNotSame($inspection['id'], $wider['id']);

        // The failed sheet is left exactly as it was found.
        $original = FabricInspection::findOrFail($inspection['id']);
        $this->assertSame('REJECTED', $original->status);
        $this->assertSame('FAIL', $original->result);
    }

    public function test_escalation_stops_once_the_whole_lot_has_been_opened(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $rolls = $this->receiveLot($token);
        $inspection = $this->openSheet($token, ['stage' => 'FULL']);

        $this->recordRoll($token, $inspection['id'], $rolls['199'], 300, '124');
        $this->withToken($token)->postJson("/api/v1/fabric-inspections/{$inspection['id']}/submit")->assertOk();
        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/fabric-inspections/{$inspection['id']}/reject")->assertOk();

        // There is no sample wider than all of it; what happens next is a
        // commercial decision rather than more inspecting.
        $this->withToken($token)
            ->postJson("/api/v1/fabric-inspections/{$inspection['id']}/escalate")
            ->assertStatus(409)
            ->assertJsonValidationErrors(['stage']);
    }

    public function test_a_roll_with_no_measured_length_is_refused_rather_than_scored_clean(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $rolls = $this->receiveLot($token);
        $inspection = $this->openSheet($token);

        $this->withToken($token)
            ->postJson("/api/v1/fabric-inspections/{$inspection['id']}/rolls", [
                'fabric_roll_id' => $rolls['199'],
                'width_start' => self::WIDTH,
                'inspected_length' => '0',
            ])
            ->assertStatus(422);

        // Nothing partially written: the sheet is still empty.
        $this->assertSame(0, FabricInspection::findOrFail($inspection['id'])->rolls_inspected);
    }

    public function test_the_score_uses_the_mean_of_three_widths_and_records_the_shortfall(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $rolls = $this->receiveLot($token);
        $inspection = $this->openSheet($token);

        $data = $this->withToken($token)
            ->postJson("/api/v1/fabric-inspections/{$inspection['id']}/rolls", [
                'fabric_roll_id' => $rolls['199'],
                // A mill's width drifts; 59 + 60 + 61 means 60.
                'width_start' => '59', 'width_middle' => '60', 'width_end' => '61',
                'marked_length' => '124',
                'inspected_length' => '120',
                'defects' => $this->tallyFor(34),
            ])
            ->assertOk()->json('data');

        $row = collect($data['rolls'])->firstWhere('roll_no', '199');

        $this->assertSame('60.00', $row['actual_width']);
        // The ticket said 124, the table measured 120.
        $this->assertSame('4.00', $row['length_shortfall']);
        // 34 × 3600 ÷ (120 × 60) = 17.00.
        $this->assertSame('17.00', $row['penalty_percent']);
    }

    public function test_a_shade_failure_under_any_light_marks_the_roll(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $rolls = $this->receiveLot($token);
        $inspection = $this->openSheet($token);

        $data = $this->withToken($token)
            ->postJson("/api/v1/fabric-inspections/{$inspection['id']}/rolls", [
                'fabric_roll_id' => $rolls['199'],
                'width_start' => self::WIDTH,
                'inspected_length' => '124',
                // Matches in daylight, goes off under the store's tubes —
                // which a single boolean could never have said.
                'shade_assessment' => [
                    'D65.APPROVED' => 'PASS',
                    'TL83.APPROVED' => 'FAIL',
                    'A.APPROVED' => 'PASS',
                ],
            ])
            ->assertOk()->json('data');

        $row = collect($data['rolls'])->firstWhere('roll_no', '199');

        $this->assertFalse($row['shade_clean']);
        $this->assertFalse(FabricRoll::where('roll_no', '199')->firstOrFail()->shade_match);
    }

    public function test_a_defect_from_the_wrong_family_is_refused(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $rolls = $this->receiveLot($token);
        $inspection = $this->openSheet($token);

        // This fabric is WOVEN; a drop stitch is a knit fault.
        $knit = FabricDefect::where('fabric_family', 'KNIT')->where('code', 'DROP_STITCH')->firstOrFail();

        $this->withToken($token)
            ->postJson("/api/v1/fabric-inspections/{$inspection['id']}/rolls", [
                'fabric_roll_id' => $rolls['199'],
                'width_start' => self::WIDTH,
                'inspected_length' => '124',
                'defects' => [['defect_id' => $knit->id, 'point_class' => 4, 'occurrences' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['defects']);
    }

    public function test_re_recording_a_roll_replaces_its_tally_rather_than_adding_to_it(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $rolls = $this->receiveLot($token);
        $inspection = $this->openSheet($token);

        $this->recordRoll($token, $inspection['id'], $rolls['199'], 34, '124');
        // The inspector recounts and finds fewer faults.
        $data = $this->recordRoll($token, $inspection['id'], $rolls['199'], 12, '124');

        $row = collect($data['rolls'])->firstWhere('roll_no', '199');

        $this->assertSame('12.00', $row['inspection_points']);
        $this->assertSame('12.00', $data['total_points']);
    }

    // ---------------------------------------------------------------- helpers

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    /**
     * The four rolls off the sheet, received through a GRN.
     *
     * @return array<string, string> roll_no => id
     */
    private function receiveLot(string $token): array
    {
        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'vendor_id' => $this->vendor->id,
            'lines' => [['item_id' => $this->fabric->id, 'received_qty' => '466', 'rate' => '250']],
        ])->assertCreated()->json('data');

        $rolls = [];

        foreach (self::SHEET as $rollNo => $row) {
            $rolls[] = [
                'roll_no' => (string) $rollNo,
                'net_length' => $row['yards'],
                'gross_length' => $row['yards'],
                'shade' => 'Indigo',
                'bin_id' => $this->bin->id,
            ];
        }

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete", [
            'rolls' => [$grn['lines'][0]['id'] => $rolls],
        ])->assertOk();

        $this->grnId = $grn['id'];

        return FabricRoll::pluck('id', 'roll_no')->all();
    }


    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function openSheet(string $token, array $overrides = []): array
    {
        return $this->withToken($token)->postJson('/api/v1/fabric-inspections', array_merge([
            'goods_receipt_id' => $this->grnId,
            'item_id' => $this->fabric->id,
            'buyer_reference' => 'HI-GLOTEX',
            'style_reference' => '45.6921-CMB',
            'colour' => 'Indigo',
            'composition' => '100% CTN',
            'rolls_in_lot' => 33,
            'yards_in_lot' => '3275',
        ], $overrides))->assertCreated()->json('data');
    }

    /**
     * @return array<string, mixed>
     */
    private function recordRoll(string $token, string $inspectionId, string $rollId, int $points, string $yards): array
    {
        return $this->withToken($token)
            ->postJson("/api/v1/fabric-inspections/{$inspectionId}/rolls", [
                'fabric_roll_id' => $rollId,
                'width_start' => self::WIDTH,
                'width_middle' => self::WIDTH,
                'width_end' => self::WIDTH,
                'marked_length' => $yards,
                'inspected_length' => $yards,
                'defects' => $this->tallyFor($points),
            ])
            ->assertOk()->json('data');
    }

    /**
     * @param  array<string, string>  $rolls
     * @return array<string, mixed>
     */
    private function recordWholeSheet(string $token, string $inspectionId, array $rolls): array
    {
        $data = [];

        foreach (self::SHEET as $rollNo => $row) {
            $data = $this->recordRoll($token, $inspectionId, $rolls[$rollNo], $row['points'], $row['yards']);
        }

        return $data;
    }

    /**
     * A tally that comes to exactly `$points`, using four-point faults and
     * then a single-point one for the remainder — the arithmetic under
     * test is the formula, not how the inspector reached the total.
     *
     * @return list<array<string, mixed>>
     */
    private function tallyFor(int $points): array
    {
        $hole = FabricDefect::where('fabric_family', 'WOVEN')->where('code', 'HOLE')->firstOrFail();
        $knot = FabricDefect::where('fabric_family', 'WOVEN')->where('code', 'KNOT')->firstOrFail();

        $tally = [];

        if (intdiv($points, 4) > 0) {
            $tally[] = ['defect_id' => $hole->id, 'point_class' => 4, 'occurrences' => intdiv($points, 4)];
        }

        if ($points % 4 > 0) {
            $tally[] = ['defect_id' => $knot->id, 'point_class' => 1, 'occurrences' => $points % 4];
        }

        return $tally;
    }
}
