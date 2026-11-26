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
