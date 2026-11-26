<?php

declare(strict_types=1);

namespace Tests\Feature\AttendanceDevice;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Settings → Device Integration (AnnotechERP 03/01) and HR → Biometric /
 * Fingerprint (24/03): a ZKTeco push terminal's conversation with /iclock —
 * the handshake, punches (kept once, matched by card then code, in the
 * terminal's time zone), users and finger templates, commands and their
 * answers; a disabled terminal and a foreign address refused — then the
 * screens: the register and its actions, the punch log, enrolled users, the
 * command queue, and Biometric's enrolment, sending, mapping and deleting.
 */
class DeviceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const D = '/api/v1/settings/devices';

    private const B = '/api/v1/hr-payroll/biometric';

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->travelTo(CarbonImmutable::parse('2026-11-10 06:00:00', 'UTC'));
        $dept = tap(strtolower((string) Str::ulid()), fn ($id) => DB::table('departments')->insert(['id' => $id, 'company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Sewing', 'code' => 'SEW', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]));
        $worker = collect($this->as()->getJson('/api/v1/hr-payroll/profiles/options')->json('data.employee_types'))->firstWhere('label', 'Worker')['value'];
        foreach ([['50001001', 'Md. Ripon'], ['50001002', 'Asia Begum'], ['50001003', 'Karim Mia']] as [$card, $name]) {
            $this->as()->postJson('/api/v1/hr-payroll/profiles', ['card_no' => $card, 'factory_id' => $factory->id, 'name' => $name, 'nationality' => 'Bangladesh', 'religion' => 'ISLAM', 'gender' => 'MALE',
                'employment_type_id' => $worker, 'department_id' => null, 'gross_salary' => 14000, 'status' => 'ACTIVE'])->assertCreated();
        }
        DB::table('hr_employees')->update(['department_id' => $dept]);
    }

    public function test_a_push_terminal_talks_to_iclock(): void
    {
        // Register: a push terminal needs its serial number; the serial is one terminal's.
        $this->as()->postJson(self::D, ['code' => 'GATE-01', 'name' => 'Main Gate', 'protocol' => 'ADMS_PUSH'])->assertUnprocessable()->assertJsonPath('errors.serial_no.0', __('settings.dev.v.serial_required'));
        $this->as()->postJson(self::D, ['code' => 'GATE-01', 'name' => 'Main Gate', 'protocol' => 'TCP_PULL'])->assertUnprocessable()->assertJsonPath('errors.ip_address.0', __('settings.dev.v.ip_required'));
        $this->as()->postJson(self::D, ['code' => 'GATE-01', 'name' => 'Main Gate', 'protocol' => 'ADMS_PUSH', 'serial_no' => 'CQUJ203960123', 'model' => 'F22'])->assertCreated()->assertJsonPath('data.state', 'ACTIVE');
        $this->as()->postJson(self::D, ['code' => 'GATE-02', 'name' => 'Other', 'protocol' => 'ADMS_PUSH', 'serial_no' => 'CQUJ203960123'])->assertUnprocessable()->assertJsonPath('errors.serial_no.0', __('settings.dev.v.serial_taken'));

        // The handshake: an unknown serial is turned away; a known one is told what to send, in its time zone.
        $this->get('/iclock/cdata?SN=NOPE&options=all')->assertNotFound();
        $hello = $this->get('/iclock/cdata?SN=CQUJ203960123&options=all')->assertOk();
        $this->assertStringContainsString('GET OPTION FROM: CQUJ203960123', $hello->getContent());
        $this->assertStringContainsString('TimeZone=6', $hello->getContent());

        // Punches: by card, by employee code, and one nobody holds; a resend is kept once.
        $log = "50001001\t2026-11-10 08:05:12\t0\t1\t0\t0\nEMP-000002\t2026-11-10 08:07:00\t0\t2\t\t\n9999\t2026-11-10 08:09:00\t0\t1\t\t\n";
        $this->assertSame('OK: 3', $this->push('cdata?SN=CQUJ203960123&table=ATTLOG&Stamp=1', $log)->getContent());
        $this->assertSame('OK: 0', $this->push('cdata?SN=CQUJ203960123&table=ATTLOG&Stamp=2', $log)->getContent());
        $first = DB::table('dev_punches')->where('pin', '50001001')->first();
        $this->assertSame('2026-11-10 02:05:12', CarbonImmutable::parse($first->punched_at)->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(2, DB::table('dev_punches')->whereNotNull('employee_id')->where('posted_to_hr', true)->count());

        // Users and fingers.
        $oper = "USER PIN=50001001\tName=Ripon\tPri=0\tPasswd=\tCard=50001001\tGrp=1\nUSER PIN=1001\tName=Sayem\tPri=14\tPasswd=\tCard=\tGrp=1\nFP PIN=50001001\tFID=6\tSize=8\tValid=1\tTMP=QUJDREVGR0g=\nOPLOG 4\t0\t2026-11-10 08:10:00\t0\t0\t0\t0\n";
        $this->assertSame('OK: 3', $this->push('cdata?SN=CQUJ203960123&table=OPERLOG', $oper)->getContent());
        $this->assertSame(1, DB::table('dev_users')->whereNotNull('employee_id')->count());
        $this->assertSame(1, DB::table('dev_fingerprints')->whereNotNull('employee_id')->count());

        // Commands: Send Command queues; the terminal takes it on its next poll and answers it.
        $id = $this->as()->postJson('/api/v1/settings/device-commands', ['device' => 'GATE-01', 'command' => 'INFO'])->assertCreated()->json('data.id');
        $this->assertSame("C:{$id}:INFO\n", $this->get('/iclock/getrequest?SN=CQUJ203960123')->getContent());
        $this->assertSame('OK', $this->get('/iclock/getrequest?SN=CQUJ203960123&INFO=Ver 6.60,781,772,953,10.0.0.5')->getContent());
        $this->push('devicecmd?SN=CQUJ203960123', "ID={$id}&Return=0&CMD=INFO\n~DeviceName=F22\nUserCount=782\nFPCount=773\nTransactionCount=954\nFWVersion=Ver 6.60 Nov 2026\n")->assertOk();
        $cmd = collect($this->as()->getJson('/api/v1/settings/device-commands')->json('data'))->firstWhere('id', $id);
        $this->assertEquals(['DONE', 0], [$cmd['status'], $cmd['return_code']]);
        $row = $this->as()->getJson(self::D.'/GATE-01')->json('data');
        $this->assertEquals([782, 773, 954, true, 'Ver 6.60 Nov 2026'], [$row['user_count'], $row['fp_count'], $row['log_count'], $row['online'], $row['firmware']]);

        // A disabled terminal keeps its punches; a foreign address is refused.
        $this->as()->postJson(self::D.'/GATE-01/action', ['action' => 'disable'])->assertOk();
        $this->push('cdata?SN=CQUJ203960123&table=ATTLOG', "50001001\t2026-11-10 17:00:00\t1\t1\n")->assertForbidden();
        $this->as()->postJson(self::D.'/GATE-01/action', ['action' => 'enable'])->assertOk();
        $this->as()->putJson(self::D.'/GATE-01', ['allowed_ips' => '10.20.30.40'] + $row)->assertOk();
        $this->push('cdata?SN=CQUJ203960123&table=ATTLOG', "50001001\t2026-11-10 17:00:00\t1\t1\n")->assertForbidden();
        $this->as()->putJson(self::D.'/GATE-01', ['allowed_ips' => '10.20.30.40, 127.0.0.1'] + $row)->assertOk();
        $this->assertSame('OK: 1', $this->push('cdata?SN=CQUJ203960123&table=ATTLOG', "50001001\t2026-11-10 17:00:00\t1\t1\n")->getContent());

        // The register's numbers and actions.
        $list = $this->as()->getJson(self::D)->assertOk()->json('data');
        $this->assertEquals(['devices' => 1, 'online' => 1, 'awaiting' => 0, 'punches_today' => 4, 'not_in_hr' => 1], $list['summary']);
        $this->as()->postJson(self::D.'/GATE-01/action', ['action' => 'reupload'])->assertOk();
        $this->assertSame(3, DB::table('dev_commands')->where('status', 'QUEUED')->count());

        // Punch log: today by default; by User ID or employee code; matched or not.
        $punches = $this->as()->getJson('/api/v1/settings/device-punches')->assertOk()->json('data');
        $this->assertEquals(['punches' => 4, 'matched' => 3, 'unmatched' => 1, 'posted' => 3], $punches['summary']);
        $this->assertCount(1, $this->as()->getJson('/api/v1/settings/device-punches?pin=EMP-000002')->json('data.rows'));
        $this->assertCount(1, $this->as()->getJson('/api/v1/settings/device-punches?match=unmatched')->json('data.rows'));

        // A card registered after the punch: Post Pending to HR matches it now.
        DB::table('hr_employees')->where('employee_code', 'EMP-000003')->update(['card_no' => '9999']);
        $this->assertStringContainsString('1', $this->as()->postJson('/api/v1/settings/device-punches/post-pending')->assertOk()->json('data.message'));
        $this->assertSame(0, DB::table('dev_punches')->whereNull('employee_id')->count());

        // Enrolled users; a terminal with punches is not removed.
        $this->assertEquals(['enrolled' => 2, 'unmatched' => 1], $this->as()->getJson('/api/v1/settings/device-users')->json('data.summary'));
        $this->as()->deleteJson(self::D.'/GATE-01')->assertUnprocessable();

        // Who may: device setup is admin-only; a factory admin reads, management and a storekeeper do not.
        $this->as(TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'fa@delta.test'))->getJson(self::D)->assertOk();
        $fm = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $this->as($fm)->getJson(self::D)->assertForbidden();
        $this->as($fm)->postJson(self::D.'/GATE-01/action', ['action' => 'info'])->assertForbidden();
        $this->as(TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test'))->getJson(self::D)->assertForbidden();
    }

    public function test_biometric_enrols_sends_maps_and_deletes(): void
    {
        foreach ([['M-01', 'SN-AAA'], ['M-02', 'SN-BBB']] as [$code, $sn]) {
            $this->as()->postJson(self::D, ['code' => $code, 'name' => "Machine {$code}", 'protocol' => 'ADMS_PUSH', 'serial_no' => $sn])->assertCreated();
        }
        $this->assertEquals(['employees' => 3, 'registered' => 0, 'pending' => 0, 'not_registered' => 3], $this->as()->getJson(self::B.'/overview')->assertOk()->json('data.counts'));

        // Queue enrolment: mapped on M-01 under the card number, Pending, and sent as a user.
        $this->as()->postJson(self::B.'/enrol', ['employees' => ['EMP-000001', 'EMP-000002'], 'device' => 'M-01'])->assertOk();
        $this->assertSame("DATA UPDATE USERINFO PIN=50001001\tName=Md. Ripon\tPri=0\tPasswd=\tCard=50001001\tGrp=1\tTZ=0000000100000000\tVerify=0", DB::table('dev_commands')->orderBy('id')->value('command'));
        $rows = collect($this->as()->getJson(self::B.'/employees')->json('data.rows'))->keyBy('code');
        $this->assertEquals(['PENDING', [['device' => 'M-01', 'name' => 'Machine M-01', 'pin' => '50001001', 'status' => 'PENDING']]], [$rows['EMP-000001']['status'], $rows['EMP-000001']['maps']]);

        // The terminal reports the finger: Registered.
        $this->push('cdata?SN=SN-AAA&table=OPERLOG', "FP PIN=50001001\tFID=0\tSize=8\tValid=1\tTMP=QUJDREVGR0g=\n")->assertOk();
        $this->assertEquals(['employees' => 3, 'registered' => 1, 'pending' => 1, 'not_registered' => 1], $this->as()->getJson(self::B.'/employees')->json('data.counts'));
        $this->assertSame(['EMP-000002'], array_column($this->as()->getJson(self::B.'/employees?status=PENDING')->json('data.rows'), 'code'));

        // Send to all machines: the user and its finger to both terminals.
        DB::table('dev_commands')->delete();
        $this->as()->postJson(self::B.'/send', ['employees' => ['EMP-000001']])->assertOk();
        $this->assertSame(['FINGER' => 2, 'USER' => 2], DB::table('dev_commands')->selectRaw('kind, count(*) as n')->groupBy('kind')->orderBy('kind')->pluck('n', 'kind')->map(fn ($n) => (int) $n)->all());
        $this->assertStringStartsWith("DATA UPDATE FINGERTMP PIN=50001001\tFID=0\tSize=8\tValid=1\tTMP=", DB::table('dev_commands')->where('kind', 'FINGER')->value('command'));
        $this->assertSame('REGISTERED', DB::table('dev_employee_maps as m')->join('dev_devices as d', 'd.id', '=', 'm.device_id')->where('d.code', 'M-02')->value('m.status'));

        // Employee Mapping: a terminal user whose card is an employee's — auto-mapped.
        $this->push('cdata?SN=SN-BBB&table=OPERLOG', "USER PIN=77\tName=Karim\tPri=0\tCard=50001003\nUSER PIN=78\tName=Stranger\tPri=0\tCard=\n")->assertOk();
        $unmapped = $this->as()->getJson(self::B.'/unmapped')->json('data');
        $this->assertEquals([2, 1, 'Karim Mia (EMP-000003)'], [count($unmapped['rows']), $unmapped['suggested'], collect($unmapped['rows'])->firstWhere('pin', '77')['suggested']]);
        $this->as()->postJson(self::B.'/automap')->assertOk();
        $this->assertSame(['78'], array_column($this->as()->getJson(self::B.'/unmapped')->json('data.rows'), 'pin'));
        // A PIN already someone's on a terminal is refused; a map can be taken off.
        $this->as()->postJson(self::B.'/map', ['employee' => 'EMP-000002', 'device' => 'M-02', 'pin' => '77'])->assertUnprocessable()->assertJsonValidationErrors(['pin']);
        $this->as()->postJson(self::B.'/map', ['employee' => 'EMP-000002', 'device' => 'M-02', 'pin' => '78'])->assertOk();
        $this->assertSame([], $this->as()->getJson(self::B.'/unmapped')->json('data.rows'));
        $this->as()->postJson(self::B.'/unmap', ['employee' => 'EMP-000002', 'device' => 'M-02'])->assertOk();
        $this->assertSame(['78'], array_column($this->as()->getJson(self::B.'/unmapped')->json('data.rows'), 'pin'));

        // Collect, then delete from all machines: off every terminal, fingers and maps gone.
        $this->as()->postJson(self::B.'/collect')->assertOk();
        $this->as()->postJson(self::B.'/delete', ['employees' => ['EMP-000001']])->assertOk();
        $this->assertSame(2, DB::table('dev_commands')->where('kind', 'DELETE')->count());
        $this->assertSame(0, DB::table('dev_fingerprints')->count() + DB::table('dev_employee_maps')->where('employee_id', DB::table('hr_employees')->where('employee_code', 'EMP-000001')->value('id'))->count());

        $this->as(TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test'))->postJson(self::B.'/collect')->assertForbidden();
    }

    private function push(string $path, string $body): TestResponse
    {
        return $this->call('POST', '/iclock/'.$path, [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
