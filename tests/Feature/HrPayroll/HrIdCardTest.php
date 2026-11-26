<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * HR & Payroll → Employee → ID Card (AnnotechERP 24/03): the print queue —
 * one employee or everyone the Search boxes pick, each queued once (queuing
 * again changes the option), the cards read from the profile at print time,
 * a card removed or the queue cleared — and who may.
 */
class HrIdCardTest extends TestCase
{
    use RefreshDatabase;

    private const C = '/api/v1/hr-payroll/id-cards';

    private Company $delta;

    private User $owner;

    private string $sewing;

    private string $cutting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $row = fn (string $table, array $data): string => tap(strtolower((string) Str::ulid()), fn (string $id) => DB::table($table)->insert(['id' => $id, 'company_id' => $this->delta->id, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()] + $data));
        $dept = $row('departments', ['factory_id' => $factory->id, 'name' => 'Sewing', 'code' => 'SEW']);
        $this->sewing = $row('sections', ['department_id' => $dept, 'name' => 'Sewing', 'code' => 'S1']);
        $this->cutting = $row('sections', ['department_id' => $dept, 'name' => 'Cutting', 'code' => 'S2']);
        $designation = $row('designations', ['department_id' => $dept, 'name' => 'Jr. Operator', 'code' => 'JOP']);

        $worker = collect($this->as()->getJson('/api/v1/hr-payroll/profiles/options')->json('data.employee_types'))->firstWhere('label', 'Worker')['value'];
        foreach ([['50001001', 'Md. Ripon', $this->sewing, 'ACTIVE', '2026-10-01'], ['50001002', 'Asia Begum', $this->sewing, 'ACTIVE', '2026-10-05'], ['50001003', 'Karim Mia', $this->cutting, 'ACTIVE', '2026-10-01'], ['50001004', 'Old Hand', $this->sewing, 'SEPARATED', '2020-01-01']] as [$card, $name, $section, $status, $joined]) {
            $this->as()->postJson('/api/v1/hr-payroll/profiles', [
                'card_no' => $card, 'factory_id' => $factory->id, 'name' => $name, 'name_bn' => $name === 'Md. Ripon' ? 'মোঃ রিপন' : null, 'nationality' => 'Bangladesh', 'religion' => 'ISLAM', 'gender' => 'MALE',
                'employment_type_id' => $worker, 'department_id' => $dept, 'section_id' => $section, 'designation_id' => $designation, 'joining_date' => $joined, 'gross_salary' => 14000, 'status' => $status, 'blood_group' => 'B_POS',
            ])->assertCreated();
        }
    }

    public function test_the_queue_is_filled_one_by_one_or_by_search_and_printed_from_the_profile(): void
    {
        // Add to print queue: one employee; the form asks for both boxes.
        $this->as()->postJson(self::C, [])->assertUnprocessable()->assertJsonPath('errors.employee.0', 'Employee Code is required.')->assertJsonPath('errors.font.0', 'Print Option is required.');
        $this->as()->postJson(self::C, ['employee' => 'EMP-000001', 'font' => 'MTEX_ENGLISH', 'single_per_page' => true])->assertOk();

        // Add: everyone the boxes pick — active sewing — with the option chosen there; EMP-000001 is changed, not doubled.
        $this->as()->postJson(self::C.'/add', ['status' => 'ACTIVE', 'section' => $this->sewing, 'font' => 'BANGLA'])->assertOk()->assertJsonPath('data.queued', 2);
        $this->as()->postJson(self::C.'/add', ['status' => 'ACTIVE', 'code' => 'nobody', 'font' => 'BANGLA'])->assertUnprocessable()->assertJsonPath('errors.employee.0', __('hrpayroll.emp.idc.v.none'));
        $queue = collect($this->as()->getJson(self::C)->assertOk()->json('data'))->keyBy('code');
        $this->assertSame(['EMP-000001', 'EMP-000002'], $queue->keys()->sort()->values()->all());
        $this->assertEquals(['BANGLA', false, false, 'Sewing', 'Jr. Operator'], [$queue['EMP-000001']['font'], $queue['EMP-000001']['single'], $queue['EMP-000001']['photo'], $queue['EMP-000001']['section'], $queue['EMP-000001']['designation']]);

        // Search narrows the queue; joining date picks a day.
        $this->as()->postJson(self::C.'/add', ['joining_date' => '2026-10-01', 'font' => 'ENGLISH', 'single_per_page' => true])->assertOk()->assertJsonPath('data.queued', 2);
        $this->assertSame(['EMP-000003'], array_column($this->as()->getJson(self::C.'?section='.$this->cutting)->json('data'), 'code'));
        $this->assertCount(3, $this->as()->getJson(self::C)->json('data'));

        // Print: the cards as the profiles stand now — a renamed section shows at once.
        DB::table('sections')->where('id', $this->sewing)->update(['name' => 'Sewing Line A']);
        $print = $this->as()->getJson(self::C.'/print')->assertOk()->json('data');
        $this->assertSame('Delta Apparels Ltd', $print['company']);
        $cards = collect($print['cards'])->keyBy('code');
        $this->assertEquals(['ENGLISH', true, 'Sewing Line A', 'Sewing', 'মোঃ রিপন', '50001001', 'B_POS', '2026-10-01'], [$cards['EMP-000001']['font'], $cards['EMP-000001']['single'], $cards['EMP-000001']['section'], $cards['EMP-000001']['department'], $cards['EMP-000001']['name_bn'], $cards['EMP-000001']['card_no'], $cards['EMP-000001']['blood_group'], $cards['EMP-000001']['joining_date']]);
        $one = $cards['EMP-000002']['id'];
        $this->assertSame(['EMP-000002'], array_column($this->as()->getJson(self::C.'/print?ids='.$one)->json('data.cards'), 'code'));

        // Edit, remove, clear.
        $this->as()->putJson(self::C."/{$one}", ['font' => 'MTEX_ENGLISH', 'single_per_page' => true])->assertOk();
        $this->assertSame('MTEX_ENGLISH', collect($this->as()->getJson(self::C)->json('data'))->firstWhere('code', 'EMP-000002')['font']);
        $this->as()->deleteJson(self::C."/{$one}")->assertOk();
        $this->assertCount(2, $this->as()->getJson(self::C)->json('data'));
        $this->assertSame(2, $this->as()->deleteJson(self::C)->assertOk()->json('data.deleted'));
        $this->assertSame([], $this->as()->getJson(self::C)->json('data'));

        // Who may: a factory manager reads and prints, but does not queue; a storekeeper sees nothing.
        $fm = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $this->as($fm)->getJson(self::C)->assertOk();
        $this->as($fm)->postJson(self::C, ['employee' => 'EMP-000001', 'font' => 'BANGLA'])->assertForbidden();
        $this->as(TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test'))->getJson(self::C)->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
