<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\HrPayroll\Support\FactoryCalendarSetup;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Master Data (AnnotechERP 15/01): the machine reference
 * lists saved as a whole, machines and the gauges they support, capacity
 * bands and operator postings that never overlap, yarn counts, label
 * printers, users' office mapping, Sub-Contract Party Rate shared with Dye
 * House — and who may change them.
 */
class KnittingMasterTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private Floor $floor;

    private Factory $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->unit = Factory::query()->first();
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->unit->id, 'name' => 'Knit', 'code' => 'B1']);
        $this->floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Knit Floor', 'code' => 'KF-1']);
    }

    public function test_reference_lists_start_with_bricklands_entries_and_save_as_a_whole(): void
    {
        $lists = collect($this->as()->getJson('/api/v1/knitting/ref-lists')->assertOk()->json('data'));
        $this->assertSame([6, 6, 6, 6], $lists->pluck('count')->all());
        $types = collect($this->as()->getJson('/api/v1/knitting/ref-lists/MC_TYPE')->json('data'));
        $this->assertSame(['SJ', 'RIB', 'INTL', 'FLEECE', 'JCQ', 'FLAT'], $types->pluck('code')->all());

        $rows = $types->map(fn ($t) => ['id' => $t['id'], 'code' => $t['code'], 'name' => $t['name'], 'sort_order' => $t['sort_order'], 'active' => true])->all();
        $this->as()->putJson('/api/v1/knitting/ref-lists/MC_TYPE', ['rows' => [...$rows, ['code' => 'sj', 'name' => 'Duplicate']]])->assertUnprocessable()->assertJsonValidationErrors('rows');
        $rows[0]['name'] = 'Single Jersey (Open Width)';
        $this->as()->putJson('/api/v1/knitting/ref-lists/MC_TYPE', ['rows' => [...array_slice($rows, 0, 5), ['code' => 'tuck', 'name' => 'Tuck Stitch', 'sort_order' => 7]]])->assertOk()
            ->assertJsonCount(6, 'data')->assertJsonPath('data.0.name', 'Single Jersey (Open Width)')->assertJsonPath('data.5.code', 'TUCK');

        // An entry a machine points at cannot be removed.
        $machine = $this->machine();
        $sj = $this->ref('MC_TYPE', 'SJ');
        $left = collect($this->as()->getJson('/api/v1/knitting/ref-lists/MC_TYPE')->json('data'))->reject(fn ($e) => $e['id'] === $sj)->map(fn ($e) => ['id' => $e['id'], 'code' => $e['code'], 'name' => $e['name']])->values()->all();
        $this->as()->putJson('/api/v1/knitting/ref-lists/MC_TYPE', ['rows' => $left])->assertUnprocessable()->assertJsonPath('errors.rows.0', 'An entry in use cannot be deleted — clear Active? instead.');
        $this->as()->getJson('/api/v1/knitting/ref-lists/GG')->assertJsonPath('data.0.machines', 0)->assertJsonPath('data.1.machines', 1)->assertJsonPath('data.2.machines', 0)->assertJsonPath('data.3.machines', 1);
        $this->assertNotNull($machine);
    }

    public function test_machines_run_a_gauge_they_support(): void
    {
        $base = $this->machineData();
        $this->as()->postJson('/api/v1/erp/knit-machines', ['machine_no' => null] + $base)->assertUnprocessable()->assertJsonPath('errors.machine_no.0', 'Machine# is required.');
        $this->as()->postJson('/api/v1/erp/knit-machines', ['supported_gauge_ids' => $this->ref('GG', 'GG18')] + $base)->assertUnprocessable()->assertJsonPath('errors.gauge_id.0', 'The gauge must be one of the supported gauges.');
        $this->as()->postJson('/api/v1/erp/knit-machines', $base)->assertCreated()
            ->assertJsonPath('data.machine_type', 'Single Jersey')->assertJsonPath('data.gauge', '24GG')->assertJsonPath('data.supported', '18GG, 24GG')->assertJsonPath('data.floor', 'Knit Floor — FAC01');
        $this->as()->postJson('/api/v1/erp/knit-machines', ['machine_no' => 'mc-01'] + $base)->assertUnprocessable()->assertJsonValidationErrors('machine_no');
        $id = $this->as()->getJson('/api/v1/erp/knit-machines')->json('data.0.id');
        $this->as()->deleteJson("/api/v1/erp/knit-machines/{$id}")->assertStatus(405);
    }

    public function test_capacity_bands_and_postings_never_overlap(): void
    {
        $machine = $this->machine();
        $band = ['machine_id' => $machine, 'effective_from' => '2026-10-01', 'plan_hours' => 22, 'running_days' => 26, 'efficiency' => 85, 'capacity_per_hour' => 8.5];
        $this->as()->postJson('/api/v1/erp/knit-capacity-plans', array_diff_key($band, ['effective_from' => 1]))->assertUnprocessable()->assertJsonPath('errors.effective_from.0', 'Effective From is required.');
        $this->as()->postJson('/api/v1/erp/knit-capacity-plans', ['effective_to' => '2026-12-31'] + $band)->assertCreated()->assertJsonPath('data.plan_qty_month', 4132.7)->assertJsonPath('data.machine_no', 'MC-01');
        $this->as()->postJson('/api/v1/erp/knit-capacity-plans', ['effective_from' => '2026-12-31'] + $band)->assertUnprocessable()->assertJsonValidationErrors('effective_from');
        $this->as()->postJson('/api/v1/erp/knit-capacity-plans', ['effective_from' => '2027-01-01'] + $band)->assertCreated();

        $shift = FactoryCalendarSetup::shift($this->delta->id, 'GS001', 'General', '08:00', '17:00');
        $operator = TenantFixture::user($this->delta, 'LINE_CHIEF', 'operator@delta.test');
        $posting = ['machine_id' => $machine, 'user_id' => $operator->id, 'shift_id' => $shift];
        $this->as()->postJson('/api/v1/erp/knit-operator-postings', $posting)->assertUnprocessable()->assertJsonPath('errors.effective_at.0', 'Effective is required.');
        $first = $this->as()->postJson('/api/v1/erp/knit-operator-postings', $posting + ['effective_at' => '2026-10-01T06:00'])->assertCreated()->assertJsonPath('data.standing', true)->json('data.id');
        $this->as()->postJson('/api/v1/erp/knit-operator-postings', $posting + ['effective_at' => '2026-10-05T06:00'])->assertUnprocessable()->assertJsonValidationErrors('effective_at');

        // Machine wise: close the standing posting by dating it, then the next one fits.
        $this->as()->getJson('/api/v1/knitting/posting-machines')->assertJsonPath('data.0.count', 1);
        $this->as()->putJson("/api/v1/knitting/posting-machines/{$machine}", ['rows' => [['id' => $first, 'until_at' => '2026-09-30T06:00']]])->assertUnprocessable();
        $this->as()->putJson("/api/v1/knitting/posting-machines/{$machine}", ['rows' => [['id' => $first, 'until_at' => '2026-10-04T22:40']]])->assertOk()->assertJsonPath('data.0.until_at', '2026-10-04T22:40');
        $second = $this->as()->postJson('/api/v1/erp/knit-operator-postings', $posting + ['effective_at' => '2026-10-05T06:00'])->assertCreated()->json('data.id');
        $this->as()->putJson("/api/v1/knitting/posting-machines/{$machine}", ['rows' => [['id' => $second, 'until_at' => null]]])->assertOk()->assertJsonCount(1, 'data');
        $this->as()->getJson('/api/v1/knitting/posting-machines')->assertJsonPath('data.0.count', 1);
    }

    public function test_yarn_counts_printers_and_office_mapping(): void
    {
        $counts = $this->as()->getJson('/api/v1/erp/knit-yarn-counts')->assertOk()->json('data');
        $this->assertCount(25, $counts);
        $this->assertSame(['50D', '20/2'], [$counts[0]['count_no'], $counts[23]['count_no']]);
        $this->assertFalse($counts[23]['is_single']);
        $this->as()->postJson('/api/v1/erp/knit-yarn-counts', ['count_system' => 'Ne', 'description' => '36/1'])->assertUnprocessable()->assertJsonPath('errors.count_no.0', 'Yarn Count No is required.');
        $this->as()->postJson('/api/v1/erp/knit-yarn-counts', ['count_no' => '30/S', 'count_system' => 'Ne', 'description' => '30/s'])->assertUnprocessable()->assertJsonValidationErrors('count_no');
        $this->as()->patchJson("/api/v1/erp/knit-yarn-counts/{$counts[0]['id']}", ['active' => false])->assertOk()->assertJsonPath('data.active', false);
        $this->as()->deleteJson("/api/v1/erp/knit-yarn-counts/{$counts[0]['id']}")->assertNoContent();

        $this->as()->postJson('/api/v1/erp/knit-label-printers', ['name' => 'MFL-SOUTH', 'address' => 'Printer1', 'user_ids' => $this->owner->id.',nope'])->assertUnprocessable()->assertJsonValidationErrors('user_ids');
        $this->as()->postJson('/api/v1/erp/knit-label-printers', ['name' => 'MFL-SOUTH', 'address' => 'Printer1', 'user_ids' => $this->owner->id, 'auto_scale' => true])->assertCreated()
            ->assertJsonPath('data.users', $this->owner->name)->assertJsonPath('data.user_ids', $this->owner->id);

        $users = collect($this->as()->getJson('/api/v1/knitting/office-users')->assertOk()->json('data'));
        $this->assertSame(0, $users->firstWhere('key', $this->owner->id)['count']);
        $offices = $this->as()->getJson("/api/v1/knitting/office-users/{$this->owner->id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.selected', false)->json('data');
        $this->as()->putJson("/api/v1/knitting/office-users/{$this->owner->id}", ['rows' => [['factory_id' => $offices[0]['factory_id'], 'selected' => true, 'is_primary' => true, 'can_write' => false]]])->assertOk()
            ->assertJsonPath('data.0.selected', true)->assertJsonPath('data.0.can_write', false);
        $this->as()->getJson('/api/v1/knitting/user-offices')->assertJsonCount(1, 'data')->assertJsonPath('data.0.office', 'Dhaka Unit 1')->assertJsonPath('data.0.is_primary', true);
    }

    public function test_who_may_change_the_knit_production_masters(): void
    {
        $manager = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'manager@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($manager)->postJson('/api/v1/erp/knit-yarn-counts', ['count_no' => '36/1', 'count_system' => 'Ne', 'description' => '36/1'])->assertCreated();

        // A line chief reads Knit Production — Sub-Contract Party Rate included, though it is Dye House's quotation — and changes nothing.
        $chief = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($chief)->getJson('/api/v1/knitting/lookups')->assertOk()->assertJsonStructure(['data' => ['machines', 'floors', 'shifts', 'users', 'countries', 'refs', 'suppliers', 'currencies']]);
        $this->withToken($chief)->getJson('/api/v1/dyehouse/sc-quotations')->assertOk();
        $this->withToken($chief)->postJson('/api/v1/erp/knit-yarn-counts', ['count_no' => '38/1', 'count_system' => 'Ne', 'description' => '38/1'])->assertForbidden();

        $store = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($store)->getJson('/api/v1/erp/knit-machines')->assertOk(); // the stores read the masters (roles by department)
        $this->withToken($store)->postJson('/api/v1/erp/knit-yarn-counts', ['count_no' => '40/1', 'count_system' => 'Ne', 'description' => '40/1'])->assertForbidden();
        $this->withToken($store)->getJson('/api/v1/dyehouse/sc-quotations')->assertOk(); // through Dye House, which the store manager reads
    }

    /** @return array<string, mixed> */
    private function machineData(): array
    {
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '276', 'name' => 'Germany'])->json('data.id')
            ?? collect($this->as()->getJson('/api/v1/erp/origin-countries')->json('data'))->firstWhere('name', 'Germany')['id'];

        return [
            'machine_no' => 'MC-01', 'machine_type_id' => $this->ref('MC_TYPE', 'SJ'), 'brand_id' => $this->ref('BRAND', 'MAYER'), 'origin_country_id' => $country,
            'dia_id' => $this->ref('DIA', 'D30'), 'gauge_id' => $this->ref('GG', 'GG24'), 'supported_gauge_ids' => $this->ref('GG', 'GG18').','.$this->ref('GG', 'GG24'),
            'capacity_kg' => 180, 'feeders' => 96, 'rpm' => 24, 'efficiency' => 85, 'floor_id' => $this->floor->id, 'mfg_year' => 2019,
        ];
    }

    private function machine(): string
    {
        return $this->as()->postJson('/api/v1/erp/knit-machines', $this->machineData())->assertCreated()->json('data.id');
    }

    private function ref(string $list, string $code): string
    {
        return collect($this->as()->getJson("/api/v1/knitting/ref-lists/{$list}")->json('data'))->firstWhere('code', $code)['id'];
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
