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
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Buyer Gateway → Buyer Engagement (AnnotechERP 25/04): the request inbox
 * (route first, age, closed date), complaints (the year's next CMP number,
 * a settled or rejected one keeps its resolution), announcements (the state
 * follows the window unless draft, one-buyer needs the buyer, never expires
 * before published), surveys (responses within recipients, no score without
 * a response, sent before open) and tickets (TKT numbers, owner, hours open,
 * resolved time); and who may.
 */
class BuyerGatewayEngagementTest extends TestCase
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
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'Asia/Dhaka'));
        $buyer = fn (string $code, string $short) => Buyer::query()->create(['company_id' => $this->delta->id, 'code' => $code, 'name' => "{$short} Ltd", 'short_name' => $short, 'pi_address' => 'EU', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->hm = $buyer('HM', 'H&M');
        $this->zara = $buyer('ITX', 'Zara');
    }

    public function test_requests_and_complaints(): void
    {
        $this->as()->postJson(self::E.'/bg-requests', ['buyer_id' => $this->hm])->assertUnprocessable()->assertJsonPath('errors.subject.0', __('buyergateway.v.subject'));
        $r = $this->as()->postJson(self::E.'/bg-requests', ['subject' => ' Move ex-factory of SO-4402 by a week ', 'buyer_id' => $this->hm, 'kind' => 'ORDER_CHANGE', 'raised_on' => '2026-11-01'])->assertCreated()->json('data');
        $this->assertEquals(['Move ex-factory of SO-4402 by a week', 'UNROUTED', 'NORMAL', 'NEW', 10, null], [$r['subject'], $r['routed_to'], $r['priority'], $r['state'], $r['age'], $r['closed_on']]);
        $this->assertSame('2026-11-11', $this->as()->postJson(self::E.'/bg-requests', ['subject' => 'Lab dips', 'buyer_id' => $this->zara])->assertCreated()->json('data.raised_on'));
        // Unrouted cannot be worked on — route it first (or in the same save).
        $this->as()->patchJson(self::E."/bg-requests/{$r['id']}", ['state' => 'IN_PROGRESS'])->assertUnprocessable()->assertJsonPath('errors.state.0', __('buyergateway.v.route_first'));
        $this->as()->postJson(self::E.'/bg-requests', ['subject' => 'X', 'buyer_id' => $this->hm, 'state' => 'ANSWERED'])->assertUnprocessable()->assertJsonPath('errors.state.0', __('buyergateway.v.route_first'));
        $this->as()->patchJson(self::E."/bg-requests/{$r['id']}", ['state' => 'IN_PROGRESS', 'routed_to' => 'PLANNING'])->assertOk()->assertJsonPath('data.state', 'IN_PROGRESS');
        $this->as()->patchJson(self::E."/bg-requests/{$r['id']}", ['routed_to' => 'BAD'])->assertUnprocessable()->assertJsonValidationErrors(['routed_to']);
        // Closing stamps the day; age stops there.
        $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00', 'Asia/Dhaka'));
        $c = $this->as()->patchJson(self::E."/bg-requests/{$r['id']}", ['state' => 'CLOSED'])->assertOk()->json('data');
        $this->assertEquals(['2026-11-13', 12], [$c['closed_on'], $c['age']]);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00:00', 'Asia/Dhaka'));
        $this->assertSame(12, collect($this->as()->getJson(self::E.'/bg-requests')->json('data'))->firstWhere('id', $r['id'])['age']);
        $this->assertNull($this->as()->patchJson(self::E."/bg-requests/{$r['id']}", ['state' => 'ANSWERED'])->assertOk()->json('data.closed_on'));
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', 'Asia/Dhaka'));

        // Complaints.
        $this->as()->postJson(self::E.'/bg-complaints', [])->assertUnprocessable()->assertJsonPath('errors.buyer_id.0', __('buyergateway.v.buyer'));
        $a = $this->as()->postJson(self::E.'/bg-complaints', ['buyer_id' => $this->hm, 'shipment' => ' shp-26-118 ', 'cause' => 'SHADE', 'claim_value' => 18400])->assertCreated()->json('data');
        $this->assertEquals(['CMP-2026-001', 'SHP-26-118', 'OPEN', '2026-11-11', 18400], [$a['reference'], $a['shipment'], $a['state'], $a['raised_on'], $a['claim_value']]);
        $this->as()->postJson(self::E.'/bg-complaints', ['buyer_id' => $this->zara, 'reference' => 'cmp-2026-040'])->assertCreated()->assertJsonPath('data.reference', 'CMP-2026-040');
        $this->as()->postJson(self::E.'/bg-complaints', ['buyer_id' => $this->zara])->assertCreated()->assertJsonPath('data.reference', 'CMP-2026-041');
        $this->as()->postJson(self::E.'/bg-complaints', ['buyer_id' => $this->zara, 'reference' => 'CMP-2026-001'])->assertUnprocessable()->assertJsonPath('errors.reference.0', __('buyergateway.v.complaint_taken'));
        $this->as()->postJson(self::E.'/bg-complaints', ['buyer_id' => $this->zara, 'cause' => 'WEATHER'])->assertUnprocessable()->assertJsonValidationErrors(['cause']);
        // Settled or rejected needs the resolution, and keeps it.
        $this->as()->patchJson(self::E."/bg-complaints/{$a['id']}", ['state' => 'SETTLED'])->assertUnprocessable()->assertJsonPath('errors.state.0', __('buyergateway.v.resolution'));
        $this->as()->patchJson(self::E."/bg-complaints/{$a['id']}", ['state' => 'SETTLED', 'resolution' => '8% discount on next order'])->assertOk()->assertJsonPath('data.state', 'SETTLED');
        $this->as()->patchJson(self::E."/bg-complaints/{$a['id']}", ['resolution' => ''])->assertUnprocessable()->assertJsonPath('errors.resolution.0', __('buyergateway.v.resolution'));
        $this->as()->patchJson(self::E."/bg-complaints/{$a['id']}", ['state' => 'OPEN', 'resolution' => ''])->assertOk()->assertJsonPath('data.resolution', null);
        $this->as()->patchJson(self::E."/bg-complaints/{$a['id']}", ['reference' => ''])->assertOk()->assertJsonPath('data.reference', 'CMP-2026-001');
    }

    public function test_announcements_and_surveys(): void
    {
        $this->as()->postJson(self::E.'/bg-announcements', [])->assertUnprocessable()->assertJsonPath('errors.title.0', __('buyergateway.v.notice_title'));
        $live = $this->as()->postJson(self::E.'/bg-announcements', ['title' => ' Factory closed for Eid ', 'kind' => 'HOLIDAY', 'expires_on' => '2026-11-30'])->assertCreated()->json('data');
        $this->assertEquals(['Factory closed for Eid', 'ALL', '2026-11-11', 'LIVE', false, 0], [$live['title'], $live['audience'], $live['published_on'], $live['state'], $live['draft'], $live['reads']]);
        $states = [];
        foreach ([['published_on' => '2026-11-20'], ['published_on' => '2026-10-01', 'expires_on' => '2026-11-01'], ['state' => 'DRAFT', 'published_on' => '2026-10-01', 'expires_on' => '2026-11-01'], ['published_on' => '2026-11-11', 'expires_on' => '2026-11-11']] as $i => $window) {
            $states[] = $this->as()->postJson(self::E.'/bg-announcements', ['title' => "N{$i}"] + $window)->assertCreated()->json('data.state');
        }
        $this->assertSame(['SCHEDULED', 'EXPIRED', 'DRAFT', 'LIVE'], $states);
        // Draft is the only state kept; publishing a draft puts it back on the window.
        $this->as()->patchJson(self::E."/bg-announcements/{$live['id']}", ['state' => 'DRAFT'])->assertOk()->assertJsonPath('data.state', 'DRAFT')->assertJsonPath('data.draft', true);
        $this->as()->patchJson(self::E."/bg-announcements/{$live['id']}", ['state' => 'EXPIRED'])->assertOk()->assertJsonPath('data.state', 'LIVE');
        // One buyer names the buyer; other audiences drop it.
        $this->as()->postJson(self::E.'/bg-announcements', ['title' => 'Your LC', 'audience' => 'ONE'])->assertUnprocessable()->assertJsonPath('errors.audience.0', __('buyergateway.v.one_buyer'));
        $one = $this->as()->postJson(self::E.'/bg-announcements', ['title' => 'Your LC', 'audience' => 'ONE', 'buyer_id' => $this->zara])->assertCreated()->json('data');
        $this->assertSame($this->zara, $one['buyer_id']);
        $this->as()->patchJson(self::E."/bg-announcements/{$one['id']}", ['buyer_id' => null])->assertUnprocessable()->assertJsonPath('errors.buyer_id.0', __('buyergateway.v.one_buyer'));
        $this->assertNull($this->as()->patchJson(self::E."/bg-announcements/{$one['id']}", ['audience' => 'STRATEGIC'])->assertOk()->json('data.buyer_id'));
        $this->as()->patchJson(self::E."/bg-announcements/{$live['id']}", ['expires_on' => '2026-11-01'])->assertUnprocessable()->assertJsonPath('errors.expires_on.0', __('buyergateway.v.expires_before_published'));
        $this->as()->postJson(self::E.'/bg-announcements', ['title' => 'X', 'audience' => 'PRESS'])->assertUnprocessable()->assertJsonValidationErrors(['audience']);

        // Surveys.
        $this->as()->postJson(self::E.'/bg-surveys', ['buyer_id' => $this->hm])->assertUnprocessable()->assertJsonPath('errors.title.0', __('buyergateway.v.survey'));
        $s = $this->as()->postJson(self::E.'/bg-surveys', ['title' => 'Winter 26 season review', 'buyer_id' => $this->hm, 'season' => 'Winter 26'])->assertCreated()->json('data');
        $this->assertEquals(['DRAFT', 0, 0, null], [$s['state'], $s['recipients'], $s['responses'], $s['score']]);
        $this->as()->postJson(self::E.'/bg-surveys', ['title' => 'X', 'buyer_id' => $this->hm, 'season' => 'Spring 19'])->assertUnprocessable()->assertJsonValidationErrors(['season']);
        $this->as()->patchJson(self::E."/bg-surveys/{$s['id']}", ['state' => 'OPEN'])->assertUnprocessable()->assertJsonPath('errors.state.0', __('buyergateway.v.survey_unsent'));
        $this->as()->patchJson(self::E."/bg-surveys/{$s['id']}", ['state' => 'OPEN', 'sent_on' => '2026-11-02', 'recipients' => 24])->assertOk()->assertJsonPath('data.state', 'OPEN');
        $this->as()->patchJson(self::E."/bg-surveys/{$s['id']}", ['score' => 8.2])->assertUnprocessable()->assertJsonPath('errors.score.0', __('buyergateway.v.score_without_responses'));
        $this->as()->patchJson(self::E."/bg-surveys/{$s['id']}", ['responses' => 30])->assertUnprocessable()->assertJsonPath('errors.responses.0', __('buyergateway.v.responses_over_recipients'));
        $this->as()->patchJson(self::E."/bg-surveys/{$s['id']}", ['responses' => 18, 'score' => 8.2])->assertOk()->assertJsonPath('data.responses', 18);
        // Nor can the recipients drop below, or the responses vanish under a score.
        $this->as()->patchJson(self::E."/bg-surveys/{$s['id']}", ['recipients' => 10])->assertUnprocessable()->assertJsonPath('errors.recipients.0', __('buyergateway.v.responses_over_recipients'));
        $this->as()->patchJson(self::E."/bg-surveys/{$s['id']}", ['responses' => 0])->assertUnprocessable()->assertJsonPath('errors.responses.0', __('buyergateway.v.score_without_responses'));
        $this->as()->patchJson(self::E."/bg-surveys/{$s['id']}", ['score' => 11])->assertUnprocessable()->assertJsonValidationErrors(['score']);
        $row = DB::table('bg_surveys')->where('id', $s['id'])->first();
        $this->assertEquals([24, 18, 8.2], [$row->recipients, $row->responses, (float) $row->score]);
    }

    public function test_tickets(): void
    {
        $this->as()->postJson(self::E.'/bg-tickets', [])->assertUnprocessable()->assertJsonPath('errors.subject.0', __('buyergateway.v.subject'));
        $t = $this->as()->postJson(self::E.'/bg-tickets', ['subject' => ' Cannot download packing list ', 'buyer_id' => $this->hm, 'priority' => 'URGENT'])->assertCreated()->json('data');
        $this->assertEquals(['TKT-2026-001', 'Cannot download packing list', 'NEW', '2026-11-11', 10, null, null], [$t['reference'], $t['subject'], $t['state'], $t['raised_on'], $t['hours'], $t['owner'], $t['resolved_at']]);
        $this->as()->postJson(self::E.'/bg-tickets', ['subject' => 'X', 'reference' => 'tkt-2026-001'])->assertUnprocessable()->assertJsonPath('errors.reference.0', __('buyergateway.v.ticket_taken'));
        $this->as()->postJson(self::E.'/bg-tickets', ['subject' => 'Login loop', 'raised_on' => '2026-11-10'])->assertCreated()->assertJsonPath('data.reference', 'TKT-2026-002')->assertJsonPath('data.hours', 34);
        $this->as()->postJson(self::E.'/bg-tickets', ['subject' => 'X', 'priority' => 'CRITICAL'])->assertUnprocessable()->assertJsonValidationErrors(['priority']);
        // Owner from the company's staff; a stranger's user is refused.
        $other = TenantFixture::company('Other Ltd', 'OTH');
        $outsider = TenantFixture::user($other, 'COMPANY_OWNER', 'owner@other.test');
        TenantFixture::actingAsTenant($this->delta);
        $this->as()->patchJson(self::E."/bg-tickets/{$t['id']}", ['owner_id' => $outsider->id])->assertUnprocessable()->assertJsonValidationErrors(['owner_id']);
        $owned = $this->as()->patchJson(self::E."/bg-tickets/{$t['id']}", ['owner_id' => $this->owner->id, 'state' => 'IN_PROGRESS'])->assertOk()->json('data');
        $this->assertSame($this->owner->name, $owned['owner']);
        // Resolving stamps the time; hours stop there; reopening clears it.
        $this->travelTo(CarbonImmutable::parse('2026-11-12 16:30:00', 'Asia/Dhaka'));
        $done = $this->as()->patchJson(self::E."/bg-tickets/{$t['id']}", ['state' => 'RESOLVED'])->assertOk()->json('data');
        $this->assertSame(40, $done['hours']);
        $this->assertNotNull($done['resolved_at']);
        $this->travelTo(CarbonImmutable::parse('2026-11-14 09:00:00', 'Asia/Dhaka'));
        $this->assertSame(40, $this->as()->patchJson(self::E."/bg-tickets/{$t['id']}", ['state' => 'CLOSED'])->assertOk()->json('data.hours'));
        $this->assertNull($this->as()->patchJson(self::E."/bg-tickets/{$t['id']}", ['state' => 'WAITING'])->assertOk()->json('data.resolved_at'));
    }

    public function test_who_may(): void
    {
        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'auditor@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');
        $this->as($auditor)->getJson(self::E.'/bg-complaints')->assertOk();
        $this->as($auditor)->getJson('/api/v1/buyer-gateway/options')->assertOk();
        $this->as($auditor)->postJson(self::E.'/bg-tickets', ['subject' => 'X'])->assertForbidden();
        $this->as($manager)->postJson(self::E.'/bg-tickets', ['subject' => 'X'])->assertCreated();
        $this->as($keeper)->getJson(self::E.'/bg-requests')->assertForbidden();
        $this->as($keeper)->getJson(self::E.'/bg-announcements')->assertForbidden();

        $other = TenantFixture::company('Other Ltd', 'OTH');
        TenantFixture::actingAsTenant($other);
        $stranger = TenantFixture::user($other, 'COMPANY_OWNER', 'owner@other.test');
        $this->assertSame([], $this->as($stranger, $other)->getJson(self::E.'/bg-tickets')->assertOk()->json('data'));
        $this->as($stranger, $other)->postJson(self::E.'/bg-requests', ['subject' => 'X', 'buyer_id' => $this->hm])->assertUnprocessable()->assertJsonValidationErrors(['buyer_id']);
    }

    private function as(?User $user = null, ?Company $company = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, ($company ?? $this->delta)->id, 'Test token');

        return $this->withToken($plain);
    }
}
