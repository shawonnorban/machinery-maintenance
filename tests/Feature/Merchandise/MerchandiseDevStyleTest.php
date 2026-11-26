<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\Size;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Style & Order → Development Style Catalogue (AnnotechERP 06/07): styles
 * numbered DEV-000001, sent for approval and approved, returned or rejected
 * with a note; the Inbox every approver shares and the sender's Sent list;
 * the wizard's review, attachments and packing details.
 */
class MerchandiseDevStyleTest extends TestCase
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
        Storage::fake(config('filesystems.default'));
    }

    public function test_a_development_style_goes_through_its_approval(): void
    {
        $first = $this->style('637PK');
        $second = $this->style('529');

        $list = $this->as()->getJson('/api/v1/merchandise/dev-styles')->assertOk()->json('data');
        $this->assertSame(['total' => 2, 'DRAFT' => 2, 'SENT' => 0, 'APPROVED' => 0, 'REJECTED' => 0, 'REVISION' => 0], $list['stats']);
        $this->assertEqualsCanonicalizing(['DEV-000001', 'DEV-000002'], array_column($list['rows'], 'dev_no'));

        // Only a style that is with its approver can be approved; a returned or rejected one says why.
        $this->as()->postJson("/api/v1/merchandise/styles/{$first}/approval/approve")->assertUnprocessable()->assertJsonValidationErrors('status');
        $sent = $this->as()->postJson("/api/v1/merchandise/styles/{$first}/approval/send", ['note' => 'Please approve'])->assertOk()->json('data');
        $this->assertSame(['SENT', true, 'Please approve'], [$sent['status'], $sent['locked'], $sent['last_note']]);
        $this->as()->postJson("/api/v1/merchandise/styles/{$first}/approval/send")->assertUnprocessable();
        $this->assertSame(['637PK'], array_column($this->as()->getJson('/api/v1/merchandise/dev-styles/inbox')->assertOk()->json('data'), 'style_no'));

        $this->as()->postJson("/api/v1/merchandise/styles/{$first}/approval/revision", ['note' => ''])->assertUnprocessable()->assertJsonPath('errors.note.0', 'Say what needs changing.');
        $this->as()->postJson("/api/v1/merchandise/styles/{$first}/approval/revision", ['note' => 'Fix the BOM'])->assertOk()->assertJsonPath('data.status', 'REVISION')->assertJsonPath('data.locked', false);
        $this->as()->postJson("/api/v1/merchandise/styles/{$first}/approval/send")->assertOk();
        $approved = $this->as()->postJson("/api/v1/merchandise/styles/{$first}/approval/approve", ['note' => 'OK'])->assertOk()->json('data');
        $this->assertSame(['APPROVED', true], [$approved['status'], $approved['locked']]);
        $this->assertSame(['SENT', 'REVISION', 'SENT', 'APPROVED'], array_column($approved['history'], 'action'));
        // Approving makes the style Style Approved on its sheet.
        $this->as()->getJson("/api/v1/merchandise/styles/{$first}")->assertJsonPath('data.status', 'STYLE_APPROVED');

        $sentList = $this->as()->getJson('/api/v1/merchandise/dev-styles/sent')->assertOk()->json('data');
        $this->assertSame([['637PK', 'APPROVED', 'OK']], array_map(fn ($r) => [$r['style_no'], $r['dev_status'], $r['approver_note']], $sentList));

        // An approver may reject (with a note) or approve without the style being sent.
        $this->as()->postJson("/api/v1/merchandise/styles/{$second}/approval/direct")->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->as()->postJson("/api/v1/merchandise/styles/{$second}/approval/direct")->assertUnprocessable();
        $third = $this->style('121');
        $this->as()->postJson("/api/v1/merchandise/styles/{$third}/approval/send")->assertOk();
        $this->as()->postJson("/api/v1/merchandise/styles/{$third}/approval/reject")->assertUnprocessable()->assertJsonPath('errors.note.0', 'Say why the style is rejected.');
        $this->as()->postJson("/api/v1/merchandise/styles/{$third}/approval/reject", ['note' => 'Not this season'])->assertOk()->assertJsonPath('data.status', 'REJECTED');

        $stats = $this->as()->getJson('/api/v1/merchandise/dev-styles')->json('data');
        $this->assertSame([3, 2, 1, 0, 0], [$stats['stats']['total'], $stats['stats']['APPROVED'], $stats['stats']['REJECTED'], $stats['inbox'], $stats['sent_pending']]);
    }

    public function test_the_review_counts_each_step_and_files_and_packing_are_kept(): void
    {
        $style = $this->style('637PK');

        $review = $this->as()->getJson("/api/v1/merchandise/styles/{$style}/review")->assertOk()->json('data');
        $this->assertSame(1, $review['counts']['profile']);
        $this->assertSame(11, $review['completion']);
        $this->assertSame(['637PK', 'M'], [$review['style']['style_no'], $review['style']['size_set']]);

        $png = UploadedFile::fake()->createWithContent('front.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $this->as()->post("/api/v1/merchandise/styles/{$style}/attachments", ['kind' => 'WALL', 'file' => $png], ['Accept' => 'application/json'])->assertUnprocessable();
        $files = $this->as()->post("/api/v1/merchandise/styles/{$style}/attachments", ['kind' => 'FRONT_VIEW', 'file' => $png], ['Accept' => 'application/json'])->assertOk()->json('data');
        $this->assertSame('front.png', $files['FRONT_VIEW']['name']);
        $this->assertSame("/api/files/{$files['FRONT_VIEW']['file_id']}/download", $files['FRONT_VIEW']['path']);
        $this->as()->getJson("/api/v1/merchandise/styles/{$style}/attachments")->assertOk()->assertJsonCount(1, 'data');

        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/packing-details", ['packing_method' => 'BOX'])->assertUnprocessable();
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/packing-details", ['packing_method' => 'FOLD_POLY_CARTON', 'carton_type' => 'PLY_5', 'pcs_per_carton' => 24, 'carton_size' => '60 × 40 × 40 CM', 'hangtag' => true, 'remarks' => ' '])
            ->assertOk()->assertJsonPath('data.details.pcs_per_carton', 24)->assertJsonPath('data.details.hangtag', true)->assertJsonPath('data.details.remarks', null);

        $review = $this->as()->getJson("/api/v1/merchandise/styles/{$style}/review")->json('data');
        $this->assertSame([1, 1, 33], [$review['counts']['attachments'], $review['counts']['packing'], $review['completion']]);

        $this->as()->deleteJson("/api/v1/merchandise/styles/{$style}/attachments/FRONT_VIEW")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_an_approved_style_is_registered_and_changed_through_requests(): void
    {
        $draft = $this->style('DRAFT-1');
        $style = $this->style('637PK');
        $this->as()->postJson("/api/v1/merchandise/styles/{$style}/approval/direct")->assertOk();
        $year = now()->format('Y');

        // Only an approved development style is offered and registered.
        $this->assertSame(['637PK'], array_column($this->as()->getJson('/api/v1/merchandise/style-registrations/candidates')->assertOk()->json('data'), 'style_no'));
        $this->as()->postJson('/api/v1/merchandise/style-registrations', ['style_id' => $draft])->assertUnprocessable()->assertJsonValidationErrors('style_id');

        $raised = $this->as()->postJson('/api/v1/merchandise/style-registrations', ['style_id' => $style])->assertCreated()->json('data');
        $id = $raised['request']['id'];
        $this->assertSame(["SRQ-{$year}-000001", "ST-{$year}-00001", 'DRAFT', 'REGISTRATION', 1, null], [$raised['request']['request_no'], $raised['request']['style_code'], $raised['request']['status'], $raised['request']['type'], $raised['request']['version'], $raised['request']['registration_no']]);
        $this->assertContains('Brand is required.', $raised['missing']);
        $this->assertSame(['637PK', 'DEV-000002'], [$raised['profile']['dev_style_no'], $raised['profile']['dev_no']]);
        $this->as()->postJson('/api/v1/merchandise/style-registrations', ['style_id' => $style])->assertUnprocessable();

        // A profile with gaps is not submitted.
        $this->as()->postJson("/api/v1/merchandise/style-registrations/{$id}/submit")->assertUnprocessable()->assertJsonValidationErrors('profile');
        $profile = ['brand' => 'Sinsay', 'product' => 'Hoodie', 'product_category' => 'Tops', 'product_type' => 'Knit', 'gender' => 'KIDS', 'season_year' => 2027, 'department' => 'Kids'];
        $this->as()->putJson("/api/v1/merchandise/style-registrations/{$id}", ['gender' => 'ROBOT'])->assertUnprocessable();
        $saved = $this->as()->putJson("/api/v1/merchandise/style-registrations/{$id}", $profile)->assertOk()->json('data');
        $this->assertSame([[], 100], [$saved['missing'], $saved['steps']['profile']]);

        $this->as()->postJson("/api/v1/merchandise/style-registrations/{$id}/submit", ['note' => 'Ready'])->assertOk()->assertJsonPath('data.request.status', 'PENDING');
        $this->as()->putJson("/api/v1/merchandise/style-registrations/{$id}", ['brand' => 'X'])->assertUnprocessable();
        $this->as()->postJson("/api/v1/merchandise/style-registrations/{$id}/return")->assertUnprocessable()->assertJsonPath('errors.note.0', 'Say what needs changing.');
        $this->as()->postJson("/api/v1/merchandise/style-registrations/{$id}/return", ['note' => 'Brand?'])->assertOk()->assertJsonPath('data.request.status', 'RETURNED');
        $this->as()->postJson("/api/v1/merchandise/style-registrations/{$id}/submit")->assertOk();
        $approved = $this->as()->postJson("/api/v1/merchandise/style-registrations/{$id}/approve")->assertOk()->json('data');
        $this->assertSame(['APPROVED', "REG-{$year}-000001", true], [$approved['request']['status'], $approved['request']['registration_no'], $approved['request']['active']]);
        $this->assertSame(['SUBMITTED', 'RETURNED', 'SUBMITTED', 'APPROVED'], array_column($approved['history'], 'action'));

        $index = $this->as()->getJson('/api/v1/merchandise/style-registrations')->assertOk()->json('data');
        $this->assertSame([1, 1, 1], [$index['counts']['registered'], $index['counts']['active'], $index['counts']['APPROVED']]);
        $this->assertSame(["ST-{$year}-00001", "REG-{$year}-000001", '637PK'], [$index['registered'][0]['style_code'], $index['registered'][0]['registration_no'], $index['registered'][0]['style_ref']]);

        // A registered style changes through a change request: the next version, the same number.
        $change = $this->as()->postJson("/api/v1/merchandise/style-registrations/{$id}/change")->assertCreated()->json('data');
        $this->assertSame(['CHANGE', 2, "REG-{$year}-000001", 'Sinsay', 'DRAFT'], [$change['request']['type'], $change['request']['version'], $change['request']['registration_no'], $change['request']['brand'], $change['request']['status']]);
        $this->as()->postJson("/api/v1/merchandise/style-registrations/{$id}/change")->assertUnprocessable();
        $cid = $change['request']['id'];
        $this->as()->putJson("/api/v1/merchandise/style-registrations/{$cid}", ['brand' => 'House'])->assertOk();
        $this->as()->postJson("/api/v1/merchandise/style-registrations/{$cid}/submit")->assertOk();
        $this->as()->postJson("/api/v1/merchandise/style-registrations/{$cid}/approve")->assertOk()->assertJsonPath('data.request.active', true);
        $index = $this->as()->getJson('/api/v1/merchandise/style-registrations')->json('data');
        $this->assertSame([1, 2, 'House'], [$index['counts']['active'], $index['registered'][0]['version'], $index['registered'][0]['brand']]);

        // A draft may be discarded; nothing else.
        $this->as()->deleteJson("/api/v1/merchandise/style-registrations/{$cid}")->assertUnprocessable();
    }

    public function test_the_style_catalogue_shows_the_running_styles(): void
    {
        $running = $this->style('RUN-1');
        $this->style('DEV-1');
        $this->as()->postJson("/api/v1/merchandise/styles/{$running}/approval/direct")->assertOk();
        Style::query()->whereKey($running)->update(['status' => 'RUNNING']);

        $this->assertSame(['RUN-1'], array_column($this->as()->getJson('/api/v1/merchandise/style-order/gallery')->assertOk()->json('data'), 'style_no'));
    }

    public function test_only_an_approver_approves_and_a_manager_only_reads(): void
    {
        $style = $this->style('637PK');
        $this->as()->postJson("/api/v1/merchandise/styles/{$style}/approval/send")->assertOk();
        $manager = TenantFixture::user($this->delta, 'VIEWER', 'viewer@delta.test');
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->as($manager)->getJson('/api/v1/merchandise/dev-styles/inbox')->assertOk()->assertJsonCount(1, 'data');
        $this->as($manager)->postJson("/api/v1/merchandise/styles/{$style}/approval/approve")->assertForbidden();
        $this->as($technician)->getJson('/api/v1/merchandise/dev-styles')->assertForbidden();
    }

    private function style(string $ref): string
    {
        $buyer = Buyer::query()->value('id') ?? $this->buyer();
        $size = Size::query()->value('id') ?? $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');

        return $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'style_ref' => $ref, 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
    }

    private function buyer(): string
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP S.A.', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;

        return $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
