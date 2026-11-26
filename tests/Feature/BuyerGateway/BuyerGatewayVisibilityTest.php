<?php

declare(strict_types=1);

namespace Tests\Feature\BuyerGateway;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Buyer Gateway → Order & Shipment Visibility (AnnotechERP 25/02): one
 * visibility rule per buyer; samples (one per style, type and version, a sent
 * date once past In development, the decision not due before sending);
 * shipments (Arrived / Delivered need the actual date); the document vault
 * (an attached file sets the size, only a vault upload is accepted); and who
 * may.
 */
class BuyerGatewayVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const E = '/api/v1/erp';

    private Company $delta;

    private User $owner;

    private string $hm;

    private string $zara;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'Asia/Dhaka'));
        $buyer = fn (string $code, string $short) => Buyer::query()->create(['company_id' => $this->delta->id, 'code' => $code, 'name' => "{$short} Ltd", 'short_name' => $short, 'pi_address' => 'EU', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->hm = $buyer('HM', 'H&M');
        $this->zara = $buyer('ITX', 'Zara');
    }

    public function test_visibility_rules_samples_and_shipments(): void
    {
        $this->as()->postJson(self::E.'/bg-visibility-rules', [])->assertUnprocessable()->assertJsonPath('errors.buyer_id.0', __('buyergateway.v.buyer'));
        $rule = $this->as()->postJson(self::E.'/bg-visibility-rules', ['buyer_id' => $this->hm, 'scope' => 'ALL', 'depth' => 'FULL', 'delays' => 'ALWAYS', 'note' => 'Strategic account'])->assertCreated()->json('data');
        $this->assertSame(['ALL', 'FULL', 'ALWAYS', '2026-11-11', $this->owner->name, true, 'H&M'], [$rule['scope'], $rule['depth'], $rule['delays'], $rule['effective_from'], $rule['set_by_name'], $rule['active'], $rule['buyer']]);
        $this->as()->postJson(self::E.'/bg-visibility-rules', ['buyer_id' => $this->hm])->assertUnprocessable()->assertJsonPath('errors.buyer_id.0', __('buyergateway.v.rule_taken', ['buyer' => 'H&M']));
        $this->as()->postJson(self::E.'/bg-visibility-rules', ['buyer_id' => $this->zara, 'depth' => 'EVERYTHING'])->assertUnprocessable()->assertJsonValidationErrors(['depth']);
        $this->as()->postJson(self::E.'/bg-visibility-rules', ['buyer_id' => $this->zara])->assertCreated()->assertJsonPath('data.depth', 'STATUS')->assertJsonPath('data.scope', 'CONFIRMED');
        $this->as()->patchJson(self::E."/bg-visibility-rules/{$rule['id']}", ['active' => false])->assertOk()->assertJsonPath('data.active', false);
        $this->as()->patchJson(self::E."/bg-visibility-rules/{$rule['id']}", ['note' => 'Paused'])->assertOk()->assertJsonPath('data.note', 'Paused');

        // Samples.
        $this->as()->postJson(self::E.'/bg-sample-progress', [])->assertUnprocessable()->assertJsonPath('errors.style.0', __('buyergateway.v.style'))->assertJsonPath('errors.buyer_id.0', __('buyergateway.v.buyer'));
        $this->as()->postJson(self::E.'/bg-sample-progress', ['style' => 'st-4471', 'buyer_id' => $this->hm, 'sample_type' => 'PP', 'stage' => 'SENT'])->assertUnprocessable()->assertJsonPath('errors.stage.0', __('buyergateway.v.sent_required'));
        $this->as()->postJson(self::E.'/bg-sample-progress', ['style' => 'st-4471', 'buyer_id' => $this->hm, 'sample_type' => 'PP', 'stage' => 'SENT', 'sent_on' => '2026-11-05', 'decision_due' => '2026-11-01'])
            ->assertUnprocessable()->assertJsonPath('errors.decision_due.0', __('buyergateway.v.due_before_sent'));
        $sample = $this->as()->postJson(self::E.'/bg-sample-progress', ['style' => 'st-4471', 'buyer_id' => $this->hm, 'sample_type' => 'PP', 'stage' => 'SENT', 'sent_on' => '2026-11-05', 'decision_due' => '2026-11-12', 'version' => 2, 'courier' => 'DHL'])->assertCreated()->json('data');
        $this->assertSame(['ST-4471', 2, 'SENT'], [$sample['style'], $sample['version'], $sample['stage']]);
        $this->as()->postJson(self::E.'/bg-sample-progress', ['style' => 'ST-4471', 'buyer_id' => $this->hm, 'sample_type' => 'PP', 'version' => 2])->assertUnprocessable()->assertJsonPath('errors.style.0', __('buyergateway.v.sample_taken'));
        $this->as()->postJson(self::E.'/bg-sample-progress', ['style' => 'ST-4471', 'buyer_id' => $this->hm, 'sample_type' => 'PP', 'version' => 3])->assertCreated()->assertJsonPath('data.stage', 'IN_DEVELOPMENT');
        $this->as()->patchJson(self::E."/bg-sample-progress/{$sample['id']}", ['stage' => 'APPROVED'])->assertOk()->assertJsonPath('data.stage', 'APPROVED');

        // Shipments.
        $this->as()->postJson(self::E.'/bg-shipment-progress', [])->assertUnprocessable()->assertJsonPath('errors.order_no.0', __('buyergateway.v.order'));
        $ship = $this->as()->postJson(self::E.'/bg-shipment-progress', ['order_no' => 'so-4402', 'buyer_id' => $this->hm, 'mode' => 'SEA', 'agreed_date' => '2026-11-14', 'quantity' => 184000, 'cartons' => 3680])->assertCreated()->json('data');
        $this->assertSame(['SO-4402', 'BOOKED', 184000, 3680], [$ship['order_no'], $ship['stage'], $ship['quantity'], $ship['cartons']]);
        $this->as()->patchJson(self::E."/bg-shipment-progress/{$ship['id']}", ['stage' => 'DELIVERED'])->assertUnprocessable()->assertJsonPath('errors.stage.0', __('buyergateway.v.actual_required'));
        $this->as()->patchJson(self::E."/bg-shipment-progress/{$ship['id']}", ['stage' => 'ON_THE_WATER'])->assertOk();
        $this->as()->patchJson(self::E."/bg-shipment-progress/{$ship['id']}", ['stage' => 'DELIVERED', 'actual_date' => '2026-11-12'])->assertOk()->assertJsonPath('data.actual_date', '2026-11-12');
        $this->as()->patchJson(self::E."/bg-shipment-progress/{$ship['id']}", ['quantity' => -1])->assertUnprocessable()->assertJsonValidationErrors(['quantity']);
    }

    public function test_document_vault(): void
    {
        $this->as()->postJson(self::E.'/bg-shared-documents', [])->assertUnprocessable()->assertJsonPath('errors.title.0', __('buyergateway.v.document'));
        $typed = $this->as()->postJson(self::E.'/bg-shared-documents', ['title' => 'OEKO-TEX certificate 2026', 'buyer_id' => $this->zara, 'kind' => 'CERTIFICATE', 'size_mb' => 1.4])->assertCreated()->json('data');
        $this->assertEquals(['SHARED', 1.4, 0, '2026-11-11', $this->owner->name], [$typed['state'], $typed['size_mb'], $typed['downloads'], $typed['shared_on'], $typed['shared_by_name']]);

        // A file uploaded for the vault sets the size; any other path is refused.
        $upload = $this->as()->post('/api/v1/buyer-gateway/document-files', ['file' => UploadedFile::fake()->image('tech-pack.png', 1200, 1200)->size(2300)])->assertCreated()->json('data');
        $this->assertMatchesRegularExpression('#^/api/files/[0-9A-Za-z]{26}/download$#', $upload['path']);
        $doc = $this->as()->postJson(self::E.'/bg-shared-documents', ['title' => 'ST-4471 tech pack rev 3', 'buyer_id' => $this->hm, 'kind' => 'TECH_PACK', 'size_mb' => 99, 'file_path' => $upload['path']])->assertCreated()->json('data');
        $this->assertEquals([$upload['path'], $upload['size_mb']], [$doc['file_path'], $doc['size_mb']]);
        $this->assertNotEquals(99, $doc['size_mb']);
        $this->as()->postJson(self::E.'/bg-shared-documents', ['title' => 'X', 'buyer_id' => $this->hm, 'file_path' => '/api/files/01ARZ3NDEKTSV4RRFFQ69G5FAV/download'])->assertUnprocessable()->assertJsonPath('errors.file_path.0', __('buyergateway.v.file'));
        $this->as()->postJson(self::E.'/bg-shared-documents', ['title' => 'X', 'buyer_id' => $this->hm, 'file_path' => 'https://elsewhere.example/x.pdf'])->assertUnprocessable()->assertJsonValidationErrors(['file_path']);
        $this->as()->get(str_replace('/api/', '/api/v1/', $upload['path']))->assertRedirect();
        $this->as()->patchJson(self::E."/bg-shared-documents/{$doc['id']}", ['state' => 'WITHDRAWN', 'downloads' => 18])->assertOk()->assertJsonPath('data.state', 'WITHDRAWN')->assertJsonPath('data.downloads', 18);
        $this->as()->deleteJson(self::E."/bg-shared-documents/{$typed['id']}")->assertNoContent();
    }

    public function test_who_may(): void
    {
        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'auditor@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');
        $this->as($auditor)->getJson(self::E.'/bg-sample-progress')->assertOk();
        $this->as($auditor)->getJson('/api/v1/buyer-gateway/options')->assertOk()->assertJsonPath('data.can_manage_visibility', false);
        $this->as($auditor)->postJson(self::E.'/bg-visibility-rules', ['buyer_id' => $this->hm])->assertForbidden();
        $this->as($auditor)->post('/api/v1/buyer-gateway/document-files', ['file' => UploadedFile::fake()->image('a.png')])->assertForbidden();
        $this->as($manager)->postJson(self::E.'/bg-shipment-progress', ['order_no' => 'SO-1', 'buyer_id' => $this->hm])->assertCreated();
        $this->as($keeper)->getJson(self::E.'/bg-shared-documents')->assertForbidden();

        $other = TenantFixture::company('Other Ltd', 'OTH');
        TenantFixture::actingAsTenant($other);
        $stranger = TenantFixture::user($other, 'COMPANY_OWNER', 'owner@other.test');
        $this->assertSame([], $this->as($stranger, $other)->getJson(self::E.'/bg-shipment-progress')->assertOk()->json('data'));
    }

    private function as(?User $user = null, ?Company $company = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, ($company ?? $this->delta)->id, 'Test token');

        return $this->withToken($plain);
    }
}
