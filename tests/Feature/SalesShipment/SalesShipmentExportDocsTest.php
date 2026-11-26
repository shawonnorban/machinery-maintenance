<?php

declare(strict_types=1);

namespace Tests\Feature\SalesShipment;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
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
 * Sales & Shipment → Export Documentation (AnnotechERP 21/06): export
 * documents filed by family (number unique per type; the expiry warned
 * inside the settings' certificate window; pending → received → submitted
 * → approved), certificates typed from the Certificate Master, the final
 * document handover kept apart from the commercial one, shipment
 * attachments (PDF or image up to 5 MB, the file kept on an edit), no paper
 * on a closed shipment, the documents readiness check — and who may.
 */
class SalesShipmentExportDocsTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $shp = 'SHP-2026-000001';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-08 10:00:00', 'Asia/Dhaka'));
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $buyer = MaterialSupplier::query()->create(['company_id' => $this->delta->id, 'code' => 'SUP-1', 'name' => 'Arafah Knit Composite', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $usd = (string) Str::ulid();
        DB::table('currencies')->insert(['id' => $usd, 'company_id' => $this->delta->id, 'code' => 'USD', 'name' => 'US Dollar', 'exchange_rate' => 110, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $item = MaterialItem::query()->create(['company_id' => $this->delta->id, 'kind' => 'TRIMS', 'item_code' => 'PL-01', 'name' => 'Poly Bag', 'category' => 'Trims', 'uom' => 'PCS', 'active' => true])->id;
        $so = $this->as()->postJson('/api/v1/sales-shipment/orders', [
            'search_for' => 'SALES_ORDER', 'so_type' => 'GENERAL', 'customer_id' => $buyer, 'receive_date' => '2026-11-01', 'program_by' => 'Rahim', 'program_to' => 'Karim', 'bill_to_id' => $buyer,
            'delivery_target_date' => '2026-11-20', 'same_as_bill_to' => true, 'payment_method' => 'LC', 'currency_id' => $usd, 'conv_rate' => 110, 'mode_of_shipment' => 'By Truck', 'po_wo_no' => 'WO-1',
            'shipment_date' => '2026-11-25', 'lines' => [['sl_no' => 1, 'item_id' => $item, 'rate' => 2]], 'sizes' => [['sl_no' => 1, 'size' => 'M', 'qty' => 500]],
        ])->assertCreated()->json('data.so_no');
        foreach (['submit', 'check', 'approve', 'confirm'] as $s) {
            $this->as()->postJson("/api/v1/sales-shipment/orders/{$so}/{$s}")->assertOk();
        }
        $this->as()->postJson('/api/v1/sales-shipment/shipments', ['so_no' => $so, 'plan_date' => '2026-11-07', 'shipment_date' => '2026-11-25', 'shipment_mode' => 'Sea',
            'lines' => [['size_id' => DB::table('ss_sales_order_sizes')->value('id'), 'qty' => 300]]])->assertCreated();
    }

    public function test_export_documents_by_family_their_expiry_status_steps_and_the_readiness_check(): void
    {
        $doc = fn (string $family, array $extra) => $this->as()->postJson("/api/v1/sales-shipment/export-docs/{$family}", $extra + ['shipment_no' => $this->shp, 'document_date' => '2026-11-08']);
        $doc('export', ['doc_type' => 'COO', 'document_no' => 'X1'])->assertUnprocessable();
        $doc('export', ['doc_type' => 'EXP', 'document_no' => 'EXP-1', 'issue_date' => '2026-11-08', 'expiry_date' => '2026-11-01'])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.ed_expiry')]);
        $id = $doc('export', ['doc_type' => 'EXP', 'document_no' => 'EXP-1', 'issue_date' => '2026-11-01', 'expiry_date' => '2026-11-20', 'issuing_authority' => 'EPB'])->assertCreated()->json('data.id');
        $doc('export', ['doc_type' => 'EXP', 'document_no' => 'EXP-1'])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.ed_twice', ['no' => 'EXP-1', 'type' => __('salesshipment.ed.type.EXP')])]);
        $doc('export', ['doc_type' => 'INSURANCE', 'document_no' => 'EXP-1', 'expiry_date' => '2026-11-05'])->assertCreated();
        $doc('export', ['doc_type' => 'INSPECTION', 'document_no' => 'INS-9', 'expiry_date' => '2027-03-01'])->assertCreated();
        $rows = collect($this->as()->getJson('/api/v1/sales-shipment/export-docs/export')->assertOk()->json('data'))->keyBy(fn ($r) => $r['doc_type'] === 'EXP' ? 'EXP-1' : $r['document_no'].'/'.$r['doc_type']);
        $this->assertSame([['EXPIRING', 12], ['VALID', 113]], [[$rows['EXP-1']['expiry'], $rows['EXP-1']['expiry_days']], [$rows['INS-9/INSPECTION']['expiry'], $rows['INS-9/INSPECTION']['expiry_days']]]);
        $this->assertSame('EXPIRED', collect($this->as()->getJson('/api/v1/sales-shipment/export-docs/export')->json('data'))->firstWhere('doc_type', 'INSURANCE')['expiry']);
        $this->as()->getJson('/api/v1/sales-shipment/export-docs/coo')->assertOk()->assertJsonCount(0, 'data');

        $this->as()->postJson("/api/v1/sales-shipment/export-doc-items/{$id}/approved")->assertUnprocessable();
        $this->as()->postJson("/api/v1/sales-shipment/export-doc-items/{$id}/received")->assertOk()->assertJsonPath('data.status', 'RECEIVED');
        $this->as()->postJson("/api/v1/sales-shipment/export-doc-items/{$id}/submitted")->assertOk();
        $this->as()->postJson("/api/v1/sales-shipment/export-doc-items/{$id}/approved")->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $file = $this->as()->post("/api/v1/sales-shipment/attachments-of/export-docs/{$id}", ['upload' => UploadedFile::fake()->create('exp.pdf', 80, 'application/pdf')], ['Accept' => 'application/json'])->assertOk()->json('data.file_id');
        $this->as()->get("/api/v1/files/{$file}/download")->assertRedirect();
        $this->withToken($this->token('TECHNICIAN', 'tech@delta.test'))->getJson("/api/v1/files/{$file}/download")->assertForbidden();

        // The readiness check reads the EXP and the B/L or AWB.
        $check = fn () => $this->as()->getJson("/api/v1/sales-shipment/confirmations-shipment/{$this->shp}/check")->json('data.checks.documents');
        $this->assertFalse($check());
        $awb = $doc('blawb', ['doc_type' => 'AIR_WAYBILL', 'document_no' => 'AWB-777'])->assertCreated()->json('data.id');
        $this->assertTrue($check());
        $this->as()->putJson("/api/v1/sales-shipment/export-docs/blawb/{$awb}", ['shipment_no' => $this->shp, 'doc_type' => 'AIR_WAYBILL', 'document_no' => 'AWB-777', 'document_date' => '2026-11-08', 'status' => 'CANCELLED'])->assertOk();
        $this->assertFalse($check());
        $doc('gsp', ['doc_type' => 'REX', 'document_no' => 'REX-5'])->assertCreated();
        $doc('coo', ['doc_type' => 'COO', 'document_no' => 'COO-5'])->assertCreated();

        // A closed shipment takes no further papers.
        DB::table('ss_shipments')->update(['stage' => 'CLOSED']);
        $doc('export', ['doc_type' => 'OTHER', 'document_no' => 'O-1'])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.ed_closed', ['no' => $this->shp, 'stage' => __('salesshipment.sp.stage.CLOSED')])]);
        $this->as()->deleteJson("/api/v1/sales-shipment/export-doc-items/{$id}")->assertUnprocessable();
        $this->assertTrue(collect($this->as()->getJson('/api/v1/sales-shipment/export-docs/export')->json('data'))->firstWhere('document_no', 'INS-9')['locked']);
    }

    public function test_certificates_come_from_the_master_and_handovers_keep_their_kind(): void
    {
        $iso = (string) Str::ulid();
        DB::table('company_documents')->insert(['id' => $iso, 'company_id' => $this->delta->id, 'document_type' => 'CERTIFICATE', 'title' => 'ISO 9001:2015', 'document_no' => 'DOC-00001',
            'issued_by' => 'ISO', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame('ISO 9001:2015 (DOC-00001)', $this->as()->getJson('/api/v1/sales-shipment/export-documentation/options')->json('data.certificate_types.0.label'));
        $cert = ['certificate_no' => 'C-1', 'shipment_no' => $this->shp, 'issue_date' => '2026-11-01'];
        $this->as()->postJson('/api/v1/sales-shipment/certificates', $cert)->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.ce_name')]);
        $this->as()->postJson('/api/v1/sales-shipment/certificates', ['certificate_type_id' => $iso, 'expiry_date' => '2026-12-01'] + $cert)->assertCreated()
            ->assertJsonPath('data.certificate_name', 'ISO 9001:2015')->assertJsonPath('data.issuing_authority', 'ISO');
        $this->as()->postJson('/api/v1/sales-shipment/certificates', ['certificate_name' => 'Other'] + $cert)->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.ce_twice', ['no' => 'C-1', 'shipment' => $this->shp])]);
        $row = $this->as()->getJson('/api/v1/sales-shipment/certificates')->json('data.0');
        $this->assertSame(['ISO 9001:2015', 'EXPIRING', 23], [$row['type'], $row['expiry'], $row['expiry_days']]);

        $this->as()->postJson('/api/v1/sales-shipment/final-handovers', ['handover_date' => '2026-11-08', 'shipment_no' => $this->shp])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.fh_list')]);
        $this->as()->postJson('/api/v1/sales-shipment/final-handovers', ['handover_date' => '2026-11-08', 'shipment_no' => $this->shp, 'document_list' => "Commercial invoice\nPacking list\nB/L", 'received_by' => 'HSBC'])
            ->assertCreated()->assertJsonPath('data.handover_no', 'DH-2026-000001');
        $this->as()->postJson('/api/v1/sales-shipment/handovers', ['handover_date' => '2026-11-08', 'shipment_no' => $this->shp])->assertCreated()->assertJsonPath('data.handover_no', 'DH-2026-000002');
        $this->as()->getJson('/api/v1/sales-shipment/final-handovers')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.handover_no', 'DH-2026-000001');
        $this->as()->getJson('/api/v1/sales-shipment/handovers')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.handover_no', 'DH-2026-000002');
        $this->as()->postJson('/api/v1/sales-shipment/handovers/DH-2026-000001/handed-over')->assertOk();
        $this->as()->postJson('/api/v1/sales-shipment/handovers/DH-2026-000001/received')->assertOk();

        // The final handover is export documentation's; the commercial one the shipment desk's.
        $keeper = $this->token('STORE_MANAGER', 'sm@delta.test');
        $this->withToken($keeper)->getJson('/api/v1/sales-shipment/final-handovers')->assertOk();
        $this->withToken($keeper)->postJson('/api/v1/sales-shipment/final-handovers', ['handover_date' => '2026-11-08', 'shipment_no' => $this->shp, 'document_list' => 'x'])->assertForbidden();
        $this->withToken($this->token('FACTORY_MANAGER', 'fm@delta.test'))->postJson('/api/v1/sales-shipment/final-handovers', ['handover_date' => '2026-11-08', 'shipment_no' => $this->shp, 'document_list' => 'x'])->assertCreated();
    }

    public function test_shipment_attachments_take_pdf_or_image_up_to_five_megabytes_and_keep_the_file_on_an_edit(): void
    {
        $post = fn (string $path, array $body) => $this->as()->post("/api/v1/sales-shipment/{$path}", $body, ['Accept' => 'application/json']);
        $base = ['shipment_no' => $this->shp, 'document_type' => 'Commercial Invoice', 'title' => 'Signed commercial invoice'];
        $post('shipment-attachments', $base)->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.sa_file')]);
        $post('shipment-attachments', $base + ['upload' => UploadedFile::fake()->create('a.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.sa_type')]);
        $post('shipment-attachments', $base + ['upload' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.sa_size')]);
        $id = $post('shipment-attachments', $base + ['upload' => UploadedFile::fake()->image('ci.png')])->assertCreated()->assertJsonPath('data.file', 'ci.png')->json('data.id');
        $post("shipment-attachments/{$id}", ['title' => 'Signed CI (rev)', 'document_type' => 'Commercial Invoice', 'shipment_no' => $this->shp])->assertOk()->assertJsonPath('data.file', 'ci.png');
        $row = $this->as()->getJson('/api/v1/sales-shipment/shipment-attachments')->assertOk()->json('data.0');
        $this->assertSame(['Signed CI (rev)', 'ci.png', $this->owner->name], [$row['title'], $row['file'], $row['uploaded_by']]);
        $this->as()->get("/api/v1/files/{$row['file_id']}/download")->assertRedirect();
        $this->withToken($this->token('AUDITOR', 'audit@delta.test'))->getJson('/api/v1/sales-shipment/shipment-attachments')->assertOk();
        $this->withToken($this->token('AUDITOR', 'audit2@delta.test'))->deleteJson("/api/v1/sales-shipment/shipment-attachments/{$id}")->assertForbidden();
        $this->as()->deleteJson("/api/v1/sales-shipment/shipment-attachments/{$id}")->assertOk();
        $this->withToken($this->token('STOREKEEPER', 'keeper@delta.test'))->getJson('/api/v1/sales-shipment/certificates')->assertForbidden();
    }

    private function token(string $role, string $email): string
    {
        return app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, $role, $email), $this->delta->id, 'Test token')['plain'];
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
