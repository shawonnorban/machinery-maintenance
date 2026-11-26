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
 * Buyer Gateway → Portal Administration (AnnotechERP 25/05): usage lines (one
 * per buyer per month, within the last twelve, no more active users than
 * logins), branding profiles (one per buyer, an uploaded logo of this
 * company's shown by its own name, stamped when changed, switched live) and
 * the activity trail (an actor, a 24-hour time, never in the future, newest
 * first); and who may.
 */
class BuyerGatewayAdminTest extends TestCase
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

        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'Asia/Dhaka'));
        $buyer = fn (string $code, string $short) => Buyer::query()->create(['company_id' => $this->delta->id, 'code' => $code, 'name' => "{$short} Ltd", 'short_name' => $short, 'pi_address' => 'EU', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->hm = $buyer('HM', 'H&M');
        $this->zara = $buyer('ITX', 'Zara');
    }

    public function test_usage_lines(): void
    {
        $periods = $this->as()->getJson('/api/v1/buyer-gateway/options')->assertOk()->json('data.periods');
        $this->assertCount(12, $periods);
        $this->assertEquals([['value' => '2026-11', 'label' => 'Nov 2026'], ['value' => '2025-12', 'label' => 'Dec 2025']], [$periods[0], $periods[11]]);

        $this->as()->postJson(self::E.'/bg-usage-lines', ['period' => '2026-08'])->assertUnprocessable()->assertJsonPath('errors.buyer_id.0', __('buyergateway.v.buyer'));
        $this->as()->postJson(self::E.'/bg-usage-lines', ['buyer_id' => $this->hm])->assertUnprocessable()->assertJsonPath('errors.period.0', __('buyergateway.v.period'));
        $this->as()->postJson(self::E.'/bg-usage-lines', ['buyer_id' => $this->hm, 'period' => '2025-11'])->assertUnprocessable()->assertJsonPath('errors.period.0', __('buyergateway.v.period'));
        $this->as()->postJson(self::E.'/bg-usage-lines', ['buyer_id' => $this->hm, 'period' => '2026-12'])->assertUnprocessable()->assertJsonValidationErrors(['period']);
        $line = $this->as()->postJson(self::E.'/bg-usage-lines', ['buyer_id' => $this->hm, 'period' => '2026-08', 'logins' => 412, 'active_users' => 9, 'most_opened' => 'ORDERS', 'downloads' => 148, 'avg_session' => 11.4, 'adoption' => 92.4])->assertCreated()->json('data');
        $this->assertEquals(['H&M', 'Aug 2026', 412, 9, 'ORDERS', 148, 11.4, 92.4], [$line['buyer'], $line['period_label'], $line['logins'], $line['active_users'], $line['most_opened'], $line['downloads'], $line['avg_session'], $line['adoption']]);
        // One line per buyer per month: the same month again, or moving another line onto it.
        $this->as()->postJson(self::E.'/bg-usage-lines', ['buyer_id' => $this->hm, 'period' => '2026-08'])->assertUnprocessable()->assertJsonPath('errors.buyer_id.0', __('buyergateway.v.usage_taken', ['buyer' => 'H&M']));
        $july = $this->as()->postJson(self::E.'/bg-usage-lines', ['buyer_id' => $this->hm, 'period' => '2026-07'])->assertCreated()->json('data');
        $this->as()->patchJson(self::E."/bg-usage-lines/{$july['id']}", ['period' => '2026-08'])->assertUnprocessable()->assertJsonPath('errors.period.0', __('buyergateway.v.usage_taken', ['buyer' => 'H&M']));
        $this->as()->postJson(self::E.'/bg-usage-lines', ['buyer_id' => $this->zara, 'period' => '2026-08'])->assertCreated();
        // Never more active users than logins, either way round.
        $this->as()->postJson(self::E.'/bg-usage-lines', ['buyer_id' => $this->zara, 'period' => '2026-07', 'logins' => 3, 'active_users' => 5])->assertUnprocessable()->assertJsonPath('errors.active_users.0', __('buyergateway.v.users_over_logins'));
        $this->as()->patchJson(self::E."/bg-usage-lines/{$line['id']}", ['logins' => 4])->assertUnprocessable()->assertJsonPath('errors.logins.0', __('buyergateway.v.users_over_logins'));
        $this->as()->patchJson(self::E."/bg-usage-lines/{$line['id']}", ['adoption' => 104])->assertUnprocessable()->assertJsonValidationErrors(['adoption']);
        $this->as()->patchJson(self::E."/bg-usage-lines/{$line['id']}", ['most_opened' => 'HOME'])->assertUnprocessable()->assertJsonValidationErrors(['most_opened']);
        $this->as()->patchJson(self::E."/bg-usage-lines/{$line['id']}", ['logins' => 430, 'active_users' => 10])->assertOk()->assertJsonPath('data.logins', 430)->assertJsonPath('data.period', '2026-08');
    }

    public function test_branding_profiles(): void
    {
        $this->as()->postJson(self::E.'/bg-branding-profiles', [])->assertUnprocessable()->assertJsonPath('errors.buyer_id.0', __('buyergateway.v.buyer'));
        $p = $this->as()->postJson(self::E.'/bg-branding-profiles', ['buyer_id' => $this->hm, 'theme' => 'SYSTEM', 'accent_slot' => 3, 'logo_file' => ' hm-portal-mark.png ', 'welcome_line' => 'Welcome to your production view', 'support_email' => 'portal@delta.test', 'updated_on' => '2026-06-18'])->assertCreated()->json('data');
        $this->assertEquals(['SYSTEM', 3, 'hm-portal-mark.png', 'hm-portal-mark.png', null, '2026-06-18', true], [$p['theme'], $p['accent_slot'], $p['logo_file'], $p['logo_name'], $p['logo_url'], $p['updated_on'], $p['live']]);
        $this->as()->postJson(self::E.'/bg-branding-profiles', ['buyer_id' => $this->hm])->assertUnprocessable()->assertJsonPath('errors.buyer_id.0', __('buyergateway.v.profile_taken', ['buyer' => 'H&M']));
        $this->as()->postJson(self::E.'/bg-branding-profiles', ['buyer_id' => $this->zara, 'theme' => 'NEON'])->assertUnprocessable()->assertJsonValidationErrors(['theme']);
        $this->as()->postJson(self::E.'/bg-branding-profiles', ['buyer_id' => $this->zara, 'accent_slot' => 9])->assertUnprocessable()->assertJsonValidationErrors(['accent_slot']);
        $this->as()->postJson(self::E.'/bg-branding-profiles', ['buyer_id' => $this->zara, 'support_email' => 'not an address'])->assertUnprocessable()->assertJsonValidationErrors(['support_email']);
        // Left blank, the day is the day of the change.
        $z = $this->as()->postJson(self::E.'/bg-branding-profiles', ['buyer_id' => $this->zara])->assertCreated()->json('data');
        $this->assertEquals(['LIGHT', 1, '2026-11-11', true], [$z['theme'], $z['accent_slot'], $z['updated_on'], $z['live']]);
        $this->travelTo(CarbonImmutable::parse('2026-11-13 09:00:00', 'Asia/Dhaka'));
        $this->as()->patchJson(self::E."/bg-branding-profiles/{$p['id']}", ['theme' => 'DARK'])->assertOk()->assertJsonPath('data.updated_on', '2026-11-13');
        $this->as()->patchJson(self::E."/bg-branding-profiles/{$z['id']}", ['live' => false])->assertOk()->assertJsonPath('data.live', false);

        // A logo uploaded here: an image only, shown by its own name; any other path is refused.
        $this->as()->post('/api/v1/buyer-gateway/logo-files', ['file' => UploadedFile::fake()->createWithContent('mark.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonPath('errors.file.0', __('buyergateway.v.logo_type'));
        $this->as()->post('/api/v1/buyer-gateway/logo-files', ['file' => UploadedFile::fake()->image('big.png')->size(3000)], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonPath('errors.file.0', __('buyergateway.v.logo_size'));
        $upload = $this->as()->post('/api/v1/buyer-gateway/logo-files', ['file' => UploadedFile::fake()->image('hm-logo.png', 240, 80)], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertSame('hm-logo.png', $upload['name']);
        $logo = $this->as()->patchJson(self::E."/bg-branding-profiles/{$p['id']}", ['logo_file' => $upload['path']])->assertOk()->json('data');
        $this->assertEquals([$upload['path'], 'hm-logo.png', $upload['path']], [$logo['logo_file'], $logo['logo_name'], $logo['logo_url']]);
        $this->as()->get(str_replace('/api/', '/api/v1/', $upload['path']))->assertRedirect();
        $this->as()->patchJson(self::E."/bg-branding-profiles/{$p['id']}", ['logo_file' => '/api/files/01ARZ3NDEKTSV4RRFFQ69G5FAV/download'])->assertUnprocessable()->assertJsonPath('errors.logo_file.0', __('buyergateway.v.logo_file'));
        $doc = $this->as()->post('/api/v1/buyer-gateway/document-files', ['file' => UploadedFile::fake()->image('tech-pack.png')], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->as()->patchJson(self::E."/bg-branding-profiles/{$p['id']}", ['logo_file' => $doc['path']])->assertUnprocessable()->assertJsonPath('errors.logo_file.0', __('buyergateway.v.logo_file'));
    }

    public function test_activity_trail(): void
    {
        $this->as()->postJson(self::E.'/bg-activity-entries', ['buyer_id' => $this->hm])->assertUnprocessable()->assertJsonPath('errors.actor.0', __('buyergateway.v.actor'));
        // Left blank, today and now.
        $now = $this->as()->postJson(self::E.'/bg-activity-entries', ['actor' => ' Hiro Tanaka ', 'buyer_id' => $this->zara, 'action' => 'SIGNED_IN', 'device' => 'TABLET'])->assertCreated()->json('data');
        $this->assertEquals(['Hiro Tanaka', '2026-11-11', '10:00', 'Zara'], [$now['actor'], $now['occurred_on'], $now['occurred_time'], $now['buyer']]);
        $a = $this->as()->postJson(self::E.'/bg-activity-entries', ['actor' => 'Anna Lindqvist', 'buyer_id' => $this->hm, 'action' => 'APPROVED', 'target' => 'ST-4471 PP sample, version 2', 'occurred_on' => '2026-08-19', 'occurred_time' => '14:22', 'device' => 'DESKTOP'])->assertCreated()->json('data');
        $b = $this->as()->postJson(self::E.'/bg-activity-entries', ['actor' => 'Anna Lindqvist', 'buyer_id' => $this->hm, 'action' => 'DOWNLOADED', 'occurred_on' => '2026-08-19', 'occurred_time' => '9:41'])->assertCreated()->json('data');
        $this->assertSame('09:41', $b['occurred_time']);
        $this->as()->postJson(self::E.'/bg-activity-entries', ['actor' => 'X', 'occurred_time' => '25:00'])->assertUnprocessable()->assertJsonPath('errors.occurred_time.0', __('buyergateway.v.time'));
        $this->as()->postJson(self::E.'/bg-activity-entries', ['actor' => 'X', 'occurred_time' => '2pm'])->assertUnprocessable()->assertJsonPath('errors.occurred_time.0', __('buyergateway.v.time'));
        // Never in the future: a later day, or later today.
        $this->as()->postJson(self::E.'/bg-activity-entries', ['actor' => 'X', 'occurred_on' => '2026-11-12', 'occurred_time' => '08:00'])->assertUnprocessable()->assertJsonPath('errors.occurred_on.0', __('buyergateway.v.activity_future'));
        $this->as()->postJson(self::E.'/bg-activity-entries', ['actor' => 'X', 'occurred_time' => '11:30'])->assertUnprocessable()->assertJsonPath('errors.occurred_time.0', __('buyergateway.v.activity_future'));
        $this->as()->postJson(self::E.'/bg-activity-entries', ['actor' => 'X', 'action' => 'DELETED'])->assertUnprocessable()->assertJsonValidationErrors(['action']);
        // Newest first; clearing the day or time on an edit keeps them.
        $this->assertSame([$now['id'], $a['id'], $b['id']], array_column($this->as()->getJson(self::E.'/bg-activity-entries')->json('data'), 'id'));
        $kept = $this->as()->patchJson(self::E."/bg-activity-entries/{$a['id']}", ['occurred_on' => '', 'occurred_time' => '', 'device' => 'MOBILE'])->assertOk()->json('data');
        $this->assertEquals(['2026-08-19', '14:22', 'MOBILE'], [$kept['occurred_on'], $kept['occurred_time'], $kept['device']]);
    }

    public function test_who_may(): void
    {
        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'auditor@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'manager@delta.test');
        $keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');
        $this->as($auditor)->getJson(self::E.'/bg-branding-profiles')->assertOk();
        $this->as($auditor)->postJson(self::E.'/bg-activity-entries', ['actor' => 'X'])->assertForbidden();
        $this->as($auditor)->post('/api/v1/buyer-gateway/logo-files', ['file' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])->assertForbidden();
        $this->as($manager)->postJson(self::E.'/bg-usage-lines', ['buyer_id' => $this->hm, 'period' => '2026-10'])->assertCreated();
        $upload = $this->as($manager)->post('/api/v1/buyer-gateway/logo-files', ['file' => UploadedFile::fake()->image('z.png')], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->as($auditor)->get(str_replace('/api/', '/api/v1/', $upload['path']))->assertRedirect();
        $this->as($keeper)->getJson(self::E.'/bg-usage-lines')->assertForbidden();
        $this->as($keeper)->get(str_replace('/api/', '/api/v1/', $upload['path']), ['Accept' => 'application/json'])->assertForbidden();

        $other = TenantFixture::company('Other Ltd', 'OTH');
        TenantFixture::actingAsTenant($other);
        $stranger = TenantFixture::user($other, 'COMPANY_OWNER', 'owner@other.test');
        $this->assertSame([], $this->as($stranger, $other)->getJson(self::E.'/bg-usage-lines')->assertOk()->json('data'));
        $this->as($stranger, $other)->postJson(self::E.'/bg-branding-profiles', ['buyer_id' => $this->hm])->assertUnprocessable()->assertJsonValidationErrors(['buyer_id']);
        $this->as($stranger, $other)->postJson(self::E.'/bg-activity-entries', ['actor' => 'X', 'buyer_id' => $this->hm])->assertUnprocessable()->assertJsonValidationErrors(['buyer_id']);
        // Nor is another company's logo theirs to show.
        $theirs = Buyer::query()->create(['company_id' => $other->id, 'code' => 'NX', 'name' => 'Next Ltd', 'short_name' => 'Next', 'pi_address' => 'UK', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->as($stranger, $other)->postJson(self::E.'/bg-branding-profiles', ['buyer_id' => $theirs, 'logo_file' => $upload['path']])->assertUnprocessable()->assertJsonPath('errors.logo_file.0', __('buyergateway.v.logo_file'));
    }

    private function as(?User $user = null, ?Company $company = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, ($company ?? $this->delta)->id, 'Test token');

        return $this->withToken($plain);
    }
}
