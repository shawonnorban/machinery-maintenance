<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\HrPayroll\Support\MovementBook;
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
 * HR & Payroll → Employee Movement (AnnotechERP 24/07): a transfer saved as
 * a draft, edited and finalized (the posting and its history move, the floor
 * following the line), finalized at once, deleted; promotions and
 * increments sent, rejected, withdrawn, approved now or for a later date
 * (applied on the day); re-joins with the pay period and letter, removed
 * again; and who may.
 */
class HrEmployeeMovementTest extends TestCase
{
    use RefreshDatabase;

    private const H = '/api/v1/hr-payroll';

    private Company $delta;

    private User $owner;

    private string $factory;

    private string $dept;

    private string $section;

    private string $line;

    private string $designation;

    /** @var array<string, string> */
    private array $to = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01')->id;
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'Asia/Dhaka'));
        $row = fn (string $table, array $data): string => tap(strtolower((string) Str::ulid()), fn ($id) => DB::table($table)->insert(['id' => $id, 'company_id' => $this->delta->id, 'created_at' => now(), 'updated_at' => now()] + $data));
        $this->dept = $row('departments', ['factory_id' => $this->factory, 'name' => 'Sewing', 'code' => 'SEW', 'status' => 'ACTIVE']);
        $this->section = $row('sections', ['department_id' => $this->dept, 'name' => 'Sewing A', 'code' => 'SA', 'status' => 'ACTIVE']);
        $this->line = $row('production_lines', ['department_id' => $this->dept, 'section_id' => $this->section, 'name' => 'Line 1', 'code' => 'L1', 'status' => 'ACTIVE']);
        $this->designation = $row('designations', ['department_id' => $this->dept, 'name' => 'Operator', 'code' => 'OP', 'status' => 'ACTIVE']);
        // Where they move to: Finishing → Packing → Line 9, on Floor 2 of Building B.
        $building = $row('buildings', ['factory_id' => $this->factory, 'name' => 'Building B', 'code' => 'BB', 'status' => 'ACTIVE']);
        $this->to['floor'] = $row('floors', ['building_id' => $building, 'name' => 'Floor 2', 'code' => 'F2', 'status' => 'ACTIVE']);
        $this->to['building'] = $building;
        $this->to['dept'] = $row('departments', ['factory_id' => $this->factory, 'name' => 'Finishing', 'code' => 'FIN', 'status' => 'ACTIVE']);
        $this->to['section'] = $row('sections', ['department_id' => $this->to['dept'], 'name' => 'Packing', 'code' => 'PK', 'status' => 'ACTIVE']);
        $this->to['line'] = $row('production_lines', ['department_id' => $this->to['dept'], 'section_id' => $this->to['section'], 'floor_id' => $this->to['floor'], 'name' => 'Line 9', 'code' => 'L9', 'status' => 'ACTIVE']);
        $this->to['designation'] = $row('designations', ['department_id' => $this->dept, 'name' => 'Senior Operator', 'code' => 'SOP', 'status' => 'ACTIVE']);
        $types = collect($this->as()->getJson(self::H.'/profiles/options')->json('data.employee_types'))->pluck('value', 'label')->all();
        foreach ([['1001', 'Worker One', 'Worker'], ['1002', 'Worker Two', 'Worker'], ['1003', 'Staff One', 'Staff']] as [$card, $name, $type]) {
            $this->as()->postJson(self::H.'/profiles', ['card_no' => $card, 'factory_id' => $this->factory, 'name' => $name, 'nationality' => 'Bangladesh', 'religion' => 'ISLAM', 'gender' => 'MALE',
                'employment_type_id' => $types[$type], 'department_id' => $this->dept, 'section_id' => $this->section, 'line_id' => $this->line, 'designation_id' => $this->designation,
                'joining_date' => '2026-01-01', 'gross_salary' => 21000, 'status' => 'ACTIVE'])->assertCreated();
        }
    }

    public function test_transfer_draft_edit_finalize_and_delete(): void
    {
        $options = $this->as()->getJson(self::H.'/movement/options')->assertOk()->json('data');
        $this->assertEquals(['EMP-000001', 'Worker One, Operator', 'Sewing', 'Sewing A', 'Line 1'], array_values(array_intersect_key($options['employees'][0], array_flip(['value', 'name', 'department', 'section', 'line']))));
        $this->assertSame('Floor 2 · Building B', collect($options['lines'])->firstWhere('value', $this->to['line'])['floor']);
        $this->assertSame([], $this->as()->getJson(self::H.'/transfers')->assertOk()->json('data'));

        $this->as()->postJson(self::H.'/transfers', [])->assertUnprocessable()->assertJsonPath('errors.employee.0', __('hrpayroll.mv.v.employee'))->assertJsonValidationErrors(['new_department_id', 'effective_date', 'status']);
        $this->as()->postJson(self::H.'/transfers', ['employee' => 'EMP-000001', 'new_department_id' => $this->dept, 'new_section_id' => $this->section, 'new_line_id' => $this->line, 'effective_date' => '2026-11-15', 'status' => 'DRAFT'])
            ->assertUnprocessable()->assertJsonPath('errors.new_department_id.0', __('hrpayroll.mv.v.same_posting'));
        $this->as()->postJson(self::H.'/transfers', ['employee' => 'EMP-000001', 'new_department_id' => $this->to['dept'], 'new_section_id' => $this->section, 'effective_date' => '2026-11-15', 'status' => 'DRAFT'])
            ->assertUnprocessable()->assertJsonValidationErrors(['new_section_id']);

        // A draft: nothing moves yet; one draft per employee.
        $base = ['employee' => 'EMP-000001', 'new_department_id' => $this->to['dept'], 'new_section_id' => $this->to['section'], 'new_line_id' => $this->to['line'], 'effective_date' => '2026-11-14', 'reason' => 'Packing short of hands', 'status' => 'DRAFT'];
        $saved = $this->as()->postJson(self::H.'/transfers', $base)->assertCreated()->json('data');
        $this->assertSame(__('hrpayroll.mv.done.transfer_saved', ['ref' => 'TRF-000001']), $saved['message']);
        $this->assertEquals(['TRF-000001', 'Sewing', 'Sewing A', 'Line 1', 'Finishing', 'Packing', 'Line 9', 'Floor 2 · Building B', 'DRAFT'], array_values(array_intersect_key($saved['rows'][0], array_flip(['ref', 'status', 'old_department', 'old_section', 'old_line', 'new_department', 'new_section', 'new_line', 'new_floor']))));
        $this->assertSame($this->dept, DB::table('hr_employees')->where('card_no', '1001')->value('department_id'));
        $this->as()->postJson(self::H.'/transfers', $base)->assertUnprocessable()->assertJsonPath('errors.employee.0', __('hrpayroll.mv.v.draft_exists'));
        $this->as()->putJson(self::H.'/transfers/TRF-000001', ['effective_date' => '2026-11-15'] + $base)->assertOk()->assertJsonPath('data.rows.0.effective_date', '2026-11-15');
        $this->as()->putJson(self::H.'/transfers/TRF-000001', ['employee' => 'EMP-000002'] + $base)->assertUnprocessable()->assertJsonPath('errors.employee.0', __('hrpayroll.mv.v.employee_fixed'));

        // Finalize: the employee, the floor and building, and the history move.
        $this->as()->postJson(self::H.'/transfers/TRF-000001/finalize')->assertOk()->assertJsonPath('data.rows.0.status', 'FINALIZED');
        $e = DB::table('hr_employees')->where('card_no', '1001')->first();
        $this->assertEquals([$this->to['dept'], $this->to['section'], $this->to['line'], $this->to['floor'], $this->to['building'], $this->designation], [$e->department_id, $e->section_id, $e->line_id, $e->floor_id, $e->building_id, $e->designation_id]);
        $history = DB::table('hr_employee_assignments')->where('employee_id', $e->id)->orderBy('effective_from')->get();
        $this->assertEquals([['2026-01-01', '2026-11-15', $this->dept], ['2026-11-15', null, $this->to['dept']]], $history->map(fn ($h) => [substr((string) $h->effective_from, 0, 10), $h->effective_to ? substr((string) $h->effective_to, 0, 10) : null, $h->department_id])->all());
        $this->assertStringStartsWith('Transfer TRF-000001', $history[1]->reason);
        $this->as()->postJson(self::H.'/transfers/TRF-000001/finalize')->assertUnprocessable()->assertJsonPath('errors.status.0', __('hrpayroll.mv.v.finalized'));
        $this->as()->deleteJson(self::H.'/transfers/TRF-000001')->assertUnprocessable();
        $this->as()->putJson(self::H.'/transfers/TRF-000001', $base)->assertUnprocessable();

        // Finalized on save moves at once; a draft can be deleted.
        $this->as()->postJson(self::H.'/transfers', ['employee' => 'EMP-000002', 'status' => 'FINALIZED'] + $base)->assertCreated()->assertJsonPath('data.message', __('hrpayroll.mv.done.transfer_finalized', ['ref' => 'TRF-000002']));
        $this->assertSame($this->to['line'], DB::table('hr_employees')->where('card_no', '1002')->value('line_id'));
        $this->as()->postJson(self::H.'/transfers', ['employee' => 'EMP-000003'] + $base)->assertCreated();
        $this->as()->deleteJson(self::H.'/transfers/TRF-000003')->assertOk();
        $this->assertSame(['TRF-000002', 'TRF-000001'], array_column($this->as()->getJson(self::H.'/transfers')->json('data'), 'ref'));

        // Who may: an auditor sees; a store keeper does not.
        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'audit@delta.test');
        $this->as($auditor)->getJson(self::H.'/transfers')->assertOk();
        $this->as($auditor)->postJson(self::H.'/transfers', ['employee' => 'EMP-000003'] + $base)->assertForbidden();
    }

    public function test_promotions_and_increments(): void
    {
        $found = $this->as()->getJson(self::H.'/movement/employees?q=worker')->assertOk()->json('data');
        $this->assertEquals([['EMP-000001', 'Operator', 21000.0], ['EMP-000002', 'Operator', 21000.0]], array_map(fn ($e) => [$e['code'], $e['designation'], $e['gross_a']], $found));
        $this->assertCount(1, $this->as()->getJson(self::H.'/movement/employees?q=1003')->json('data'));
        $grade = DB::table('grades')->where('company_id', $this->delta->id)->where('code', '4')->value('id');

        // Promotion: nothing changing is refused; one pending at a time; reject; withdraw.
        $this->as()->postJson(self::H.'/promotions', ['employee' => 'EMP-000001', 'new_designation_id' => $this->designation, 'effective_date' => '2026-11-11', 'reason' => 'x'])
            ->assertUnprocessable()->assertJsonPath('errors.new_designation_id.0', __('hrpayroll.mv.v.nothing_changes'));
        $this->as()->postJson(self::H.'/promotions', ['employee' => 'EMP-000001'])->assertUnprocessable()->assertJsonValidationErrors(['new_designation_id', 'effective_date', 'reason']);
        $promo = ['employee' => 'EMP-000001', 'new_designation_id' => $this->to['designation'], 'new_grade_id' => $grade, 'new_gross' => 24000, 'effective_date' => '2026-11-11', 'reason' => 'Best line output for six months'];
        $this->as()->postJson(self::H.'/promotions', $promo)->assertCreated()->assertJsonPath('data.message', __('hrpayroll.mv.done.sent', ['ref' => 'PRM-000001']));
        $this->as()->postJson(self::H.'/promotions', $promo)->assertUnprocessable()->assertJsonPath('errors.employee.0', __('hrpayroll.mv.v.pending_promotion'));
        $this->as()->postJson(self::H.'/promotions/PRM-000001/decide', ['decision' => 'REJECT', 'note' => 'Wait for the next review'])->assertOk()->assertJsonPath('data.rows.0.status', 'REJECTED');
        $this->as()->postJson(self::H.'/promotions/PRM-000001/decide', ['decision' => 'APPROVE'])->assertUnprocessable();
        $this->as()->postJson(self::H.'/promotions', $promo)->assertCreated();
        $this->as()->deleteJson(self::H.'/promotions/PRM-000002')->assertOk()->assertJsonPath('data.message', __('hrpayroll.mv.done.withdrawn', ['ref' => 'PRM-000002']));

        // A withdrawn number is used again. Approved and dated today: in force at once — designation, grade, gross, histories, core pay lines.
        $this->as()->postJson(self::H.'/promotions', $promo)->assertCreated();
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->postJson(self::H.'/promotions', $promo)->assertForbidden();
        $done = $this->as($manager)->postJson(self::H.'/promotions/PRM-000002/decide', ['decision' => 'APPROVE', 'note' => 'Agreed'])->assertOk()->json('data');
        $this->assertSame(__('hrpayroll.mv.done.approved_now', ['ref' => 'PRM-000002']), $done['message']);
        $p = collect($done['rows'])->firstWhere('ref', 'PRM-000002');
        $this->assertEquals(['APPROVED', 'Operator', 'Senior Operator', 21000.0, 24000.0, 'Agreed'], [$p['status'], $p['old_designation'], $p['new_designation'], $p['old_gross'], $p['new_gross'], $p['decision_note']]);
        $this->assertNotNull($p['applied_at']);
        $e = DB::table('hr_employees')->where('card_no', '1001')->first();
        $this->assertEquals([$this->to['designation'], $grade, 24000.0], [$e->designation_id, $e->grade_id, (float) $e->gross_a]);
        $latest = DB::table('hr_employee_salaries')->where('employee_id', $e->id)->orderByDesc('effective_from')->first();
        $this->assertSame(['2026-11-11', 24000.0], [substr((string) $latest->effective_from, 0, 10), (float) $latest->gross_a]);
        $this->assertSame($this->to['designation'], DB::table('hr_employee_assignments')->where('employee_id', $e->id)->whereNull('effective_to')->value('designation_id'));
        $basicLine = DB::table('hr_employee_pay_lines as l')->join('hr_pay_elements as p', 'p.id', '=', 'l.pay_element_id')->where('l.employee_id', $e->id)->where('p.code', 'BASIC')->where('l.core_salary', true)->value('l.amount');
        if ($basicLine !== null) {
            $this->assertEquals(MovementBook::basicOf($e, 24000), (float) $basicLine);
        }

        // Increment: new salary, amount or percent; never below the old one; dated ahead it waits.
        $this->as()->postJson(self::H.'/increments', ['employee' => 'EMP-000002', 'mode' => 'NEW', 'value' => 20000, 'effective_date' => '2026-12-01', 'reason' => 'x'])
            ->assertUnprocessable()->assertJsonPath('errors.value.0', __('hrpayroll.mv.v.above_old'));
        $sent = $this->as()->postJson(self::H.'/increments', ['employee' => 'EMP-000002', 'mode' => 'PERCENT', 'value' => 5, 'effective_date' => '2026-12-01', 'reason' => 'Yearly increment'])->assertCreated()->json('data.rows.0');
        $this->assertEquals(['INC-000001', 21000.0, 22050.0, 1050.0, 5.0], [$sent['ref'], $sent['old_gross'], $sent['new_gross'], $sent['increment'], $sent['percent']]);
        $this->as()->postJson(self::H.'/increments', ['employee' => 'EMP-000003', 'mode' => 'AMOUNT', 'value' => 1500, 'effective_date' => '2026-11-01', 'reason' => 'Skill allowance folded in'])->assertCreated()->assertJsonPath('data.rows.0.new_gross', 22500);
        $this->as()->postJson(self::H.'/increments/INC-000001/decide', ['decision' => 'APPROVE'])->assertOk()->assertJsonPath('data.message', __('hrpayroll.mv.done.approved_later', ['ref' => 'INC-000001', 'date' => '2026-12-01']));
        $this->assertEquals(21000.0, (float) DB::table('hr_employees')->where('card_no', '1002')->value('gross_a'));
        $this->as()->postJson(self::H.'/increments/INC-000002/decide', ['decision' => 'APPROVE'])->assertOk();
        $s1 = DB::table('hr_employees')->where('card_no', '1003')->first();
        $this->assertEquals([22500.0, (float) $s1->gross_b], [(float) $s1->gross_a, (float) DB::table('hr_employee_salaries')->where('employee_id', $s1->id)->orderByDesc('effective_from')->value('gross_b')]);
        $list = $this->as()->getJson(self::H.'/increments')->json('data');
        $this->assertEquals([1, 1], [collect($list['rows'])->where('status', 'APPROVED')->whereNull('applied_at')->count(), collect($list['rows'])->whereNotNull('applied_at')->count()]);

        // On its day the increment is applied (the daily command; a screen load does the same).
        $this->travelTo(CarbonImmutable::parse('2026-12-01 00:10:00', 'Asia/Dhaka'));
        $this->artisan('hr:apply-movements')->assertSuccessful();
        $this->assertEquals(22050.0, (float) DB::table('hr_employees')->where('card_no', '1002')->value('gross_a'));
        $this->assertNotNull($this->as()->getJson(self::H.'/increments')->json('data.rows.1.applied_at'));

        // Who may: an auditor sees but cannot decide or request.
        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'audit@delta.test');
        $this->assertSame([false, false], array_values(array_intersect_key($this->as($auditor)->getJson(self::H.'/promotions')->assertOk()->json('data'), array_flip(['can_request', 'can_approve']))));
        $this->as($auditor)->postJson(self::H.'/increments', ['employee' => 'EMP-000001', 'mode' => 'AMOUNT', 'value' => 100, 'effective_date' => '2026-12-01', 'reason' => 'x'])->assertForbidden();
    }

    public function test_rejoin_with_period_and_letter(): void
    {
        Storage::fake('local');
        DB::table('hr_employees')->where('card_no', '1003')->update(['status' => 'SEPARATED', 'separation_date' => '2026-10-31', 'active' => false]);
        $year = DB::table('acc_fiscal_years')->where('company_id', $this->delta->id)->where('name', 'FY2026-27')->value('id');
        DB::table('hr_pay_periods')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $this->delta->id, 'period_no' => 'APP-DALFY2026-27110001', 'period_type' => 'MONTHLY', 'fiscal_year_id' => $year, 'month' => 11,
            'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'pay_date' => '2026-12-07', 'status' => 'INITIATED', 'show_for_report' => true, 'closed' => false, 'created_at' => now(), 'updated_at' => now()]);

        $data = $this->as()->getJson(self::H.'/rejoins')->assertOk()->json('data');
        $this->assertEquals([['EMP-000003', 'Staff One, Operator, Sewing, left 31 Oct 2026']], array_map(fn ($e) => [$e['value'], $e['summary']], $data['employees']));
        $this->assertSame('APP-DALFY2026-27110001 — Delta Apparels Ltd', $data['periods'][0]['label']);

        $this->as()->postJson(self::H.'/rejoins', [])->assertUnprocessable()->assertJsonPath('errors.employee.0', __('hrpayroll.mv.v.employee_code'))->assertJsonValidationErrors(['rejoin_date']);
        $this->as()->postJson(self::H.'/rejoins', ['employee' => 'EMP-000001', 'rejoin_date' => '2026-11-10'])->assertUnprocessable()->assertJsonPath('errors.employee.0', __('hrpayroll.mv.v.not_left'));
        $this->as()->postJson(self::H.'/rejoins', ['employee' => 'EMP-000003', 'rejoin_date' => '2026-10-20'])->assertUnprocessable()->assertJsonPath('errors.rejoin_date.0', __('hrpayroll.mv.v.rejoin_before', ['date' => '2026-10-31']));
        $saved = $this->as()->postJson(self::H.'/rejoins', ['employee' => 'EMP-000003', 'period' => 'APP-DALFY2026-27110001', 'rejoin_date' => '2026-11-10', 'remarks' => 'Back after family move'])->assertCreated()->json('data');
        $this->assertEquals(['RJN-000001', 'EMP-000003', '2026-11-10', 'APP-DALFY2026-27110001', '2026-10-31', []], [$saved['ref'], $saved['rows'][0]['code'], $saved['rows'][0]['rejoin_date'], $saved['rows'][0]['period'], $saved['rows'][0]['left_on'], $saved['employees']]);
        $e = DB::table('hr_employees')->where('card_no', '1003')->first();
        $this->assertEquals(['ACTIVE', null, true], [$e->status, $e->separation_date, (bool) $e->active]);

        $this->as()->putJson(self::H.'/rejoins/RJN-000001', ['rejoin_date' => '2026-11-12', 'remarks' => 'Back after family move', 'remarks_bn' => 'পরিবার নিয়ে ফিরেছেন'])->assertOk()->assertJsonPath('data.rows.0.rejoin_date', '2026-11-12');
        $this->as()->post(self::H.'/rejoins/RJN-000001/doc', ['upload' => UploadedFile::fake()->create('letter.txt', 10, 'text/plain')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->as()->post(self::H.'/rejoins/RJN-000001/doc', ['upload' => UploadedFile::fake()->create('rejoin-letter.pdf', 120, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $row = $this->as()->getJson(self::H.'/rejoins')->json('data.rows.0');
        $this->assertSame('rejoin-letter.pdf', $row['doc_path']);
        $this->assertSame('hr_rejoin_doc', DB::table('file_attachments')->where('id', $row['doc_file_id'])->value('attachable_type'));

        // Removing the re-join puts the employee back as they had left.
        $this->as()->deleteJson(self::H.'/rejoins/RJN-000001')->assertOk()->assertJsonPath('data.rows', []);
        $e = DB::table('hr_employees')->where('card_no', '1003')->first();
        $this->assertEquals(['SEPARATED', '2026-10-31'], [$e->status, substr((string) $e->separation_date, 0, 10)]);

        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'audit@delta.test');
        $this->as($auditor)->getJson(self::H.'/rejoins')->assertOk()->assertJsonPath('data.can_manage', false);
        $this->as($auditor)->postJson(self::H.'/rejoins', ['employee' => 'EMP-000003', 'rejoin_date' => '2026-11-12'])->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
