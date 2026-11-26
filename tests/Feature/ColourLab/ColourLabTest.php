<?php

declare(strict_types=1);

namespace Tests\Feature\ColourLab;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\ColourLab\Models\LabCertificate;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Colour Lab → Operations (AnnotechERP 16/01): a certificate is filed with
 * its type, equipment, dates in order and real addresses; the issue register
 * numbers its entries for the month and keeps a certificate it has issued;
 * the reminder mails once per Valid Date; a trial starts, completes only
 * when every line has its result, and then reads only; a fabric test is
 * submitted with its summary, and approved or rejected and re-submitted.
 */
class ColourLabTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $floor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $unit = Factory::query()->first();
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Lab Block', 'code' => 'LB']);
        $this->floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Colour Lab', 'code' => 'LAB-1'])->id;
    }

    public function test_certificates_register_and_reminder(): void
    {
        $today = now('Asia/Dhaka')->toDateString();
        $ym = now('Asia/Dhaka')->format('Ym');
        $type = $this->as()->postJson('/api/v1/erp/lab-cert-types', ['code' => 'OTS', 'name' => 'OEKO-TEX Standard 100', 'issuing_body' => 'OEKO-TEX'])->assertCreated()->json('data.id');
        $this->as()->postJson('/api/v1/erp/lab-cert-types', ['code' => 'ots', 'name' => 'Again'])->assertCreated();
        $this->as()->postJson('/api/v1/erp/lab-cert-types', ['code' => 'OTS', 'name' => 'Again'])->assertUnprocessable()->assertJsonPath('errors.code.0', 'That Code is already in use.');
        $equipment = $this->as()->postJson('/api/v1/erp/lab-equipment', ['code' => 'SPEC-1', 'name' => 'Spectrophotometer', 'manufacturer' => 'Datacolor', 'serial_no' => 'DC-650'])->assertCreated()->json('data.id');

        // The certificate: every starred box, dates in order, real addresses.
        $this->as()->postJson('/api/v1/erp/lab-certificates', [])->assertUnprocessable()->assertJsonPath('errors.cert_type_id.0', 'Certification Type is required.');
        $cert = ['cert_type_id' => $type, 'equipment_id' => $equipment, 'document_ref' => 'OTS-2026-118', 'upload_doc' => 'OEKO-TEX certificate 2026.pdf', 'apply_date' => $today,
            'applied_by' => $this->owner->id, 'issue_date' => $today, 'valid_date' => now('Asia/Dhaka')->addDays(10)->toDateString(), 'mail_to' => 'lab@delta.test, qa@delta.test', 'mail_cc' => 'gm@delta.test',
            'notify_days' => 30, 'lab_floor_id' => $this->floor];
        $this->as()->postJson('/api/v1/erp/lab-certificates', ['issue_date' => now()->subDay()->toDateString()] + $cert)->assertUnprocessable()
            ->assertJsonPath('errors.issue_date.0', 'Issue Date cannot be before the Apply Date.');
        $this->as()->postJson('/api/v1/erp/lab-certificates', ['valid_date' => $today] + $cert)->assertUnprocessable()->assertJsonPath('errors.valid_date.0', 'Valid Date must come after the Issue Date.');
        $this->as()->postJson('/api/v1/erp/lab-certificates', ['mail_to' => 'lab@delta.test, not-an-address'] + $cert)->assertUnprocessable()
            ->assertJsonPath('errors.mail_to.0', 'not-an-address is not an email address.');
        $id = $this->as()->postJson('/api/v1/erp/lab-certificates', $cert)->assertCreated()->assertJsonPath('data.cert_type', 'OEKO-TEX Standard 100 — OEKO-TEX')
            ->assertJsonPath('data.mail_to', 'lab@delta.test, qa@delta.test')->json('data.id');
        $this->as()->postJson('/api/v1/erp/lab-certificates', $cert)->assertUnprocessable()->assertJsonPath('errors.document_ref.0', 'That Document Ref # is already filed.');

        // The register: numbered for the month, dates not before the entry.
        $entry = ['certificate_id' => $id, 'entry_date' => $today, 'issued_to' => 'SGS Bangladesh — audit team', 'copies' => 2];
        $this->as()->postJson('/api/v1/erp/lab-cert-register', ['entry_date' => $today])->assertUnprocessable()->assertJsonPath('errors.certificate_id.0', 'Certificate is required.');
        $this->as()->postJson('/api/v1/erp/lab-cert-register', ['due_date' => now()->subDay()->toDateString()] + $entry)->assertUnprocessable()
            ->assertJsonPath('errors.due_date.0', 'Due Date cannot be before the Entry Date.');
        $first = $this->as()->postJson('/api/v1/erp/lab-cert-register', $entry)->assertCreated()->assertJsonPath('data.action', 'ISSUED')->json('data');
        $this->assertSame("CIR-{$ym}0001", $first['register_no']);
        $this->assertSame('OEKO-TEX Standard 100 · OTS-2026-118', $first['certificate']);
        $this->as()->postJson('/api/v1/erp/lab-cert-register', ['action' => 'RETURNED', 'return_date' => $today] + $entry)->assertCreated()->assertJsonPath('data.register_no', "CIR-{$ym}0002");
        $this->as()->patchJson("/api/v1/erp/lab-cert-register/{$first['id']}", ['register_no' => 'X-1', 'copies' => 3] + $entry)->assertOk()
            ->assertJsonPath('data.register_no', "CIR-{$ym}0001")->assertJsonPath('data.copies', 3);
        $this->as()->deleteJson("/api/v1/erp/lab-certificates/{$id}")->assertUnprocessable()->assertJsonValidationErrors('certificate');

        // The reminder: once, Notify BF Days before the Valid Date; a new Valid Date earns a fresh one.
        Mail::fake();
        $this->artisan('colour-lab:certificate-reminders')->expectsOutput('1 certificate reminder(s) sent.')->assertSuccessful();
        $this->artisan('colour-lab:certificate-reminders')->expectsOutput('0 certificate reminder(s) sent.')->assertSuccessful();
        $this->assertSame($cert['valid_date'], LabCertificate::withoutGlobalScope(TenantScope::class)->find($id)->notified_for->toDateString());
        $this->as()->patchJson("/api/v1/erp/lab-certificates/{$id}", ['valid_date' => now()->addDays(20)->toDateString()] + $cert)->assertOk();
        $this->assertNull(LabCertificate::withoutGlobalScope(TenantScope::class)->find($id)->notified_for);
        $this->artisan('colour-lab:certificate-reminders')->expectsOutput('1 certificate reminder(s) sent.')->assertSuccessful();
        $this->as()->patchJson("/api/v1/erp/lab-certificates/{$id}", ['valid_date' => now()->addDays(90)->toDateString()] + $cert)->assertOk();
        $this->artisan('colour-lab:certificate-reminders')->expectsOutput('0 certificate reminder(s) sent.')->assertSuccessful();

        $this->as()->getJson('/api/v1/colour-lab/options')->assertOk()->assertJsonPath('data.certificates.0.label', 'OEKO-TEX Standard 100 · OTS-2026-118')
            ->assertJsonPath('data.floors.0.label', 'Colour Lab (LAB-1)');
    }

    public function test_trial_workbench(): void
    {
        $today = now('Asia/Dhaka')->toDateString();
        $ym = now('Asia/Dhaka')->format('Ym');
        $kg = UnitOfMeasure::availableTo($this->delta->id)->where('code', 'KG')->value('id');
        $dept = ItemDepartment::query()->create(['company_id' => $this->delta->id, 'code' => 'DCS', 'name' => 'Dyes Chemical Store', 'active' => true])->id;
        $item = Item::query()->create(['company_id' => $this->delta->id, 'item_department_id' => $dept, 'item_code' => 'DC-0003', 'name' => 'Acetic Acid', 'uom_id' => $kg, 'brand' => 'BASF', 'active' => true])->id;
        $this->as()->getJson("/api/v1/colour-lab/items?department={$dept}")->assertOk()->assertJsonPath('data.0.label', 'Acetic Acid (DC-0003)')->assertJsonPath('data.0.brand', 'BASF');

        $head = ['lab_floor_id' => $this->floor, 'trial_date' => $today, 'trial_by' => $this->owner->id];
        $this->as()->postJson('/api/v1/colour-lab/trials', $head + ['lines' => []])->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A trial with no dye or chemical on it tries nothing. Add a row before saving.');
        $line = ['item_department_id' => $dept, 'item_id' => $item, 'claim_dose' => 1.5, 'dose_unit' => 'g/l', 'claim_cost' => 0.42, 'claim_ratio' => '1:8'];
        $this->as()->postJson('/api/v1/colour-lab/trials', $head + ['lines' => [['claim_ratio' => '8'] + $line]])->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), 'Line 1: a liquor ratio is written 1:N'));
        $no = $this->as()->postJson('/api/v1/colour-lab/trials', $head + ['lines' => [$line]])->assertCreated()->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.lines.0.result', 'PENDING')->json('data.trial_no');
        $this->assertSame("TRL-{$ym}0001", $no);

        $go = fn (string $action) => $this->as()->postJson("/api/v1/colour-lab/trials/{$no}/{$action}");
        $go('complete')->assertUnprocessable();
        $go('start')->assertOk()->assertJsonPath('data.status', 'IN_PROGRESS');
        $go('complete')->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Line 1 is still pending — give every line its result before completing the trial.');
        $this->as()->putJson("/api/v1/colour-lab/trials/{$no}", $head + ['lines' => [['actual_dose' => 1.2, 'actual_ratio' => '1:7', 'result' => 'PASS'] + $line]])->assertOk();
        $this->as()->deleteJson("/api/v1/colour-lab/trials/{$no}")->assertUnprocessable();
        $go('complete')->assertOk()->assertJsonPath('data.status', 'COMPLETED');
        $this->as()->putJson("/api/v1/colour-lab/trials/{$no}", $head + ['lines' => [$line]])->assertUnprocessable()->assertJsonPath('errors.status.0', "{$no} is Completed, so it reads only.");
        $this->as()->getJson('/api/v1/colour-lab/trials?status=COMPLETED')->assertJsonPath('data.0.passed', 1)->assertJsonPath('data.0.lab', 'Colour Lab (LAB-1)');

        $other = $this->as()->postJson('/api/v1/colour-lab/trials', $head + ['lines' => [$line]])->assertCreated()->json('data.trial_no');
        $this->as()->postJson("/api/v1/colour-lab/trials/{$other}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $third = $this->as()->postJson('/api/v1/colour-lab/trials', $head + ['lines' => [$line]])->assertCreated()->json('data.trial_no');
        $this->as()->deleteJson("/api/v1/colour-lab/trials/{$third}")->assertOk();
        $this->as()->getJson('/api/v1/colour-lab/trials')->assertJsonCount(2, 'data');
    }

    public function test_fabric_tests_and_who_may_work_the_lab(): void
    {
        $today = now('Asia/Dhaka')->toDateString();
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $other = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'KIA', 'name' => 'Kiabi', 'short_name' => 'Kiabi', 'pi_address' => 'Lille', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000060', 'job_no' => '7701', 'style_no' => '770MR', 'buyer_id' => $buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;

        $test = ['test_date' => $today, 'buyer_id' => $buyer, 'order_id' => $order, 'style_no' => '770MR', 'colour' => 'Black', 'lot_no' => 'B-118', 'qty' => 25, 'drying' => 'TUMBLE',
            'std_dia' => 72, 'act_dia' => 71.5, 'std_gsm' => 180, 'act_gsm' => 176, 'shrink_l' => -4.5, 'shrink_w' => -3, 'spirality' => 3.5, 'stitch_length' => 2.75,
            'wash' => '4-5', 'rub_dry' => '4', 'rub_wet' => '3-4', 'rh' => 65];
        $this->as()->postJson('/api/v1/colour-lab/fabric-tests', ['test_date' => $today])->assertUnprocessable()->assertJsonPath('errors.buyer_id.0', 'Customer is required.');
        $this->as()->postJson('/api/v1/colour-lab/fabric-tests', ['buyer_id' => $other] + $test)->assertUnprocessable()->assertJsonPath('errors.order_id.0', "That order is not this customer's.");
        $this->as()->postJson('/api/v1/colour-lab/fabric-tests', ['wash' => '6'] + $test)->assertUnprocessable()->assertJsonValidationErrors('wash');
        $no = $this->as()->postJson('/api/v1/colour-lab/fabric-tests', $test)->assertCreated()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.customer', 'LPP')
            ->assertJsonPath('data.order_no', 'LPP-2026-000060')->json('data.test_no');

        $go = fn (string $action, array $body = []) => $this->as()->postJson("/api/v1/colour-lab/fabric-tests/{$no}/{$action}", $body);
        $go('submit')->assertUnprocessable()->assertJsonPath('errors.summary.0', 'Give the Summary before submitting the test.');
        $this->as()->putJson("/api/v1/colour-lab/fabric-tests/{$no}", ['summary' => 'FAIL'] + $test)->assertOk();
        $go('approve')->assertUnprocessable();
        $go('submit')->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $this->as()->putJson("/api/v1/colour-lab/fabric-tests/{$no}", ['summary' => 'PASS'] + $test)->assertUnprocessable()->assertJsonValidationErrors('status');
        $go('reject', ['note' => 'Spirality over the buyer limit'])->assertOk()->assertJsonPath('data.status', 'REJECTED')->assertJsonPath('data.decision_note', 'Spirality over the buyer limit');
        $this->as()->putJson("/api/v1/colour-lab/fabric-tests/{$no}", ['summary' => 'PASS', 'spirality' => 2.5] + $test)->assertOk();
        $go('submit')->assertOk();
        $go('approve')->assertOk()->assertJsonPath('data.status', 'APPROVED')->assertJsonPath('data.decided_by', 'Owner');
        $this->as()->deleteJson("/api/v1/colour-lab/fabric-tests/{$no}")->assertUnprocessable();

        // A line chief neither sees nor works the lab.
        $chief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test');
        $this->as($chief)->getJson('/api/v1/colour-lab/fabric-tests')->assertForbidden();
        $this->as($chief)->getJson('/api/v1/erp/lab-certificates')->assertForbidden();
    }

    public function test_lab_reports(): void
    {
        $today = now('Asia/Dhaka')->toDateString();
        $c = $this->delta->id;
        $buyer = Buyer::query()->create(['company_id' => $c, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $test = ['test_date' => $today, 'buyer_id' => $buyer, 'lab_floor_id' => $this->floor, 'style_no' => '770MR', 'lot_no' => 'B-1', 'std_dia' => 72, 'act_dia' => 71.5, 'std_gsm' => 180,
            'act_gsm' => 176, 'shrink_l' => -4.5, 'shrink_w' => -3, 'wash' => '4-5', 'light' => '4', 'perspiration' => '3-4'];
        $this->as()->postJson('/api/v1/colour-lab/fabric-tests', ['summary' => 'PASS'] + $test)->assertCreated();
        $this->as()->postJson('/api/v1/colour-lab/fabric-tests', ['summary' => 'FAIL', 'remarks' => 'Spirality 6%'] + $test)->assertCreated();
        $this->as()->postJson('/api/v1/colour-lab/fabric-tests', $test)->assertCreated();

        $kg = UnitOfMeasure::availableTo($c)->where('code', 'KG')->value('id');
        $dept = ItemDepartment::query()->create(['company_id' => $c, 'code' => 'DCS', 'name' => 'Dyes Chemical Store', 'active' => true])->id;
        $item = Item::query()->create(['company_id' => $c, 'item_department_id' => $dept, 'item_code' => 'DC-0003', 'name' => 'Acetic Acid', 'uom_id' => $kg, 'active' => true])->id;
        $head = ['lab_floor_id' => $this->floor, 'trial_date' => $today, 'trial_by' => $this->owner->id];
        $line = ['item_id' => $item, 'claim_dose' => 1.5, 'dose_unit' => 'g/l', 'sample_qty' => 5];
        $this->as()->postJson('/api/v1/colour-lab/trials', $head + ['lines' => [['result' => 'PASS', 'actual_dose' => 1.2] + $line, ['result' => 'FAIL'] + $line]])->assertCreated();
        $open = $this->as()->postJson('/api/v1/colour-lab/trials', $head + ['lines' => [$line]])->assertCreated()->json('data.trial_no');
        $this->as()->postJson("/api/v1/colour-lab/trials/{$open}/start")->assertOk();

        $type = $this->as()->postJson('/api/v1/erp/lab-cert-types', ['code' => 'OTS', 'name' => 'OEKO-TEX Standard 100', 'issuing_body' => 'OEKO-TEX'])->json('data.id');
        $equipment = $this->as()->postJson('/api/v1/erp/lab-equipment', ['code' => 'SPEC-1', 'name' => 'Spectrophotometer'])->json('data.id');
        $cert = ['cert_type_id' => $type, 'equipment_id' => $equipment, 'upload_doc' => 'cert.pdf', 'apply_date' => $today, 'applied_by' => $this->owner->id, 'issue_date' => $today,
            'notify_days' => 30, 'lab_floor_id' => $this->floor];
        $due = $this->as()->postJson('/api/v1/erp/lab-certificates', ['document_ref' => 'OTS-1', 'valid_date' => now('Asia/Dhaka')->addDays(10)->toDateString()] + $cert)->assertCreated()->json('data.id');
        $this->as()->postJson('/api/v1/erp/lab-certificates', ['document_ref' => 'OTS-2', 'valid_date' => now()->addDays(200)->toDateString()] + $cert)->assertCreated();
        $this->as()->postJson('/api/v1/erp/lab-cert-register', ['certificate_id' => $due, 'entry_date' => $today, 'issued_to' => 'SGS'])->assertCreated();

        $get = fn (string $r) => $this->as()->getJson("/api/v1/colour-lab/reports/{$r}")->assertOk()->json('data');
        $this->assertCount(3, $get('fabric-test-register')['rows']);
        $variance = $get('dia-gsm-variance')['rows'][0];
        $this->assertSame(['72 / 71.5 (-0.5)', '180 / 176 (-4)', '-4.5 / -3'], [$variance['dia'], $variance['gsm'], $variance['shrinkage']]);
        $this->assertSame(['4', '3-4'], [$get('colour-fastness')['rows'][0]['light'], $get('colour-fastness')['rows'][0]['perspiration']]);
        $failed = $get('failed-conditional');
        $this->assertSame([['FAIL', 'Spirality 6%']], array_map(fn ($r) => [$r['result'], $r['remarks']], $failed['rows']));
        $this->assertSame(['key' => 'result', 'type' => 'summary'], $failed['columns'][4]);
        $rate = $get('buyer-pass-rate')['rows'][0];
        $this->assertEquals([3, 1, 1, 1, 50.0], [$rate['tested'], $rate['passed'], $rate['failed'], $rate['pending'], $rate['pass_rate']]);

        $register = collect($get('trial-register')['rows'])->firstWhere('chemicals', 2);
        $this->assertSame(['FAIL', 10.0], [$register['result'], (float) $register['batch']]);
        $this->assertCount(3, $get('trial-recipe')['rows']);
        $usage = $get('chemical-usage')['rows'][0];
        $this->assertSame([2, 15.0], [$usage['trials'], (float) $usage['total_qty']]);
        $awaiting = $get('trials-awaiting')['rows'];
        // The trial never started waits on Start; the started one on its lines' results.
        $this->assertEqualsCanonicalizing([[$open, 'RESULTS'], [str_replace('0002', '0001', $open), 'START']], array_map(fn ($r) => [$r['trial_no'], $r['waiting']], $awaiting));

        $this->assertSame(['DUE', 'VALID'], array_column($get('certification-register')['rows'], 'cert_status'));
        $renewal = $get('renewal-due')['rows'];
        $this->assertSame([['OTS-1', 10]], array_map(fn ($r) => [$r['document_ref'], $r['falls_due']], $renewal));
        $this->assertSame('SGS', $get('certificate-movement')['rows'][0]['issued_to']);
        $lab = $get('lab-master')['rows'][0];
        $this->assertSame(['LAB-1', 2, 3], [$lab['code'], $lab['trials'], $lab['tests']]);

        $this->as()->getJson('/api/v1/colour-lab/reports/nope')->assertNotFound();
        $chief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test');
        $this->as($chief)->getJson('/api/v1/colour-lab/reports/lab-master')->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
