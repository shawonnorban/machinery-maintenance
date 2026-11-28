<?php

declare(strict_types=1);

namespace Tests\Feature\PreBudget;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Approval\Models\ApprovalRuleUser;
use App\Modules\Identity\Models\User;
use App\Modules\PreBudget\Support\BudgetApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Pre-Budget step 6 (AnnotechERP 33/34): Finalize once the checklist is
 * complete; the Approval Center chain Merchandising Head → CFO → MD (made
 * for the company, each step needing someone to sign); waiting and approved
 * budgets locked with their requisitions; a rejection sends the budget back;
 * withdrawing makes it a draft; Revise copies an approved budget as -R1.
 */
class BudgetApprovalTest extends TestCase
{
    use PreBudgetFixture;
    use RefreshDatabase;

    /** @var array<string, User> */
    private array $signers = [];

    /** @return array{0: string, 1: string} a budget of one knit requisition, its checklist complete, and the requisition's id */
    private function readyBudget(): array
    {
        $id = $this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['values' => $this->knit()]]])->assertCreated()->json('data.rows.0.id');
        $no = $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => [$id]])->assertCreated()->json('data.budget_no');
        $this->as()->putJson("/api/v1/pre-budget/budgets/{$no}/costing", [
            'dollar_rate' => 122, 'complete' => true,
            'fabrics' => [['fabrication' => 'Single Jersey 160 GSM', 'knit_rate' => 25, 'dye_rate' => 150]],
            'styles' => [['style' => '2261582', 'cm_per_dz' => 7]],
        ])->assertOk();
        $yarn = $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}")->json('data.sections.YARN.lines.0.key');
        $this->as()->putJson("/api/v1/pre-budget/budgets/{$no}/stock", ['complete' => true, 'lines' => [['section' => 'YARN', 'key' => $yarn, 'no_stock' => true, 'lots' => []]]])->assertOk();
        DB::table('pb_accessory_items')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $this->delta->id, 'code' => 'ACC-1', 'name' => 'Care Label', 'uom' => 'PCS', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/boms", ['final' => true, 'styles' => [['style' => '2261582', 'lines' => [
            ['item' => 'Care Label', 'supplier' => 'Huatai Textile', 'nominated' => 'Nominated', 'rate' => 0.03, 'unit' => 'PCS', 'body_cons' => 1, 'extra_percent' => 2],
        ]]]])->assertCreated();

        return [$no, $id];
    }

    /** The chain's three steps, each given its person (Approval Workflow → Action Assignment). */
    private function assignSigners(): void
    {
        $w = BudgetApproval::workflow($this->delta->id);
        foreach (BudgetApproval::steps($w) as $i => $rule) {
            $u = TenantFixture::user($this->delta, 'FACTORY_MANAGER', "signer{$i}@delta.test");
            ApprovalRuleUser::query()->create(['company_id' => $this->delta->id, 'rule_id' => $rule->id, 'user_id' => $u->id]);
            $this->signers[$rule->name] = $u;
        }
    }

    private function asUser(User $u): static
    {
        $this->flushHeaders();

        return $this->withToken(app(IssueApiToken::class)->forUser($u, $this->delta->id, 'Test token')['plain']);
    }

    public function test_finalize_goes_merchandising_head_cfo_md_and_locks_the_budget(): void
    {
        $id = $this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['values' => $this->knit()]]])->json('data.rows.0.id');
        $draft = $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => [$id]])->json('data.budget_no');
        // The checklist first.
        $this->as()->postJson("/api/v1/pre-budget/budgets/{$draft}/finalize")->assertUnprocessable()->assertJsonValidationErrors('budget');

        [$no, $req] = $this->readyBudget();
        // The chain is made (Merchandising Head → CFO → MD), but nobody signs its steps yet.
        $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/finalize")->assertUnprocessable()->assertJsonValidationErrors('budget');
        $this->assertSame(BudgetApproval::STEPS, BudgetApproval::steps(BudgetApproval::workflow($this->delta->id))->pluck('name')->all());
        $this->assignSigners();

        $b = $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/finalize")->assertOk()->json('data');
        $this->assertSame('PENDING', $b['status']);
        $this->assertFalse($b['editable']);
        $this->assertSame('Merchandising Head', $b['approval']['current']);
        $this->assertSame(['CURRENT', 'WAITING', 'WAITING'], array_column($b['approval']['levels'], 'state'));

        // Waiting: the budget, its BOM and its requisition are locked.
        $this->as()->patchJson("/api/v1/pre-budget/budgets/{$no}", ['version' => $b['version'], 'contract_no' => 'X'])->assertUnprocessable();
        $this->as()->patchJson('/api/v1/pre-budget/requisitions', ['rows' => [['id' => $req, 'version' => 1, 'values' => ['order_color' => 'Red']]]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.0.id');

        // In the Approval Center inbox of the first signer only; the sender cannot sign it.
        $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/decision", ['action' => 'approve'])->assertForbidden();
        $this->asUser($this->signers['CFO'])->postJson("/api/v1/pre-budget/budgets/{$no}/decision", ['action' => 'approve'])->assertForbidden();
        foreach (BudgetApproval::STEPS as $step) {
            $b = $this->asUser($this->signers[$step])->postJson("/api/v1/pre-budget/budgets/{$no}/decision", ['action' => 'approve', 'comment' => "OK {$step}"])->assertOk()->json('data');
        }
        $this->assertSame('APPROVED', $b['status']);
        $this->assertSame(['APPROVED', 'APPROVED', 'APPROVED'], array_column($b['approval']['levels'], 'state'));
        $this->assertSame($this->signers['MD']->name, $b['approval']['approved_by']);

        $list = collect($this->as()->getJson('/api/v1/pre-budget/budgets/finalized')->assertOk()->json('data'))->firstWhere('budget_no', $no);
        $this->assertSame('APPROVED', $list['status']);

        // Revise: -R1 a draft with the same requisitions, costing, stock and BOM; the approved one kept as REVISED.
        $r1 = $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/revise")->assertCreated()->json('data.budget_no');
        $this->assertSame("{$no}-R1", $r1);
        $rev = $this->as()->getJson("/api/v1/pre-budget/budgets/{$r1}")->assertOk()->json('data');
        $this->assertSame('DRAFT', $rev['status']);
        $this->assertTrue($rev['check']['costing'] && $rev['check']['stock'] && $rev['check']['bom']);
        $this->assertEquals($b['costs']['accessories'], $rev['costs']['accessories']);
        $this->assertSame('REVISED', $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}")->json('data.status'));
        // The requisition opens again with the revision.
        $this->as()->patchJson('/api/v1/pre-budget/requisitions', ['rows' => [['id' => $req, 'version' => 1, 'values' => ['order_color' => 'Red']]]])->assertOk();
    }

    public function test_the_phones_budget_list_shows_every_budget_as_a_card_with_its_counts_and_filters(): void
    {
        $id = $this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['values' => $this->knit()]]])->json('data.rows.0.id');
        $draft = $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => [$id]])->json('data.budget_no');
        [$no] = $this->readyBudget();
        $this->assignSigners();
        $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/finalize")->assertOk();

        $cards = $this->as()->getJson('/api/v1/pre-budget/budgets/cards')->assertOk()->json('data');
        // Every state counted, from the database — none made up.
        $this->assertSame(['ALL' => 2, 'MINE' => 0, 'DRAFT' => 1, 'PENDING' => 1, 'APPROVED' => 0, 'RETURNED' => 0, 'REVISED' => 0, 'CANCELLED' => 0], $cards['counts']);
        $this->assertSame(['factories' => ['NCL'], 'departments' => ['Mens'], 'brands' => ['Clockhouse'], 'buyers' => ['C&A']], array_intersect_key($cards['options'], array_flip(['factories', 'departments', 'brands', 'buyers'])));
        $this->assertSame(['current' => 1, 'last' => 1, 'per_page' => 20, 'total' => 2], $cards['page']);
        $sent = collect($cards['rows'])->firstWhere('budget_no', $no);
        $this->assertSame(['C&A', ['Clockhouse'], 1, 1, $this->owner->name, 'Merchandising Head'], [$sent['buyer'], $sent['brands'], $sent['styles'], $sent['items'], $sent['created_by'], $sent['waiting_at']['current']]);
        // The budget's own figures (the card's foot): the order, Net FOB, cost and the margin between them; the date of its state.
        $this->assertSame(2000, (int) $sent['order_qty']);
        $this->assertEqualsWithDelta($sent['net_fob'] - $sent['total_cost'], $sent['margin'], 0.01);
        $this->assertSame('submitted', $sent['event']);
        // What may be done here follows the budget's own rules: a sent one withdrawn, not finalized; a draft finalized once its checklist is done, or deleted.
        $this->assertSame(['finalize' => false, 'withdraw' => true, 'revise' => false, 'delete' => false], $sent['actions']);
        $this->assertNull($sent['check']);
        $open = collect($cards['rows'])->firstWhere('budget_no', $draft);
        $this->assertSame([true, ['costing' => false, 'stock' => false, 'bom' => false]], [$open['actions']['finalize'], $open['check']]);

        // A state, a search (brand, style, job no …), a fiscal year (July–June), a factory, department, buyer and brand — all together; the counts follow every filter but the state.
        $nos = fn (string $query) => array_column($this->as()->getJson("/api/v1/pre-budget/budgets/cards?{$query}")->json('data.rows'), 'budget_no');
        $this->assertSame([$draft], $nos('status=DRAFT'));
        $this->assertSame([$no], $nos('status=PENDING&q='.urlencode('clockhouse')));
        $this->assertCount(2, $nos('q=2261582'));
        $this->assertSame([$no], $nos('q='.urlencode($no)));
        $start = now('Asia/Dhaka')->month >= 7 ? now('Asia/Dhaka')->year : now('Asia/Dhaka')->year - 1;
        $this->assertCount(2, $nos('fy='.sprintf('%d-%02d', $start, ($start + 1) % 100).'&dept=Mens&factory=NCL&buyer='.urlencode('C&A').'&brand=Clockhouse'));
        $this->assertSame([], $nos('dept=Ladies'));
        $this->assertSame([], $nos('fy='.sprintf('%d-%02d', $start - 1, $start % 100)));
        $this->assertSame(0, $this->as()->getJson('/api/v1/pre-budget/budgets/cards?brand=Nobody')->json('data.counts.ALL'));
        $this->as()->getJson('/api/v1/pre-budget/budgets/cards?fy=2026')->assertUnprocessable();
        // A page at a time, the size one of 10, 20, 50.
        $this->assertSame(['current' => 1, 'last' => 1, 'per_page' => 10, 'total' => 2], $this->as()->getJson('/api/v1/pre-budget/budgets/cards?per_page=10')->json('data.page'));
        $this->assertSame([], $this->as()->getJson('/api/v1/pre-budget/budgets/cards?per_page=10&page=2')->json('data.rows'));
        $this->as()->getJson('/api/v1/pre-budget/budgets/cards?per_page=500')->assertUnprocessable();

        // The first signer sees it waiting at their step (MINE), and only the sent one; the detail carries each requisition's brand.
        $signer = $this->asUser($this->signers['Merchandising Head'])->getJson('/api/v1/pre-budget/budgets/cards?status=MINE')->json('data');
        $this->assertSame([$no], array_column($signer['rows'], 'budget_no'));
        $this->assertTrue($signer['rows'][0]['can_act']);
        $this->assertSame('Clockhouse', $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}")->json('data.requisitions.0.brand'));
    }

    public function test_a_rejection_sends_the_budget_back_and_withdrawing_makes_it_a_draft(): void
    {
        [$no] = $this->readyBudget();
        $this->assignSigners();
        $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/finalize")->assertOk();
        $this->asUser($this->signers['Merchandising Head'])->postJson("/api/v1/pre-budget/budgets/{$no}/decision", ['action' => 'approve'])->assertOk();
        // A rejection needs a reason; with one, the budget is back with the merchandiser.
        $this->asUser($this->signers['CFO'])->postJson("/api/v1/pre-budget/budgets/{$no}/decision", ['action' => 'reject'])->assertUnprocessable();
        $b = $this->asUser($this->signers['CFO'])->postJson("/api/v1/pre-budget/budgets/{$no}/decision", ['action' => 'reject', 'comment' => 'CM too low'])->assertOk()->json('data');
        $this->assertSame('RETURNED', $b['status']);
        $this->assertSame('CM too low', collect($b['approval']['history'])->firstWhere('action', 'REJECTED')['comment']);

        // Sent again, then taken back by the sender: a draft.
        $b = $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/finalize")->assertOk()->json('data');
        $this->assertSame('PENDING', $b['status']);
        $this->assertTrue($b['can_withdraw']);
        $b = $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/withdraw")->assertOk()->json('data');
        $this->assertSame('DRAFT', $b['status']);
        $this->assertTrue($b['editable']);
    }
}
