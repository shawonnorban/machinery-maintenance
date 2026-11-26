<?php

declare(strict_types=1);

namespace Tests\Feature\SalesShipment;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\SalesShipment\Support\SsSettings;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Sales & Shipment → Operation → Sales & Shipment Settings (AnnotechERP
 * 21/01): Brickland's defaults until the settings are first saved; the edit
 * kept per company with who changed it; the alert windows, prefixes and
 * sequence width checked — every series its own prefix; the next number of a
 * series printed the way the settings say; and who may.
 */
class SalesShipmentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private const BODY = [
        'ship_against_delivered' => true, 'require_export_docs' => false, 'due_alert_days' => 10, 'cert_alert_days' => 45,
        'prefix_amendment' => 'SOA', 'prefix_challan_return' => 'DCR', 'prefix_sc_return' => 'SCRT', 'prefix_sc_reconciliation' => 'SCRC',
        'prefix_shipment' => 'SHIP', 'prefix_booking' => 'BKG', 'prefix_invoice' => 'CINV', 'prefix_packing_list' => 'PL', 'prefix_handover' => 'DH',
        'seq_width' => 5,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-07 10:00:00', 'Asia/Dhaka'));
        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_the_defaults_show_until_the_settings_are_first_saved(): void
    {
        $this->as()->getJson('/api/v1/sales-shipment/settings')->assertOk()
            ->assertJsonPath('data.saved', false)->assertJsonPath('data.ship_against_delivered', false)->assertJsonPath('data.require_export_docs', true)
            ->assertJsonPath('data.due_alert_days', 7)->assertJsonPath('data.cert_alert_days', 30)->assertJsonPath('data.seq_width', 6)
            ->assertJsonPath('data.prefix_amendment', 'SOA')->assertJsonPath('data.prefix_sc_reconciliation', 'SCRC')->assertJsonPath('data.prefix_handover', 'DH')
            ->assertJsonPath('data.updated_by', null)->assertJsonPath('data.example', 'SHP-2026-000001');
    }

    public function test_the_settings_are_saved_per_company_with_who_changed_them(): void
    {
        $this->as()->putJson('/api/v1/sales-shipment/settings', self::BODY)->assertOk()
            ->assertJsonPath('data.saved', true)->assertJsonPath('data.ship_against_delivered', true)->assertJsonPath('data.require_export_docs', false)
            ->assertJsonPath('data.due_alert_days', 10)->assertJsonPath('data.prefix_shipment', 'SHIP')->assertJsonPath('data.seq_width', 5)
            ->assertJsonPath('data.updated_by', $this->owner->name)->assertJsonPath('data.example', 'SHIP-2026-00001');
        $this->as()->putJson('/api/v1/sales-shipment/settings', ['cert_alert_days' => 60] + self::BODY)->assertOk()->assertJsonPath('data.cert_alert_days', 60);
        $this->assertSame(1, DB::table('ss_settings')->where('company_id', $this->delta->id)->count());

        $other = TenantFixture::company('Other Knit Ltd', 'OKL');
        TenantFixture::actingAsTenant($other);
        $otherOwner = TenantFixture::user($other, 'COMPANY_OWNER', 'owner@other.test');
        $this->withToken(app(IssueApiToken::class)->forUser($otherOwner, $other->id, 'Test token')['plain'])
            ->getJson('/api/v1/sales-shipment/settings')->assertOk()->assertJsonPath('data.saved', false)->assertJsonPath('data.prefix_shipment', 'SHP');
    }

    public function test_the_windows_prefixes_and_width_are_checked(): void
    {
        $put = fn (array $change) => $this->as()->putJson('/api/v1/sales-shipment/settings', $change + self::BODY);
        $put(['due_alert_days' => 0])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.days')]);
        $put(['cert_alert_days' => 366])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.days')]);
        $put(['prefix_booking' => 'B'])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.prefix')]);
        $put(['prefix_booking' => 'bkg'])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.prefix')]);
        $put(['prefix_booking' => 'BOOKING'])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.prefix')]);
        $put(['seq_width' => 3])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.width')]);
        $put(['seq_width' => 9])->assertUnprocessable()->assertJsonFragment([__('salesshipment.v.width')]);
        $put(['prefix_invoice' => 'SHIP'])->assertUnprocessable()
            ->assertJsonFragment([__('salesshipment.v.prefix_taken', ['prefix' => 'SHIP', 'other' => __('salesshipment.set.prefix_shipment')])]);
        $this->assertSame(0, DB::table('ss_settings')->count());
    }

    public function test_the_next_number_follows_the_prefix_and_width(): void
    {
        Schema::create('ss_probe_docs', function (Blueprint $t): void {
            $t->id();
            $t->string('company_id');
            $t->string('doc_no');
        });
        $no = fn () => SsSettings::nextNo('prefix_shipment', 'ss_probe_docs', 'doc_no', '2026-11-07', $this->delta->id);
        $this->assertSame('SHP-2026-000001', $no());
        DB::table('ss_probe_docs')->insert([['company_id' => $this->delta->id, 'doc_no' => 'SHP-2026-000009'], ['company_id' => 'someone-else', 'doc_no' => 'SHP-2026-000050']]);
        $this->assertSame('SHP-2026-000010', $no());

        $this->as()->putJson('/api/v1/sales-shipment/settings', self::BODY)->assertOk();
        $this->assertSame('SHIP-2026-00001', $no());
        $this->assertSame('SHIP-2025-00001', SsSettings::nextNo('prefix_shipment', 'ss_probe_docs', 'doc_no', '2025-03-01', $this->delta->id));
    }

    public function test_who_may(): void
    {
        $this->withToken($this->token('AUDITOR', 'audit@delta.test'))->getJson('/api/v1/sales-shipment/settings')->assertOk();
        $this->withToken($this->token('AUDITOR', 'audit2@delta.test'))->putJson('/api/v1/sales-shipment/settings', self::BODY)->assertForbidden();
        $this->withToken($this->token('FACTORY_MANAGER', 'fm@delta.test'))->putJson('/api/v1/sales-shipment/settings', self::BODY)->assertForbidden(); // settings are admin-only
        $this->withToken($this->token('FACTORY_ADMIN', 'fa@delta.test'))->putJson('/api/v1/sales-shipment/settings', self::BODY)->assertOk();
        $this->withToken($this->token('TECHNICIAN', 'tech@delta.test'))->getJson('/api/v1/sales-shipment/settings')->assertForbidden();
        $this->withToken($this->token('VIEWER', 'viewer@delta.test'))->putJson('/api/v1/sales-shipment/settings', self::BODY)->assertForbidden();
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
