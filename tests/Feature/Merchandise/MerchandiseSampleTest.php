<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\LabdipShade;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\SampleLine;
use App\Modules\Merchandise\Models\Size;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Section;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Merchandise Desk → Lab Dip & Sample (AnnotechERP 06/10): Lab Dip
 * Programme — requests numbered as they are raised, sent to an internal
 * section or a nominated house, and the shades on each kept across saves —
 * and the sample programmes and print design sheets of the same desk.
 */
class MerchandiseSampleTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $factory;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_a_lab_dip_program_is_numbered_and_sent_to_a_section_or_a_nominated_house(): void
    {
        [$buyer, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $lab = $this->section();
        $house = MaterialSupplier::query()->create(['code' => 'DH1', 'name' => 'Colour House Ltd', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;

        $this->as()->postJson('/api/v1/erp/merch-labdip-programs', ['buyer_id' => $buyer])->assertUnprocessable()
            ->assertJsonPath('errors.request_date.0', 'Date of Request is required.')->assertJsonPath('errors.buyer_office_id.0', 'Buyer Acc is required.')
            ->assertJsonPath('errors.request_to.0', 'Request To is required.')->assertJsonPath('errors.request_by.0', 'Request By is required.');
        $base = ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'request_date' => '2026-10-13', 'request_by' => $this->owner->id];
        $this->as()->postJson('/api/v1/erp/merch-labdip-programs', $base + ['request_to' => 'SECTION:01aaaaaaaaaaaaaaaaaaaaaaaa'])->assertUnprocessable()->assertJsonValidationErrors('request_to');

        $first = $this->as()->postJson('/api/v1/erp/merch-labdip-programs', $base + ['request_to' => "SECTION:{$lab}", 'remarks' => ' rush '])->assertCreated()->json('data');
        $this->assertSame(['LD-'.now()->format('Y').'-000001', 'DRAFT', "SECTION:{$lab}", 'Dyeing Lab', 'LPP', 'rush', 0], [$first['program_no'], $first['status'], $first['request_to'], $first['request_to_name'], $first['buyer'], $first['remarks'], $first['shades']]);
        $this->assertSame([$lab, null], [$first['request_section_id'], $first['request_supplier_id']]);

        $second = $this->as()->postJson('/api/v1/erp/merch-labdip-programs', $base + ['request_to' => "SUPPLIER:{$house}", 'status' => 'SENT'])->assertCreated()->json('data');
        $this->assertSame(['LD-'.now()->format('Y').'-000002', 'Colour House Ltd', null, $house], [$second['program_no'], $second['request_to_name'], $second['request_section_id'], $second['request_supplier_id']]);

        // Edited, the lab moves from the house to the section and the number stays.
        $edited = $this->as()->patchJson("/api/v1/erp/merch-labdip-programs/{$second['id']}", ['request_to' => "SECTION:{$lab}"])->assertOk()->json('data');
        $this->assertSame([$second['program_no'], $lab, null], [$edited['program_no'], $edited['request_section_id'], $edited['request_supplier_id']]);
        $this->as()->deleteJson("/api/v1/erp/merch-labdip-programs/{$first['id']}")->assertSuccessful();
        $this->assertSame([$second['program_no']], array_column($this->as()->getJson('/api/v1/erp/merch-labdip-programs')->json('data'), 'program_no'));
    }

    public function test_the_shades_on_a_program_keep_their_ids_across_saves(): void
    {
        [$buyer, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        [$other, $otherOffice] = $this->buyer('NIS', 'Nishimatsuya');
        $style = $this->style($buyer, $office, '637PK');
        $foreign = $this->style($other, $otherOffice, 'N-1');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => 'J1', 'style_id' => $style, 'total_qty' => 100])->assertCreated()->json('data');
        $program = $this->as()->postJson('/api/v1/erp/merch-labdip-programs', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'request_date' => '2026-10-13', 'request_to' => 'SECTION:'.$this->section(), 'request_by' => $this->owner->id])->assertCreated()->json('data.id');

        $data = $this->as()->getJson("/api/v1/merchandise/labdip-programs/{$program}/shades")->assertOk()->json('data');
        $this->assertSame([[], ['637PK'], [$order['order_no']]], [$data['rows'], array_map(fn ($s) => explode(' · ', $s['label'])[0], $data['styles']), array_map(fn ($o) => explode(' · ', $o['label'])[0], $data['orders'])]);
        $this->assertContains('D65', $data['light_sources']);

        $navy = ['colour' => ' Navy ', 'pantone' => '19-4052 TCX', 'style_id' => $style, 'order_id' => $order['id'], 'fabric' => 'S/J 160', 'light_source' => 'D65', 'target_date' => '2026-10-20'];
        $this->as()->putJson("/api/v1/merchandise/labdip-programs/{$program}/shades", ['rows' => [['colour' => '']]])->assertUnprocessable()->assertJsonValidationErrors(['rows.0.colour' => 'Every shade needs its colour.']);
        $this->as()->putJson("/api/v1/merchandise/labdip-programs/{$program}/shades", ['rows' => [['colour' => 'Red', 'style_id' => $foreign]]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.style_id');
        $this->as()->putJson("/api/v1/merchandise/labdip-programs/{$program}/shades", ['rows' => [['colour' => 'Red', 'light_source' => 'SUN']]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.light_source');
        $this->as()->putJson("/api/v1/merchandise/labdip-programs/{$program}/shades", ['rows' => [$navy, ['colour' => 'navy', 'style_id' => $style]]])->assertUnprocessable()->assertJsonValidationErrors('rows');

        $rows = $this->as()->putJson("/api/v1/merchandise/labdip-programs/{$program}/shades", ['rows' => [$navy, ['colour' => 'Ecru']]])->assertOk()->json('data.rows');
        $this->assertSame([['Navy', '637PK', $order['order_no'], 'D65', '2026-10-20'], ['Ecru', null, null, null, null]], array_map(fn ($r) => [$r['colour'], $r['style_no'], $r['order_no'], $r['light_source'], $r['target_date']], $rows));
        $this->assertSame(2, $this->as()->getJson("/api/v1/erp/merch-labdip-programs/{$program}")->json('data.shades'));

        // The navy shade's target moves and keeps its id; Ecru is dropped and Black added.
        $again = $this->as()->putJson("/api/v1/merchandise/labdip-programs/{$program}/shades", ['rows' => [['id' => $rows[0]['id'], 'target_date' => '2026-10-25'] + $navy, ['colour' => 'Black']]])->assertOk()->json('data.rows');
        $this->assertSame([[$rows[0]['id'], 'Navy', '2026-10-25'], ['Black']], [[$again[0]['id'], $again[0]['colour'], $again[0]['target_date']], [$again[1]['colour']]]);
        $this->assertNotSame($rows[1]['id'], $again[1]['id']);
        $this->as()->putJson("/api/v1/merchandise/labdip-programs/{$program}/shades", ['rows' => [['id' => $rows[1]['id'], 'colour' => 'Ecru']]])->assertUnprocessable()->assertJsonValidationErrors('rows');

        // Removing the program takes its shades along.
        $this->as()->deleteJson("/api/v1/erp/merch-labdip-programs/{$program}")->assertSuccessful();
        $this->as()->getJson("/api/v1/merchandise/labdip-programs/{$program}/shades")->assertNotFound();
    }

    public function test_a_shade_is_submitted_to_the_buyer_and_the_verdict_dated(): void
    {
        [$buyer, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $style = $this->style($buyer, $office, '637PK');
        $program = $this->as()->postJson('/api/v1/erp/merch-labdip-programs', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'request_date' => '2026-10-01', 'request_to' => 'SECTION:'.$this->section(), 'request_by' => $this->owner->id])->assertCreated()->json('data');
        $url = "/api/v1/merchandise/labdip-programs/{$program['id']}/shades";
        $rows = $this->as()->putJson($url, ['rows' => [['colour' => 'Navy', 'style_id' => $style, 'target_date' => '2026-10-10'], ['colour' => 'Ecru']]])->assertOk()->json('data.rows');
        $this->assertSame(['PENDING', null, null], [$rows[0]['status'], $rows[0]['submitted_on'], $rows[0]['decided_on']]);

        $navy = ['id' => $rows[0]['id'], 'colour' => 'Navy', 'style_id' => $style, 'target_date' => '2026-10-10'];
        $ecru = ['id' => $rows[1]['id'], 'colour' => 'Ecru', 'status' => 'PENDING'];
        // Anything but Pending needs its submission day, not before the program and not ahead of today.
        $this->as()->putJson($url, ['rows' => [$navy + ['status' => 'SUBMITTED'], $ecru]])->assertUnprocessable()->assertJsonValidationErrors(['rows.0.submitted_on' => 'Enter the day "Navy" was submitted to the buyer.']);
        $this->as()->putJson($url, ['rows' => [$navy + ['status' => 'SUBMITTED', 'submitted_on' => '2026-09-30'], $ecru]])->assertUnprocessable()->assertJsonValidationErrors(['rows.0.submitted_on' => 'A shade cannot be submitted before its program was requested.']);
        $this->as()->putJson($url, ['rows' => [$navy + ['status' => 'SUBMITTED', 'submitted_on' => now()->addDay()->toDateString()], $ecru]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.submitted_on');
        $this->as()->putJson($url, ['rows' => [$navy + ['status' => 'LOST'], $ecru]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.status');

        $sent = $this->as()->putJson($url, ['rows' => [$navy + ['status' => 'SUBMITTED', 'submitted_on' => '2026-10-05'], $ecru]])->assertOk()->json('data.rows');
        $this->assertSame(['SUBMITTED', '2026-10-05', null], [$sent[0]['status'], $sent[0]['submitted_on'], $sent[0]['decided_on']]);

        // The buyer's verdict is dated the day it is recorded, and keeps that date while it stands.
        $verdict = $navy + ['status' => 'RESUBMIT', 'submitted_on' => '2026-10-05', 'feedback' => ' Too red under TL84 '];
        $judged = $this->as()->putJson($url, ['rows' => [$verdict, $ecru]])->assertOk()->json('data.rows');
        $this->assertSame(['RESUBMIT', now()->toDateString(), 'Too red under TL84'], [$judged[0]['status'], $judged[0]['decided_on'], $judged[0]['feedback']]);
        LabdipShade::query()->whereKey($rows[0]['id'])->update(['decided_on' => '2026-10-07']);
        $this->assertSame('2026-10-07', $this->as()->putJson($url, ['rows' => [$verdict, $ecru]])->assertOk()->json('data.rows.0.decided_on'));

        // The programme's own panel leaves the submission as it is, and a shade with the buyer stays.
        $kept = $this->as()->putJson($url, ['rows' => [['target_date' => '2026-10-12'] + $navy, ['id' => $rows[1]['id'], 'colour' => 'Ecru']]])->assertOk()->json('data.rows.0');
        $this->assertSame(['RESUBMIT', '2026-10-05', '2026-10-12'], [$kept['status'], $kept['submitted_on'], $kept['target_date']]);
        $this->as()->putJson($url, ['rows' => [['id' => $rows[1]['id'], 'colour' => 'Ecru']]])->assertUnprocessable()->assertJsonValidationErrors(['rows' => '"Navy" has gone to the buyer and stays on the program.']);
        $this->as()->putJson($url, ['rows' => [$navy]])->assertOk();

        $records = $this->as()->getJson('/api/v1/merchandise/labdip-submissions')->assertOk()->json('data');
        $this->assertSame([['637PK', $program['program_no'], 'Navy', '2026-10-01', '2026-10-10', '2026-10-05', 'RESUBMIT', 'LPP SPOLKA AKCYJNA OFFICE']], array_map(fn ($r) => [$r['style_no'], $r['program_no'], $r['colour'], $r['reg_date'], $r['target_date'], $r['submitted_on'], $r['status'], $r['buyer_ac']], $records));

        // The style's lab dips are counted on the Style Progress Board.
        $board = collect($this->as()->getJson('/api/v1/merchandise/style-order/progress')->assertOk()->json('data'))->keyBy('style_no');
        $this->assertSame(1, $board['637PK']['lab_dips']);
    }

    public function test_a_sample_programme_is_numbered_and_its_lines_belong_to_a_batch_once_pulled(): void
    {
        [$buyer, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $this->style($buyer, $office, '637PK'), 'total_qty' => 100])->assertCreated()->json('data');

        $this->as()->postJson('/api/v1/erp/merch-sample-programs', [])->assertUnprocessable()->assertJsonPath('errors.program_date.0', 'Prog. Date is required.');
        $first = $this->as()->postJson('/api/v1/erp/merch-sample-programs', ['program_date' => '2026-10-13', 'order_id' => $order['id'], 'tran_ref' => ' BK-BUL-00012 ', 'buyer_office_id' => $office])->assertCreated()->json('data');
        $this->assertSame(['SMP-'.now()->format('Y').'00001', 'DRAFT', 'BK-BUL-00012', '529', $order['order_no'], 0, 0], [$first['program_no'], $first['status'], $first['tran_ref'], $first['job_label'], $first['order_no'], $first['lines'], $first['req_qty']]);
        $second = $this->as()->postJson('/api/v1/erp/merch-sample-programs', ['program_date' => '2026-10-13', 'order_id' => $order['id'], 'work_style_no' => '529-W'])->assertCreated()->json('data');
        $this->assertSame(['SMP-'.now()->format('Y').'00002', '529-W'], [$second['program_no'], $second['job_label']]);

        $url = "/api/v1/merchandise/sample-programs/{$first['id']}/lines";
        $data = $this->as()->getJson($url)->assertOk()->json('data');
        $this->assertSame(['529 ('.$order['order_no'].')', []], [$data['program']['job'], $data['rows']]);
        $proto = collect($data['types'])->firstWhere('label', 'Proto Sample')['value'];
        $fit = collect($data['types'])->firstWhere('label', 'Fit Sample')['value'];

        // Add line keeps the line at once, as Brickland does.
        $this->as()->postJson($url, ['req_qty' => 3])->assertUnprocessable()->assertJsonPath('errors.sample_type_id.0', 'Smp. Type is required.');
        $this->as()->postJson($url, ['sample_type_id' => $proto])->assertUnprocessable()->assertJsonPath('errors.req_qty.0', 'R.Qty is required.');
        $this->as()->postJson($url, ['sample_type_id' => $proto, 'req_qty' => 0])->assertUnprocessable()->assertJsonValidationErrors('req_qty');
        $added = $this->as()->postJson($url, ['sample_type_id' => $proto, 'item' => ' T-Shirt ', 'colour' => 'Navy', 'size' => 'M', 'req_qty' => 3, 'unit' => 'Pcs'])->assertOk()->json('data');
        $this->assertSame(['Proto Sample', 'T-Shirt', 'PLANNED', null, false], [$added['rows'][0]['type'], $added['rows'][0]['item'], $added['rows'][0]['status'], $added['rows'][0]['batch_no'], $added['rows'][0]['locked']]);
        $this->assertSame('Proto Sample', $added['added']['type']);
        $this->assertEquals(3, $added['added']['qty']);
        $rows = $this->as()->postJson($url, ['sample_type_id' => $fit, 'req_qty' => 2])->assertOk()->json('data.rows');
        $listed = collect($this->as()->getJson('/api/v1/erp/merch-sample-programs')->json('data'))->firstWhere('id', $first['id']);
        $this->assertSame([2, 5], [$listed['lines'], (int) $listed['req_qty']]);

        // In place: the proto's size and quantity change; the fit line is left out and goes.
        $saved = $this->as()->putJson($url, ['rows' => [['id' => $rows[0]['id'], 'item' => 'T-Shirt', 'colour' => 'Navy', 'size' => 'L', 'req_qty' => 4, 'unit' => 'Pcs']]])->assertOk()->json('data.rows');
        $this->assertSame([[$rows[0]['id'], 'L']], array_map(fn ($r) => [$r['id'], $r['size']], $saved));
        $this->assertEquals(4, $saved[0]['req_qty']);
        $this->as()->putJson($url, ['rows' => [['id' => $rows[1]['id'], 'req_qty' => 1]]])->assertUnprocessable()->assertJsonValidationErrors('rows');

        // Pulled into a batch, the line is the batch's: not changed, not removed, and the programme stays.
        SampleLine::query()->whereKey($rows[0]['id'])->update(['batch_no' => 'B-0001', 'status' => 'IN_BATCH']);
        $kept = $this->as()->putJson($url, ['rows' => [['id' => $rows[0]['id'], 'size' => 'XL', 'req_qty' => 9]]])->assertOk()->json('data.rows.0');
        $this->assertSame(['L', true, 'B-0001'], [$kept['size'], $kept['locked'], $kept['batch_no']]);
        $this->assertEquals(4, $kept['req_qty']);
        $this->as()->putJson($url, ['rows' => []])->assertUnprocessable()->assertJsonPath('errors.rows.0', 'A line in production batch B-0001 stays on the programme.');
        $this->as()->deleteJson("/api/v1/erp/merch-sample-programs/{$first['id']}")->assertUnprocessable()->assertJsonValidationErrors('id');
        $this->as()->deleteJson("/api/v1/erp/merch-sample-programs/{$second['id']}")->assertSuccessful();
    }

    public function test_a_print_design_program_carries_its_lines_and_their_uploaded_artwork(): void
    {
        Storage::fake(config('filesystems.default'));
        [$buyer, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        [$other, $otherOffice] = $this->buyer('NIS', 'Nishimatsuya');
        $style = $this->style($buyer, $office, '637PK');
        $foreign = $this->style($other, $otherOffice, 'N-1');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 100, 'ship_date' => '2026-12-04'])->assertCreated()->json('data');

        // Program Parameter: Brickland's messages, and only the company being worked in.
        $this->as()->postJson('/api/v1/erp/merch-print-programs', [])->assertUnprocessable()
            ->assertJsonPath('errors.buyer_office_id.0', 'Buyer Acc is required.')->assertJsonPath('errors.requisition_date.0', 'Date of Requisition is required.')
            ->assertJsonPath('errors.program_by.0', 'Program By is required.')->assertJsonPath('errors.request_to.0', 'Request To is required.')->assertJsonPath('errors.company.0', 'Company is required.');
        $header = ['buyer_office_id' => $office, 'buyer_id' => $buyer, 'requisition_date' => '2026-10-13', 'program_by' => $this->owner->id, 'request_to' => ' Print section ', 'company' => $this->delta->id];
        $this->as()->postJson('/api/v1/erp/merch-print-programs', ['company' => 'another'] + $header)->assertUnprocessable()->assertJsonValidationErrors('company');
        $program = $this->as()->postJson('/api/v1/erp/merch-print-programs', $header)->assertCreated()->json('data');
        $this->assertSame(['PD-'.now()->format('Y').'00001', 'Print section', 'LPP', 0], [$program['program_no'], $program['request_to'], $program['buyer'], $program['lines']]);
        $this->as()->patchJson("/api/v1/erp/merch-print-programs/{$program['id']}", ['request_to' => 'Creative Touch', 'company' => $this->delta->id])->assertOk()->assertJsonPath('data.program_no', $program['program_no']);

        // The lines panel offers the program buyer's own styles and orders.
        $url = "/api/v1/merchandise/print-programs/{$program['id']}/lines";
        $data = $this->as()->getJson($url)->assertOk()->json('data');
        $this->assertSame([['637PK — Hoodie'], '2026-12-04', []], [array_column($data['styles'], 'label'), $data['orders'][0]['ship_date'], $data['rows']]);
        $proto = collect($data['types'])->firstWhere('label', 'Proto Sample')['value'];

        // Add line: the entry row's three needs, then the line is kept with its artwork.
        $this->as()->post($url, ['sample_type_id' => $proto], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors(['material_description' => 'Material Description is needed before the row can be added.', 'shipment_date' => 'Shipment Date is needed before the row can be added.']);
        $this->as()->post($url, ['sample_type_id' => $proto, 'material_description' => 'x', 'shipment_date' => '2026-12-04', 'style_id' => $foreign], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('style_id');
        $pdf = UploadedFile::fake()->create('art.pdf', 20, 'application/pdf');
        $this->as()->post($url, ['sample_type_id' => $proto, 'material_description' => 'x', 'shipment_date' => '2026-12-04', 'artwork' => $pdf], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('artwork');
        $png = UploadedFile::fake()->createWithContent('chest.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $rows = $this->as()->post($url, [
            'style_id' => $style, 'order_id' => $order['id'], 'sample_type_id' => $proto, 'material_description' => ' Chest print, water base ', 'shipment_date' => '2026-12-04',
            'print_type' => 'Rubber', 'req_qty' => 5, 'unit' => 'Pcs', 'priority' => 'HIGH', 'artwork' => $png,
        ], ['Accept' => 'application/json'])->assertOk()->json('data.rows');
        $this->assertSame(['Chest print, water base', 'chest.png', 'HIGH'], [$rows[0]['material_description'], $rows[0]['artwork_name'], $rows[0]['priority']]);
        $this->assertNotNull($rows[0]['artwork_file_id']);
        $this->as()->getJson("/api/v1/files/{$rows[0]['artwork_file_id']}/download")->assertRedirect();
        $rows = $this->as()->post($url, ['sample_type_id' => $proto, 'material_description' => 'Back neck label print', 'shipment_date' => '2026-12-04'], ['Accept' => 'application/json'])->assertOk()->json('data.rows');
        $this->assertSame([2, null], [count($rows), $rows[1]['artwork_file_id']]);

        // The artwork of one line is replaced on its own.
        $again = UploadedFile::fake()->createWithContent('back.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $this->as()->post("{$url}/{$rows[1]['id']}/artwork", [], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonPath('errors.artwork.0', 'Choose the artwork image to upload.');
        $replaced = $this->as()->post("{$url}/{$rows[1]['id']}/artwork", ['artwork' => $again], ['Accept' => 'application/json'])->assertOk()->json('data.rows');
        $this->assertSame(['back.png', $rows[0]['artwork_file_id']], [$replaced[1]['artwork_name'], $replaced[0]['artwork_file_id']]);

        // In place: the chest print's quantity changes and keeps its artwork; the back neck line is left out and goes.
        $saved = $this->as()->putJson($url, ['rows' => [['id' => $rows[0]['id'], 'req_qty' => 8] + collect($rows[0])->only(['style_id', 'order_id', 'sample_type_id', 'material_description', 'shipment_date', 'print_type', 'unit', 'priority'])->all()]])->assertOk()->json('data.rows');
        $this->assertSame([1, $rows[0]['artwork_file_id']], [count($saved), $saved[0]['artwork_file_id']]);
        $this->assertEquals(8, $saved[0]['req_qty']);
        $this->as()->putJson($url, ['rows' => [['id' => $rows[1]['id'], 'sample_type_id' => $proto, 'material_description' => 'x', 'shipment_date' => '2026-12-04']]])->assertUnprocessable()->assertJsonValidationErrors('rows');
        $listed = $this->as()->getJson("/api/v1/erp/merch-print-programs/{$program['id']}")->json('data');
        $this->assertSame([1, 'Creative Touch'], [$listed['lines'], $listed['request_to']]);

        // A sheet's artwork is closed to whoever cannot see lab dips and samples.
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->as($technician)->getJson("/api/v1/files/{$rows[0]['artwork_file_id']}/download")->assertForbidden();

        $this->as()->deleteJson("/api/v1/erp/merch-print-programs/{$program['id']}")->assertSuccessful();
        $this->as()->getJson($url)->assertNotFound();
    }

    public function test_planned_sample_lines_are_sewn_in_a_batch_that_closes_on_submit(): void
    {
        [$buyer, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $this->style($buyer, $office, '637PK'), 'total_qty' => 100])->assertCreated()->json('data');
        $program = $this->as()->postJson('/api/v1/erp/merch-sample-programs', ['program_date' => '2026-10-13', 'order_id' => $order['id'], 'buyer_office_id' => $office])->assertCreated()->json('data');
        $lineUrl = "/api/v1/merchandise/sample-programs/{$program['id']}/lines";
        $types = collect($this->as()->getJson($lineUrl)->json('data.types'));
        $this->as()->postJson($lineUrl, ['sample_type_id' => $types->firstWhere('label', 'Proto Sample')['value'], 'item' => 'T-Shirt', 'colour' => 'Navy', 'size' => 'M', 'req_qty' => 3, 'unit' => 'Pcs'])->assertOk();
        $lines = $this->as()->postJson($lineUrl, ['sample_type_id' => $types->firstWhere('label', 'Fit Sample')['value'], 'req_qty' => 2])->assertOk()->json('data.rows');

        $board = $this->as()->getJson('/api/v1/merchandise/sample-production')->assertOk()->json('data');
        $this->assertSame([['529 ('.$order['order_no'].')', 'Proto Sample', 'LPP SPOLKA AKCYJNA OFFICE'], [], ['LINDEX', 'OTHER']], [[$board['planned'][0]['job'], $board['planned'][0]['type'], $board['planned'][0]['buyer_ac']], $board['batches'], $board['card_formats']]);

        // New Batch opens SMB-00001 on the date; the ticked lines join it and leave the planned list.
        $this->as()->postJson('/api/v1/merchandise/sample-batches', [])->assertUnprocessable()->assertJsonValidationErrors('batch_date');
        $batch = $this->as()->postJson('/api/v1/merchandise/sample-batches', ['batch_date' => '2026-10-14'])->assertCreated()->json('data.batch');
        $this->assertSame(['SMB-00001', 'DRAFT', 'LINDEX'], [$batch['batch_no'], $batch['status'], $batch['card_format']]);
        $url = "/api/v1/merchandise/sample-batches/{$batch['id']}";
        $this->as()->postJson("{$url}/lines", ['line_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('line_ids');
        $added = $this->as()->postJson("{$url}/lines", ['line_ids' => [$lines[0]['id'], $lines[1]['id']]])->assertOk()->json('data');
        $this->assertSame([2, ['IN_BATCH', 'IN_BATCH']], [$added['added'], array_column($added['lines'], 'status')]);
        $this->assertSame([], $this->as()->getJson('/api/v1/merchandise/sample-production')->json('data.planned'));
        $this->as()->postJson("{$url}/lines", ['line_ids' => [$lines[0]['id']]])->assertUnprocessable()->assertJsonValidationErrors('line_ids');
        // On Sample Programme the line is now the batch's.
        $this->assertSame([true, 'SMB-00001'], [$this->as()->getJson($lineUrl)->json('data.rows.0.locked'), $this->as()->getJson($lineUrl)->json('data.rows.0.batch_no')]);

        // Taken off, a line is planned again.
        $this->as()->deleteJson("{$url}/lines/{$lines[1]['id']}")->assertOk();
        $this->assertSame([$lines[1]['id']], array_column($this->as()->getJson('/api/v1/merchandise/sample-production')->json('data.planned'), 'id'));

        // Delivered plus rejected cannot exceed what was sewn.
        $this->as()->putJson($url, ['card_format' => 'OTHER', 'lines' => [['id' => $lines[0]['id'], 'sewn_qty' => 2, 'delivered_qty' => 2, 'rejected_qty' => 1]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.delivered_qty' => 'Delivered plus rejected cannot exceed what was sewn.']);
        $saved = $this->as()->putJson($url, ['card_format' => 'OTHER', 'lines' => [['id' => $lines[0]['id'], 'sewn_qty' => 3, 'delivered_qty' => 2, 'rejected_qty' => 1, 'batch_remarks' => ' one stain ']]])->assertOk()->json('data');
        $this->assertSame([1, 'OTHER', 'one stain'], [$saved['updated'], $saved['batch']['card_format'], $saved['lines'][0]['batch_remarks']]);
        $this->assertEquals([3, 2, 1], [$saved['lines'][0]['sewn_qty'], $saved['lines'][0]['delivered_qty'], $saved['lines'][0]['rejected_qty']]);

        // Submit closes the batch; its lines are produced and no longer change.
        $empty = $this->as()->postJson('/api/v1/merchandise/sample-batches', ['batch_date' => '2026-10-14'])->assertCreated()->json('data.batch');
        $this->assertSame('SMB-00002', $empty['batch_no']);
        $this->as()->postJson("/api/v1/merchandise/sample-batches/{$empty['id']}/submit")->assertUnprocessable()->assertJsonValidationErrors('batch');
        $done = $this->as()->postJson("{$url}/submit")->assertOk()->json('data');
        $this->assertSame([1, 'SUBMITTED', 'PRODUCED'], [$done['submitted'], $done['batch']['status'], $done['lines'][0]['status']]);
        $this->as()->putJson($url, ['card_format' => 'LINDEX', 'lines' => []])->assertUnprocessable()->assertJsonPath('errors.batch.0', 'SMB-00001 is submitted: its lines and quantities can no longer be changed.');
        $this->as()->postJson("{$url}/lines", ['line_ids' => [$lines[1]['id']]])->assertUnprocessable()->assertJsonValidationErrors('batch');
        $this->as()->deleteJson("{$url}/lines/{$lines[0]['id']}")->assertUnprocessable();

        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->getJson('/api/v1/merchandise/sample-production')->assertOk();
        $this->as($manager)->postJson('/api/v1/merchandise/sample-batches', ['batch_date' => '2026-10-14'])->assertForbidden();
    }

    public function test_a_sample_delivery_challan_is_numbered_and_carries_its_items(): void
    {
        [, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');

        $this->as()->postJson('/api/v1/erp/merch-sample-challans', [])->assertUnprocessable()->assertJsonPath('errors.challan_date.0', 'Challan Date is required.');
        $first = $this->as()->postJson('/api/v1/erp/merch-sample-challans', ['challan_date' => '2026-10-15', 'buyer_office_id' => $office, 'vehicle_no' => ' DHA-1234 ', 'carrier' => 'DHL'])->assertCreated()->json('data');
        $this->assertSame(['SDC-00001', 'DRAFT', 'DHA-1234', 'LPP SPOLKA AKCYJNA OFFICE', 0, now()->toDateString()], [$first['challan_no'], $first['status'], $first['vehicle_no'], $first['buyer_office'], $first['items'], $first['created_on']]);
        $this->assertSame('SDC-00002', $this->as()->postJson('/api/v1/erp/merch-sample-challans', ['challan_date' => '2026-10-15'])->assertCreated()->json('data.challan_no'));

        $url = "/api/v1/merchandise/sample-challans/{$first['id']}/items";
        $data = $this->as()->getJson($url)->assertOk()->json('data');
        $this->assertSame(['SDC-00001', []], [$data['challan']['challan_no'], $data['rows']]);
        $proto = collect($data['types'])->firstWhere('label', 'Proto Sample')['value'];

        // Add item keeps the item at once, with Brickland's messages.
        $this->as()->postJson($url, ['qty' => 2])->assertUnprocessable()->assertJsonPath('errors.description.0', 'Description is required.');
        $this->as()->postJson($url, ['description' => 'x'])->assertUnprocessable()->assertJsonPath('errors.qty.0', 'Qty is required.');
        $added = $this->as()->postJson($url, ['description' => ' Proto sample T-Shirt Navy M ', 'sample_type_id' => $proto, 'qty' => 2, 'uom' => 'Pcs'])->assertOk()->json('data');
        $this->assertSame(['Proto sample T-Shirt Navy M', 'Proto Sample'], [$added['added'], $added['rows'][0]['type']]);
        $rows = $this->as()->postJson($url, ['description' => 'Fit sample', 'qty' => 1])->assertOk()->json('data.rows');
        $this->assertSame(2, $this->as()->getJson("/api/v1/erp/merch-sample-challans/{$first['id']}")->json('data.items'));

        // In place: the proto's quantity changes; the fit item is left out and goes.
        $saved = $this->as()->putJson($url, ['rows' => [['id' => $rows[0]['id'], 'description' => 'Proto sample T-Shirt Navy M', 'qty' => 3, 'uom' => 'Pcs']]])->assertOk()->json('data.rows');
        $this->assertSame([1, 'Proto Sample'], [count($saved), $saved[0]['type']]);
        $this->assertEquals(3, $saved[0]['qty']);
        $this->as()->putJson($url, ['rows' => [['id' => $rows[1]['id'], 'description' => 'x', 'qty' => 1]]])->assertUnprocessable()->assertJsonValidationErrors('rows');

        $this->as()->deleteJson("/api/v1/erp/merch-sample-challans/{$first['id']}")->assertSuccessful();
        $this->as()->getJson($url)->assertNotFound();
    }

    public function test_buyer_feedback_names_its_batch_and_holds_the_bulk_shade_apart(): void
    {
        [$buyer, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $this->style($buyer, $office, '637PK'), 'total_qty' => 100])->assertCreated()->json('data');
        $program = $this->as()->postJson('/api/v1/erp/merch-sample-programs', ['program_date' => '2026-10-13', 'order_id' => $order['id']])->assertCreated()->json('data');
        $types = collect($this->as()->getJson("/api/v1/merchandise/sample-programs/{$program['id']}/lines")->json('data.types'));
        $proto = $types->firstWhere('label', 'Proto Sample')['value'];
        $fit = $types->firstWhere('label', 'Fit Sample')['value'];
        $line = $this->as()->postJson("/api/v1/merchandise/sample-programs/{$program['id']}/lines", ['sample_type_id' => $proto, 'req_qty' => 3])->assertOk()->json('data.rows.0.id');
        $batch = $this->as()->postJson('/api/v1/merchandise/sample-batches', ['batch_date' => '2026-10-14'])->assertCreated()->json('data.batch');
        $this->as()->postJson("/api/v1/merchandise/sample-batches/{$batch['id']}/lines", ['line_ids' => [$line]])->assertOk();

        $this->as()->postJson('/api/v1/erp/merch-sample-feedback', [])->assertUnprocessable()->assertJsonPath('errors.submit_date.0', 'Batch Submit DT is required.');
        // A draft batch is not yet submitted, so it is not named.
        $early = $this->as()->postJson('/api/v1/erp/merch-sample-feedback', ['order_id' => $order['id'], 'sample_type_id' => $proto, 'submit_date' => '2026-10-15'])->assertCreated()->json('data');
        $this->assertSame([null, 'SUBMITTED', false, 'LPP SPOLKA AKCYJNA OFFICE', '637PK', $order['order_no'], 'Proto Sample'], [$early['batch_no'], $early['status'], $early['bulk_shade_approved'], $early['buyer_ac'], $early['job'], $early['order_no'], $early['type']]);

        $this->as()->postJson("/api/v1/merchandise/sample-batches/{$batch['id']}/submit")->assertOk();
        $feedback = $this->as()->postJson('/api/v1/erp/merch-sample-feedback', [
            'order_id' => $order['id'], 'sample_type_id' => $proto, 'submit_date' => '2026-10-15', 'status' => 'APPROVED', 'bulk_shade_approved' => '1', 'comments' => ' Good fit ',
        ])->assertCreated()->json('data');
        $this->assertSame(['SMB-00001', 'APPROVED', true, 'Good fit'], [$feedback['batch_no'], $feedback['status'], $feedback['bulk_shade_approved'], $feedback['comments']]);

        // Bulk Shade Approval holds the shade on its own; the rest of the feedback stays.
        $held = $this->as()->patchJson("/api/v1/erp/merch-sample-feedback/{$feedback['id']}", ['bulk_shade_approved' => false])->assertOk()->json('data');
        $this->assertSame([false, 'APPROVED', 'SMB-00001', 'Good fit'], [$held['bulk_shade_approved'], $held['status'], $held['batch_no'], $held['comments']]);
        // Another sample type of the same order was made in no batch.
        $this->assertNull($this->as()->patchJson("/api/v1/erp/merch-sample-feedback/{$feedback['id']}", ['sample_type_id' => $fit])->assertOk()->json('data.batch_no'));
        $this->as()->postJson('/api/v1/erp/merch-sample-feedback', ['submit_date' => '2026-10-15', 'status' => 'LOST'])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertCount(2, $this->as()->getJson('/api/v1/erp/merch-sample-feedback')->json('data'));
        $this->as()->deleteJson("/api/v1/erp/merch-sample-feedback/{$early['id']}")->assertSuccessful();
    }

    public function test_a_measurement_chart_keeps_each_point_of_measure_once_per_size(): void
    {
        [$buyer, $office] = $this->buyer('LPP', 'LPP Spolka Akcyjna');
        $style = $this->style($buyer, $office, '637PK');

        $this->as()->postJson('/api/v1/erp/merch-measure-charts', [])->assertUnprocessable()
            ->assertJsonPath('errors.style_id.0', 'Style is required.')->assertJsonPath('errors.item_name.0', 'Item Name is required.');
        $chart = $this->as()->postJson('/api/v1/erp/merch-measure-charts', ['style_id' => $style, 'item_name' => ' Collar ', 'sub_style' => 'A', 'model_no' => 'M-1'])->assertCreated()->json('data');
        $this->assertSame(['637PK', 'Hoodie', '637PK · Collar', 'LPP SPOLKA AKCYJNA OFFICE', 'DRAFT', 0], [$chart['style_no'], $chart['style_name'], $chart['label'], $chart['buyer_ac'], $chart['status'], $chart['points']]);

        $url = "/api/v1/merchandise/measure-charts/{$chart['id']}/points";
        $this->assertSame(['637PK · Collar', []], [$this->as()->getJson($url)->assertOk()->json('data.chart.label'), $this->as()->getJson($url)->json('data.rows')]);
        $this->as()->postJson($url, ['value' => 42])->assertUnprocessable()
            ->assertJsonPath('errors.size.0', 'Size is required.')->assertJsonPath('errors.point.0', 'Point of Measure is required.');
        $this->as()->postJson($url, ['size' => 'M', 'point' => 'Collar length'])->assertUnprocessable()->assertJsonPath('errors.value.0', 'Value is required.');
        $added = $this->as()->postJson($url, ['size' => ' M ', 'point' => 'Collar length', 'value' => 42.5, 'tolerance' => 0.5, 'uom' => 'cm'])->assertOk()->json('data');
        $this->assertSame(['M · Collar length', 'M'], [$added['added'], $added['rows'][0]['size']]);
        // A size has each point of measure once, whatever the letter case.
        $this->as()->postJson($url, ['size' => 'm', 'point' => 'COLLAR LENGTH', 'value' => 43])->assertUnprocessable()
            ->assertJsonPath('errors.point.0', 'That point already exists. Change it, or edit the existing record.');
        $rows = $this->as()->postJson($url, ['size' => 'L', 'point' => 'Collar length', 'value' => 44])->assertOk()->json('data.rows');
        $this->assertSame(2, $this->as()->getJson("/api/v1/erp/merch-measure-charts/{$chart['id']}")->json('data.points'));

        // In place: M's value and tolerance change; L is left out and goes.
        $saved = $this->as()->putJson($url, ['rows' => [['id' => $rows[0]['id'], 'value' => 43, 'tolerance' => null, 'uom' => 'cm']]])->assertOk()->json('data.rows');
        $this->assertSame([1, null], [count($saved), $saved[0]['tolerance']]);
        $this->assertEquals(43, $saved[0]['value']);
        $this->as()->putJson($url, ['rows' => [['id' => $rows[1]['id'], 'value' => 1]]])->assertUnprocessable()->assertJsonValidationErrors('rows');

        $this->as()->deleteJson("/api/v1/erp/merch-measure-charts/{$chart['id']}")->assertSuccessful();
        $this->as()->getJson($url)->assertNotFound();
    }

    public function test_lab_dips_are_closed_to_a_technician_and_read_only_to_a_manager(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');

        $this->as($technician)->getJson('/api/v1/erp/merch-labdip-programs')->assertForbidden();
        $this->as($manager)->getJson('/api/v1/erp/merch-labdip-programs')->assertOk();
        $this->as($manager)->postJson('/api/v1/erp/merch-labdip-programs', ['buyer_id' => 'x'])->assertForbidden();
    }

    private function section(): string
    {
        $department = Department::query()->create(['factory_id' => $this->factory->id, 'name' => 'Dyeing', 'code' => 'DYE', 'department_type' => 'PRODUCTION', 'status' => 'ACTIVE']);

        return Section::query()->create(['department_id' => $department->id, 'name' => 'Dyeing Lab', 'code' => 'LAB', 'status' => 'ACTIVE'])->id;
    }

    private function buyer(string $code, string $name): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => "{$name} Office", 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => $code, 'name' => $name, 'short_name' => $code, 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');

        return [$buyer, $office];
    }

    private function style(string $buyer, string $office, string $ref): string
    {
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id')
            ?? Size::query()->where('code', 'M')->value('id');

        return $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => $ref, 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
