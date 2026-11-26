<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * HR & Payroll → Employee → Employee Profile (AnnotechERP 24/03): the full
 * record in seven tabs written in one save — the posting going through the
 * assignment history, the gross through the salary history, the pay lines
 * replaced, background checks keeping the day they were filed — its photo,
 * and an employee removed only while no other record uses them.
 */
class HrEmployeeProfileTest extends TestCase
{
    use RefreshDatabase;

    private const P = '/api/v1/hr-payroll/profiles';

    private Company $delta;

    private User $owner;

    /** @var array<string, string> */
    private array $place = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->travelTo(CarbonImmutable::parse('2026-11-10 06:00:00', 'UTC'));
        $row = fn (string $table, array $data): string => tap(strtolower((string) Str::ulid()), fn (string $id) => DB::table($table)->insert(['id' => $id, 'company_id' => $this->delta->id, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()] + $data));
        $building = $row('buildings', ['factory_id' => $factory->id, 'name' => 'Main Building', 'code' => 'B1']);
        $floor = $row('floors', ['building_id' => $building, 'name' => 'Sewing Floor', 'code' => 'F1']);
        $sewing = $row('departments', ['factory_id' => $factory->id, 'name' => 'Sewing', 'code' => 'SEW']);
        $store = $row('departments', ['factory_id' => $factory->id, 'name' => 'Store', 'code' => 'STR']);
        $section = $row('sections', ['department_id' => $sewing, 'name' => 'Sewing', 'code' => 'S1']);
        $this->place = [
            'factory_id' => $factory->id, 'building_id' => $building, 'floor_id' => $floor, 'department_id' => $sewing, 'section_id' => $section,
            'line_id' => $row('production_lines', ['department_id' => $sewing, 'section_id' => $section, 'floor_id' => $floor, 'name' => 'Line 1', 'code' => 'L1']),
            'designation_id' => $row('designations', ['department_id' => $sewing, 'name' => 'Jr. Operator', 'code' => 'JOP']),
            'store' => $store, 'store_designation' => $row('designations', ['department_id' => $store, 'name' => 'Receive Man', 'code' => 'RCV']),
        ];
    }

    public function test_the_profile_is_written_in_one_save_with_its_posting_lines_and_checks(): void
    {
        $opt = $this->as()->getJson(self::P.'/options')->assertOk()->json('data');
        $this->assertSame('EMP-000001', $opt['next_code']);
        $this->assertSame('Bangladesh', $opt['nationalities'][0]['value']);
        $worker = collect($opt['employee_types'])->firstWhere('label', 'Worker')['value'];
        [$e1, $e2] = array_column($opt['pay_elements'], 'value');

        // An empty form is refused tab by tab, in Brickland's words.
        $this->as()->postJson(self::P, [])->assertUnprocessable()
            ->assertJsonPath('errors.factory_id.0', 'Office is required.')->assertJsonPath('errors.name.0', 'Full Name [E] is required.')
            ->assertJsonPath('errors.employment_type_id.0', 'Employee Type is required.')->assertJsonPath('errors.status.0', 'Job Status is required.')
            ->assertJsonPath('errors.card_no.0', __('hrpayroll.emp.prof.v.card_required'))->assertJsonPath('errors.gross_salary.0', __('hrpayroll.emp.prof.v.gross_required'));

        $form = $this->form($worker, [['pay_element_id' => $e1, 'type' => 'ALLOWANCE', 'core_salary' => true, 'amount' => 500, 'active' => true, 'effect_salary' => true], ['pay_element_id' => $e2, 'type' => 'DEDUCTION', 'amount' => 120, 'active' => true, 'effect_salary' => true]]);
        // A posting needs its department and designation; an element is listed once.
        $this->as()->postJson(self::P, ['department_id' => null, 'designation_id' => null] + $form)->assertUnprocessable()->assertJsonPath('errors.department_id.0', 'Department is required.');
        $this->as()->postJson(self::P, ['pay_lines' => [$form['pay_lines'][0], $form['pay_lines'][0]]] + $form)->assertUnprocessable()->assertJsonValidationErrors(['pay_lines.1.pay_element_id']);
        $this->as()->postJson(self::P, ['nid' => '12345'] + $form)->assertUnprocessable()->assertJsonValidationErrors(['nid']);

        $saved = $this->as()->postJson(self::P, $form)->assertCreated()->json('data');
        $this->assertEquals(['EMP-000001', true, 'Worker', 'Worker', 'Sewing', 'Jr. Operator', 'Line 1', 14000.0], [$saved['code'], $saved['assigned'], $saved['employee_type'], $saved['business_type'], $saved['department'], $saved['designation'], $saved['line'], $saved['gross_salary']]);
        $this->assertEquals(['Bangladesh', 'ISLAM', 'রিপন', true, 'A1234567', '2026-01-05'], [$saved['nationality'], $saved['religion'], $saved['name_bn'], $saved['stay_with_family'], $saved['passport_no'], $saved['passport_issue_date']]);
        $this->assertEquals([2, 380.0], [count($saved['pay_lines']), $saved['net_lines']]);
        $this->assertEquals(['Police verification', '2026-11-10', false], [$saved['checks'][0]['check_type'], $saved['checks'][0]['verified_on'], $saved['checks'][0]['cleared']]);
        $history = $this->as()->getJson('/api/v1/hr-payroll/employees/EMP-000001/history')->json('data');
        $this->assertEquals([['2026-10-01', null]], array_map(fn ($a) => [$a['from'], $a['to']], $history['assignments']));
        $this->as()->postJson(self::P, ['name' => 'Someone Else'] + $form)->assertUnprocessable()->assertJsonPath('errors.card_no.0', 'Card / Proxi No. 50001001 is already used by EMP-000001.');

        // Records: the Search panel narrows by department, section, status and part of a code.
        $this->assertSame(['EMP-000001'], array_column($this->as()->getJson(self::P.'?department='.$this->place['department_id'])->json('data'), 'code'));
        $this->assertSame([], $this->as()->getJson(self::P.'?department='.$this->place['store'])->json('data'));
        $this->assertSame(['EMP-000001'], array_column($this->as()->getJson(self::P.'?code=0001&status=ACTIVE')->json('data'), 'code'));

        // Who may: a factory manager reads, a storekeeper does not.
        $this->as(TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test'))->postJson(self::P, $form)->assertForbidden();
        $this->as(TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test'))->getJson(self::P)->assertForbidden();
    }

    public function test_edit_keeps_the_history_the_photo_and_removal(): void
    {
        Storage::fake('local');
        $opt = $this->as()->getJson(self::P.'/options')->json('data');
        $worker = collect($opt['employee_types'])->firstWhere('label', 'Worker')['value'];
        $e1 = $opt['pay_elements'][0]['value'];
        $form = $this->form($worker, [['pay_element_id' => $e1, 'type' => 'ALLOWANCE', 'amount' => 500]]);
        $saved = $this->as()->postJson(self::P, $form)->assertCreated()->json('data');

        // A week later: transferred to the store on the profile, a raise, the check cleared, the pay line gone.
        $this->travelTo(CarbonImmutable::parse('2026-11-17 06:00:00', 'UTC'));
        $edit = ['department_id' => $this->place['store'], 'designation_id' => $this->place['store_designation'], 'section_id' => null, 'line_id' => null, 'gross_salary' => 15500, 'pay_lines' => [],
            'checks' => [['id' => $saved['checks'][0]['id'], 'check_type' => 'Police verification', 'verified_by' => 'SI Karim', 'cleared' => true], ['check_type' => 'Previous employer']]] + $form;
        $after = $this->as()->putJson(self::P.'/EMP-000001', $edit)->assertOk()->json('data');
        $this->assertEquals(['Store', 'Receive Man', 15500.0, 0], [$after['department'], $after['designation'], $after['gross_salary'], count($after['pay_lines'])]);
        $this->assertEquals([['Police verification', '2026-11-10', true, 'SI Karim'], ['Previous employer', '2026-11-17', false, null]], array_map(fn ($c) => [$c['check_type'], $c['verified_on'], $c['cleared'], $c['verified_by']], $after['checks']));
        $history = $this->as()->getJson('/api/v1/hr-payroll/employees/EMP-000001/history')->json('data');
        $this->assertEquals([['2026-11-17', null, __('hrpayroll.emp.prof.reason')], ['2026-10-01', '2026-11-17', null]], array_map(fn ($a) => [$a['from'], $a['to'], $a['reason']], $history['assignments']));
        $this->assertEquals([15500, 14000], array_column($history['salaries'], 'gross_a'));

        // The photograph: JPG or PNG up to 2 MB; it can be taken off again.
        $this->as()->post(self::P.'/EMP-000001/photo', ['upload' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonPath('errors.upload.0', __('hrpayroll.emp.prof.photo_type'));
        $photo = $this->as()->post(self::P.'/EMP-000001/photo', ['upload' => UploadedFile::fake()->image('ripon.png', 300, 360)], ['Accept' => 'application/json'])->assertOk()->json('data.photo');
        $this->assertSame('ripon.png', $photo['name']);
        $this->assertNull($this->as()->deleteJson(self::P.'/EMP-000001/photo')->assertOk()->json('data.photo'));

        // Removal: refused while another record uses the employee; their own history goes with them.
        DB::statement('create table tmp_employee_use (id serial primary key, employee_id char(26) references hr_employees(id))');
        DB::table('tmp_employee_use')->insert(['employee_id' => DB::table('hr_employees')->where('employee_code', 'EMP-000001')->value('id')]);
        $this->as()->deleteJson(self::P.'/EMP-000001')->assertUnprocessable()->assertJsonPath('errors.employee.0', __('hrpayroll.emp.prof.in_use', ['code' => 'EMP-000001', 'n' => 1]));
        DB::table('tmp_employee_use')->delete();
        $this->as()->deleteJson(self::P.'/EMP-000001')->assertOk();
        $this->assertSame(0, DB::table('hr_employee_assignments')->count() + DB::table('hr_employee_background_checks')->count());
        $this->as()->getJson(self::P.'/EMP-000001')->assertNotFound();
    }

    /** @param list<array<string, mixed>> $lines */
    private function form(string $type, array $lines): array
    {
        return [
            'card_no' => '50001001', 'factory_id' => $this->place['factory_id'], 'name' => 'Md. Ripon', 'father_name' => 'Abdul Karim', 'nid' => '1234567890', 'date_of_birth' => '1995-03-12',
            'nationality' => 'Bangladesh', 'religion' => 'ISLAM', 'gender' => 'MALE', 'marital_status' => 'MARRIED', 'child_count' => 1, 'blood_group' => 'B_POS', 'mobile' => '01712345678',
            'employment_type_id' => $type, 'department_id' => $this->place['department_id'], 'section_id' => $this->place['section_id'], 'designation_id' => $this->place['designation_id'],
            'building_id' => $this->place['building_id'], 'floor_id' => $this->place['floor_id'], 'line_id' => $this->place['line_id'], 'joining_date' => '2026-10-01', 'gross_salary' => 14000, 'status' => 'ACTIVE', 'active' => true,
            'present_address' => 'Kashimpur, Gazipur', 'emergency_name' => 'Abdul Karim', 'emergency_relation' => 'FATHER', 'emergency_mobile' => '01812345678',
            'passport_no' => 'A1234567', 'passport_issue_date' => '2026-01-05', 'passport_expire_date' => '2031-01-04', 'name_bn' => 'রিপন', 'stay_with_family' => true,
            'pay_lines' => $lines, 'checks' => [['check_type' => 'Police verification']],
        ];
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
