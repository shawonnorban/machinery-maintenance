<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Fabric Booking → Imported Fabric Booking (AnnotechERP 06/11): the confirmed
 * orders with their lead time; an import booking raised against one,
 * numbered BK-IMP-00001, approved with one press — onto the Approved list and
 * closed to its lines and to removal — or removed while still a draft.
 */
class MerchandiseImportBookingTest extends TestCase
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

    public function test_an_import_booking_is_raised_against_a_confirmed_order_approved_and_closed(): void
    {
        [$confirmed, $draft] = $this->orders();
        $sj = $this->as()->postJson('/api/v1/erp/fabric-types', ['code' => 'SJ', 'name' => 'Single Jersey', 'short_name' => 'SJ', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'sort_order' => 1])->assertCreated()->json('data.id');

        // Records: only the confirmed order, with its lead time (8 Oct → 1 Nov: 24 days).
        $orders = $this->as()->getJson('/api/v1/merchandise/import-bookings/orders')->assertOk()->json('data');
        $this->assertSame([[$confirmed->order_no, 'LPP', 'Hoodie', '637PK', 'BULK_NEW', '2026-10-08', '2026-11-01', 24, 0]], array_map(
            fn ($o) => [$o['order_no'], $o['buyer'], $o['style_desc'], $o['style'], $o['order_type'], $o['conf_date'], $o['ship_date'], $o['lead_time'], $o['bookings']], $orders,
        ));
        $this->assertEquals(1562, $orders[0]['order_qty']);

        $this->as()->postJson('/api/v1/merchandise/import-bookings', ['order_id' => $confirmed->id])->assertUnprocessable()
            ->assertJsonValidationErrors(['booking_date' => 'Booking Date is required.']);
        $this->as()->postJson('/api/v1/merchandise/import-bookings', ['order_id' => $draft->id, 'booking_date' => '2026-10-16'])->assertUnprocessable()
            ->assertJsonValidationErrors(['order_id' => 'An import booking is raised only against a confirmed order.']);

        $rows = $this->as()->postJson('/api/v1/merchandise/import-bookings', ['order_id' => $confirmed->id, 'booking_date' => '2026-10-16', 'ref_no' => ' LC-IMP-1 ', 'sp_no' => 'SP-IMP-1'])->assertCreated()->json('data');
        $this->assertSame([['BK-IMP-00001', 0, 'LC-IMP-1', 'SP-IMP-1', 0, 'DRAFT', false]], array_map(fn ($r) => [$r['booking_no'], $r['revision'], $r['ref_no'], $r['sp_no'], $r['items'], $r['status'], $r['locked']], $rows));
        $rows = $this->as()->postJson('/api/v1/merchandise/import-bookings', ['order_id' => $confirmed->id, 'booking_date' => '2026-10-16'])->assertCreated()->json('data');
        $this->assertSame(['BK-IMP-00001', 'BK-IMP-00002'], array_column($rows, 'booking_no'));
        $this->assertSame(2, $this->as()->getJson('/api/v1/merchandise/import-bookings/orders')->json('data.0.bookings'));

        // Its fabric lines go on the Booking Items panel while it is a draft.
        $this->as()->postJson('/api/v1/merchandise/fabric-booking-items/BK-IMP-00001', ['fabric_type_id' => $sj, 'qty' => 400])->assertCreated();
        $this->assertSame(1, $this->as()->getJson("/api/v1/merchandise/import-bookings?order_id={$confirmed->id}")->json('data.0.items'));

        // A draft is removed; an approved one is closed.
        $this->as()->deleteJson('/api/v1/merchandise/import-bookings/BK-IMP-00002')->assertOk();
        $approved = $this->as()->postJson('/api/v1/merchandise/import-bookings/BK-IMP-00001/approve')->assertOk()->json('data');
        $this->assertSame([['BK-IMP-00001', 'APPROVED', true]], array_map(fn ($r) => [$r['booking_no'], $r['status'], $r['locked']], $approved));
        $this->as()->deleteJson('/api/v1/merchandise/import-bookings/BK-IMP-00001')->assertUnprocessable()
            ->assertJsonValidationErrors(['booking' => 'An approved import booking cannot be removed.']);
        $this->as()->postJson('/api/v1/merchandise/fabric-booking-items/BK-IMP-00001', ['fabric_type_id' => $sj, 'qty' => 5])->assertUnprocessable()
            ->assertJsonValidationErrors(['booking' => 'This booking is approved and closed to changes.']);
        $this->assertFalse($this->as()->getJson('/api/v1/merchandise/fabric-booking-items/BK-IMP-00001')->json('data.editable'));

        $list = $this->as()->getJson('/api/v1/merchandise/fabric-bookings/approved')->json('data');
        $this->assertSame([['IMPORT', 'BK-IMP-00001', 'SP-IMP-1', $confirmed->order_no, '529']], array_map(fn ($r) => [$r['booking_type'], $r['booking_no'], $r['sp_no'], $r['order_no'], $r['job_no']], $list));
    }

    public function test_import_bookings_are_closed_to_a_technician_and_read_only_to_a_manager(): void
    {
        [$confirmed] = $this->orders();
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');

        $this->as($technician)->getJson('/api/v1/merchandise/import-bookings/orders')->assertForbidden();
        $this->as($manager)->getJson("/api/v1/merchandise/import-bookings?order_id={$confirmed->id}")->assertOk();
        $this->as($manager)->postJson('/api/v1/merchandise/import-bookings', ['order_id' => $confirmed->id, 'booking_date' => '2026-10-16'])->assertForbidden();
    }

    /** A confirmed order of 637PK (LPP) and a draft one: [confirmed, draft]. */
    private function orders(): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
        $made = array_map(function (string $job) use ($style): Order {
            $id = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => $job, 'style_id' => $style, 'total_qty' => 1562, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data.id');

            return Order::query()->findOrFail($id);
        }, ['529', '530']);
        $made[0]->forceFill(['status' => 'CONFIRMED', 'order_type' => 'BULK_NEW', 'conf_date' => '2026-10-08', 'ship_date' => '2026-11-01'])->save();
        $made[1]->forceFill(['status' => 'DRAFT', 'ship_date' => '2026-11-05'])->save();

        return [$made[0]->refresh(), $made[1]->refresh()];
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
