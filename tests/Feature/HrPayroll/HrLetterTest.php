<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * HR & Payroll → Letter Management (AnnotechERP 24/09): letter types added
 * and switched off and on; template versions saved (corrected in place
 * while unused, a new active version once letters came from one),
 * activated, deactivated and deleted when unused; employees looked up by
 * number or card and loaded by filters; letters generated singly and in a
 * batch with per-type yearly references; printed and listed on the
 * history, each keeping the version it came from; and who may.
 */
class HrLetterTest extends TestCase
{
    use RefreshDatabase;

    private const H = '/api/v1/hr-payroll';

    private Company $delta;

    private User $owner;

    private string $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01')->id;
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'Asia/Dhaka'));
        $row = fn (string $table, array $data): string => tap(strtolower((string) Str::ulid()), fn ($id) => DB::table($table)->insert(['id' => $id, 'company_id' => $this->delta->id, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()] + $data));
        $dept = $row('departments', ['factory_id' => $factory, 'name' => 'Sewing', 'code' => 'SEW']);
        $this->section = $row('sections', ['department_id' => $dept, 'name' => 'Sewing A', 'code' => 'SA']);
        $other = $row('sections', ['department_id' => $dept, 'name' => 'Sewing B', 'code' => 'SB']);
        $designation = $row('designations', ['department_id' => $dept, 'name' => 'Operator', 'code' => 'OP']);
        $types = collect($this->as()->getJson(self::H.'/profiles/options')->json('data.employee_types'))->pluck('value', 'label')->all();
        foreach ([['1001', 'Worker One', $this->section], ['1002', 'Worker Two', $this->section], ['1003', 'Worker Three', $other]] as [$card, $name, $section]) {
            $this->as()->postJson(self::H.'/profiles', ['card_no' => $card, 'factory_id' => $factory, 'name' => $name, 'nationality' => 'Bangladesh', 'religion' => 'ISLAM', 'gender' => 'MALE',
                'employment_type_id' => $types['Worker'], 'department_id' => $dept, 'section_id' => $section, 'designation_id' => $designation, 'joining_date' => '2026-01-01', 'gross_salary' => 21000, 'status' => 'ACTIVE'])->assertCreated();
        }
    }

    public function test_letter_types_and_template_versions(): void
    {
        $m = $this->as()->getJson(self::H.'/letters/master')->assertOk()->json('data');
        $this->assertCount(16, $m['types']);
        $this->assertSame(['APPOINTMENT', 'Appointment Letter', true, 1], [$m['types'][0]['code'], $m['types'][0]['name'], $m['types'][0]['active'], $m['types'][0]['version']]);
        $this->assertCount(1, $m['templates']);
        $app = $m['templates'][0];
        $this->assertSame(['APPOINTMENT', 1, 'DAL/HR/APP/{YYYY}/{SEQ}', 'ACTIVE', 0, true, 2], [$app['type'], $app['version'], $app['referenceFormat'], $app['status'], $app['letterCount'], $app['showSalaryTables'], $app['salaryTablesAfter']]);
        $this->assertCount(10, $app['clauses']);
        $this->assertSame(['EMP-000001', 'Worker One', 'Sewing A'], [$m['sample']['employeeNumber'], $m['sample']['name'], $m['sample']['section']]);
        $this->assertTrue($m['can_edit']);
        $this->assertSame('Delta Apparels Ltd', $m['letterhead']['company']);
        // Opening it again doesn't seed twice.
        $this->assertCount(16, $this->as()->getJson(self::H.'/letters/master')->json('data.types'));

        // A new type; the same name again is refused; switched off and on.
        $this->as()->postJson(self::H.'/letters/types', [])->assertUnprocessable()->assertJsonPath('errors.name.0', __('hrpayroll.lt.v.type_name'));
        $this->as()->postJson(self::H.'/letters/types', ['name' => 'NOC Letter'])->assertCreated()->assertJsonPath('data.code', 'NOC_LETTER')->assertJsonPath('data.message', __('hrpayroll.lt.done.type_added', ['name' => 'NOC Letter']));
        $this->as()->postJson(self::H.'/letters/types', ['name' => 'noc letter'])->assertUnprocessable()->assertJsonPath('errors.name.0', __('hrpayroll.lt.v.type_exists'));
        $this->as()->postJson(self::H.'/letters/types/NOC_LETTER/toggle')->assertOk()->assertJsonPath('data.message', __('hrpayroll.lt.done.type_off', ['name' => 'NOC Letter']));
        $this->assertFalse(collect($this->as()->getJson(self::H.'/letters/master')->json('data.types'))->firstWhere('code', 'NOC_LETTER')['active']);
        $this->as()->postJson(self::H.'/letters/types/NOC_LETTER/toggle')->assertOk()->assertJsonPath('data.message', __('hrpayroll.lt.done.type_on', ['name' => 'NOC Letter']));

        // Its first template; corrected in place while no letter came from it.
        $this->as()->postJson(self::H.'/letters/templates', ['type' => 'NOC_LETTER'])->assertUnprocessable()->assertJsonPath('errors.title.0', __('hrpayroll.lt.v.title'));
        $body = ['type' => 'NOC_LETTER', 'title' => 'No Objection Certificate', 'subject' => 'NOC', 'reference_format' => 'DAL/HR/NOC/{YYYY}/{SEQ}', 'salutation' => 'Dear **{{EmployeeName}}**,',
            'opening_message' => 'We have no objection.', 'clauses' => ['First', '  ', 'Second'], 'signature_title' => 'HR Manager', 'show_salary_tables' => false];
        $this->as()->postJson(self::H.'/letters/templates', $body)->assertOk()->assertJsonPath('data.version', 1)->assertJsonPath('data.message', __('hrpayroll.lt.done.saved_new', ['name' => 'NOC Letter', 'v' => 1]));
        $saved = $this->as()->postJson(self::H.'/letters/templates', ['version' => 1, 'title' => 'NOC'] + $body)->assertOk()->assertJsonPath('data.message', __('hrpayroll.lt.done.saved_in_place', ['name' => 'NOC Letter', 'v' => 1]))->json('data');
        $noc = collect($saved['templates'])->where('type', 'NOC_LETTER')->values();
        $this->assertCount(1, $noc);
        $this->assertSame(['NOC', ['First', 'Second'], 'ACTIVE', '2026-11-11'], [$noc[0]['title'], $noc[0]['clauses'], $noc[0]['status'], $noc[0]['effectiveFrom']]);

        // Saving without a version makes v2 the active one; v1 can be switched back on, switched off and deleted.
        $this->as()->postJson(self::H.'/letters/templates', ['title' => 'NOC v2'] + $body)->assertOk()->assertJsonPath('data.version', 2);
        $statuses = fn () => collect($this->as()->getJson(self::H.'/letters/master')->json('data.templates'))->where('type', 'NOC_LETTER')->pluck('status', 'version')->all();
        $this->assertSame([2 => 'ACTIVE', 1 => 'SUPERSEDED'], $statuses());
        $this->as()->postJson(self::H.'/letters/templates/NOC_LETTER/1/status', ['action' => 'activate'])->assertOk()->assertJsonPath('data.message', __('hrpayroll.lt.done.activated', ['name' => 'NOC Letter', 'v' => 1]));
        $this->assertSame([2 => 'SUPERSEDED', 1 => 'ACTIVE'], $statuses());
        $this->as()->postJson(self::H.'/letters/templates/NOC_LETTER/1/status', ['action' => 'deactivate'])->assertOk()->assertJsonPath('data.message', __('hrpayroll.lt.done.deactivated', ['name' => 'NOC Letter', 'v' => 1]));
        $this->assertSame([2 => 'SUPERSEDED', 1 => 'INACTIVE'], $statuses());
        $this->as()->deleteJson(self::H.'/letters/templates/NOC_LETTER/2')->assertOk()->assertJsonPath('data.message', __('hrpayroll.lt.done.deleted', ['name' => 'NOC Letter', 'v' => 2]));
        $this->assertSame([1 => 'INACTIVE'], $statuses());
        $this->as()->deleteJson(self::H.'/letters/templates/NOC_LETTER/9')->assertNotFound();
        $this->as()->postJson(self::H.'/letters/types/NOPE/toggle')->assertNotFound();

        // No active template, so nothing can be generated from it.
        $this->as()->postJson(self::H.'/letters', ['type' => 'NOC_LETTER', 'employees' => ['EMP-000001'], 'letter_date' => '2026-11-11'])
            ->assertUnprocessable()->assertJsonPath('errors.type.0', __('hrpayroll.lt.v.no_template', ['name' => 'NOC Letter']));
    }

    public function test_generate_print_and_history(): void
    {
        $o = $this->as()->getJson(self::H.'/letters/generate')->assertOk()->json('data');
        $this->assertSame('APPOINTMENT', $o['letter_types'][0]['code']);
        $this->assertSame(1, $o['letter_types'][0]['template']['version']);
        $this->assertNull($o['letter_types'][1]['template']);
        $this->assertSame(['Sewing A', 'Sewing B'], array_column($o['sections'], 'label'));
        $this->assertTrue($o['can_generate']);

        // One employee by number or by card; nothing for an unknown one.
        $this->assertSame('Worker Two', $this->as()->getJson(self::H.'/letters/employee?q=emp-000002&type=APPOINTMENT')->assertOk()->json('data.name'));
        $e = $this->as()->getJson(self::H.'/letters/employee?q=1003')->assertOk()->json('data');
        $this->assertSame(['EMP-000003', '1003', 'Sewing B', '2026-01-01', 'ACTIVE'], [$e['id'], $e['cardNumber'], $e['section'], $e['joiningDate'], $e['status']]);
        $this->assertNull($this->as()->getJson(self::H.'/letters/employee?q=9999')->assertOk()->json('data'));

        // A group by section.
        $run = $this->as()->postJson(self::H.'/letters/employees', ['type' => 'APPOINTMENT', 'section' => $this->section, 'status' => 'ACTIVE'])->assertOk()->json('data');
        $this->assertSame(['EMP-000001', 'EMP-000002'], array_column($run['employees'], 'id'));
        $this->assertSame([false, __('hrpayroll.lt.done.loaded', ['n' => 2])], [$run['truncated'], $run['message']]);
        $this->assertCount(3, $this->as()->postJson(self::H.'/letters/employees', ['status' => 'ALL'])->json('data.employees'));

        // Refused: no employee, no date, an unknown employee.
        $this->as()->postJson(self::H.'/letters', ['type' => 'APPOINTMENT'])->assertUnprocessable()
            ->assertJsonPath('errors.employees.0', __('hrpayroll.lt.v.employees'))->assertJsonPath('errors.letter_date.0', __('hrpayroll.lt.v.letter_date'));
        $this->as()->postJson(self::H.'/letters', ['type' => 'APPOINTMENT', 'employees' => ['EMP-000001', 'EMP-999999'], 'letter_date' => '2026-11-11'])->assertUnprocessable()->assertJsonValidationErrors(['employees']);

        // One letter, then a batch of two: references run on per type per year.
        $one = $this->as()->postJson(self::H.'/letters', ['type' => 'APPOINTMENT', 'employees' => ['EMP-000003'], 'letter_date' => '2026-11-11', 'probation_months' => 3])->assertCreated()->json('data');
        $this->assertSame([['DAL/HR/APP/2026/001'], null, __('hrpayroll.lt.done.generated_one', ['ref' => 'DAL/HR/APP/2026/001'])], [$one['refs'], $one['batch_no'], $one['message']]);
        $this->assertSame(['Appointment Letter', 3, 1, 'Worker Three'], [$one['letters'][0]['letter_type'], $one['letters'][0]['probation_months'], $one['letters'][0]['template']['version'], $one['letters'][0]['employee']['name']]);
        $two = $this->as()->postJson(self::H.'/letters', ['type' => 'APPOINTMENT', 'employees' => ['EMP-000001', 'EMP-000002'], 'letter_date' => '2026-11-12', 'effective_date' => '2026-12-01'])->assertCreated()->json('data');
        $this->assertSame([['DAL/HR/APP/2026/002', 'DAL/HR/APP/2026/003'], 'B-20261112-001'], [$two['refs'], $two['batch_no']]);
        $this->assertSame(__('hrpayroll.lt.done.generated', ['n' => 2, 'batch' => 'B-20261112-001']), $two['message']);
        $this->assertSame('2026-12-01', $two['letters'][1]['effective_date']);
        $next = $this->as()->postJson(self::H.'/letters', ['type' => 'APPOINTMENT', 'employees' => ['EMP-000001'], 'letter_date' => '2027-01-05'])->assertCreated()->json('data.refs');
        $this->assertSame(['DAL/HR/APP/2027/001'], $next);

        // Printed, counted.
        $p = $this->as()->postJson(self::H.'/letters/print', ['refs' => ['DAL/HR/APP/2026/001', 'NOPE']])->assertOk()->json('data');
        $this->assertCount(1, $p['letters']);
        $this->assertSame('Delta Apparels Ltd', $p['letterhead']['company']);
        $this->as()->postJson(self::H.'/letters/printed', ['refs' => ['DAL/HR/APP/2026/001']])->assertOk();
        $this->as()->postJson(self::H.'/letters/printed', ['refs' => ['DAL/HR/APP/2026/001']])->assertOk();

        // The template changes: letters came from v1, so saving makes v2; v1 is kept.
        $master = $this->as()->getJson(self::H.'/letters/master')->json('data');
        $this->assertSame(4, $master['templates'][0]['letterCount']);
        $this->as()->postJson(self::H.'/letters/templates', ['type' => 'APPOINTMENT', 'version' => 1, 'title' => 'Appointment Letter', 'clauses' => ['Only one clause']])->assertOk()
            ->assertJsonPath('data.version', 2)->assertJsonPath('data.message', __('hrpayroll.lt.done.saved_new', ['name' => 'Appointment Letter', 'v' => 2]));
        $this->as()->deleteJson(self::H.'/letters/templates/APPOINTMENT/1')->assertUnprocessable()->assertJsonPath('errors.version.0', __('hrpayroll.lt.v.template_used'));
        $old = $this->as()->postJson(self::H.'/letters/print', ['refs' => ['DAL/HR/APP/2026/001']])->json('data.letters.0.template');
        $this->assertSame([1, 10], [$old['version'], count($old['clauses'])]);
        $new = $this->as()->postJson(self::H.'/letters', ['type' => 'APPOINTMENT', 'employees' => ['EMP-000002'], 'letter_date' => '2027-01-06'])->assertCreated()->json('data.letters.0.template');
        $this->assertSame([2, ['Only one clause']], [$new['version'], $new['clauses']]);

        // The history: newest first, with the version and print count.
        $h = $this->as()->getJson(self::H.'/letters')->assertOk()->json('data');
        $this->assertCount(5, $h['rows']);
        $first = collect($h['rows'])->firstWhere('ref', 'DAL/HR/APP/2026/001');
        $this->assertSame(['EMP-000003', 'Worker Three', 'Operator', 'Sewing', 'Sewing B', '2026-11-11', 1, 'PRINTED', 2], [$first['code'], $first['name'], $first['designation'], $first['department'], $first['section'], $first['letter_date'], $first['version'], $first['status'], $first['print_count']]);
        $this->assertSame(['B-20261112-001', 'GENERATED', 0], [collect($h['rows'])->firstWhere('ref', 'DAL/HR/APP/2026/003')['batch_no'], collect($h['rows'])->firstWhere('ref', 'DAL/HR/APP/2026/003')['status'], collect($h['rows'])->firstWhere('ref', 'DAL/HR/APP/2026/003')['print_count']]);
        $this->assertSame(['Sewing A', 'Sewing B'], array_column($h['sections'], 'value'));
        $this->assertFalse($h['truncated']);
    }

    public function test_who_may(): void
    {
        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'auditor@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($auditor)->getJson(self::H.'/letters/master')->assertOk()->assertJsonPath('data.can_edit', false);
        $this->as($auditor)->getJson(self::H.'/letters/generate')->assertOk()->assertJsonPath('data.can_generate', false);
        $this->as($auditor)->getJson(self::H.'/letters')->assertOk();
        $this->as($auditor)->postJson(self::H.'/letters/types', ['name' => 'NOC'])->assertForbidden();
        $this->as($auditor)->postJson(self::H.'/letters/templates', ['type' => 'APPOINTMENT', 'title' => 'x'])->assertForbidden();
        $this->as($auditor)->postJson(self::H.'/letters', ['type' => 'APPOINTMENT', 'employees' => ['EMP-000001'], 'letter_date' => '2026-11-11'])->assertForbidden();
        $this->as($auditor)->postJson(self::H.'/letters/printed', ['refs' => ['x']])->assertForbidden();

        $this->as($manager)->postJson(self::H.'/letters/types', ['name' => 'NOC'])->assertForbidden();
        $this->as(TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'admin@delta.test'))->postJson(self::H.'/letters/types', ['name' => 'NOC'])->assertCreated();
        $ref = $this->as($manager)->postJson(self::H.'/letters', ['type' => 'APPOINTMENT', 'employees' => ['EMP-000001'], 'letter_date' => '2026-11-11'])->assertCreated()->json('data.refs.0');
        $this->assertCount(1, $this->as($auditor)->postJson(self::H.'/letters/print', ['refs' => [$ref]])->assertOk()->json('data.letters'));

        // Another company sees none of it.
        $other = TenantFixture::company('Other Ltd', 'OTH');
        TenantFixture::actingAsTenant($other);
        $stranger = TenantFixture::user($other, 'COMPANY_OWNER', 'owner@other.test');
        $this->assertSame([], $this->as($stranger, $other)->getJson(self::H.'/letters')->assertOk()->json('data.rows'));
        $this->assertSame([], $this->as($stranger, $other)->postJson(self::H.'/letters/print', ['refs' => [$ref]])->assertOk()->json('data.letters'));
        $this->as($stranger, $other)->postJson(self::H.'/letters', ['type' => 'APPOINTMENT', 'employees' => ['EMP-000001'], 'letter_date' => '2026-11-11'])->assertNotFound();
    }

    private function as(?User $user = null, ?Company $company = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, ($company ?? $this->delta)->id, 'Test token');

        return $this->withToken($plain);
    }
}
