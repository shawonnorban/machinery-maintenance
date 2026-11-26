<?php

declare(strict_types=1);

namespace Tests\Feature\Finishing;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Finishing Line → QC Process (AnnotechERP 18/04): roll readings off the
 * machine, one shade decision per batch, the four-point inspection whose
 * posted verdict moves the batch on the floor board, the re-process advised
 * on what it rejected — and who may do what.
 */
class FinishingQcProcessTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    /** @var array<string, string> code => id */
    private array $process = [];

    private string $stenter;

    private string $dryer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => Factory::query()->first()->id, 'name' => 'Finishing', 'code' => 'B1']);
        $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Finishing', 'code' => 'FN-1']);
        $look = $this->as()->getJson('/api/v1/finishing/lookups')->assertOk()->json('data');
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '276', 'name' => 'Germany'])->json('data.id');
        $machine = fn (string $no, string $type) => $this->as()->postJson('/api/v1/erp/fin-machines', ['machine_no' => $no, 'name' => $no, 'machine_type' => $type, 'origin_country_id' => $country, 'brand_id' => $look['brands'][0]['value'], 'mou' => 'KG', 'status' => 'RUNNING', 'floor_id' => $floor->id])->assertCreated()->json('data.id');
        $this->stenter = $machine('ST-01', 'STENTER');
        $this->dryer = $machine('DR-01', 'DRYER');
        $this->process = collect($this->as()->getJson('/api/v1/erp/fin-process-types')->json('data'))->pluck('id', 'code')->all();
        $this->as()->postJson('/api/v1/erp/fin-machine-processes', ['machine_id' => $this->stenter, 'process_id' => $this->process['DF-ST'], 'dia_type' => 'OPEN', 'sequence' => 1, 'batch_status' => 'ON_HOLD', 'next_status' => 'STENTERING'])->assertCreated();
    }

    public function test_a_roll_is_read_as_it_comes_off_the_machine(): void
    {
        $card = $this->card('B-26110001', 'Navy');
        $e = ['entry_date' => '2026-11-06', 'receipt_id' => $card, 'machine_id' => $this->stenter, 'process_id' => $this->process['DF-ST'], 'shift' => 'DAY', 'roll_no' => ' r-01 ', 'weight_kg' => 18.4, 'gsm' => 182, 'dia' => 72, 'shade' => 'OK', 'result' => 'OK'];
        $this->as()->postJson('/api/v1/erp/fin-qc-entries', ['result' => 'FAULT'] + $e)->assertUnprocessable()->assertJsonPath('errors.defect.0', 'Name the defect of a faulty roll.');
        $this->as()->postJson('/api/v1/erp/fin-qc-entries', ['entry_date' => '2026-11-04'] + $e)->assertUnprocessable()->assertJsonPath('errors.entry_date.0', 'This cannot be dated before the batch was received (FBR-2026110001).');
        $this->as()->postJson('/api/v1/erp/fin-qc-entries', $e)->assertCreated()->assertJsonPath('data.roll_no', 'R-01')->assertJsonPath('data.batch_no', 'B-26110001')->assertJsonPath('data.machine', 'ST-01 — ST-01')->assertJsonPath('data.weight_kg', 18.4);
        $this->as()->postJson('/api/v1/erp/fin-qc-entries', $e)->assertUnprocessable()->assertJsonPath('errors.roll_no.0', 'Roll R-01 of batch B-26110001 is read already.');
        $this->as()->postJson('/api/v1/erp/fin-qc-entries', ['roll_no' => 'R-02', 'shade' => 'OFF', 'result' => 'FAULT', 'defect' => 'Crease mark'] + $e)->assertCreated();
        $this->as()->getJson('/api/v1/erp/fin-qc-entries')->assertJsonCount(2, 'data');
    }

    public function test_one_shade_decision_per_batch_stamped_when_decided(): void
    {
        $card = $this->card('B-26110011', 'Red');
        $id = $this->as()->postJson('/api/v1/erp/fin-shade-decisions', ['receipt_id' => $card, 'status' => 'PENDING', 'model_continuity' => 'Body + rib'])->assertCreated()
            ->assertJsonPath('data.colour', 'Red')->assertJsonPath('data.batch_qty', 100)->assertJsonPath('data.decided_by', null)->assertJsonPath('data.batch_no', 'B-26110011')->json('data.id');
        $this->as()->postJson('/api/v1/erp/fin-shade-decisions', ['receipt_id' => $card, 'status' => 'APPROVED'])->assertUnprocessable()->assertJsonPath('errors.receipt_id.0', 'Batch B-26110011 is on the queue already — open it from the Batch List.');
        $this->as()->patchJson("/api/v1/erp/fin-shade-decisions/{$id}", ['status' => 'APPROVED'])->assertOk()->assertJsonPath('data.decided_by_name', $this->owner->name)
            ->assertJsonPath('data.decided_at', fn ($v) => $v !== null);
        $this->as()->patchJson("/api/v1/erp/fin-shade-decisions/{$id}", ['remarks' => 'Matched under D65'])->assertOk()->assertJsonPath('data.status', 'APPROVED')->assertJsonPath('data.decided_by', $this->owner->id);
        $this->as()->patchJson("/api/v1/erp/fin-shade-decisions/{$id}", ['status' => 'PENDING'])->assertOk()->assertJsonPath('data.decided_by', null)->assertJsonPath('data.decided_at', null);
        $this->as()->patchJson("/api/v1/erp/fin-shade-decisions/{$id}", ['receipt_id' => $this->card('B-26110012', 'Blue')])->assertUnprocessable()->assertJsonPath('errors.receipt_id.0', "A decision's batch is fixed.");
    }

    public function test_the_four_point_verdict_moves_the_batch_on_the_floor_board(): void
    {
        $held = $this->card('B-26110021', 'Navy');
        $passed = $this->card('B-26110022', 'Black');
        $i = ['receipt_id' => $held, 'insp_date' => '2026-11-06', 'shift' => 'DAY', 'actual_dia' => 72, 'shrinkage_l' => -3.5, 'shrinkage_w' => -2, 'spirality' => 4, 'inspector_id' => $this->owner->id, 'verdict' => 'HOLD', 'shade_note' => 'Slightly red'];
        $roll = fn (string $no, float $kg, int $pts, string $result = 'PASS') => ['roll_no' => $no, 'weight_kg' => $kg, 'points' => $pts, 'grade' => 'a', 'result' => $result];
        $this->as()->postJson('/api/v1/finishing/inspections', $i + ['rolls' => []])->assertUnprocessable()->assertJsonPath('errors.rolls.0', 'An inspection with no rolls posts nothing — add at least one.');
        $this->as()->postJson('/api/v1/finishing/inspections', $i + ['rolls' => [$roll('r-1', 18, 6), $roll('R-1', 17, 2)]])->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => $e['rolls.1.roll_no'][0] === 'Row 2: roll R-1 is on this sheet already.');

        $ref = $this->as()->postJson('/api/v1/finishing/inspections', $i + ['rolls' => [$roll('r-1', 18.2, 6), $roll('r-2', 17.5, 14, 'HOLD')]])->assertCreated()
            ->assertJsonPath('data.ref_no', 'FFI-2026110001')->assertJsonPath('data.roll_count', 2)->assertJsonPath('data.total_kg', 35.7)->assertJsonPath('data.total_points', 20)
            ->assertJsonPath('data.rolls.0.roll_no', 'R-1')->assertJsonPath('data.rolls.0.grade', 'A')->assertJsonPath('data.colour', 'Navy')->json('data.ref_no');
        $this->as()->putJson("/api/v1/finishing/inspections/{$ref}", $i + ['rolls' => [$roll('R-1', 18.2, 6), $roll('R-2', 17.5, 14, 'HOLD'), $roll('R-3', 16, 0)]])->assertOk()->assertJsonPath('data.roll_count', 3);
        $this->as()->postJson('/api/v1/finishing/inspections', ['ref_no' => 'ffi-2026110001', 'receipt_id' => $passed] + $i + ['rolls' => [$roll('X', 1, 0)]])->assertUnprocessable()->assertJsonPath('errors.ref_no.0', 'Ref # FFI-2026110001 is taken already.');

        $this->as()->postJson("/api/v1/finishing/inspections/{$ref}/post")->assertOk()->assertJsonPath('data.status', 'POSTED');
        $this->assertSame('ON_HOLD', DB::table('fin_batch_receipts')->where('id', $held)->value('stage'));
        $this->as()->putJson("/api/v1/finishing/inspections/{$ref}", $i + ['rolls' => [$roll('R-1', 1, 0)]])->assertUnprocessable()->assertJsonPath('errors.ref_no.0', 'FFI-2026110001 is posted and reads only.');

        $ok = $this->as()->postJson('/api/v1/finishing/inspections', ['ref_no' => 'QC-77', 'receipt_id' => $passed, 'verdict' => 'PASS'] + $i + ['rolls' => [$roll('P-1', 20, 2)]])->assertCreated()->json('data.ref_no');
        $this->as()->postJson("/api/v1/finishing/inspections/{$ok}/post")->assertOk();
        $this->assertSame('READY_FOR_DELIVERY', DB::table('fin_batch_receipts')->where('id', $passed)->value('stage'));
        $this->assertSame('QC_INSP', DB::table('fin_process_deliveries')->where('receipt_id', $passed)->orderByDesc('created_at')->orderByDesc('id')->value('source'));
        $rows = collect($this->as()->getJson('/api/v1/finishing/inspections')->assertOk()->json('data'));
        $this->assertSame('R-1 R-2 R-3', $rows->firstWhere('ref_no', $ref)['rolls']);
    }

    public function test_rejected_rolls_are_advised_back_onto_the_floor(): void
    {
        $card = $this->card('B-26110031', 'Navy');
        $passed = $this->inspect($card, 'PASS', [['roll_no' => 'A1', 'weight_kg' => 20, 'result' => 'PASS']]);
        $a = ['advice_date' => '2026-11-07', 'process_id' => $this->process['DF-ST'], 'machine_id' => $this->stenter, 'reason' => 'SHADE'];
        $this->as()->postJson('/api/v1/erp/fin-reprocess-advices', ['inspection_id' => $passed['id']] + $a)->assertUnprocessable()->assertJsonPath('errors.inspection_id.0', "{$passed['ref_no']} is not a posted inspection that rejected anything.");

        $other = $this->card('B-26110032', 'Navy');
        $rej = $this->inspect($other, 'REJECT', [['roll_no' => 'B1', 'weight_kg' => 19.5, 'result' => 'REJECT'], ['roll_no' => 'B2', 'weight_kg' => 20, 'result' => 'PASS'], ['roll_no' => 'B3', 'weight_kg' => 18, 'result' => 'REJECT']]);
        $this->assertSame('ON_HOLD', DB::table('fin_batch_receipts')->where('id', $other)->value('stage'));
        $this->as()->postJson('/api/v1/erp/fin-reprocess-advices', ['inspection_id' => $rej['id'], 'machine_id' => $this->dryer] + $a)->assertUnprocessable()->assertJsonPath('errors.machine_id.0', "That machine is not mapped to that process for the batch's dia.");
        $this->as()->postJson('/api/v1/erp/fin-reprocess-advices', ['inspection_id' => $rej['id'], 'advice_date' => '2026-11-05'] + $a)->assertUnprocessable()->assertJsonValidationErrors('advice_date');
        $this->as()->postJson('/api/v1/erp/fin-reprocess-advices', ['inspection_id' => $rej['id'], 'reason' => 'OTHER'] + $a)->assertUnprocessable()->assertJsonPath('errors.remarks.0', 'Say what the other reason is in Remarks.');
        $id = $this->as()->postJson('/api/v1/erp/fin-reprocess-advices', ['inspection_id' => $rej['id']] + $a)->assertCreated()
            ->assertJsonPath('data.advice_no', 'FRA-2026110001')->assertJsonPath('data.rejected_rolls', 2)->assertJsonPath('data.rejected_kg', 37.5)->assertJsonPath('data.batch_no', 'B-26110032')->json('data.id');
        $this->assertSame('AWAITING_LOAD', DB::table('fin_batch_receipts')->where('id', $other)->value('stage'));
        $this->as()->postJson('/api/v1/erp/fin-reprocess-advices', ['inspection_id' => $rej['id']] + $a)->assertUnprocessable()->assertJsonPath('errors.inspection_id.0', "{$rej['ref_no']} has been advised already.");
        $this->assertSame([], $this->as()->getJson('/api/v1/finishing/qc/options')->json('data.rejected'));

        // Undone while nothing has happened since: the batch is held again.
        $this->as()->deleteJson("/api/v1/erp/fin-reprocess-advices/{$id}")->assertNoContent();
        $this->assertSame('ON_HOLD', DB::table('fin_batch_receipts')->where('id', $other)->value('stage'));
        $again = $this->as()->postJson('/api/v1/erp/fin-reprocess-advices', ['inspection_id' => $rej['id']] + $a)->assertCreated()->json('data.id');
        $no = DB::table('fin_batch_receipts')->where('id', $other)->value('receipt_no');
        $this->as()->postJson("/api/v1/finishing/delivery-board/{$no}/deliver", ['to_stage' => 'STENTERING'])->assertOk();
        $this->as()->deleteJson("/api/v1/erp/fin-reprocess-advices/{$again}")->assertUnprocessable()->assertJsonPath('errors.advice_no.0', 'The batch has moved on since FRA-2026110001, so it stays.');
    }

    public function test_who_may_judge_quality(): void
    {
        $chief = $this->token('LINE_CHIEF', 'chief@delta.test');
        $this->withToken($chief)->getJson('/api/v1/finishing/inspections')->assertOk();
        $this->withToken($chief)->postJson('/api/v1/erp/fin-shade-decisions', ['status' => 'PENDING'])->assertForbidden();
        $manager = $this->token('FACTORY_MANAGER', 'manager@delta.test');
        $this->withToken($manager)->postJson('/api/v1/erp/fin-shade-decisions', ['receipt_id' => $this->card('B-26110041', 'Navy'), 'status' => 'PENDING'])->assertCreated();
        $technician = $this->token('TECHNICIAN', 'tech@delta.test');
        $this->withToken($technician)->getJson('/api/v1/erp/fin-qc-entries')->assertForbidden();
    }

    /** A dyed batch received onto the floor as Open (100 kg, 6 rolls); its card id. */
    private function card(string $no, string $colour): string
    {
        $batch = (string) Str::ulid();
        $cid = DB::table('colours')->where('company_id', $this->delta->id)->where('name', $colour)->value('id');
        if (! $cid) {
            DB::table('colours')->insert(['id' => $cid = (string) Str::ulid(), 'company_id' => $this->delta->id, 'name' => $colour, 'colour_type' => 'SOLID', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('dye_batches')->insert(['id' => $batch, 'company_id' => $this->delta->id, 'batch_no' => $no, 'batch_date' => '2026-11-01', 'scope' => 'BULK', 'source' => 'MANUAL', 'colour_id' => $cid, 'lot_no' => 'L-'.substr($no, -2), 'plan_qty' => 100, 'card_qty' => 100, 'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('dye_productions')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'batch_id' => $batch, 'production_date' => '2026-11-04', 'liquor_ratio' => '1:8', 'created_at' => now(), 'updated_at' => now()]);

        return $this->as()->postJson('/api/v1/erp/fin-batch-receipts', ['receive_date' => '2026-11-05', 'batch_id' => $batch, 'dia_type' => 'OPEN', 'recv_qty_kg' => 100, 'recv_rolls' => 6])->assertCreated()->json('data.id');
    }

    /** A posted inspection. @param list<array<string, mixed>> $rolls @return array<string, mixed> */
    private function inspect(string $card, string $verdict, array $rolls): array
    {
        $doc = $this->as()->postJson('/api/v1/finishing/inspections', ['receipt_id' => $card, 'insp_date' => '2026-11-06', 'shift' => 'NIGHT', 'verdict' => $verdict, 'rolls' => $rolls])->assertCreated()->json('data');
        $this->as()->postJson("/api/v1/finishing/inspections/{$doc['ref_no']}/post")->assertOk();

        return $doc;
    }

    private function token(string $role, string $email): string
    {
        return app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, $role, $email), $this->delta->id, 'Test token')['plain'];
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
