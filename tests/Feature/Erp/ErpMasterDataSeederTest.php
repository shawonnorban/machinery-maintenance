<?php

declare(strict_types=1);

namespace Tests\Feature\Erp;

use App\Modules\Erp\Support\ResourceRegistry;
use App\Modules\HrPayroll\Support\HrDefaults;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ErpMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * erp:seed-masters: every built module's sample masters go in through the
 * screens' own rules, as the company's owner; a second run adds nothing;
 * a company without the setup a module needs (no floors) keeps the rest and
 * gets the reason; and the sample UOMs are the UOM master's codes.
 */
class ErpMasterDataSeederTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        // Enough people for the operator, inspector and posting rows, which each want a different user.
        foreach (['store', 'manager', 'admin', 'merch', 'qc', 'knit'] as $i => $who) {
            TenantFixture::user($this->delta, $i % 2 ? 'FACTORY_MANAGER' : 'STORE_MANAGER', "{$who}@delta.test");
        }
        foreach (['A' => 'Building A — Knitting and Garments', 'B' => 'Building B — Dyeing and Finishing'] as $code => $name) {
            $building = $this->row('buildings', ['factory_id' => $factory->id, 'name' => $name, 'code' => $code, 'status' => 'ACTIVE']);
            foreach (['GF' => 'Ground Floor', '1F' => '1st Floor'] as $suffix => $floor) {
                $this->row('floors', ['building_id' => $building, 'name' => $floor, 'code' => "{$code}-{$suffix}", 'status' => 'ACTIVE']);
            }
        }
        // Shifts live in HR & Payroll (hr_shifts) since the old Calendar module went: HR's own
        // Day and Night, which the planning lines and the knitting plan take.
        app(TenantContext::class)->set($this->delta->id);
        HrDefaults::ensure($this->delta->id);
        app(TenantContext::class)->forget();
    }

    public function test_seeds_every_module_through_the_screens_once_and_reports_what_a_company_lacks(): void
    {
        $seeder = new ErpMasterDataSeeder;
        $seeder->companies = ['DAL'];
        $seeder->run();

        $this->assertSame([], $seeder->skipped['DAL']);
        $created = $seeder->report['DAL'];
        // Buyers: the seven sample ones plus Norban's list that Pre-Budget's masters bring in (PreBudgetMasterSeed).
        $buyers = DB::table('buyers')->where('company_id', $this->delta->id)->count();
        $this->assertGreaterThan(7, $buyers);
        $this->assertEquals(
            ['buyers' => $buyers, 'colours' => 20, 'sizes' => 20, 'material-suppliers' => 8, 'planning-lines' => 8, 'dye-machines' => 8, 'knit-machines' => 8, 'fin-machines' => 8, 'hr-holidays' => 9],
            array_intersect_key($created, array_flip(['buyers', 'colours', 'sizes', 'material-suppliers', 'planning-lines', 'dye-machines', 'knit-machines', 'fin-machines', 'hr-holidays'])),
        );
        $this->assertGreaterThan(500, array_sum($created));

        app(TenantContext::class)->set($this->delta->id);
        $screens = app(ResourceRegistry::class);
        // A buyer keeps both its offices (afterSave ran), a composition its described parts (beforeSave ran), names upper-cased as the screen stores them.
        $lpp = DB::table('buyers')->where('company_id', $this->delta->id)->where('code', 'LPP')->first();
        $this->assertSame(2, DB::table('buyer_office_links')->where('buyer_id', $lpp->id)->count());
        $this->assertSame('60% Cotton 40% Polyester', DB::table('fiber_compositions')->where('company_id', $this->delta->id)->where('code', 'CVC6040')->value('description'));
        $this->assertTrue($screens->find('buyer-offices')->query()->where('name', 'LPP BANGLADESH LIAISON OFFICE')->exists());
        // Fabric and trim items carry the UOM master's codes, so the UOM pickers find them.
        $uoms = UnitOfMeasure::query()->whereNull('company_id')->pluck('code')->all();
        $this->assertSame([], array_values(array_diff(DB::table('mp_items')->where('company_id', $this->delta->id)->distinct()->pluck('uom')->all(), $uoms)));
        // The T&A templates were approved by the owner, as if keyed in.
        $owner = User::query()->where('email', 'owner@delta.test')->value('id');
        $this->assertSame(5, DB::table('tna_templates')->where('company_id', $this->delta->id)->where('approved_by', $owner)->count());

        // Run again: nothing new, nothing turned away.
        $again = new ErpMasterDataSeeder;
        $again->companies = ['DAL'];
        $again->run();
        $this->assertSame([], $again->report['DAL']);
        $this->assertSame([], $again->skipped['DAL']);

        // A company with no buildings or floors: the floor-bound modules stop with the reason, the rest seed.
        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::factory($omega, 'Narayanganj Unit', 'NGJ');
        TenantFixture::user($omega, 'COMPANY_OWNER', 'owner@omega.test');
        $this->artisan('erp:seed-masters', ['companies' => ['OTL']])
            ->expectsOutputToContain('OTL:')
            ->expectsOutputToContain('PlanningMasterSeed stopped: Seed reference not found: floor Building A/Ground Floor')
            ->assertSuccessful();
        $this->assertSame($buyers, DB::table('buyers')->where('company_id', $omega->id)->count());
        $this->assertSame(0, DB::table('planning_lines')->where('company_id', $omega->id)->count());
    }

    private function row(string $table, array $values): string
    {
        $id = strtolower((string) Str::ulid());
        DB::table($table)->insert(['id' => $id, 'company_id' => $values['company_id'] ?? $this->delta->id, 'created_at' => now(), 'updated_at' => now()] + $values);

        return $id;
    }
}
