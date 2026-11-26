<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Support\AccountingDefaults;
use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Costing\Models\CostCategory;
use App\Modules\Costing\Services\CostPoster;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Access Control → Ledger Integration Setup (the user's decision of
 * 2026-11-15): Machinery's costs reach Finance & Ledger's Chart of Account as
 * draft vouchers through the GL source of their kind, once that source has a
 * debit and a credit ledger.
 */
class LedgerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->asset = WorkOrderFixture::runningAsset($this->delta, $dhaka);

        AccountingDefaults::accounts($this->delta->id);
        AccountingDefaults::fiscalYears($this->delta->id);
    }

    public function test_the_machinery_sources_are_offered_and_a_cost_posts_a_draft_voucher_once_set(): void
    {
        $sources = collect($this->api()->getJson('/api/v1/erp/acc-gl-sources')->assertOk()->json('data'));
        $other = $sources->firstWhere('link_ref', 'MACHINERY_OTHER');
        $this->assertNotNull($other);
        $this->assertFalse($other['ready']);
        $this->assertTrue($other['system']);

        // Without ledgers nothing posts.
        $this->cost('MANUAL', '1500');
        $this->assertSame(0, DB::table('acc_vouchers')->count());

        [$expense, $payable] = $this->twoLedgers();
        $this->api()->postJson('/api/v1/erp/acc-gl-source-lines', ['gl_source_id' => $other['id'], 'side' => 'DEBIT', 'account_id' => $expense])->assertCreated();
        $this->api()->postJson('/api/v1/erp/acc-gl-source-lines', ['gl_source_id' => $other['id'], 'side' => 'CREDIT', 'account_id' => $payable])->assertCreated();

        $entry = $this->cost('MANUAL', '2500');

        $voucher = DB::table('acc_vouchers')->first();
        $this->assertSame(['JV', 'SYSTEM', 'DRAFT', 'CREATE_VOUCHER'], [$voucher->voucher_type, $voucher->trx_source, $voucher->posting_status, $voucher->action_status]);
        $this->assertEqualsWithDelta(2500, (float) $voucher->total, 0.001);
        $lines = DB::table('acc_voucher_lines')->where('voucher_id', $voucher->id)->orderBy('sort_order')->get();
        $this->assertSame([$expense, $payable], $lines->pluck('account_id')->all());
        $this->assertEqualsWithDelta(2500, (float) $lines[0]->debit, 0.001);
        $this->assertEqualsWithDelta(2500, (float) $lines[1]->credit, 0.001);

        // Reversing the cost posts the other way round; the cost itself is recorded either way.
        app(CostPoster::class)->reverse($entry, $this->owner->id, 'Keyed twice');
        $reversal = DB::table('acc_vouchers')->orderByDesc('voucher_ref_no')->first();
        $this->assertNotSame($voucher->id, $reversal->id);
        $back = DB::table('acc_voucher_lines')->where('voucher_id', $reversal->id)->orderBy('sort_order')->get();
        $this->assertSame([$payable, $expense], $back->pluck('account_id')->all());
        $this->assertSame(2, DB::table('acc_ledger_postings')->count());
    }

    public function test_a_system_source_keeps_its_link_ref_and_cannot_be_removed(): void
    {
        $other = collect($this->api()->getJson('/api/v1/erp/acc-gl-sources')->json('data'))->firstWhere('link_ref', 'MACHINERY_OTHER');

        $this->api()->patchJson("/api/v1/erp/acc-gl-sources/{$other['id']}", ['link_ref' => 'SOMETHING_ELSE', 'name_bn' => 'অন্যান্য'])->assertOk()
            ->assertJsonPath('data.link_ref', 'MACHINERY_OTHER');
        $this->api()->deleteJson("/api/v1/erp/acc-gl-sources/{$other['id']}")->assertUnprocessable();
        $this->api()->postJson('/api/v1/erp/acc-gl-sources', ['name' => 'Copy', 'mapping_type' => 'GENERAL', 'link_ref' => 'machinery_parts'])->assertUnprocessable()->assertJsonValidationErrors('link_ref');

        // A company's own source: Final Bill Collection only for bank bill sources.
        $own = $this->api()->postJson('/api/v1/erp/acc-gl-sources', ['name' => 'Export Sales Invoice', 'mapping_type' => 'GENERAL', 'final_bill_collection' => true])->assertCreated();
        $own->assertJsonPath('data.final_bill_collection', false);
        $this->api()->deleteJson('/api/v1/erp/acc-gl-sources/'.$own->json('data.id'))->assertNoContent();
    }

    /** @return array{0: string, 1: string} two postable ledgers */
    private function twoLedgers(): array
    {
        $ids = DB::table('acc_accounts')->where('company_id', $this->delta->id)->where('postable', true)->where('active', true)->orderBy('code')->limit(2)->pluck('id')->all();
        $this->assertCount(2, $ids);

        return $ids;
    }

    private function cost(string $type, string $amount)
    {
        return app(CostPoster::class)->post([
            'asset_id' => $this->asset->id,
            'cost_category_id' => CostCategory::query()->firstOrFail()->id,
            'amount' => $amount,
            'source_type' => $type,
            'description' => 'Belt guard',
        ], $this->owner->id);
    }

    private function api(): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
