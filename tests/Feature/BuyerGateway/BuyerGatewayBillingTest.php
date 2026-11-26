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
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Buyer Gateway → Pricing & Billing (AnnotechERP 25/03): RFQs (the year's next
 * reference when left blank, unique, the answer not due before the request),
 * price lines (seasons, the validity window), invoices (unique, not due
 * before issue, a published one past due reads overdue) and payment lines
 * (one per buyer, outstanding = billed − received, the ageing band from the
 * due date, never more received than billed); and who may.
 */
class BuyerGatewayBillingTest extends TestCase
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

    public function test_rfqs_and_price_lines(): void
    {
        $tee = strtolower((string) Str::ulid());
        DB::table('ws_garment_items')->insert(['id' => $tee, 'company_id' => $this->delta->id, 'item_group' => 'TOP', 'name' => 'Basic T-Shirt', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $o = $this->as()->getJson('/api/v1/buyer-gateway/options')->assertOk()->json('data');
        $this->assertSame(['Basic T-Shirt'], array_column($o['products'], 'label'));
        $this->assertContains('Winter 26', array_column($o['seasons'], 'value'));

        $this->as()->postJson(self::E.'/bg-rfqs', [])->assertUnprocessable()->assertJsonPath('errors.buyer_id.0', __('buyergateway.v.buyer'));
        $a = $this->as()->postJson(self::E.'/bg-rfqs', ['buyer_id' => $this->hm, 'product_id' => $tee, 'quantity' => 420000, 'target_price' => 2, 'respond_by' => '2026-11-20'])->assertCreated()->json('data');
        $this->assertEquals(['RFQ-2026-001', 'NEW', '2026-11-11', 'Basic T-Shirt', 420000, 2], [$a['reference'], $a['state'], $a['received_on'], $a['product'], $a['quantity'], $a['target_price']]);
        $this->as()->postJson(self::E.'/bg-rfqs', ['buyer_id' => $this->zara, 'reference' => 'rfq-2026-118'])->assertCreated()->assertJsonPath('data.reference', 'RFQ-2026-118');
        $this->as()->postJson(self::E.'/bg-rfqs', ['buyer_id' => $this->zara])->assertCreated()->assertJsonPath('data.reference', 'RFQ-2026-119');
        $this->as()->postJson(self::E.'/bg-rfqs', ['buyer_id' => $this->zara, 'reference' => 'RFQ-2026-001'])->assertUnprocessable()->assertJsonPath('errors.reference.0', __('buyergateway.v.rfq_taken'));
        $this->as()->postJson(self::E.'/bg-rfqs', ['buyer_id' => $this->zara, 'received_on' => '2026-11-10', 'respond_by' => '2026-11-01'])->assertUnprocessable()->assertJsonPath('errors.respond_by.0', __('buyergateway.v.respond_before_received'));
        $this->as()->patchJson(self::E."/bg-rfqs/{$a['id']}", ['state' => 'CONVERTED'])->assertOk()->assertJsonPath('data.state', 'CONVERTED')->assertJsonPath('data.reference', 'RFQ-2026-001');

        // Price lines.
        $this->as()->postJson(self::E.'/bg-price-lines', [])->assertUnprocessable()->assertJsonPath('errors.product.0', __('buyergateway.v.product'));
        $line = $this->as()->postJson(self::E.'/bg-price-lines', ['product' => ' Basic crew tee, 160 gsm ', 'buyer_id' => $this->hm, 'season' => 'Winter 26', 'price' => 2.15, 'incoterm' => 'FOB', 'min_qty' => 50000, 'valid_from' => '2026-11-01', 'valid_to' => '2026-12-31'])->assertCreated()->json('data');
        $this->assertEquals(['Basic crew tee, 160 gsm', 'Winter 26', 2.15, 'FOB', 50000], [$line['product'], $line['season'], $line['price'], $line['incoterm'], $line['min_qty']]);
        $this->as()->postJson(self::E.'/bg-price-lines', ['product' => 'X', 'buyer_id' => $this->hm, 'season' => 'Spring 26'])->assertUnprocessable()->assertJsonValidationErrors(['season']);
        $this->as()->postJson(self::E.'/bg-price-lines', ['product' => 'X', 'buyer_id' => $this->hm, 'incoterm' => 'FCA'])->assertUnprocessable()->assertJsonValidationErrors(['incoterm']);
        $this->as()->patchJson(self::E."/bg-price-lines/{$line['id']}", ['valid_to' => '2026-10-01'])->assertUnprocessable()->assertJsonPath('errors.valid_to.0', __('buyergateway.v.valid_to_before_from'));
    }

    public function test_invoices_and_payments(): void
    {
        $this->as()->postJson(self::E.'/bg-invoices', [])->assertUnprocessable()->assertJsonPath('errors.invoice_no.0', __('buyergateway.v.invoice'));
        $inv = $this->as()->postJson(self::E.'/bg-invoices', ['invoice_no' => 'inv-20261184', 'buyer_id' => $this->hm, 'order_no' => 'so-4402', 'amount' => 1840000, 'due_on' => '2026-12-13'])->assertCreated()->json('data');
        $this->assertEquals(['INV-20261184', 'SO-4402', 1840000, '2026-11-11', 'PUBLISHED'], [$inv['invoice_no'], $inv['order_no'], $inv['amount'], $inv['issued_on'], $inv['state']]);
        $this->as()->postJson(self::E.'/bg-invoices', ['invoice_no' => 'INV-20261184', 'buyer_id' => $this->zara])->assertUnprocessable()->assertJsonPath('errors.invoice_no.0', __('buyergateway.v.invoice_taken'));
        $this->as()->postJson(self::E.'/bg-invoices', ['invoice_no' => 'INV-1', 'buyer_id' => $this->zara, 'issued_on' => '2026-11-10', 'due_on' => '2026-11-01'])->assertUnprocessable()->assertJsonPath('errors.due_on.0', __('buyergateway.v.due_before_issued'));
        // Published and past its due date → overdue; paid stays paid.
        $late = $this->as()->postJson(self::E.'/bg-invoices', ['invoice_no' => 'INV-20261108', 'buyer_id' => $this->zara, 'amount' => 486000, 'issued_on' => '2026-06-24', 'due_on' => '2026-08-23'])->assertCreated()->json('data');
        $this->assertSame('OVERDUE', $late['state']);
        $this->as()->patchJson(self::E."/bg-invoices/{$late['id']}", ['state' => 'PAID'])->assertOk()->assertJsonPath('data.state', 'PAID');

        // Payments: one line per buyer; outstanding and ageing from the due date.
        $this->as()->postJson(self::E.'/bg-payment-lines', ['buyer_id' => $this->hm, 'billed' => 100, 'received' => 150])->assertUnprocessable()->assertJsonPath('errors.received.0', __('buyergateway.v.received_over_billed'));
        $hm = $this->as()->postJson(self::E.'/bg-payment-lines', ['buyer_id' => $this->hm, 'billed' => 18400000, 'received' => 16200000, 'payment_terms' => 'LC_60', 'next_due' => '2026-12-13'])->assertCreated()->json('data');
        $this->assertEquals([2200000, 'NOT_DUE'], [$hm['outstanding'], $hm['ageing']]);
        $this->as()->postJson(self::E.'/bg-payment-lines', ['buyer_id' => $this->hm])->assertUnprocessable()->assertJsonPath('errors.buyer_id.0', __('buyergateway.v.payment_taken', ['buyer' => 'H&M']));
        $bands = [];
        foreach (['2026-11-01' => 'D30', '2026-09-20' => 'D60', '2026-08-20' => 'D90', '2026-07-01' => 'OVER_90'] as $due => $band) {
            $bands[] = $this->as()->patchJson(self::E."/bg-payment-lines/{$hm['id']}", ['next_due' => $due])->assertOk()->json('data.ageing');
        }
        $this->assertSame(['D30', 'D60', 'D90', 'OVER_90'], $bands);
        // Settled: nothing outstanding is never overdue.
        $this->as()->patchJson(self::E."/bg-payment-lines/{$hm['id']}", ['received' => 18400000])->assertOk()->assertJsonPath('data.ageing', 'NOT_DUE')->assertJsonPath('data.outstanding', 0);
        // Nor can billed drop below what already came in.
        $this->as()->patchJson(self::E."/bg-payment-lines/{$hm['id']}", ['billed' => 1000])->assertUnprocessable()->assertJsonPath('errors.billed.0', __('buyergateway.v.received_over_billed'));
        $this->assertEquals(18400000, DB::table('bg_payment_lines')->where('id', $hm['id'])->value('billed'));
    }

    public function test_who_may(): void
    {
        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'auditor@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');
        $this->as($auditor)->getJson(self::E.'/bg-invoices')->assertOk();
        $this->as($auditor)->postJson(self::E.'/bg-rfqs', ['buyer_id' => $this->hm])->assertForbidden();
        $this->as($manager)->postJson(self::E.'/bg-rfqs', ['buyer_id' => $this->hm])->assertCreated();
        $this->as($keeper)->getJson(self::E.'/bg-payment-lines')->assertForbidden();
        $this->as($keeper)->getJson('/api/v1/buyer-gateway/options')->assertForbidden();

        $other = TenantFixture::company('Other Ltd', 'OTH');
        TenantFixture::actingAsTenant($other);
        $stranger = TenantFixture::user($other, 'COMPANY_OWNER', 'owner@other.test');
        $this->assertSame([], $this->as($stranger, $other)->getJson(self::E.'/bg-rfqs')->assertOk()->json('data'));
        $this->as($stranger, $other)->postJson(self::E.'/bg-rfqs', ['buyer_id' => $this->hm])->assertUnprocessable()->assertJsonValidationErrors(['buyer_id']);
    }

    private function as(?User $user = null, ?Company $company = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, ($company ?? $this->delta)->id, 'Test token');

        return $this->withToken($plain);
    }
}
