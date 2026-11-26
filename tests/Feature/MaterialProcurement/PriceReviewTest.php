<?php

declare(strict_types=1);

namespace Tests\Feature\MaterialProcurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\Quotation;
use App\Modules\Notification\Models\Notification;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Material Procurement → Price Review & Approval (AnnotechERP 08/05): every
 * supplier's latest price per line; a quotation sent back for negotiation
 * (the supplier answers with a new revision), rejected with a reason, or
 * approved and handed to a follow-up merchandiser — the line's other open
 * quotations closed; a line rejected outright. A requisition whose every
 * line is decided becomes Approved, or Rejected if none was approved.
 */
class PriceReviewTest extends TestCase
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

    public function test_a_quotation_is_negotiated_revised_and_approved_and_the_requisition_follows(): void
    {
        $poly = $this->supplierLogin('SUP-002', 'Poly Plan Ltd', 'nasir@polyplan.test');
        $ykk = $this->supplierLogin('SUP-003', 'YKK Bangladesh', 'shafiq@ykk.test');
        [$pr, $lineId] = $this->submitted(lines: 1);
        $price = ['quoted_qty' => 120, 'unit_price' => 2.85, 'currency' => 'USD', 'lead_time_days' => 14, 'expected_delivery' => '2026-11-15'];
        $quote = fn (User $who, array $over = []) => $this->as($who)->postJson("/api/v1/material-procurement/portal/requirements/{$lineId}/quote", $over + $price)->assertCreated()->json('data.qt_no');
        $polyQt = $quote($poly);
        $ykkQt = $quote($ykk, ['unit_price' => 3.10]);

        $review = $this->as()->getJson('/api/v1/material-procurement/price-review')->assertOk()->json('data');
        $this->assertSame(['pending' => 2, 'negotiation' => 0, 'approved' => 0, 'rejected' => 0], $review['counts']);
        $line = collect($review['lines'])->firstWhere('line_id', $lineId);
        $this->assertSame([$pr, 'OPEN_FOR_QUOTATION', true, [$polyQt, $ykkQt]], [$line['pr_no'], $line['status'], $line['open'], array_column($line['quotations'], 'qt_no')]);
        $this->assertEquals([2.85, 'USD', 14, true, true], [$line['quotations'][0]['unit_price'], $line['quotations'][0]['currency'], $line['quotations'][0]['lead_time_days'], $line['quotations'][0]['can_approve'], $line['quotations'][0]['can_negotiate']]);
        $this->assertContains($this->owner->name, array_column($review['merchandisers'], 'label'));
        // A supplier's portal login is nobody's follow-up merchandiser.
        $this->assertNotContains('Poly Plan Ltd Desk', array_column($review['merchandisers'], 'label'));
        $polyId = Quotation::query()->where('qt_no', $polyQt)->value('id');
        $ykkId = Quotation::query()->where('qt_no', $ykkQt)->value('id');
        $url = '/api/v1/material-procurement/price-review';

        // Negotiate: a message is required; the supplier hears it and answers with a new revision.
        $this->as()->postJson("{$url}/quotations/{$polyId}/negotiate", [])->assertUnprocessable()->assertJsonPath('errors.message.0', 'Message to supplier is required.');
        $this->as()->postJson("{$url}/quotations/{$polyId}/negotiate", ['message' => 'Please revise your quotation and submit your best possible price.'])->assertOk()->assertJsonPath('data.status', 'NEGOTIATION');
        $this->as()->postJson("{$url}/quotations/{$polyId}/negotiate", ['message' => 'Again'])->assertUnprocessable()->assertJsonPath('errors.quotation.0', 'This quotation is Negotiation Requested; it cannot be decided this way.');
        $note = Notification::query()->withoutGlobalScopes()->where('user_id', $poly->id)->where('event_type', 'MP_QUOTATION_NEGOTIATION')->sole();
        $this->assertSame("{$polyQt} for Cotton Jersey ({$pr}): Please revise your quotation and submit your best possible price.", $note->body);
        $this->assertSame(1, $this->as()->getJson($url)->json('data.counts.negotiation'));
        $history = $this->as($poly)->getJson('/api/v1/material-procurement/portal/quotations')->json('data.quotations.0');
        $this->assertSame(['NEGOTIATION', 'Please revise your quotation and submit your best possible price.'], [$history['status'], $history['negotiation_message']]);
        $quote($poly, ['unit_price' => 2.65]);
        $this->assertSame('REVISED', Quotation::query()->whereKey($polyId)->value('status'));

        // Approve & Handover: a merchandiser is required; the other open quotation is closed.
        $this->as()->postJson("{$url}/quotations/{$polyId}/approve", [])->assertUnprocessable()->assertJsonPath('errors.handover_to.0', 'Select the follow-up merchandiser.');
        $this->as()->postJson("{$url}/quotations/{$polyId}/approve", ['handover_to' => $poly->id])->assertUnprocessable()->assertJsonPath('errors.handover_to.0', 'Select the follow-up merchandiser.');
        $merch = TenantFixture::user($this->delta, 'STORE_MANAGER', 'merch@delta.test');
        $this->as()->postJson("{$url}/quotations/{$polyId}/approve", ['handover_to' => $merch->id])->assertOk()->assertJsonPath('data.handover_to', $merch->name);
        $this->assertSame(['APPROVED', 'CLOSED'], [Quotation::query()->whereKey($polyId)->value('status'), Quotation::query()->whereKey($ykkId)->value('status')]);
        $after = $this->as()->getJson($url)->json('data');
        $this->assertSame(['pending' => 0, 'negotiation' => 0, 'approved' => 1, 'rejected' => 0], $after['counts']);
        $decided = collect($after['lines'])->firstWhere('line_id', $lineId);
        $this->assertSame(['APPROVED', false, $merch->name, false], [$decided['status'], $decided['open'], $decided['followup'], $decided['quotations'][0]['can_approve']]);
        // The line and the requisition follow; the supplier and the merchandiser hear of it.
        $mine = $this->as()->getJson("/api/v1/material-procurement/requisitions/{$pr}")->json('data');
        $this->assertSame(['APPROVED', 'Poly Plan Ltd', 'USD 2.65', $merch->name, 'APPROVED'], [$mine['status'], $mine['lines'][0]['approved_supplier'], $mine['lines'][0]['approved_price'], $mine['lines'][0]['followup'], $mine['lines'][0]['status']]);
        $this->assertSame("{$polyQt} for Cotton Jersey ({$pr}) is approved at USD 2.65.", Notification::query()->withoutGlobalScopes()->where('user_id', $poly->id)->where('event_type', 'MP_QUOTATION_APPROVED')->value('body'));
        $this->assertSame("Cotton Jersey from Poly Plan Ltd ({$pr}, {$polyQt}) is yours to follow up.", Notification::query()->withoutGlobalScopes()->where('user_id', $merch->id)->where('event_type', 'MP_MATERIAL_ASSIGNED')->value('body'));
        // A decided line is decided.
        $this->as()->postJson("{$url}/quotations/{$ykkId}/reject", ['reason' => 'x'])->assertUnprocessable()->assertJsonPath('errors.line.0', 'This line is already decided.');
        $this->as()->postJson("{$url}/lines/{$lineId}/reject", ['reason' => 'x'])->assertUnprocessable()->assertJsonPath('errors.line.0', 'This line is already decided.');
        // The line is no longer open to quote; the approved quotation is revised no more.
        $this->as($poly)->postJson("/api/v1/material-procurement/portal/requirements/{$lineId}/quote", $price)->assertUnprocessable()->assertJsonPath('errors.line.0', 'This requirement is no longer open for quotation.');

        $this->as(TenantFixture::user($this->delta, 'STOREKEEPER', 'store@delta.test'))->getJson($url)->assertForbidden();
        $this->as($poly)->getJson($url)->assertForbidden();
    }

    public function test_rejections_and_a_requisition_with_no_approved_line_is_rejected(): void
    {
        $poly = $this->supplierLogin('SUP-002', 'Poly Plan Ltd', 'nasir@polyplan.test');
        [$pr, $first, $second] = $this->submitted(lines: 2);
        $price = ['quoted_qty' => 10, 'unit_price' => 5, 'currency' => 'BDT', 'lead_time_days' => 3, 'expected_delivery' => '2026-11-10'];
        $qt = $this->as($poly)->postJson("/api/v1/material-procurement/portal/requirements/{$first}/quote", $price)->assertCreated()->json('data.qt_no');
        $qtId = Quotation::query()->where('qt_no', $qt)->value('id');
        $url = '/api/v1/material-procurement/price-review';

        $this->as()->postJson("{$url}/quotations/{$qtId}/reject", [])->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Reason is required.');
        $this->as()->postJson("{$url}/quotations/{$qtId}/reject", ['reason' => 'Price too high'])->assertOk()->assertJsonPath('data.status', 'REJECTED');
        $this->assertSame("{$qt} for Cotton Jersey ({$pr}) was rejected: Price too high", Notification::query()->withoutGlobalScopes()->where('user_id', $poly->id)->where('event_type', 'MP_QUOTATION_REJECTED')->value('body'));
        // Still open: the supplier may quote again — no, a rejected quotation is revised no more.
        $this->as($poly)->postJson("/api/v1/material-procurement/portal/requirements/{$first}/quote", $price)->assertUnprocessable()->assertJsonPath('errors.line.0', 'This quotation is Rejected and can no longer be revised.');
        $this->assertSame('SUBMITTED', $this->as()->getJson("/api/v1/material-procurement/requisitions/{$pr}")->json('data.status'));

        // Reject line, both lines: the requisition has no approved line and is Rejected.
        $this->as()->postJson("{$url}/lines/{$first}/reject", ['reason' => 'Not needed now'])->assertOk()->assertJsonPath('data.quotations', 0);
        $this->assertSame('SUBMITTED', $this->as()->getJson("/api/v1/material-procurement/requisitions/{$pr}")->json('data.status'));
        $this->as()->postJson("{$url}/lines/{$second}/reject", ['reason' => 'Not needed now'])->assertOk();
        $this->assertSame('REJECTED', $this->as()->getJson("/api/v1/material-procurement/requisitions/{$pr}")->json('data.status'));
        $review = $this->as()->getJson($url)->json('data');
        $this->assertSame(2, $review['counts']['rejected']);
        $this->assertSame('Not needed now', collect($review['lines'])->firstWhere('line_id', $second)['reject_reason']);
    }

    /** A submitted direct requisition of n fabric lines: its number and line ids. @return list<string> */
    private function submitted(int $lines): array
    {
        $item = $this->as()->postJson('/api/v1/erp/material-fabrics', ['item_code' => 'FAB-001', 'name' => 'Cotton Jersey', 'category' => 'Knit', 'uom' => 'Kg'])->assertCreated()->json('data.id');
        $row = ['kind' => 'FABRIC', 'item_id' => $item, 'req_qty' => 120, 'uom' => 'KG', 'req_date' => '2026-11-20'];
        $data = $this->as()->postJson('/api/v1/material-procurement/requisitions', ['req_type' => 'DIRECT', 'submit' => true, 'lines' => array_fill(0, $lines, $row)])->assertCreated()->json('data');

        return [$data['pr_no'], ...array_column($data['lines'], 'id')];
    }

    private function supplierLogin(string $code, string $name, string $email): User
    {
        $supplier = $this->as()->postJson('/api/v1/erp/material-suppliers', ['code' => $code, 'name' => $name, 'supplier_type' => 'LOCAL_NON_EPZ', 'goods_service' => 'Fabric'])->assertCreated()->json('data.id');
        $this->as()->postJson('/api/v1/material-procurement/supplier-logins', ['supplier_id' => $supplier, 'contact_name' => $name.' Desk', 'email' => $email, 'password' => 'Quote-Ready-2026!'])->assertCreated();

        return User::query()->where('email', $email)->firstOrFail();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
