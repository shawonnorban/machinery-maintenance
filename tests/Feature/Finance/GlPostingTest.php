<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Finance\Models\GlEventMapping;
use App\Modules\Finance\Models\GlJournalEntry;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\ItemFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * GL postings (Shared Systems spec §2, §5.1).
 *
 * The spec is blunt about the shape of this: the chart of accounts is not
 * signed off, the mapping is data rather than code, and "GRN Complete /
 * Landed Cost Post / Issue-to-Production সবই GL ছাড়াই সম্পূর্ণ কাজ করবে".
 * So what these tests check is not which account a number landed in — that
 * is finance's to decide later — but that every event of §2's table posts
 * exactly one balanced entry, that posting twice is impossible, and that a
 * mapping nobody has finished stops the books without stopping the store.
 */
class GlPostingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $storekeeper;

    private User $storeManager;

    private Vendor $mill;

    private Item $button;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');
        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');

        $this->mill = Vendor::create([
            'company_id' => $this->delta->id, 'name' => 'Matin Textile Mills', 'code' => 'MTM',
            'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE', 'default_currency' => 'BDT',
        ]);

        $this->button = ItemFixture::item($this->delta, 'TRM-BTN-18L', 'Button 18L');
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    private function receipt(string $quantity = '1000', string $rate = '2'): GoodsReceipt
    {
        $token = $this->tokenFor($this->storekeeper);

        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'vendor_id' => $this->mill->id,
            'lines' => [[
                'item_id' => $this->button->id,
                'received_qty' => $quantity,
                'rate' => $rate,
                'bin_id' => $this->bin->id,
            ]],
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete")->assertOk();

        return GoodsReceipt::findOrFail($grn['id']);
    }

    private function entry(string $eventType): GlJournalEntry
    {
        return GlJournalEntry::where('event_type', $eventType)->with('lines')->firstOrFail();
    }

    public function test_every_event_posts_one_balanced_entry_against_its_source_document(): void
    {
        $receipt = $this->receipt();
        $sm = $this->tokenFor($this->storeManager);

        // 1. GRN complete — 1,000 buttons at 2.
        $grnEntry = $this->entry('GRN_COMPLETE');
        $this->assertSame('2000.0000', (string) $grnEntry->amount);
        $this->assertSame($receipt->grn_no, $grnEntry->source_no);
        $this->assertSame($this->dhaka->id, $grnEntry->factory_id);
        $this->assertTrue($grnEntry->isBalanced());
        $this->assertCount(2, $grnEntry->lines);

        // 2. Landed cost post — freight of 300 on the same receipt.
        $voucher = $this->withToken($sm)->postJson('/api/v1/landed-costs', [
            'factory_id' => $this->dhaka->id,
            'basis' => 'VALUE',
            'goods_receipt_ids' => [$receipt->id],
            'charges' => [['charge_type' => 'FREIGHT', 'amount' => '300']],
        ])->assertCreated()->json('data');

        $this->withToken($sm)->postJson("/api/v1/landed-costs/{$voucher['id']}/post")->assertOk();

        $landed = $this->entry('LANDED_COST_POST');
        $this->assertSame('300.0000', (string) $landed->amount);
        $this->assertTrue($landed->isBalanced());

        // 3. Purchase return — 200 back at the rate they came in at, which
        //    the landed cost has since moved to 2.30.
        $returned = $this->withToken($this->tokenFor($this->storekeeper))
            ->postJson("/api/v1/goods-receipts/{$receipt->id}/returns", [
                'reason' => 'QUALITY_FAIL',
                'lines' => [['goods_receipt_line_id' => $receipt->lines()->firstOrFail()->id, 'quantity' => '200']],
            ])->assertCreated()->json('data');

        $this->withToken($sm)->postJson("/api/v1/purchase-returns/{$returned['id']}/submit")->assertOk();
        $this->withToken($sm)->postJson("/api/v1/purchase-returns/{$returned['id']}/dispatch")->assertOk();

        $return = $this->entry('PURCHASE_RETURN');
        $this->assertSame('460.0000', (string) $return->amount);
        $this->assertTrue($return->isBalanced());
        // An accountant looks a posting up by the debit note, not a ULID.
        $this->assertStringStartsWith('DN-DHK-', (string) $return->source_no);

        // 4. Advance paid, and 5. part of it set against a bill.
        $advance = $this->withToken($sm)->postJson('/api/v1/supplier-advances', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->mill->id,
            'amount' => '50000',
        ])->assertCreated()->json('data');

        $paid = $this->entry('ADVANCE_PAYMENT');
        $this->assertSame('50000.0000', (string) $paid->amount);
        $this->assertTrue($paid->isBalanced());

        $this->withToken($sm)->postJson("/api/v1/supplier-advances/{$advance['id']}/adjust", [
            'amount' => '2000', 'goods_receipt_id' => $receipt->id,
        ])->assertOk();

        $settlement = $this->entry('INVOICE_SETTLEMENT');
        $this->assertSame('2000.0000', (string) $settlement->amount);
        $this->assertTrue($settlement->isBalanced());
        // The whole of it came out of the advance, so the bank leg is zero
        // and is not written at all.
        $this->assertCount(2, $settlement->lines);

        // A refund is the advance coming back, not a bill being paid.
        $this->withToken($sm)->postJson("/api/v1/supplier-advances/{$advance['id']}/refund", [
            'amount' => '1000',
        ])->assertOk();

        $this->assertSame(1, GlJournalEntry::where('event_type', 'INVOICE_SETTLEMENT')->count());

        // Five events, five entries, every one of them balanced.
        $this->assertSame(5, GlJournalEntry::count());

        foreach (GlJournalEntry::with('lines')->get() as $entry) {
            $this->assertTrue($entry->isBalanced(), "{$entry->event_type} is out of balance.");
        }
    }

    public function test_a_document_cannot_be_posted_to_the_books_twice(): void
    {
        $receipt = $this->receipt();

        $this->assertSame(1, GlJournalEntry::where('event_type', 'GRN_COMPLETE')->count());

        // Completing again is refused as a status change, and the books do
        // not move either way.
        $this->withToken($this->tokenFor($this->storekeeper))
            ->postJson("/api/v1/goods-receipts/{$receipt->id}/complete")->assertStatus(409);

        $this->assertSame(1, GlJournalEntry::where('event_type', 'GRN_COMPLETE')->count());

        // And the unique key holds even against the service directly.
        app(\App\Modules\Finance\Services\GlPoster::class)->post('GRN_COMPLETE', '2000', [
            'source_type' => 'goods_receipt',
            'source_id' => $receipt->id,
            'source_no' => $receipt->grn_no,
        ]);

        $this->assertSame(1, GlJournalEntry::where('event_type', 'GRN_COMPLETE')->count());
    }

    public function test_an_unfinished_mapping_stops_the_books_without_stopping_the_store(): void
    {
        // The first receipt seeds the placeholder mapping, which is what
        // the spec asks for while the chart of accounts is pending.
        $this->receipt();

        $mapping = GlEventMapping::where('event_type', 'GRN_COMPLETE')->firstOrFail();
        $mapping->forceFill(['active' => false])->save();

        $second = $this->receipt('500', '3');

        // Stock is on the shelf and the receipt is done...
        $this->assertSame('COMPLETED', $second->fresh()->status);
        // ...and the books simply have nothing to say about it.
        $this->assertSame(1, GlJournalEntry::where('event_type', 'GRN_COMPLETE')->count());

        // Switch it back on and the next one posts again — no migration,
        // no deployment, one row.
        $mapping->forceFill(['active' => true])->save();
        $third = $this->receipt('100', '4');

        $this->assertSame(2, GlJournalEntry::where('event_type', 'GRN_COMPLETE')->count());
        $this->assertSame('400.0000', (string) $this->entryFor($third->id)->amount);
    }

    public function test_the_mapping_is_data_a_finance_team_edits(): void
    {
        $sm = $this->tokenFor($this->storeManager);
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        // A store manager reads the books; pointing events at accounts is
        // somebody else's job.
        $screen = $this->withToken($sm)->getJson('/api/v1/gl-mappings')->assertOk()->json('data');

        $this->assertFalse($screen['can_manage']);
        $this->assertCount(count(GlEventMapping::EVENTS), $screen['mappings']);
        // Every event arrives already pointed at a placeholder.
        foreach ($screen['mappings'] as $row) {
            $this->assertTrue($row['postable'], "{$row['event_type']} is not postable out of the box.");
            $this->assertNotNull($row['debit_account']);
        }

        $this->withToken($sm)->patchJson('/api/v1/gl-mappings/GRN_COMPLETE', ['active' => false])
            ->assertForbidden();

        $ownerToken = $this->tokenFor($owner);
        $stock = collect($screen['accounts'])->firstWhere('code', 'SUS-STOCK');
        $wip = collect($screen['accounts'])->firstWhere('code', 'SUS-WIP');

        $this->withToken($ownerToken)->patchJson('/api/v1/gl-mappings/GRN_COMPLETE', [
            'debit_account_id' => $wip['id'],
            'notes' => 'Interim, until the real chart lands.',
        ])->assertOk()->assertJsonPath('data.postable', true);

        $this->assertSame($wip['id'], GlEventMapping::where('event_type', 'GRN_COMPLETE')->firstOrFail()->debit_account_id);
        $this->assertNotSame($wip['id'], $stock['id']);

        // An event nobody has heard of is not a mapping to invent.
        $this->withToken($ownerToken)->patchJson('/api/v1/gl-mappings/SOMETHING_ELSE', ['active' => true])
            ->assertNotFound();

        // The entry list reads back by the document number.
        $receipt = $this->receipt();

        $this->withToken($sm)->getJson('/api/v1/gl-entries?search='.$receipt->grn_no)
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event_type', 'GRN_COMPLETE')
            ->assertJsonPath('data.0.balanced', true);
    }

    private function entryFor(string $receiptId): GlJournalEntry
    {
        return GlJournalEntry::where('source_type', 'goods_receipt')
            ->where('source_id', $receiptId)
            ->with('lines')
            ->firstOrFail();
    }
}
