<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Models\ApiClient;
use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Modules\Metering\Actions\ManageAssetMeter;
use App\Modules\Metering\Models\AssetMeter;
use App\Modules\Metering\Models\MeterReading;
use App\Modules\Metering\Models\MeterType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The endpoints an integration actually calls (API 6, 10, 11, 12, 13).
 *
 * Two things are worth holding down here and the rest follows from them: a
 * record in a factory the caller cannot reach is indistinguishable from one
 * that never existed, and a write that arrives twice happens once.
 */
class ApiResourceTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Company $rival;

    private Asset $machine;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->machine = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $this->rival = TenantFixture::company('Rival Textiles Ltd', 'RTL');
        TenantFixture::factory($this->rival, 'Savar Unit', 'SAV');
        TenantFixture::actingAsTenant($this->rival);
        TenantFixture::user($this->rival, 'COMPANY_OWNER', 'owner@rival.test');

        TenantFixture::actingAsTenant($this->delta);

        $this->token = $this->tokenFor('owner@delta.test');
    }

    // -- Machines -----------------------------------------------------------

    public function test_machines_are_listed_with_the_standard_envelope(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/assets')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.asset_code', 'SEW-DHK-00412')
            ->assertJsonStructure(['meta' => ['current_page', 'last_page', 'per_page', 'total', 'request_id']]);

        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_a_machine_in_another_company_does_not_exist(): void
    {
        TenantFixture::actingAsTenant($this->rival);
        $theirs = WorkOrderFixture::runningAsset(
            $this->rival, Factory::where('code', 'SAV')->firstOrFail(), 'KNI-SAV-00001',
        );
        TenantFixture::actingAsTenant($this->delta);

        // 404 rather than 403. A 403 would confirm the id is real, and that is
        // all somebody probing needs (API 2).
        $this->withToken($this->token)
            ->getJson('/api/v1/assets/'.$theirs->id)
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

        $this->withToken($this->token)
            ->getJson('/api/v1/assets')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_filters_and_sorts_are_allowlisted(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/assets?status=RUNNING')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->withToken($this->token)
            ->getJson('/api/v1/assets?status=SCRAPPED')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        // A mistyped sort still returns the data, in a defined order. A 422
        // here would refuse something that changes nothing about correctness.
        $this->withToken($this->token)
            ->getJson('/api/v1/assets?sort=secret_column')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_the_page_size_is_capped(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/assets?per_page=5000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    // -- Meter readings, the retry case -------------------------------------

    public function test_a_reading_is_recorded_and_reports_what_it_brought_due(): void
    {
        $meter = $this->meter();

        $this->withToken($this->token)
            ->postJson('/api/v1/meters/'.$meter->id.'/readings', ['value' => '1200'])
            ->assertCreated()
            ->assertJsonPath('data.value', '1200.0000')
            ->assertJsonPath('data.meter.current_value', '1200.0000')
            ->assertJsonPath('data.triggered_maintenance', 0);

        $this->assertSame(1, MeterReading::where('meter_id', $meter->id)->count());
    }

    public function test_the_same_reading_sent_twice_is_recorded_once(): void
    {
        $meter = $this->meter();

        $body = ['value' => '1200'];
        $key = 'plc-dhk-2026-08-23-0600';

        $first = $this->withToken($this->token)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/meters/'.$meter->id.'/readings', $body)
            ->assertCreated();

        $second = $this->withToken($this->token)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/meters/'.$meter->id.'/readings', $body)
            ->assertCreated();

        // The second call is answered from the first, not executed. A reading
        // counted twice brings the next service forward and can raise a job
        // that is not due.
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame('true', $second->headers->get('Idempotent-Replay'));
        $this->assertSame(1, MeterReading::where('meter_id', $meter->id)->count());
    }

    public function test_the_same_key_with_a_different_body_is_refused(): void
    {
        $meter = $this->meter();
        $key = 'plc-dhk-2026-08-23-0600';

        $this->withToken($this->token)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/meters/'.$meter->id.'/readings', ['value' => '1200'])
            ->assertCreated();

        // A client bug. Executing it would be the worst reading of an
        // ambiguous request.
        $this->withToken($this->token)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/meters/'.$meter->id.'/readings', ['value' => '9999'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');

        $this->assertSame(1, MeterReading::where('meter_id', $meter->id)->count());
    }

    public function test_a_cumulative_meter_refuses_to_go_backwards(): void
    {
        $meter = $this->meter();

        $this->withToken($this->token)
            ->postJson('/api/v1/meters/'.$meter->id.'/readings', ['value' => '1200'])
            ->assertCreated();

        $this->withToken($this->token)
            ->postJson('/api/v1/meters/'.$meter->id.'/readings', ['value' => '900'])
            ->assertStatus(422);
    }

    public function test_a_machine_client_reading_is_marked_as_coming_from_a_machine(): void
    {
        $meter = $this->meter();
        $token = $this->machineToken(['meter.reading.create', 'meter.reading.view_any']);

        $this->withToken($token)
            ->postJson('/api/v1/meters/'.$meter->id.'/readings', [
                'value' => '1500',
                'source_reference' => 'PLC-7742',
            ])
            ->assertCreated();

        $reading = MeterReading::where('meter_id', $meter->id)->firstOrFail();

        // Somebody reading this row in two years should be able to tell a PLC
        // from a tablet.
        $this->assertSame('API', $reading->source);
        $this->assertSame('PLC-7742', $reading->source_reference);
        $this->assertNull($reading->recorded_by);
    }

    public function test_a_scope_a_client_was_not_given_is_refused(): void
    {
        $token = $this->machineToken(['meter.reading.create']);

        // The credential can post readings. That is all it can do, whatever
        // the administrator who minted it could have done.
        $this->withToken($token)->getJson('/api/v1/assets')->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->withToken($token)->getJson('/api/v1/work-orders')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/spare-parts')->assertForbidden();
    }

    // -- Breakdowns ---------------------------------------------------------

    public function test_reporting_a_breakdown_needs_only_the_machine_and_the_problem(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/breakdowns', [
                'asset_id' => $this->machine->id,
                'problem_description' => 'Needle bar seized, line 3 stopped',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'REPORTED')
            ->assertJsonPath('data.is_open', true);

        // Priority is derived from the machine rather than demanded from
        // somebody standing next to a stopped line.
        $this->assertNotNull($response->json('data.priority'));
        $this->assertNotNull($response->json('data.breakdown_number'));

        // The machine says it is down while it is down, not after the
        // paperwork is filed.
        $this->assertSame('BREAKDOWN', $this->machine->fresh()->status);
    }

    public function test_a_breakdown_reported_twice_is_one_breakdown(): void
    {
        $body = [
            'asset_id' => $this->machine->id,
            'problem_description' => 'Needle bar seized',
        ];

        $first = $this->withToken($this->token)
            ->withHeader('Idempotency-Key', 'tablet-4471')
            ->postJson('/api/v1/breakdowns', $body)
            ->assertCreated();

        $second = $this->withToken($this->token)
            ->withHeader('Idempotency-Key', 'tablet-4471')
            ->postJson('/api/v1/breakdowns', $body)
            ->assertCreated();

        // Two breakdown numbers for one stoppage halve the MTBF of a machine
        // that broke once.
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Breakdown::count());
    }

    public function test_a_breakdown_moves_through_its_steps(): void
    {
        $id = $this->withToken($this->token)
            ->postJson('/api/v1/breakdowns', [
                'asset_id' => $this->machine->id,
                'problem_description' => 'Needle bar seized',
            ])
            ->json('data.id');

        $this->withToken($this->token)
            ->postJson('/api/v1/breakdowns/'.$id.'/acknowledge')
            ->assertOk()
            ->assertJsonPath('data.status', 'ACKNOWLEDGED');

        $this->withToken($this->token)
            ->postJson('/api/v1/breakdowns/'.$id.'/start-repair')
            ->assertOk()
            ->assertJsonPath('data.status', 'IN_REPAIR');

        $this->withToken($this->token)
            ->postJson('/api/v1/breakdowns/'.$id.'/complete-repair')
            ->assertOk()
            ->assertJsonPath('data.status', 'REPAIRED');
    }

    public function test_a_step_the_record_cannot_take_is_a_conflict(): void
    {
        $id = $this->withToken($this->token)
            ->postJson('/api/v1/breakdowns', [
                'asset_id' => $this->machine->id,
                'problem_description' => 'Needle bar seized',
            ])
            ->json('data.id');

        // Repair cannot start on something nobody has acknowledged. The code
        // says the record is in the wrong state, not that a field is wrong.
        $this->withToken($this->token)
            ->postJson('/api/v1/breakdowns/'.$id.'/complete-repair')
            ->assertStatus(409);
    }

    public function test_a_machine_client_cannot_take_a_step_recorded_against_a_person(): void
    {
        $id = $this->withToken($this->token)
            ->postJson('/api/v1/breakdowns', [
                'asset_id' => $this->machine->id,
                'problem_description' => 'Needle bar seized',
            ])
            ->json('data.id');

        $token = $this->machineToken(['breakdown.breakdown.acknowledge', 'breakdown.breakdown.view']);

        // Acknowledgement is somebody taking responsibility. Writing down a
        // person who did not is worse than refusing.
        $this->withToken($token)
            ->postJson('/api/v1/breakdowns/'.$id.'/acknowledge')
            ->assertForbidden();
    }

    // -- Spare parts --------------------------------------------------------

    public function test_stock_reports_what_is_available_not_only_what_is_there(): void
    {
        $bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $part = InventoryFixture::part($this->delta);

        app(InventoryLedger::class)
            ->post($part, $bin, 'RECEIPT', '20', '50');

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/spare-parts/'.$part->id.'/stock')
            ->assertOk()
            ->assertJsonPath('data.total_on_hand', '20.0000');

        // Reserved stock is present and already promised. A purchasing system
        // reading on-hand alone will decline to order a part the store cannot
        // actually give it.
        $this->assertArrayHasKey('available', $response->json('data.locations.0'));
        $this->assertArrayHasKey('reserved', $response->json('data.locations.0'));
    }

    // -- Helpers ------------------------------------------------------------

    private function meter(): AssetMeter
    {
        TenantFixture::actingAsTenant($this->delta);

        $type = MeterType::where('code', 'RUNNING_HOURS')->first()
            ?? MeterType::whereNull('company_id')->firstOrFail();

        return app(ManageAssetMeter::class)->attach($this->machine, $type->id, '1000');
    }

    private function tokenFor(string $email): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'correct-horse-battery',
        ])->json('data.access_token');
    }

    /**
     * @param  list<string>  $scopes
     */
    private function machineToken(array $scopes): string
    {
        $secret = 'sk_'.str_repeat('b', 48);

        TenantFixture::actingAsTenant($this->delta);

        $client = ApiClient::create([
            'company_id' => $this->delta->id,
            'name' => 'Dye house controller',
            'client_id' => ApiClient::mintClientId(),
            'secret_hash' => Hash::make($secret),
            'scopes_json' => $scopes,
            'status' => 'ACTIVE',
        ]);

        return $this->postJson('/api/v1/auth/token', [
            'client_id' => $client->client_id,
            'client_secret' => $secret,
        ])->json('data.access_token');
    }
}
