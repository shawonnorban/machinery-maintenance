<?php

declare(strict_types=1);

namespace Tests\Feature\Prototype;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleAlert;
use App\Modules\Prototype\Models\SampleEvent;
use App\Modules\Prototype\Models\SampleRequisition;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Prototype Desk → Dashboard (AnnotechERP 07/02): every step of a sample is
 * written to its timeline with the lifecycle stage it left the sample at;
 * the desk raises an alert once and resolves it when the work is done;
 * documents are versioned per sample and type; Sample 360° shows a sample
 * end to end; the dashboard counts the desk and sweeps overdue samples.
 */
class SampleDashboardLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_the_ledger_follows_the_sample_and_its_alerts_open_and_close(): void
    {
        $sample = $this->developedSample(qty: 1);
        $alerts = fn () => collect($this->as()->getJson('/api/v1/prototype/alerts')->assertOk()->json('data.alerts'))->keyBy('kind');
        $open = fn () => $alerts()->where('state', 'OPEN')->keys()->sort()->values()->all();
        $this->assertSame(['QC_REQUIRED'], $open());
        $qc = $alerts()['QC_REQUIRED'];
        $this->assertSame([$sample->sample_no, 'QC', 'INFO', "{$sample->sample_no} V1 needs its quality check"], [$qc['sample_no'], $qc['for_desk'], $qc['severity'], $qc['title']]);

        $this->as()->postJson('/api/v1/prototype/quality-checks', ['sample_id' => $sample->id, 'check_date' => '2026-10-17', 'result' => 'PASS'])->assertCreated();
        $this->assertSame(['APPROVAL_PENDING'], $open());
        $this->as()->postJson('/api/v1/prototype/approvals', ['sample_id' => $sample->id, 'submitted_date' => '2026-10-18', 'step' => 'MERCHANDISING_REVIEW', 'decision' => 'APPROVED'])->assertCreated();
        $this->as()->postJson('/api/v1/prototype/approvals', ['sample_id' => $sample->id, 'submitted_date' => '2026-10-18', 'step' => 'BUYER_APPROVAL', 'decision' => 'APPROVED'])->assertCreated();
        $this->assertSame(['HANDOVER_READY', 'SAMPLE_APPROVED'], $open());

        $form = ['sample_id' => $sample->id, 'qty' => 1, 'handover_date' => '2026-10-20', 'handover_to' => 'LPP Buying House'];
        $this->as()->postJson('/api/v1/prototype/handovers', $form)->assertCreated();
        $url = '/api/v1/prototype/handovers/HO-2026-00001';
        $this->as()->putJson($url, $form + ['status' => 'DELIVERED', 'actual_delivery' => '2026-10-21'])->assertOk();
        $this->as()->putJson($url, $form + ['status' => 'RECEIVED', 'actual_delivery' => '2026-10-21', 'received_by' => 'Anna', 'received_date' => '2026-10-21'])->assertOk();
        $this->assertContains('SAMPLE_DELIVERED', $alerts()->keys()->all());
        $this->assertContains('SAMPLE_COMPLETED', $alerts()->keys()->all());
        $this->assertNotContains('HANDOVER_READY', $open());

        // The timeline: what happened, in order, with the stage it left the sample at.
        $timeline = $this->as()->getJson("/api/v1/prototype/samples/{$sample->sample_no}")->assertOk()->json('data.timeline');
        $actions = array_column($timeline, 'action');
        $this->assertSame(['requisition_created', 'requisition_submitted'], array_slice($actions, 0, 2));
        foreach (['requisition_approved', 'development_completed', 'qc_recorded', 'approval_decided', 'handover_recorded'] as $action) {
            $this->assertContains($action, $actions);
        }
        $this->assertSame(array_values(array_filter($actions, fn ($a) => in_array($a, ['requisition_approved', 'development_completed', 'qc_recorded'], true))), ['requisition_approved', 'development_completed', 'qc_recorded']);
        $this->assertSame('COMPLETED', end($timeline)['stage']);
        $this->assertSame($this->owner->name, end($timeline)['by']);
        $created = $timeline[0];
        $this->assertSame(['Requisition Created', 'Requisition SR-2026-00001 raised.'], [$created['title'], $created['summary']]);
        // A stage change shows as From → To.
        $qcRow = collect($timeline)->firstWhere('action', 'qc_recorded');
        $this->assertSame(['QUALITY|PENDING', 'APPROVAL|PENDING'], [$qcRow['from'], $qcRow['to']]);
        $this->assertSame('Quality check QC-2026-00001 recorded — Pass.', $qcRow['summary']);

        // Read, resolve, reopen by hand.
        $id = $alerts()['SAMPLE_APPROVED']['id'];
        $this->as()->postJson("/api/v1/prototype/alerts/{$id}/reopen")->assertUnprocessable()->assertJsonPath('errors.alert.0', 'Only a resolved alert can be reopened.');
        $this->as()->postJson("/api/v1/prototype/alerts/{$id}/read")->assertOk()->assertJsonPath('data.read', true)->assertJsonPath('data.resolved', false);
        $resolved = $this->as()->postJson("/api/v1/prototype/alerts/{$id}/resolve")->assertOk()->json('data');
        $this->assertSame(['RESOLVED', true], [$resolved['state'], $resolved['resolved_today']]);
        $this->as()->postJson("/api/v1/prototype/alerts/{$id}/resolve")->assertUnprocessable()->assertJsonPath('errors.alert.0', 'This alert is already resolved.');
        $this->as()->postJson("/api/v1/prototype/alerts/{$id}/reopen")->assertOk()->assertJsonPath('data.state', 'OPEN');

        // Bangla reads the same ledger in its own words.
        $bn = $this->as()->withHeader('Accept-Language', 'bn')->getJson("/api/v1/prototype/samples/{$sample->sample_no}")->json('data.timeline.0.title');
        $this->assertSame('রিকুইজিশন তৈরি', $bn);

        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->as($technician)->getJson('/api/v1/prototype/alerts')->assertForbidden();
        $this->as($technician)->getJson('/api/v1/prototype/dashboard')->assertForbidden();
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->getJson('/api/v1/prototype/alerts')->assertOk();
        $this->as($manager)->postJson("/api/v1/prototype/alerts/{$id}/resolve")->assertForbidden();
    }

    public function test_documents_are_versioned_per_sample_and_type(): void
    {
        Storage::fake(config('filesystems.default'));
        $sample = $this->developedSample();
        $options = $this->as()->getJson('/api/v1/prototype/documents/options')->assertOk()->json('data');
        $this->assertSame([$sample->sample_no], array_column($options['samples'], 'sample_no'));
        $this->assertContains('TECH_PACK', $options['types']);

        $form = ['sample_id' => $sample->id, 'doc_type' => 'TECH_PACK', 'title' => 'Tech pack'];
        $this->as()->postJson('/api/v1/prototype/documents', [])->assertUnprocessable()
            ->assertJsonPath('errors.sample_id.0', 'Sample is required.')->assertJsonPath('errors.doc_type.0', 'Document Type is required.')->assertJsonPath('errors.title.0', 'Title is required.');
        $this->as()->postJson('/api/v1/prototype/documents', $form)->assertUnprocessable()->assertJsonPath('errors.file.0', 'Type a path or link, or attach a file.');
        $v1 = $this->as()->postJson('/api/v1/prototype/documents', $form + ['file' => 'https://drive.example/tp-v1.pdf'])->assertCreated()->json('data');
        $this->assertSame(['SDC-2026-00001', 1, $sample->sample_no, $this->owner->name], [$v1['doc_no'], $v1['version'], $v1['sample_no'], $v1['uploaded_by_name']]);

        $upload = UploadedFile::fake()->create('tech-pack-v2.pdf', 120, 'application/pdf');
        $v2 = $this->as()->post('/api/v1/prototype/documents', array_merge($form, ['title' => 'Tech pack, sleeve shortened', 'upload' => $upload]), ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertSame([2, 'tech-pack-v2.pdf', null], [$v2['version'], $v2['file_name'], $v2['file']]);
        $this->assertNotNull($v2['file_id']);
        // The file is fetched with the desk's own permission.
        $this->as()->get("/api/v1/files/{$v2['file_id']}/download")->assertRedirect();
        $this->as(TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test'))->getJson("/api/v1/files/{$v2['file_id']}/download")->assertForbidden();
        // Another type starts at its own V1.
        $this->as()->postJson('/api/v1/prototype/documents', array_merge($form, ['doc_type' => 'MEASUREMENT_SHEET', 'title' => 'Measurements', 'file' => 'MS/001']))->assertCreated()->assertJsonPath('data.version', 1);

        $shown = $this->as()->getJson("/api/v1/prototype/documents/{$v1['doc_no']}")->assertOk()->json('data');
        $this->assertSame([2, 1], array_column($shown['versions'], 'version'));
        // A saved one changes only its title and remarks.
        $this->as()->putJson("/api/v1/prototype/documents/{$v1['doc_no']}", ['title' => ''])->assertUnprocessable()->assertJsonPath('errors.title.0', 'Title is required.');
        $this->as()->putJson("/api/v1/prototype/documents/{$v1['doc_no']}", ['title' => 'Tech pack (first)', 'remarks' => 'Superseded', 'file' => 'x', 'version' => 9])->assertOk()
            ->assertJsonPath('data.title', 'Tech pack (first)')->assertJsonPath('data.version', 1)->assertJsonPath('data.file', 'https://drive.example/tp-v1.pdf');
        $this->assertCount(3, $this->as()->getJson('/api/v1/prototype/documents')->assertOk()->json('data'));

        $view = $this->as()->getJson("/api/v1/prototype/samples/{$sample->sample_no}")->assertOk()->json('data');
        $this->assertSame(['MEASUREMENT_SHEET', 'TECH_PACK', 'TECH_PACK'], array_column($view['documents'], 'type'));
        $this->assertSame('Tech pack, sleeve shortened attached as SDC-2026-00002 (V2).', collect($view['timeline'])->where('action', 'document_attached')->values()[1]['summary']);

        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->getJson('/api/v1/prototype/documents')->assertOk();
        $this->as($manager)->postJson('/api/v1/prototype/documents', $form + ['file' => 'y'])->assertForbidden();
    }

    public function test_sample_360_shows_a_sample_end_to_end(): void
    {
        $sample = $this->developedSample(qty: 2);
        $register = $this->as()->getJson('/api/v1/prototype/samples')->assertOk()->json('data');
        $this->assertCount(1, $register);
        $row = $register[0];
        $this->assertSame([$sample->sample_no, 'SR-2026-00001', 'LPP', 'SS27-TEE01', 'QUALITY', 'PENDING', true, '2026-10'], [$row['sample_no'], $row['req_no'], $row['buyer'], $row['style'], $row['stage'], $row['status'], $row['mine'], $row['req_month']]);
        $this->assertSame('Fit Sample', $row['sample_type']);

        $view = $this->as()->getJson("/api/v1/prototype/samples/{$sample->sample_no}")->assertOk()->json('data');
        $this->assertSame([$sample->sample_no, 'LPP', 'SR-2026-00001', 2, $this->owner->name], [$view['sample_no'], $view['buyer'], $view['req_no'], $view['qty'], $view['requested_by']]);
        $this->assertSame(['DEV-2026-00001'], array_column($view['developments'], 'dev_no'));
        $this->assertCount(3, $view['decisions']);
        $this->assertSame([], $view['qc']);
        $this->assertSame(['QC_REQUIRED'], collect($view['alerts'])->pluck('kind')->all());
        $this->as()->getJson('/api/v1/prototype/samples/SMP-0000-00000')->assertNotFound();

        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->as($technician)->getJson('/api/v1/prototype/samples')->assertForbidden();
    }

    public function test_the_dashboard_counts_the_desk_and_sweeps_overdue_samples_once(): void
    {
        $sample = $this->developedSample();
        $tile = fn (array $d, string $key) => collect($d['tiles'])->firstWhere('key', $key)['value'];

        $dash = $this->as()->getJson('/api/v1/prototype/dashboard')->assertOk()->json('data');
        $this->assertSame([1, 1, 1, 0, 0], [$tile($dash, 'total'), $tile($dash, 'new_requests'), $tile($dash, 'pending_qc'), $tile($dash, 'pending_approval'), $tile($dash, 'delayed')]);
        $this->assertSame(['QC' => 1], $dash['by_desk']);
        $this->assertSame('LPP', $dash['by_buyer'][0]['label']);
        $this->assertCount(12, $dash['performance']);
        $this->assertSame(1, $dash['alerts']['open']);
        $this->assertNotEmpty($dash['timeline']);
        $this->assertSame([], $dash['delayed']);

        // Past its required date: delayed, flagged once.
        SampleRequisition::query()->whereKey($sample->requisition_id)->update(['required_date' => now()->subDays(3)->toDateString()]);
        $dash = $this->as()->getJson('/api/v1/prototype/dashboard')->assertOk()->json('data');
        $this->assertSame(1, $tile($dash, 'delayed'));
        $this->assertSame([$sample->sample_no, 3], [$dash['delayed'][0]['sample_no'], $dash['delayed'][0]['days_late']]);
        $this->assertSame(1, $dash['alerts']['critical']);
        $this->as()->getJson('/api/v1/prototype/dashboard')->assertOk();
        $this->as()->getJson('/api/v1/prototype/alerts')->assertOk();
        $this->assertSame(1, SampleAlert::query()->where('kind', 'SAMPLE_OVERDUE')->count());
        $this->assertSame(1, SampleEvent::query()->where('action', 'sample_overdue')->count());
        $register = $this->as()->getJson('/api/v1/prototype/samples')->json('data.0');
        $this->assertSame([true, 'DELAYED', 3], [$register['overdue'], $register['status'], $register['days_late']]);
    }

    /** A sample through its requisition and a completed development: waiting in Quality. */
    private function developedSample(int $qty = 1): Sample
    {
        [$buyer, $style] = $this->buyerStyleOrder();
        $type = collect($this->as()->getJson('/api/v1/prototype/requisitions/options')->json('data.sample_types'))->firstWhere('label', 'Fit Sample (FIT)');
        $this->as()->postJson('/api/v1/prototype/requisitions', ['req_date' => '2026-10-10', 'buyer_id' => $buyer, 'style_id' => $style, 'sample_type_id' => $type['value'], 'qty' => $qty, 'required_date' => now()->addDays(7)->toDateString()])->assertCreated();
        $this->as()->postJson('/api/v1/prototype/requisitions/SR-2026-00001/submit')->assertOk();
        foreach (range(1, 3) as $i) {
            $this->as()->postJson('/api/v1/prototype/requisitions/SR-2026-00001/decide', ['decision' => 'APPROVE'])->assertOk();
        }
        $sample = Sample::query()->firstOrFail();
        $this->as()->postJson('/api/v1/prototype/developments', ['sample_id' => $sample->id, 'dev_date' => '2026-10-12', 'status' => 'COMPLETED'])->assertCreated();

        return $sample->fresh();
    }

    /** @return array{string, string, string} buyer, style and a confirmed order for LPP */
    private function buyerStyleOrder(): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '616'], ['name' => 'Poland', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => 'SS27-TEE01', 'description' => 'Summer Set', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 1000, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data.id');
        Order::query()->whereKey($order)->update(['status' => 'CONFIRMED']);

        return [$buyer, $style, $order];
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
