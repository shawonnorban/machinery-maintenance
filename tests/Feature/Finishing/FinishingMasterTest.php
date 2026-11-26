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
use Illuminate\Http\UploadedFile;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Finishing Line → Master Data (AnnotechERP 18/01): process types under their
 * groups and sub-groups, finishing machines on Dye House's brand and location
 * lists with a key image, machine-process mappings and machine targets — and
 * who may change them.
 */
class FinishingMasterTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private Floor $floor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => Factory::query()->first()->id, 'name' => 'Finishing', 'code' => 'B1']);
        $this->floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Finishing', 'code' => 'FN-1']);
    }

    public function test_process_types_start_with_bricklands_and_sit_under_their_group(): void
    {
        $types = collect($this->as()->getJson('/api/v1/erp/fin-process-types')->assertOk()->json('data'));
        $this->assertSame(['DF-HS', 'DF-DW', 'DF-DRY', 'DF-ST', 'DF-CMP', 'DF-CAL', 'DF-BR', 'DF-PCH'], $types->pluck('code')->all());
        $this->assertSame('Heat-Set', $types->firstWhere('code', 'DF-DW')['link_process']);
        $this->assertSame('Wet Finishing', $types->firstWhere('code', 'DF-DW')['subgroup']);

        $groups = collect($this->as()->getJson('/api/v1/finishing/groups')->json('data'));
        $this->assertSame(['Knitting', 'Yarn Dyeing', 'Dyeing', 'Dyeing Finishing', 'AOP', 'Yarn Twist'], $groups->pluck('label')->all());
        $df = $groups->firstWhere('label', 'Dyeing Finishing');
        $this->assertSame([8, 3], [$df['processes'], $df['count']]);
        $knitting = $groups->firstWhere('label', 'Knitting')['key'];
        $wet = collect($this->as()->getJson("/api/v1/finishing/groups/{$df['key']}")->json('data'))->firstWhere('code', 'DF-WET');

        $type = ['code' => 'df.sl', 'name' => 'Slitting', 'short_name' => 'SL', 'wip_stage' => 'DRYING', 'rate' => 4, 'loss_pct' => 0.5, 'display_order' => 9];
        $this->as()->postJson('/api/v1/erp/fin-process-types', $type)->assertUnprocessable()->assertJsonPath('errors.group_id.0', 'Fabric Process Group is required.');
        $this->as()->postJson('/api/v1/erp/fin-process-types', $type + ['group_id' => $knitting, 'subgroup_id' => $wet['id']])->assertUnprocessable()->assertJsonValidationErrors('subgroup_id');
        $this->as()->postJson('/api/v1/erp/fin-process-types', ['code' => 'DF SL'] + $type + ['group_id' => $df['key']])->assertUnprocessable()->assertJsonValidationErrors('code');
        $id = $this->as()->postJson('/api/v1/erp/fin-process-types', $type + ['group_id' => $df['key'], 'subgroup_id' => $wet['id']])->assertCreated()->assertJsonPath('data.code', 'DF.SL')->json('data.id');
        $this->as()->patchJson("/api/v1/erp/fin-process-types/{$id}", ['link_process_id' => $id])->assertUnprocessable()->assertJsonPath('errors.link_process_id.0', 'A process cannot link to itself.');
        // Brickland removes a process type that nothing uses.
        $this->as()->deleteJson("/api/v1/erp/fin-process-types/{$id}")->assertNoContent();

        // Sub-groups save as a whole; one with processes under it stays.
        $rows = collect($this->as()->getJson("/api/v1/finishing/groups/{$df['key']}")->json('data'))->map(fn ($s) => ['id' => $s['id'], 'code' => $s['code'], 'name' => $s['name'], 'sort_order' => $s['sort_order']])->all();
        $this->as()->putJson("/api/v1/finishing/groups/{$df['key']}", ['rows' => array_slice($rows, 1)])->assertUnprocessable()->assertJsonValidationErrors('rows');
        $this->as()->putJson("/api/v1/finishing/groups/{$df['key']}", ['rows' => [...$rows, ['code' => 'df-chem', 'name' => 'Chemical Finishing', 'sort_order' => 4]]])->assertOk()->assertJsonCount(4, 'data')->assertJsonPath('data.3.code', 'DF-CHEM');
        $this->as()->postJson('/api/v1/finishing/groups', ['code' => 'PRINT', 'name' => 'Printing'])->assertCreated();
        $this->as()->getJson('/api/v1/finishing/groups')->assertJsonCount(7, 'data');
    }

    public function test_machines_mappings_and_targets(): void
    {
        $look = $this->as()->getJson('/api/v1/finishing/lookups')->assertOk()->json('data');
        $this->assertSame(['Fongs', 'PMM Moon star', 'Thies', 'Sclavos', 'Dilmenler'], array_column($look['brands'], 'label'));
        $this->assertSame(['Dyeing Shed-1', 'Dyeing Shed-2', 'Sample House', 'Finishing Bay'], array_column($look['locations'], 'label'));
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '276', 'name' => 'Germany'])->json('data.id');

        $machine = ['machine_no' => 'MFL-01', 'name' => 'Stenter-1 (Bruckner, MFL)', 'origin_country_id' => $country, 'brand_id' => $look['brands'][2]['value'], 'mou' => 'KG', 'status' => 'RUNNING', 'floor_id' => $this->floor->id, 'capacity' => 1200, 'efficiency' => 85];
        $this->as()->postJson('/api/v1/erp/fin-machines', $machine)->assertUnprocessable()->assertJsonPath('errors.machine_type.0', 'Machine Type is required.');
        $this->as()->postJson('/api/v1/erp/fin-machines', ['machine_type' => 'STENTER', 'efficiency' => 120] + $machine)->assertUnprocessable()->assertJsonValidationErrors('efficiency');
        $mc = $this->as()->postJson('/api/v1/erp/fin-machines', ['machine_type' => 'STENTER', 'location_id' => $look['locations'][3]['value']] + $machine)->assertCreated()
            ->assertJsonPath('data.brand', 'Thies')->assertJsonPath('data.location', 'Finishing Bay')->assertJsonPath('data.country', 'Germany')->json('data.id');
        $this->as()->postJson('/api/v1/erp/fin-machines', ['machine_type' => 'DRYER', 'machine_no' => 'mfl-01'] + $machine)->assertUnprocessable()->assertJsonValidationErrors('machine_no');

        $upload = $this->as()->post('/api/v1/finishing/key-images', ['file' => $this->png('nameplate.png')], ['Accept' => 'application/json'])->assertCreated()->json('data.path');
        $this->assertStringStartsWith('/api/files/', $upload);
        $this->as()->patchJson("/api/v1/erp/fin-machines/{$mc}", ['key_image' => $upload])->assertOk()->assertJsonPath('data.key_image', $upload);

        $processes = collect($this->as()->getJson('/api/v1/erp/fin-process-types')->json('data'));
        $st = $processes->firstWhere('code', 'DF-ST')['id'];
        $map = ['dia_type' => 'OPEN', 'process_id' => $st, 'sequence' => 1, 'batch_status' => 'AWAITING_LOAD', 'next_status' => 'STENTERING', 'is_default' => true];
        $this->as()->postJson('/api/v1/erp/fin-machine-processes', $map)->assertUnprocessable()->assertJsonPath('errors.machine_id.0', 'Machine is required.');
        $this->as()->postJson('/api/v1/erp/fin-machine-processes', ['machine_id' => $mc, 'next_status' => 'AWAITING_LOAD'] + $map)->assertUnprocessable()->assertJsonPath('errors.next_status.0', 'Batch Status and Next Status must differ.');
        $this->as()->postJson('/api/v1/erp/fin-machine-processes', ['machine_id' => $mc] + $map)->assertCreated()->assertJsonPath('data.process', 'Stentering (ST)')->assertJsonPath('data.machine_no', 'MFL-01');
        $this->as()->postJson('/api/v1/erp/fin-machine-processes', ['machine_id' => $mc] + $map)->assertUnprocessable()->assertJsonValidationErrors('process_id');
        $this->as()->postJson('/api/v1/erp/fin-machine-processes', ['machine_id' => $mc, 'dia_type' => 'TUBE'] + $map)->assertCreated();

        $target = ['machine_id' => $mc, 'dia_type' => 'OPEN', 'effective_from' => '2026-10-01', 'target_qty' => 4800, 'shift_hours' => 8];
        $this->as()->postJson('/api/v1/erp/fin-machine-targets', array_diff_key($target, ['machine_id' => 1]))->assertUnprocessable()->assertJsonPath('errors.machine_id.0', 'Machine is required.');
        $this->as()->postJson('/api/v1/erp/fin-machine-targets', $target)->assertCreated()->assertJsonPath('data.per_hour', 600)->assertJsonPath('data.process', null);
        $this->as()->postJson('/api/v1/erp/fin-machine-targets', $target)->assertUnprocessable()->assertJsonValidationErrors('effective_from');
        $this->as()->postJson('/api/v1/erp/fin-machine-targets', ['process_id' => $st] + $target)->assertCreated();
        $this->as()->postJson('/api/v1/erp/fin-machine-targets', ['effective_from' => '2027-01-01', 'target_qty' => 5000] + $target)->assertCreated();
    }

    public function test_who_may_change_the_finishing_line_masters(): void
    {
        $manager = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'manager@delta.test'), $this->delta->id, 'Test token')['plain'];
        $df = collect($this->withToken($manager)->getJson('/api/v1/finishing/groups')->assertOk()->json('data'))->firstWhere('label', 'Dyeing Finishing')['key'];
        $this->withToken($manager)->postJson('/api/v1/erp/fin-process-types', ['code' => 'DF-SL', 'name' => 'Slitting', 'short_name' => 'SL', 'group_id' => $df, 'wip_stage' => 'DRYING', 'rate' => 4, 'loss_pct' => 0.5, 'display_order' => 9])->assertCreated();

        $chief = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($chief)->getJson('/api/v1/erp/fin-machines')->assertOk();
        $this->withToken($chief)->post('/api/v1/finishing/key-images', ['file' => $this->png('x.png')], ['Accept' => 'application/json'])->assertForbidden();

        $store = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($store)->getJson('/api/v1/erp/fin-process-types')->assertOk(); // the stores read the masters (roles by department)
        $this->withToken($store)->postJson('/api/v1/erp/fin-process-types', ['code' => 'X', 'name' => 'X'])->assertForbidden();
    }

    /** A real one-pixel PNG: `fake()->image()` needs GD with PNG encoding, which the container lacks. */
    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
