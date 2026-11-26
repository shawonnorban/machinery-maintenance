<?php

declare(strict_types=1);

namespace Tests\Feature\GatePass;

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
 * Gate Pass → Create, Approval, Out, In, Reconciliation, List, Dashboard and
 * Reports (AnnotechERP 11/02–11/04): a pass is prepared and submitted,
 * approved (or sent back, rejected, cancelled), let out at the gate, and a
 * returnable one comes back in Gate INs — partly, then a final return that
 * declares the rest short for an approver to close; the dashboard, the list
 * and the ten reports read the same passes.
 */
class GatePassFlowTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private User $keeper;

    private User $manager;

    private string $location;

    private string $item;

    private string $box;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');
        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');

        $this->as()->getJson('/api/v1/gate-pass/options')->assertOk();
        $type = collect($this->as()->getJson('/api/v1/erp/gatepass-location-types')->json('data'))->firstWhere('name', 'Washing Factory');
        $this->location = $this->as()->postJson('/api/v1/erp/gatepass-locations', ['code' => 'LOC-001', 'name' => 'Star Washing', 'location_type_id' => $type['id'], 'contact_person' => 'Karim', 'mobile' => '01711000000', 'address' => 'Gazipur'])->assertCreated()->json('data.id');
        $this->item = $this->as()->postJson('/api/v1/erp/gatepass-items', ['code' => 'GPI-001', 'name' => 'Denim Trouser', 'item_category' => 'FINISHED_GARMENTS', 'unit' => 'PCS'])->assertCreated()->json('data.id');
        $this->box = $this->as()->postJson('/api/v1/erp/gatepass-items', ['code' => 'GPI-002', 'name' => 'Carton Box', 'item_category' => 'PACKING_MATERIAL', 'unit' => 'CTN'])->assertCreated()->json('data.id');
    }

    public function test_an_item_pass_is_approved_goes_out_and_comes_back_short(): void
    {
        $options = $this->as($this->keeper)->getJson('/api/v1/gate-pass/options')->assertOk()->json('data');
        $this->assertSame('GP-'.now()->format('Y').'-000001', $options['next_no']);
        $this->assertSame(['MAIN', 'ITEM'], array_column($options['categories'], 'kind'));
        $this->assertSame(['Washing Factory', 'Gazipur'], [$options['locations'][0]['type'], $options['locations'][0]['address']]);
        $itemCat = collect($options['categories'])->firstWhere('kind', 'ITEM')['value'];
        $washing = collect($options['purposes'])->firstWhere('label', 'Washing')['value'];

        // Submit asks for everything Brickland's "Still needed" lists.
        $blank = ['gp_date' => now()->toDateString(), 'category_id' => $itemCat, 'pass_type' => 'RETURNABLE', 'carrier_type' => 'NON_EMPLOYEE', 'lines' => []];
        $this->as($this->keeper)->postJson('/api/v1/gate-pass/passes', $blank + ['submit' => true])->assertUnprocessable()
            ->assertJsonValidationErrors(['purpose_id', 'location_id', 'expected_return', 'carrier_name', 'carrier_mobile', 'lines'])
            ->assertJsonPath('errors.purpose_id.0', 'Purpose / Reason is required.');
        $this->as($this->keeper)->postJson('/api/v1/gate-pass/passes', ['lines' => [['item_id' => $this->item, 'unit' => 'PCS', 'quantity' => 0]]] + $blank)
            ->assertUnprocessable()->assertJsonValidationErrors(['lines.0.quantity' => 'Row 1: enter a quantity above zero.']);

        // A draft saves with less, then is submitted complete.
        $draft = $this->as($this->keeper)->postJson('/api/v1/gate-pass/passes', $blank)->assertCreated()->json('data');
        $this->assertSame(['GP-'.now()->format('Y').'-000001', 'DRAFT'], [$draft['gp_no'], $draft['status']]);
        $no = $draft['gp_no'];
        $this->as($this->keeper)->postJson("/api/v1/gate-pass/passes/{$no}/submit")->assertUnprocessable()->assertJsonPath('errors.pass.0', 'Still needed: Purpose / Reason, Destination Factory / Location, Expected Return Date, Person Name, Mobile, Items.');
        $full = [
            'purpose_id' => $washing, 'location_id' => $this->location, 'expected_return' => now()->addDays(6)->toDateString(), 'carrier_name' => 'Rafiq', 'carrier_mobile' => '01700000000',
            'vehicle_no' => 'DHA-11-5501', 'style' => 'HM-1025', 'po_no' => 'PO-5001', 'remarks' => 'For enzyme wash',
            'lines' => [['item_id' => $this->item, 'unit' => 'PCS', 'quantity' => 5, 'remarks' => 'Size M'], ['item_id' => '', 'quantity' => '']],
        ] + $blank;
        $this->as($this->keeper)->putJson("/api/v1/gate-pass/passes/{$no}", $full + ['submit' => true])->assertOk()->assertJsonPath('data.status', 'PENDING')->assertJsonCount(1, 'data.lines');

        // The approver: the storekeeper may not; the factory manager approves.
        $this->as($this->keeper)->postJson("/api/v1/gate-pass/passes/{$no}/approve")->assertForbidden();
        $this->assertSame([$no], array_column($this->as($this->manager)->getJson('/api/v1/gate-pass/passes?list=approval')->json('data.rows'), 'gp_no'));
        $this->as($this->manager)->postJson("/api/v1/gate-pass/passes/{$no}/approve", ['note' => 'OK'])->assertOk()->assertJsonPath('data.status', 'APPROVED');

        // Sent back for amendment: a draft again, changed and resubmitted, approved again.
        $this->as($this->manager)->postJson("/api/v1/gate-pass/passes/{$no}/send-back", [])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->as($this->manager)->postJson("/api/v1/gate-pass/passes/{$no}/send-back", ['reason' => 'Add the cartons'])->assertOk()->assertJsonPath('data.status', 'DRAFT');
        $full['lines'][1] = ['item_id' => $this->box, 'unit' => 'CTN', 'quantity' => 2];
        $this->as($this->keeper)->putJson("/api/v1/gate-pass/passes/{$no}", $full + ['submit' => true])->assertOk();
        $this->as($this->manager)->postJson("/api/v1/gate-pass/passes/{$no}/approve")->assertOk();

        // Gate OUT.
        $this->as($this->manager)->postJson("/api/v1/gate-pass/passes/{$no}/out")->assertForbidden();
        $this->assertSame([$no], array_column($this->as($this->keeper)->getJson('/api/v1/gate-pass/passes?list=approved')->json('data.rows'), 'gp_no'));
        $out = $this->as($this->keeper)->postJson("/api/v1/gate-pass/passes/{$no}/out", ['remarks' => 'Checked'])->assertOk()->json('data');
        $this->assertSame(['OUT', 'PENDING', 'Checked'], [$out['status'], $out['return'], $out['out_remarks']]);
        $this->as($this->keeper)->postJson("/api/v1/gate-pass/passes/{$no}/out")->assertUnprocessable();
        [$trousers, $cartons] = array_column($out['lines'], 'id');

        // Gate IN: never above the balance, never empty; 3 trousers back → Partially Returned.
        $this->as($this->keeper)->postJson("/api/v1/gate-pass/passes/{$no}/in", ['lines' => [['line_id' => $trousers, 'quantity' => 6]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['lines.0.quantity' => 'Denim Trouser: no more than 5 Pcs can come back.']);
        $this->as($this->keeper)->postJson("/api/v1/gate-pass/passes/{$no}/in", ['lines' => []])->assertUnprocessable()->assertJsonValidationErrors('lines');
        $partial = $this->as($this->keeper)->postJson("/api/v1/gate-pass/passes/{$no}/in", ['lines' => [['line_id' => $trousers, 'quantity' => 3]]])->assertOk()->json('data');
        $this->assertSame(['PARTIAL', 'PARTIAL', 'GPI-'.now()->format('Y').'-000001'], [$partial['status'], $partial['return'], $partial['ins'][0]['gpi_no']]);
        $this->assertEquals([7, 3, 0, 4], array_values(array_intersect_key($partial['totals'], array_flip(['out', 'returned', 'short', 'balance']))));

        // Final return: the rest declared short, with a reason → Short, waiting for an approver.
        $final = ['lines' => [['line_id' => $trousers, 'quantity' => 1], ['line_id' => $cartons, 'quantity' => 2]], 'final' => true];
        $this->as($this->keeper)->postJson("/api/v1/gate-pass/passes/{$no}/in", $final)->assertUnprocessable()->assertJsonPath('errors.short_reason.0', 'Pick the short reason.');
        $short = $this->as($this->keeper)->postJson("/api/v1/gate-pass/passes/{$no}/in", $final + ['short_reason' => 'MISSING', 'remarks' => 'One lost'])->assertOk()->json('data');
        $this->assertSame(['SHORT', 'SHORT', 'MISSING'], [$short['status'], $short['return'], $short['short_reason']]);
        $this->assertEquals([[5, 4, 1, 0], [2, 2, 0, 0]], array_map(fn ($l) => [$l['out'], $l['returned'], $l['short'], $l['balance']], $short['lines']));
        $this->assertSame('GPI-'.now()->format('Y').'-000002: 3 in. 1 short (Missing). — One lost', end($short['events'])['note']);

        // Reconciliation: one waiting; the approver closes it with a note.
        $recon = $this->as($this->manager)->getJson('/api/v1/gate-pass/passes?list=recon')->assertOk()->json('data');
        $this->assertSame([1, 'SHORT'], [$recon['short_waiting'], $recon['rows'][0]['return']]);
        $this->as($this->manager)->postJson("/api/v1/gate-pass/passes/{$no}/close", [])->assertUnprocessable()->assertJsonPath('errors.note.0', 'An approval note is required.');
        $closed = $this->as($this->manager)->postJson("/api/v1/gate-pass/passes/{$no}/close", ['note' => 'Accepted'])->assertOk()->json('data');
        $this->assertSame(['CLOSED', 'SHORT'], [$closed['status'], $closed['return']]);
        $this->assertSame(
            ['CREATED', 'SUBMITTED', 'APPROVED', 'SENT_BACK', 'SUBMITTED', 'APPROVED', 'OUT', 'IN', 'IN', 'CLOSED'],
            array_column($closed['events'], 'action'),
        );
        $this->assertSame([$no], array_column($this->as($this->keeper)->getJson('/api/v1/gate-pass/passes?list=in-today')->json('data.rows'), 'gp_no'));
        $this->assertSame([], $this->as($this->keeper)->getJson('/api/v1/gate-pass/passes?list=in-await')->json('data.rows'));
        $this->as($this->keeper)->getJson('/api/v1/gate-pass/passes?list=approval')->assertForbidden();
    }

    public function test_main_and_out_only_passes_reject_and_cancel(): void
    {
        $options = $this->as($this->keeper)->getJson('/api/v1/gate-pass/options')->json('data');
        $mainCat = collect($options['categories'])->firstWhere('kind', 'MAIN')['value'];
        $itemCat = collect($options['categories'])->firstWhere('kind', 'ITEM')['value'];
        $purpose = $options['purposes'][0]['value'];
        $employee = $this->employee('10235', 'Md. Ripon');

        // The carrier, found by card number.
        $this->as($this->keeper)->getJson('/api/v1/gate-pass/employees/99999')->assertUnprocessable()->assertJsonPath('errors.card_no.0', 'No employee with card no. 99999 was found.');
        $found = $this->as($this->keeper)->getJson('/api/v1/gate-pass/employees/10235')->assertOk()->json('data');
        $this->assertSame(['Md. Ripon', 'Store', 'Receive Man'], [$found['name'], $found['department'], $found['designation']]);

        // A Main pass: the employee is the carrier; no items, buyer or vehicle.
        $main = ['gp_date' => now()->toDateString(), 'category_id' => $mainCat, 'pass_type' => 'RETURNABLE', 'purpose_id' => $purpose, 'destination_text' => 'Bank', 'lines' => [], 'submit' => true];
        $this->as($this->keeper)->postJson('/api/v1/gate-pass/passes', $main)->assertUnprocessable()->assertJsonPath('errors.employee_id.0', 'Employee Card No. is required.');
        $pass = $this->as($this->keeper)->postJson('/api/v1/gate-pass/passes', $main + ['employee_id' => $found['id'], 'vehicle_no' => 'X', 'carrier_name' => 'Ignored'])->assertCreated()->json('data');
        $this->assertSame(['PENDING', 'EMPLOYEE', 'Md. Ripon', null, 'Bank'], [$pass['status'], $pass['carrier_type'], $pass['carrier_name'], $pass['vehicle_no'], $pass['destination']]);
        $this->as($this->manager)->postJson("/api/v1/gate-pass/passes/{$pass['gp_no']}/approve")->assertOk();
        $this->as($this->keeper)->postJson("/api/v1/gate-pass/passes/{$pass['gp_no']}/out")->assertOk()->assertJsonPath('data.status', 'OUT');
        $this->as($this->keeper)->postJson("/api/v1/gate-pass/passes/{$pass['gp_no']}/in", [])->assertOk()->assertJsonPath('data.status', 'CLOSED')->assertJsonPath('data.return', 'FULL');

        // OUT ONLY: complete at the gate; it never comes back in.
        $once = ['gp_date' => now()->toDateString(), 'category_id' => $itemCat, 'pass_type' => 'NON_RETURNABLE', 'purpose_id' => $purpose, 'location_id' => $this->location, 'carrier_type' => 'EMPLOYEE',
            'employee_id' => $employee, 'expected_return' => '2020-01-01', 'lines' => [['item_id' => $this->box, 'unit' => 'CTN', 'quantity' => 10]], 'submit' => true];
        $outOnly = $this->as($this->keeper)->postJson('/api/v1/gate-pass/passes', $once)->assertCreated()->json('data');
        $this->assertNull($outOnly['expected_return']);
        $this->as($this->owner)->postJson("/api/v1/gate-pass/passes/{$outOnly['gp_no']}/approve")->assertOk();
        $this->as($this->keeper)->postJson("/api/v1/gate-pass/passes/{$outOnly['gp_no']}/out")->assertOk()->assertJsonPath('data.status', 'COMPLETED')->assertJsonPath('data.return', null);
        $this->as($this->keeper)->postJson("/api/v1/gate-pass/passes/{$outOnly['gp_no']}/in", [])->assertUnprocessable()->assertJsonValidationErrors('pass');

        // Reject needs a reason; a preparer cancels their own pending pass, not an approved one.
        $rejectMe = $this->as($this->keeper)->postJson('/api/v1/gate-pass/passes', $once)->assertCreated()->json('data.gp_no');
        $this->as($this->manager)->postJson("/api/v1/gate-pass/passes/{$rejectMe}/reject", [])->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Give a reason.');
        $this->as($this->manager)->postJson("/api/v1/gate-pass/passes/{$rejectMe}/reject", ['reason' => 'Wrong destination'])->assertOk()->assertJsonPath('data.status', 'REJECTED');
        $mine = $this->as($this->keeper)->postJson('/api/v1/gate-pass/passes', $once)->assertCreated()->json('data.gp_no');
        $this->as($this->keeper)->postJson("/api/v1/gate-pass/passes/{$mine}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $approved = $this->as($this->keeper)->postJson('/api/v1/gate-pass/passes', $once)->assertCreated()->json('data.gp_no');
        $this->as($this->manager)->postJson("/api/v1/gate-pass/passes/{$approved}/approve")->assertOk();
        $this->as($this->keeper)->postJson("/api/v1/gate-pass/passes/{$approved}/cancel")->assertForbidden();
        $this->as($this->manager)->postJson("/api/v1/gate-pass/passes/{$approved}/cancel", ['reason' => 'Not needed'])->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->as($this->manager)->postJson("/api/v1/gate-pass/passes/{$approved}/cancel")->assertUnprocessable();
        $this->as($this->keeper)->getJson('/api/v1/gate-pass/passes/GP-1999-000001')->assertUnprocessable()->assertJsonPath('errors.pass.0', 'Gate pass GP-1999-000001 was not found.');
    }

    public function test_the_dashboard_the_list_and_the_reports(): void
    {
        $options = $this->as()->getJson('/api/v1/gate-pass/options')->json('data');
        $itemCat = collect($options['categories'])->firstWhere('kind', 'ITEM')['value'];
        $washing = collect($options['purposes'])->firstWhere('label', 'Washing')['value'];
        $repair = collect($options['purposes'])->firstWhere('label', 'Repair')['value'];
        $employee = $this->employee('10240', 'Asia Begum');
        $make = function (array $over) use ($itemCat, $washing): array {
            $pass = $this->as()->postJson('/api/v1/gate-pass/passes', $over + [
                'gp_date' => now()->toDateString(), 'category_id' => $itemCat, 'pass_type' => 'RETURNABLE', 'purpose_id' => $washing, 'location_id' => $this->location,
                'expected_return' => now()->addDays(3)->toDateString(), 'carrier_type' => 'NON_EMPLOYEE', 'carrier_name' => 'Rafiq', 'carrier_mobile' => '01700000000',
                'lines' => [['item_id' => $this->item, 'unit' => 'PCS', 'quantity' => 10]], 'submit' => true,
            ])->assertCreated()->json('data');
            $this->as()->postJson("/api/v1/gate-pass/passes/{$pass['gp_no']}/approve")->assertOk();

            return $this->as()->postJson("/api/v1/gate-pass/passes/{$pass['gp_no']}/out")->assertOk()->json('data');
        };

        // One overdue, one partly back, one short and closed, one OUT ONLY, one still pending approval.
        $late = $make(['gp_date' => now()->subDays(10)->toDateString(), 'expected_return' => now()->subDays(4)->toDateString()]);
        $part = $make([]);
        $this->as()->postJson("/api/v1/gate-pass/passes/{$part['gp_no']}/in", ['lines' => [['line_id' => $part['lines'][0]['id'], 'quantity' => 4]]])->assertOk();
        $short = $make(['purpose_id' => $repair, 'carrier_type' => 'EMPLOYEE', 'employee_id' => $employee]);
        $this->as()->postJson("/api/v1/gate-pass/passes/{$short['gp_no']}/in", ['lines' => [['line_id' => $short['lines'][0]['id'], 'quantity' => 8]], 'final' => true, 'short_reason' => 'DAMAGE'])->assertOk();
        $this->as()->postJson("/api/v1/gate-pass/passes/{$short['gp_no']}/close", ['note' => 'Accepted'])->assertOk();
        $make(['pass_type' => 'NON_RETURNABLE', 'lines' => [['item_id' => $this->box, 'unit' => 'CTN', 'quantity' => 3]]]);
        $this->as()->postJson('/api/v1/gate-pass/passes', ['gp_date' => now()->toDateString(), 'category_id' => $itemCat, 'pass_type' => 'RETURNABLE', 'lines' => []])->assertCreated();

        $dash = $this->as($this->keeper)->getJson('/api/v1/gate-pass/dashboard')->assertOk()->json('data');
        $this->assertSame(['total' => 5, 'today_out' => 4, 'today_in' => 2, 'pending_return' => 1, 'partial_return' => 1, 'short' => 1, 'overdue' => 1, 'closed' => 2], $dash['tiles']);
        $this->assertSame(['total' => 3, 'FULL' => 0, 'PARTIAL' => 1, 'SHORT' => 1, 'PENDING' => 1], $dash['returns']);
        $this->assertSame(['date' => now()->toDateString(), 'out' => 4, 'in' => 2], end($dash['movement']));
        $this->assertCount(14, $dash['movement']);
        $this->assertSame([['label' => 'Washing', 'count' => 3], ['label' => 'Repair', 'count' => 1]], $dash['purposes']);
        $this->assertSame([$late['gp_no']], array_column($dash['overdue'], 'gp_no'));
        $this->assertCount(5, $dash['latest']);

        // Gate Pass List and its filters.
        $list = fn (string $q = '') => array_column($this->as($this->keeper)->getJson("/api/v1/gate-pass/passes?list=all{$q}")->json('data.rows'), 'gp_no');
        $this->assertCount(5, $list());
        $this->assertSame([$short['gp_no']], $list('&q=asia'));
        $this->assertSame([$short['gp_no']], $list('&employee=10240'));
        $this->assertCount(1, $list('&item=carton'));
        $this->assertSame([$part['gp_no']], $list('&status=PARTIAL'));
        $this->assertCount(3, $list('&purpose='.$washing));
        $this->assertCount(4, $list('&from='.now()->toDateString()));
        $this->as($this->keeper)->get('/api/v1/gate-pass/passes/excel')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Reports.
        $report = fn (string $key, string $q = '') => $this->as($this->keeper)->getJson("/api/v1/gate-pass/reports/{$key}{$q}")->assertOk()->json('data');
        $register = $report('register');
        $this->assertCount(4, $register['rows']);
        $this->assertEquals(['out_qty' => 33, 'returned' => 12, 'short' => 2], $register['total']);
        $this->assertSame('Delta Apparels Ltd · 4 rows', $register['subtitle']);
        $this->assertSame('Denim Trouser (10 Pcs)', $register['rows'][0]['items']);
        $this->assertCount(4, $report('out')['rows']);
        $this->assertEquals([['Partial', 4], ['Final — short (Damage)', 8]], array_map(fn ($r) => [$r['entry'], $r['in_qty']], $report('in')['rows']));
        $this->assertEquals(['out_qty' => 20, 'returned' => 4, 'balance' => 16], $report('pending')['total']);
        $overdue = $report('overdue')['rows'];
        $this->assertSame([$late['gp_no'], 4], [$overdue[0]['gp_no'], $overdue[0]['days_overdue']]);
        $this->assertEquals([[$short['gp_no'], 10, 8, 2, 'Damage', 'Closed']], array_map(fn ($r) => [$r['gp_no'], $r['out_qty'], $r['returned'], $r['short'], $r['reason'], $r['status']], $report('short')['rows']));
        $this->assertEquals([['Star Washing', 'Washing Factory', 4, 33, 12, 2, 16]], array_map(fn ($r) => array_values($r), $report('location')['rows']));
        $this->assertEquals([['Washing', 3], ['Repair', 1]], array_map(fn ($r) => [$r['purpose'], $r['passes']], $report('purpose')['rows']));
        $this->assertEquals([['10240', 'Asia Begum', 1, 10, 8, 2, 0]], array_map(fn ($r) => [$r['card_no'], $r['employee'], $r['passes'], $r['out_qty'], $r['returned'], $r['short'], $r['balance']], $report('employee')['rows']));
        $this->assertEquals([['GPI-001', 3, 30], ['GPI-002', 1, 3]], array_map(fn ($r) => [$r['item_code'], $r['passes'], $r['out_qty']], $report('item')['rows']));
        $this->assertCount(1, $report('register', '?purpose='.$repair)['rows']);
        $this->assertCount(1, $report('register', '?type=NON_RETURNABLE')['rows']);
        $this->as($this->keeper)->get('/api/v1/gate-pass/reports/register/excel')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $pdf = $this->as($this->keeper)->get('/api/v1/gate-pass/reports/register/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->as($this->keeper)->getJson('/api/v1/gate-pass/reports/nothing')->assertNotFound();
    }

    /** An employee on the books, posted to the store. */
    private function employee(string $card, string $name): string
    {
        $worker = collect($this->as()->getJson('/api/v1/hr-payroll/employees/options')->json('data.structures'))->firstWhere('label', 'Worker — RMG Minimum Wage 2023 (Non-EPZ)');
        $this->as()->postJson('/api/v1/hr-payroll/employees', ['card_no' => $card, 'name' => $name, 'nid' => '1234567890', 'mobile' => '01712345678', 'structure' => $worker['value'], 'gross_a' => 14000, 'gross_b' => 18000])->assertCreated();
        $id = DB::table('hr_employees')->where('company_id', $this->delta->id)->where('card_no', $card)->value('id');
        $row = fn (string $table, array $data): string => tap((string) Str::ulid(), fn (string $key) => DB::table($table)->insert(['id' => $key, 'company_id' => $this->delta->id, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()] + $data));
        $factory = DB::table('factories')->where('company_id', $this->delta->id)->value('id');
        $dept = DB::table('departments')->where('company_id', $this->delta->id)->where('code', 'STR')->value('id') ?? $row('departments', ['factory_id' => $factory, 'name' => 'Store', 'code' => 'STR']);
        $desig = DB::table('designations')->where('company_id', $this->delta->id)->where('code', 'RCV')->value('id') ?? $row('designations', ['department_id' => $dept, 'name' => 'Receive Man', 'code' => 'RCV']);
        DB::table('hr_employees')->where('id', $id)->update(['department_id' => $dept, 'designation_id' => $desig]);

        return $id;
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
