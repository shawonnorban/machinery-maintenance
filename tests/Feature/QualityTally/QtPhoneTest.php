<?php

declare(strict_types=1);

namespace Tests\Feature\QualityTally;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Support\LicensedModules;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The QC's phone (AnnotechERP 37, phase 1): Company Code + Employee ID + PIN
 * (000000 until changed) on an approved phone; a tally-only session; wrong PINs
 * lock; a blocked phone stops; taps go as the phone, each for its own QC.
 * Phase 2: the tally read as the phone for its QC's session, undo, late taps
 * kept for review, refused taps kept, a QC signed in by email.
 */
class QtPhoneTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private User $qc;

    private User $qc2;

    private string $line;

    private string $line2;

    private string $job;

    private string $inspector;

    private string $inspector2;

    /** @var array{install_id: string, secret: string, model: string} */
    private array $phone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        LicensedModules::forget();
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        // A QC whose own roles allow far more than tapping: a PIN must not.
        $this->qc = TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'qc@delta.test');
        $this->qc2 = TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'qc2@delta.test');
        DB::table('company_modules')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'module_code' => 'QUALITY_TALLY', 'created_at' => now(), 'updated_at' => now()]);
        LicensedModules::forget();

        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Main Building', 'code' => 'BLD-1']);
        $floor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Floor 1', 'code' => 'FLR-001'])->id;
        DB::table('departments')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Quality', 'code' => 'QA', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['line' => 'LINE01', 'line2' => 'LINE02'] as $prop => $code) {
            $this->{$prop} = (string) Str::ulid();
            DB::table('planning_lines')->insert(['id' => $this->{$prop}, 'company_id' => $this->delta->id, 'floor_id' => $floor, 'code' => $code, 'name' => $code, 'display_order' => 1, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->job = $this->as($this->owner)->postJson('/api/v1/quality-tally/jobs', ['job_no' => 'J-100', 'buyer_name' => 'H&M', 'style' => 'Tee', 'po_no' => '', 'color' => '', 'order_qty' => 100])->assertCreated()->json('data.id');
        $this->inspector = $this->inspector($this->qc, 'EMP-001', [$this->line]);
        $this->inspector2 = $this->inspector($this->qc2, 'EMP-002', [$this->line, $this->line2]);
        $this->phone = $this->newPhone();
    }

    public function test_a_new_phone_waits_for_approval_then_the_default_pin_signs_in(): void
    {
        $res = $this->signIn('EMP-001', '000000')->assertForbidden()->assertJsonPath('meta.reason', 'PHONE_PENDING');
        $row = $this->as($this->owner)->getJson('/api/v1/quality-tally/phones')->assertOk()->json('data.0');
        $this->assertSame(['PENDING', $res->json('meta.code'), 'Qc'], [$row['status'], $row['code'], $row['first_user']]);

        $this->approve('Phone 01');
        // Any case of the code and ID.
        $ok = $this->signIn('emp-001', '000000', ['company_code' => 'dal'])->assertCreated();
        $this->assertSame(['EMP-001', true, 'Phone 01', 'LINE01'], [$ok->json('data.qc.employee_id'), $ok->json('data.pin_default'), $ok->json('data.phone.name'), $ok->json('data.lines.0.label')]);
        $this->bearer($ok)->getJson('/api/v1/quality-tally/mobile/session')->assertOk()->assertJsonPath('data.qc.name', 'Qc');
        $this->bearer($ok)->getJson('/api/v1/quality-tally/tally/context')->assertOk();
        $this->assertSame('Qc', $this->as($this->owner)->getJson('/api/v1/quality-tally/phones')->json('data.0.signed_in.name'));
        $this->assertDatabaseHas('login_attempts', ['email' => 'DAL / EMP-001', 'successful' => true]);
    }

    public function test_a_pin_session_taps_and_nothing_else(): void
    {
        $this->approve();
        $ok = $this->signIn('EMP-001', '000000')->assertCreated();

        // The QC is a factory admin, who may do all of these with a password.
        foreach (['/quality-tally/jobs', '/quality-tally/dashboard', '/quality-tally/inspectors', '/quality-tally/phones', '/assets', '/auth/companies', '/auth/tokens', '/access/navigation'] as $path) {
            $this->bearer($ok)->getJson('/api/v1'.$path)->assertForbidden();
        }
        // Not a way round to an ordinary token.
        $this->bearer($ok)->postJson('/api/v1/auth/switch-company', ['company_id' => $this->delta->id])->assertForbidden();
        $this->assertEqualsCanonicalizing(['qtally.tally.create'], array_values(array_diff($this->bearer($ok)->getJson('/api/v1/auth/permissions')->assertOk()->json('data.permissions'), ['qtally.cutting.tally', 'qtally.sewing.tally', 'qtally.quality.tally'])));
        // An email login is not a PIN session.
        $this->as($this->owner)->getJson('/api/v1/quality-tally/mobile/session')->assertForbidden();
        // Fourteen hours and it is over.
        $this->travel(15)->hours();
        $this->bearer($ok)->getJson('/api/v1/quality-tally/mobile/session')->assertUnauthorized();
    }

    public function test_wrong_pins_lock_that_id_on_that_phone_only(): void
    {
        $this->approve();
        foreach ([4, 3, 2, 1] as $left) {
            $this->signIn('EMP-001', '111222')->assertForbidden()->assertJsonPath('meta.left', $left);
        }
        $this->signIn('EMP-001', '111222')->assertStatus(429)->assertJsonPath('meta.reason', 'PIN_LOCKED');
        $this->signIn('EMP-001', '000000')->assertStatus(429);
        // Another QC on the phone, and the same QC on another approved one, are not.
        $this->signIn('EMP-002', '000000')->assertCreated();
        $other = $this->newPhone();
        $this->approve('Phone 02', [], $other);
        $this->signIn('EMP-001', '000000', [], $other)->assertCreated();

        $this->travel(16)->minutes();
        $this->signIn('EMP-001', '000000')->assertCreated();

        // A stranger's wrong tries do not lock the QC out of an approved phone, and every wrong answer reads the same.
        $stranger = $this->newPhone();
        $said = [];
        foreach ([['EMP-001', 'DAL'], ['NOBODY', 'DAL'], ['EMP-001', 'XXX']] as [$id, $company]) {
            $said[] = $this->signIn($id, '424242', ['company_code' => $company], $stranger)->assertForbidden()->json('message');
        }
        $this->assertCount(1, array_unique($said));
        foreach (range(1, 6) as $n) {
            $this->signIn('EMP-001', '424242', [], $this->newPhone());
        }
        $this->signIn('EMP-001', '000000', [], $this->newPhone())->assertStatus(429);
        $this->signIn('EMP-001', '000000')->assertCreated();
        $this->assertDatabaseHas('login_attempts', ['email' => 'DAL / EMP-001', 'failure_reason' => 'BAD_PIN']);
    }

    public function test_the_qc_sets_their_own_pin_and_a_manager_resets_it(): void
    {
        $this->approve();
        $other = $this->newPhone();
        $this->approve('Phone 02', [], $other);
        $elsewhere = $this->signIn('EMP-001', '000000', [], $other)->assertCreated();
        $ok = $this->signIn('EMP-001', '000000')->assertCreated();

        $this->changePin($ok, '999111', '573821')->assertUnprocessable()->assertJsonValidationErrors('current_pin');
        foreach (['000000', '123456', '987654', '777777', '121212', '123123'] as $easy) {
            $this->changePin($ok, '000000', $easy)->assertUnprocessable()->assertJsonValidationErrors('pin');
        }
        $this->changePin($ok, '000000', '573821')->assertOk();
        $this->bearer($ok)->getJson('/api/v1/quality-tally/mobile/session')->assertOk()->assertJsonPath('data.pin_default', false);
        $this->bearer($elsewhere)->getJson('/api/v1/quality-tally/mobile/session')->assertUnauthorized();
        $this->signIn('EMP-001', '000000')->assertForbidden();
        $ok = $this->signIn('EMP-001', '573821')->assertCreated();
        $row = fn () => collect($this->as($this->owner)->getJson('/api/v1/quality-tally/inspectors')->json('data'))->firstWhere('id', $this->inspector);
        $this->assertSame('OWN', $row()['pin']);
        $this->assertStringNotContainsString('pin_hash', (string) json_encode($row()));

        $this->as($this->owner)->postJson('/api/v1/quality-tally/inspectors/'.$this->inspector.'/reset-pin')->assertOk();
        $this->assertSame('DEFAULT', $row()['pin']);
        $this->bearer($ok)->getJson('/api/v1/quality-tally/mobile/session')->assertUnauthorized();
        $this->signIn('EMP-001', '000000')->assertCreated();
    }

    public function test_only_an_active_qc_with_an_active_employee_signs_in(): void
    {
        $this->approve();
        $ok = $this->signIn('EMP-001', '000000')->assertCreated();

        DB::table('hr_employees')->where('employee_code', 'EMP-001')->update(['status' => 'SEPARATED']);
        $this->bearer($ok)->getJson('/api/v1/quality-tally/tally/context')->assertUnauthorized();
        $this->signIn('EMP-001', '000000')->assertForbidden()->assertJsonPath('meta.reason', 'INACTIVE');
        DB::table('hr_employees')->where('employee_code', 'EMP-001')->update(['status' => 'ACTIVE']);

        $ok = $this->signIn('EMP-001', '000000')->assertCreated();
        $this->as($this->owner)->patchJson('/api/v1/quality-tally/inspectors/'.$this->inspector, ['user_id' => $this->qc->id, 'hr_employee_id' => DB::table('hr_employees')->where('employee_code', 'EMP-001')->value('id'), 'active' => false, 'lines' => []])->assertOk();
        $this->bearer($ok)->getJson('/api/v1/quality-tally/tally/context')->assertUnauthorized();
        $this->signIn('EMP-001', '000000')->assertForbidden();

        DB::table('users')->where('id', $this->qc2->id)->update(['status' => 'SUSPENDED']);
        $this->signIn('EMP-002', '000000')->assertForbidden()->assertJsonPath('meta.reason', 'INACTIVE');

        DB::table('company_modules')->delete();
        LicensedModules::forget();
        DB::table('users')->where('id', $this->qc2->id)->update(['status' => 'ACTIVE']);
        $this->signIn('EMP-002', '000000')->assertForbidden()->assertJsonPath('meta.reason', 'MODULE_OFF');
    }

    public function test_a_blocked_phone_signs_everyone_out_and_lets_nobody_in(): void
    {
        $id = $this->approve();
        $ok = $this->signIn('EMP-001', '000000')->assertCreated();
        $email = $this->emailLogin()->assertCreated()->json('data.access_token');
        $this->withToken($email)->getJson('/api/v1/auth/me')->assertOk();

        $this->as($this->owner)->postJson("/api/v1/quality-tally/phones/{$id}/block")->assertOk();
        $this->bearer($ok)->getJson('/api/v1/quality-tally/mobile/session')->assertUnauthorized();
        $this->flushHeaders();
        $this->withToken($email)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->signIn('EMP-001', '000000')->assertForbidden()->assertJsonPath('meta.reason', 'PHONE_BLOCKED');
        $this->emailLogin()->assertForbidden()->assertJsonPath('meta.reason', 'PHONE_BLOCKED');
        $this->asPhone()->getJson('/api/v1/quality-tally/phone/hello')->assertUnauthorized();

        // Unblocked, it is approved anew first.
        $this->as($this->owner)->postJson("/api/v1/quality-tally/phones/{$id}/unblock")->assertOk()->assertJsonPath('data.status', 'PENDING');
        $this->signIn('EMP-001', '000000')->assertJsonPath('meta.reason', 'PHONE_PENDING');
        // Another app cannot pose as it.
        $this->signIn('EMP-001', '000000', [], ['secret' => Str::random(48)] + $this->phone)->assertJsonPath('meta.reason', 'PHONE_UNKNOWN');
    }

    public function test_phones_are_managed_by_those_who_may_and_only_in_their_company(): void
    {
        $this->signIn('EMP-001', '000000');
        $id = DB::table('qt_phones')->value('id');
        $chief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test');
        $this->as($chief)->getJson('/api/v1/quality-tally/phones')->assertForbidden();
        $this->as($chief)->postJson("/api/v1/quality-tally/phones/{$id}/block")->assertForbidden();
        $this->as($chief)->postJson('/api/v1/quality-tally/inspectors/'.$this->inspector.'/reset-pin')->assertForbidden();

        $omega = TenantFixture::company('Omega', 'OTL');
        $theirs = TenantFixture::user($omega, 'COMPANY_OWNER', 'owner@omega.test');
        DB::table('company_modules')->insert(['id' => (string) Str::ulid(), 'company_id' => $omega->id, 'module_code' => 'QUALITY_TALLY', 'created_at' => now(), 'updated_at' => now()]);
        LicensedModules::forget();
        $this->as($theirs, $omega)->getJson('/api/v1/quality-tally/phones')->assertOk()->assertJsonPath('data', []);
        $this->as($theirs, $omega)->patchJson("/api/v1/quality-tally/phones/{$id}", ['name' => 'Mine', 'approve' => true])->assertNotFound();
        $this->as($theirs, $omega)->postJson('/api/v1/quality-tally/inspectors/'.$this->inspector.'/reset-pin')->assertNotFound();
        TenantFixture::actingAsTenant($this->delta);

        // A waiting phone nobody used can be removed; a used one cannot.
        $this->as($this->owner)->deleteJson("/api/v1/quality-tally/phones/{$id}")->assertOk();
        $id = $this->approve();
        $this->signIn('EMP-001', '000000')->assertCreated();
        $this->as($this->owner)->deleteJson("/api/v1/quality-tally/phones/{$id}")->assertUnprocessable();
        // Names do not repeat.
        $other = $this->newPhone();
        $this->signIn('EMP-001', '000000', [], $other);
        $this->as($this->owner)->patchJson('/api/v1/quality-tally/phones/'.DB::table('qt_phones')->where('install_id', $other['install_id'])->value('id'), ['name' => 'Phone 01', 'approve' => true])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_a_phone_tied_to_a_line_takes_that_lines_qcs(): void
    {
        $id = $this->approve('Phone 01', ['section' => 'SEWING', 'line_id' => $this->line2]);
        $this->signIn('EMP-001', '000000')->assertForbidden()->assertJsonPath('meta.reason', 'OTHER_LINE');
        $ok = $this->signIn('EMP-002', '000000')->assertCreated();
        $this->assertSame(['LINE02'], array_column($ok->json('data.lines'), 'label'));
        $this->asPhone()->postJson('/api/v1/quality-tally/phone/taps', ['entries' => [$this->tap($ok->json('data.session.uuid'))]])->assertOk()->assertJsonPath('data.recorded', 0);

        $this->as($this->owner)->patchJson("/api/v1/quality-tally/phones/{$id}", ['name' => 'Phone 01', 'section' => 'SEWING', 'line_id' => $this->line])->assertOk();
        $this->bearer($ok)->getJson('/api/v1/quality-tally/mobile/session')->assertUnauthorized();
        $this->signIn('EMP-001', '000000')->assertCreated();
    }

    public function test_taps_go_as_the_phone_each_for_its_own_qc(): void
    {
        $this->approve();
        $a = $this->signIn('EMP-001', '000000')->assertCreated()->json('data.session.uuid');
        $madeByA = [$this->tap($a), $this->tap($a)];
        $this->travel(10)->minutes();
        $b = $this->signIn('EMP-002', '000000')->assertCreated();
        $bUuid = $b->json('data.session.uuid');
        $madeByB = $this->tap($bUuid);

        // A's two waited while B signed in: all three arrive together, each for its QC.
        $batch = ['entries' => [...$madeByA, $madeByB]];
        $this->asPhone(['X-Waiting-Taps' => '3'])->postJson('/api/v1/quality-tally/phone/taps', $batch)->assertOk()->assertJsonPath('data.recorded', 3)->assertJsonPath('data.rejected', []);
        $this->assertSame(2, DB::table('qt_entries')->where('user_id', $this->qc->id)->count());
        $this->assertSame(1, DB::table('qt_entries')->where('user_id', $this->qc2->id)->whereNotNull('phone_id')->whereNotNull('session_id')->count());
        $this->assertSame(3, DB::table('qt_phones')->value('waiting_taps'));
        // Sent again, counted once.
        $this->asPhone()->postJson('/api/v1/quality-tally/phone/taps', $batch)->assertOk()->assertJsonPath('data.duplicate', 3);

        // A tap A could not have made: after B took the phone; before A came; in no session.
        $this->travel(5)->minutes();
        $late = $this->tap($a);
        $early = ['occurred_at' => now()->subHours(3)->toIso8601String()] + $this->tap($a);
        $res = $this->asPhone()->postJson('/api/v1/quality-tally/phone/taps', ['entries' => [$late, $early, $this->tap((string) Str::uuid()), 'x']])->assertOk()->assertJsonPath('data.recorded', 0);
        $this->assertCount(4, $res->json('data.rejected'));
        $this->assertSame($late['client_uuid'], $res->json('data.rejected.0.client_uuid'));

        // After B's session is over, B's earlier tap still counts.
        $last = $this->tap($bUuid);
        $this->bearer($b)->postJson('/api/v1/quality-tally/mobile/sign-out')->assertNoContent();
        $this->bearer($b)->getJson('/api/v1/quality-tally/mobile/session')->assertUnauthorized();
        $this->asPhone()->postJson('/api/v1/quality-tally/phone/taps', ['entries' => [$last]])->assertOk()->assertJsonPath('data.recorded', 1);

        // Another phone's session is not this one's.
        $other = $this->newPhone();
        $this->approve('Phone 02', [], $other);
        $this->asPhone([], $other)->postJson('/api/v1/quality-tally/phone/taps', ['entries' => [$this->tap($bUuid)]])->assertOk()->assertJsonPath('data.recorded', 0);
        $this->flushHeaders();
        $this->postJson('/api/v1/quality-tally/phone/taps', ['entries' => [$last]])->assertUnauthorized();
        $this->asPhone(['X-Phone-Secret' => Str::random(48)])->postJson('/api/v1/quality-tally/phone/taps', ['entries' => [$last]])->assertUnauthorized();
    }

    public function test_an_offline_sign_in_counts_only_for_a_qc_seen_on_the_phone_lately(): void
    {
        $this->approve();
        $this->signIn('EMP-001', '000000')->assertCreated();
        $this->travel(2)->days();
        $row = fn (string $id, int $minutesAgo = 30, ?int $endedAgo = null) => ['uuid' => (string) Str::uuid(), 'employee_id' => $id, 'started_at' => now()->subMinutes($minutesAgo)->toIso8601String(), 'ended_at' => $endedAgo === null ? null : now()->subMinutes($endedAgo)->toIso8601String()];
        $known = $row('EMP-001', 30, 5);
        $stranger = $row('EMP-002');
        $res = $this->asPhone()->postJson('/api/v1/quality-tally/phone/sessions', ['sessions' => [$known, $stranger]])->assertOk();
        $this->assertSame([[$known['uuid']], [$stranger['uuid']]], [$res->json('data.saved'), array_column($res->json('data.rejected'), 'uuid')]);

        $mid = ['occurred_at' => now()->subMinutes(20)->toIso8601String()] + $this->tap($known['uuid']);
        $after = $this->tap($known['uuid']);
        $this->asPhone()->postJson('/api/v1/quality-tally/phone/taps', ['entries' => [$mid, $after]])->assertOk()->assertJsonPath('data.recorded', 1);
        $this->assertDatabaseHas('qt_sessions', ['uuid' => $known['uuid'], 'offline' => true, 'user_id' => $this->qc->id]);

        // An offline sign-in does not renew itself: eight days after the last real one it is refused.
        $this->travel(6)->days();
        $this->asPhone()->postJson('/api/v1/quality-tally/phone/sessions', ['sessions' => [$row('EMP-001')]])->assertOk()->assertJsonPath('data.saved', []);
    }

    public function test_a_floor_signing_in_together_is_not_held_up_by_its_shared_address(): void
    {
        $this->approve();
        foreach (range(1, 30) as $n) {
            $id = 'EMP-1'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $this->assertNotSame(429, $this->signIn($id, '000000')->status(), $id);
        }
        $this->signIn('EMP-001', '000000')->assertCreated();
    }

    public function test_an_email_login_names_the_company_by_its_code(): void
    {
        $this->emailLogin()->assertCreated()->assertJsonPath('data.company_id', $this->delta->id);
        TenantFixture::company('Omega', 'OTL');
        $this->emailLogin(['company_code' => 'OTL'])->assertUnprocessable()->assertJsonValidationErrors('company_code');
        // The phone is on the list, for email only: a QC still needs it approved.
        $this->assertSame('PERSONAL', $this->as($this->owner)->getJson('/api/v1/quality-tally/phones')->json('data.0.status'));
        $this->signIn('EMP-001', '000000')->assertJsonPath('meta.reason', 'PHONE_PENDING');
        // The web's login is as it was.
        $this->flushHeaders();
        $this->postJson('/api/v1/auth/login', ['email' => 'qc@delta.test', 'password' => 'correct-horse-battery'])->assertCreated();
    }

    /* ── phase 2 ────────────────────────────────────────────────────── */

    public function test_the_phone_reads_the_tally_for_its_signed_in_qc_only(): void
    {
        $this->approve();
        $this->target($this->line);
        $uuid = $this->signIn('EMP-001', '000000')->assertCreated()->json('data.session.uuid');

        $ctx = $this->asPhone()->getJson('/api/v1/quality-tally/phone/tally?session='.$uuid)->assertOk()->json('data');
        $this->assertSame([['SEWING', $this->line]], array_map(fn ($l) => [$l['section'], $l['line_id']], $ctx['lines']));
        $this->assertSame($this->job, $ctx['targets']['SEWING:'.$this->line][0]['job_id']);
        $this->assertSame([], $ctx['bundles']);
        $sum = $this->asPhone()->getJson('/api/v1/quality-tally/phone/summary?'.http_build_query(['session' => $uuid, 'section' => 'SEWING', 'line_id' => $this->line, 'job_id' => $this->job]))->assertOk();
        $this->assertSame([100, 0], [$sum->json('data.target'), $sum->json('data.mine')]);
        // A section the QC does not tap in is not theirs to read.
        $this->asPhone()->getJson('/api/v1/quality-tally/phone/summary?'.http_build_query(['session' => $uuid, 'section' => 'BOGUS', 'line_id' => $this->line, 'job_id' => $this->job]))->assertUnprocessable();

        // Somebody else's session, another phone's, or none: signed out.
        foreach ([(string) Str::uuid(), ''] as $other) {
            $this->asPhone()->getJson('/api/v1/quality-tally/phone/tally?session='.$other)->assertUnauthorized()->assertJsonPath('meta.reason', 'SESSION_ENDED');
        }
        $second = $this->newPhone();
        $this->approve('Phone 02', [], $second);
        $this->asPhone([], $second)->getJson('/api/v1/quality-tally/phone/tally?session='.$uuid)->assertUnauthorized();
        // Fifteen hours on, it is over.
        $this->travel(15)->hours();
        $this->asPhone()->getJson('/api/v1/quality-tally/phone/tally?session='.$uuid)->assertUnauthorized()->assertJsonPath('meta.reason', 'SESSION_ENDED');
    }

    public function test_the_phone_undoes_its_qcs_last_tap(): void
    {
        $this->approve();
        $uuid = $this->signIn('EMP-001', '000000')->assertCreated()->json('data.session.uuid');
        $first = $this->tap($uuid);
        $second = ['action' => 'REJECT'] + $this->tap($uuid);
        $this->asPhone()->postJson('/api/v1/quality-tally/phone/taps', ['entries' => [$first, $second]])->assertOk()->assertJsonPath('data.recorded', 2);

        $undo = fn () => $this->asPhone()->postJson('/api/v1/quality-tally/phone/undo?session='.$uuid, ['section' => 'SEWING', 'line_id' => $this->line, 'job_id' => $this->job]);
        $undo()->assertOk()->assertJsonPath('data.undone.client_uuid', $second['client_uuid'])->assertJsonPath('data.undone.action', 'REJECT');
        $undo()->assertOk()->assertJsonPath('data.undone.client_uuid', $first['client_uuid']);
        $undo()->assertUnprocessable();
        // Not on a line that is not the QC's.
        $this->asPhone()->postJson('/api/v1/quality-tally/phone/undo?session='.$uuid, ['section' => 'SEWING', 'line_id' => $this->line2, 'job_id' => $this->job])->assertUnprocessable();
    }

    public function test_a_tap_made_with_no_network_is_kept_for_review_and_a_refused_one_is_kept_too(): void
    {
        $this->approve();
        $uuid = $this->signIn('EMP-001', '000000')->assertCreated()->json('data.session.uuid');
        $this->travel(20)->minutes();
        $at = fn (int $ago) => now()->subMinutes($ago)->toIso8601String();

        // No target on the line: made ten minutes ago it is kept and marked; made now it is refused.
        $late = ['action' => 'RFT', 'occurred_at' => $at(10)] + $this->tap($uuid);
        $now = ['action' => 'RFT'] + $this->tap($uuid);
        $res = $this->asPhone()->postJson('/api/v1/quality-tally/phone/taps', ['entries' => [$late, $now]])->assertOk()->assertJsonPath('data.recorded', 1);
        $this->assertSame($now['client_uuid'], $res->json('data.rejected.0.client_uuid'));
        $this->assertDatabaseHas('qt_entries', ['client_uuid' => $late['client_uuid'], 'review' => 'NO_TARGET', 'synced_late' => true]);

        // With a target but nothing from cutting yet: kept, marked NONE_READY.
        $this->target($this->line);
        $ready = ['action' => 'DEFECT', 'occurred_at' => $at(5), 'defects' => [DB::table('qt_defect_types')->where('section', 'SEWING')->value('id') ?? $this->defect()]] + $this->tap($uuid);
        $this->asPhone()->postJson('/api/v1/quality-tally/phone/taps', ['entries' => [$ready]])->assertOk()->assertJsonPath('data.recorded', 1);
        $this->assertDatabaseHas('qt_entries', ['client_uuid' => $ready['client_uuid'], 'review' => 'NONE_READY']);

        // The refused one is kept with its reason, once however often it is sent.
        $this->asPhone()->postJson('/api/v1/quality-tally/phone/taps', ['entries' => [$now]])->assertOk()->assertJsonPath('data.recorded', 0);
        $row = DB::table('qt_refused_taps')->where('client_uuid', $now['client_uuid'])->first();
        $this->assertSame([1, 'RFT', $this->qc->id, $this->line], [DB::table('qt_refused_taps')->count(), $row->action, $row->user_id, $row->line_id]);
        $this->assertNotEmpty($row->phone_id);
        $this->assertNotEmpty($row->message);
    }

    public function test_a_qc_signed_in_by_email_gets_a_qc_session_on_an_approved_phone(): void
    {
        $email = $this->emailLogin()->assertCreated();
        $attach = fn (TestResponse $login) => $this->bearer($login)->postJson('/api/v1/quality-tally/mobile/attach', ['session_uuid' => (string) Str::uuid()]);
        $attach($email)->assertForbidden()->assertJsonPath('meta.reason', 'PHONE_PENDING');

        $this->approve();
        $email = $this->emailLogin()->assertCreated();
        $ok = $attach($email)->assertCreated();
        $this->assertSame(['EMP-001', 'LINE01'], [$ok->json('data.qc.employee_id'), $ok->json('data.lines.0.label')]);
        $uuid = $ok->json('data.session.uuid');
        $this->asPhone()->getJson('/api/v1/quality-tally/phone/tally?session='.$uuid)->assertOk();
        // The email token stays whole: not a PIN session.
        $this->bearer($email)->getJson('/api/v1/quality-tally/jobs')->assertOk();

        // Somebody who is not a QC, a token not from a phone, a PIN session: none of them.
        $this->flushHeaders();
        $owner = $this->postJson('/api/v1/auth/login', ['email' => 'owner@delta.test', 'password' => 'correct-horse-battery', 'company_code' => 'DAL', 'phone' => $this->phone])->assertCreated();
        $attach($owner)->assertForbidden()->assertJsonPath('meta.reason', 'NOT_QC');
        $this->flushHeaders();
        $web = $this->postJson('/api/v1/auth/login', ['email' => 'qc@delta.test', 'password' => 'correct-horse-battery'])->assertCreated();
        $attach($web)->assertForbidden()->assertJsonPath('meta.reason', 'NOT_QC');
        $attach($this->signIn('EMP-002', '000000')->assertCreated())->assertForbidden();
        // The PIN sign-in took the phone over: the email QC's session ended with it.
        $this->asPhone()->getJson('/api/v1/quality-tally/phone/tally?session='.$uuid)->assertUnauthorized();
    }

    public function test_a_floor_signing_in_by_email_together_is_not_held_up_by_its_shared_address(): void
    {
        foreach (range(1, 15) as $n) {
            $this->flushHeaders();
            $this->assertNotSame(429, $this->postJson('/api/v1/auth/login', ['email' => "person{$n}@delta.test", 'password' => 'wrong-password'])->status(), (string) $n);
        }
        // One person is still held to ten a minute.
        $statuses = [];
        foreach (range(1, 11) as $n) {
            $this->flushHeaders();
            $statuses[] = $this->postJson('/api/v1/auth/login', ['email' => 'same@delta.test', 'password' => 'wrong-password'])->status();
        }
        $this->assertSame(429, end($statuses));
    }

    public function test_the_manager_reviews_refused_and_kept_taps_and_marks_them_seen(): void
    {
        $this->approve();
        $uuid = $this->signIn('EMP-001', '000000')->assertCreated()->json('data.session.uuid');
        $this->travel(20)->minutes();
        $late = ['action' => 'RFT', 'occurred_at' => now()->subMinutes(10)->toIso8601String()] + $this->tap($uuid);
        $now = ['action' => 'RFT'] + $this->tap($uuid);
        $this->asPhone()->postJson('/api/v1/quality-tally/phone/taps', ['entries' => [$late, $now]])->assertOk()->assertJsonPath('data.recorded', 1);

        $list = fn (string $status = 'open') => $this->as($this->owner)->getJson('/api/v1/quality-tally/review?section=SEWING&status='.$status)->assertOk();
        $open = $list();
        $this->assertSame([1, 1], [$open->json('data.open.refused'), $open->json('data.open.kept')]);
        $rows = collect($open->json('data.rows'));
        $this->assertEqualsCanonicalizing(['REFUSED', 'KEPT'], $rows->pluck('kind')->all());
        $this->assertSame(['LINE01', 'J-100', 'Qc', 'Phone 01'], [$rows->first()['line'], $rows->first()['job_no'], $rows->first()['qc'], $rows->first()['phone']]);
        // Another section's list is not this one's.
        $this->assertSame([], $this->as($this->owner)->getJson('/api/v1/quality-tally/review?section=CUTTING')->assertOk()->json('data.rows'));

        $items = $rows->map(fn ($r) => ['kind' => $r['kind'], 'id' => $r['id']])->all();
        // Marking another section's taps seen marks nothing.
        $this->as($this->owner)->postJson('/api/v1/quality-tally/review/seen', ['section' => 'CUTTING', 'items' => $items])->assertOk()->assertJsonPath('data.seen', 0);
        $this->as($this->owner)->postJson('/api/v1/quality-tally/review/seen', ['section' => 'SEWING', 'items' => $items])->assertOk()->assertJsonPath('data.seen', 2);
        $this->assertSame([], $list()->json('data.rows'));
        $all = $list('all')->json('data.rows');
        $this->assertCount(2, $all);
        $this->assertSame(['Owner', 'Owner'], array_column($all, 'seen_by'));
    }

    public function test_the_dashboard_opens_on_a_day_with_targets_and_no_taps_yet(): void
    {
        // A morning before the first tap: the line's target shows, nothing fails.
        $this->target($this->line);
        $rows = $this->as($this->owner)->getJson('/api/v1/quality-tally/dashboard?section=SEWING&date='.\App\Modules\QualityTally\Support\QtTally::currentDate($this->delta->id, $this->qc->id))->assertOk()->json('data.rows');
        $this->assertSame([[100, 0]], array_map(fn ($r) => [$r['target'], $r['checked']], $rows));
    }

    /* ── helpers ────────────────────────────────────────────────────── */

    private function signIn(string $employee, string $pin, array $extra = [], ?array $phone = null): TestResponse
    {
        $this->flushHeaders();

        return $this->postJson('/api/v1/quality-tally/mobile/sign-in', $extra + ['company_code' => 'DAL', 'employee_id' => $employee, 'pin' => $pin, 'session_uuid' => (string) Str::uuid(), 'phone' => $phone ?? $this->phone]);
    }

    private function emailLogin(array $extra = []): TestResponse
    {
        $this->flushHeaders();

        return $this->postJson('/api/v1/auth/login', $extra + ['email' => 'qc@delta.test', 'password' => 'correct-horse-battery', 'company_code' => 'dal', 'phone' => $this->phone]);
    }

    private function changePin(TestResponse $session, string $current, string $pin): TestResponse
    {
        return $this->bearer($session)->postJson('/api/v1/quality-tally/mobile/pin', ['current_pin' => $current, 'pin' => $pin, 'pin_confirmation' => $pin]);
    }

    /** Signs in once so the phone is listed, then approves it. Returns its id. */
    private function approve(string $name = 'Phone 01', array $line = [], ?array $phone = null): string
    {
        $phone ??= $this->phone;
        $this->signIn('EMP-001', '000000', [], $phone)->assertJsonPath('meta.reason', 'PHONE_PENDING');
        $id = DB::table('qt_phones')->where('install_id', $phone['install_id'])->value('id');
        $this->as($this->owner)->patchJson("/api/v1/quality-tally/phones/{$id}", ['name' => $name, 'approve' => true] + $line)->assertOk()->assertJsonPath('data.status', 'APPROVED');

        return $id;
    }

    private function target(string $line): void
    {
        DB::table('qt_line_targets')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'target_date' => \App\Modules\QualityTally\Support\QtTally::currentDate($this->delta->id, $this->qc->id), 'section' => 'SEWING', 'line_id' => $line, 'job_id' => $this->job, 'working_hours' => 8, 'target_qty' => 100, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function defect(): string
    {
        return $this->as($this->owner)->postJson('/api/v1/quality-tally/defect-types', ['section' => 'SEWING', 'code' => 'BS', 'name' => 'Broken stitch', 'active' => true])->assertCreated()->json('data.id');
    }

    private function newPhone(): array
    {
        return ['install_id' => (string) Str::uuid(), 'secret' => Str::random(48), 'model' => 'Pixel 10'];
    }

    private function tap(string $session): array
    {
        return ['session' => $session, 'section' => 'SEWING', 'line_id' => $this->line, 'job_id' => $this->job, 'action' => 'RECTIFY', 'client_uuid' => (string) Str::uuid(), 'occurred_at' => now()->toIso8601String()];
    }

    private function inspector(User $user, string $code, array $lines): string
    {
        if (! DB::table('hr_gazettes')->exists()) {
            $gazette = (string) Str::ulid();
            DB::table('hr_gazettes')->insert(['id' => $gazette, 'company_id' => $this->delta->id, 'name' => 'RMG 2023', 'effective_date' => '2023-12-01', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('hr_employment_types')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'name' => 'Staff', 'name_bn' => 'স্টাফ', 'short_name' => 'STF', 'created_at' => now(), 'updated_at' => now()]);
        }
        $employee = (string) Str::ulid();
        DB::table('hr_employees')->insert(['id' => $employee, 'company_id' => $this->delta->id, 'employee_code' => $code, 'card_no' => 'C'.$code, 'name' => $user->name, 'status' => 'ACTIVE', 'active' => true, 'joining_date' => '2026-01-01', 'gazette_id' => DB::table('hr_gazettes')->value('id'), 'employment_type_id' => DB::table('hr_employment_types')->value('id'), 'gross_a' => 1, 'gross_b' => 1, 'factory_id' => DB::table('factories')->where('company_id', $this->delta->id)->value('id'), 'department_id' => DB::table('departments')->where('company_id', $this->delta->id)->value('id'), 'created_at' => now(), 'updated_at' => now()]);

        return $this->as($this->owner)->postJson('/api/v1/quality-tally/inspectors', ['user_id' => $user->id, 'hr_employee_id' => $employee, 'lines' => array_map(fn ($l) => ['section' => 'SEWING', 'line_id' => $l], $lines)])->assertCreated()->json('data.id');
    }

    private function bearer(TestResponse $session): static
    {
        $this->flushHeaders();

        return $this->withToken($session->json('data.access_token'));
    }

    private function asPhone(array $headers = [], ?array $phone = null): static
    {
        $phone ??= $this->phone;
        $this->flushHeaders();

        return $this->withHeaders($headers + ['X-Company-Id' => $this->delta->id, 'X-Phone-Id' => $phone['install_id'], 'X-Phone-Secret' => $phone['secret'], 'X-App-Version' => '0.1.0']);
    }

    private function as(User $user, ?Company $company = null): static
    {
        $this->flushHeaders();
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, ($company ?? $this->delta)->id, 'Test token');

        return $this->withToken($plain);
    }
}
