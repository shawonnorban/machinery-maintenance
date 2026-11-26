<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Merchandise Desk → Master Data, step C (AnnotechERP 06/01): process-loss
 * norms that never overlap, the inquiry rate chart and its revisions, trims
 * spec templates, and the T&A register, templates and task rights.
 */
class MerchandiseNormsTnaTest extends TestCase
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

    public function test_dyeing_loss_bands_meet_but_never_overlap(): void
    {
        $fibers = collect($this->as()->getJson('/api/v1/erp/fibers')->json('data'))->keyBy('code');
        $cotton = $this->as()->postJson('/api/v1/erp/fiber-compositions', ['code' => 'COT100', 'items' => [['fiber_id' => $fibers['COT']['id'], 'percent' => 100]]])->assertCreated()->json('data.id');
        $band = ['fiber_composition_id' => $cotton, 'color_group' => 'DARK', 'weight_low' => 1, 'weight_high' => 200, 'loss_percent' => 12];

        $this->as()->postJson('/api/v1/erp/dyeing-loss-bands', $band)->assertCreated()->assertJsonPath('data.composition', '100% Cotton');
        // 200–500 meets 1–200 at 200; 150–300 runs into it.
        $this->as()->postJson('/api/v1/erp/dyeing-loss-bands', ['weight_low' => 200, 'weight_high' => 500] + $band)->assertCreated();
        $this->as()->postJson('/api/v1/erp/dyeing-loss-bands', ['weight_low' => 150, 'weight_high' => 300] + $band)->assertUnprocessable()->assertJsonValidationErrors('weight_low');
        // Another colour group, or a switched-off band, is free to.
        $this->as()->postJson('/api/v1/erp/dyeing-loss-bands', ['color_group' => 'LIGHT', 'weight_low' => 150, 'weight_high' => 300] + $band)->assertCreated();
        $this->as()->postJson('/api/v1/erp/dyeing-loss-bands', ['active' => false, 'weight_low' => 150, 'weight_high' => 300] + $band)->assertCreated();
        $this->as()->postJson('/api/v1/erp/dyeing-loss-bands', ['weight_high' => 0] + $band)->assertUnprocessable();
        $this->as()->postJson('/api/v1/erp/dyeing-loss-bands', ['loss_percent' => 120] + $band)->assertUnprocessable();
    }

    public function test_process_loss_allowances_keep_to_their_own_stage(): void
    {
        $row = ['qty_low' => 1, 'qty_high' => 500, 'loss_qty' => 10, 'effective_from' => '2026-01-01'];

        $this->as()->postJson('/api/v1/erp/booking-loss-allowances', $row + ['stage' => 'CUTTING'])->assertCreated()->assertJsonPath('data.stage', 'BOOKING')->assertJsonPath('data.add_to_booking', true);
        // The cutting stage has its own bands; the booking stage has this one already.
        $this->as()->postJson('/api/v1/erp/cutting-loss-allowances', $row)->assertCreated()->assertJsonPath('data.stage', 'CUTTING');
        $this->as()->postJson('/api/v1/erp/booking-loss-allowances', ['qty_low' => 400, 'qty_high' => 900] + $row)->assertUnprocessable();
        // A band that ended before this one starts, or a different fabric group, does not clash.
        $this->as()->postJson('/api/v1/erp/booking-loss-allowances', ['effective_from' => '2025-01-01', 'effective_to' => '2025-12-31'] + $row)->assertCreated();
        $this->as()->postJson('/api/v1/erp/booking-loss-allowances', ['fabric_group' => 'RIB'] + $row)->assertCreated();

        $this->as()->getJson('/api/v1/erp/booking-loss-allowances')->assertJsonCount(3, 'data');
        $this->as()->getJson('/api/v1/erp/cutting-loss-allowances')->assertJsonCount(1, 'data');
    }

    public function test_the_rate_chart_keeps_every_revision(): void
    {
        $lines = collect($this->as()->getJson('/api/v1/erp/rate-lines')->assertOk()->assertJsonCount(28, 'data')->json('data'));
        $combed = $lines->firstWhere('description', '100% Cotton Any Count (Combed)')['id'];
        $sj = $lines->firstWhere('description', 'S/Jersey')['id'];
        $lpp = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP S.A.', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $tex = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'TEX EBO', 'office_type' => 'BUYING_HOUSE'])->json('data.id');

        $this->as()->putJson("/api/v1/merchandise/rate-chart/{$lpp}", ['rates' => [
            ['rate_line_id' => $combed, 'rate' => 3.2, 'currency' => 'usd', 'effective_from' => '2026-01-01'],
            ['rate_line_id' => $sj, 'rate' => 0.45, 'currency' => 'USD', 'effective_from' => '2026-01-01'],
        ]])->assertOk();
        // The same rate again is no revision; a new date is.
        $this->as()->putJson("/api/v1/merchandise/rate-chart/{$lpp}", ['rates' => [
            ['rate_line_id' => $combed, 'rate' => 3.2, 'currency' => 'USD', 'effective_from' => '2026-01-01'],
            ['rate_line_id' => $sj, 'rate' => 0.5, 'currency' => 'USD', 'effective_from' => '2026-07-01'],
        ]])->assertOk();
        $this->as()->putJson("/api/v1/merchandise/rate-chart/{$tex}", ['rates' => [['rate_line_id' => $combed, 'rate' => 3.0, 'currency' => 'USD', 'effective_from' => '2026-02-01']]])->assertOk();

        $chart = collect($this->as()->getJson("/api/v1/merchandise/rate-chart/{$lpp}")->assertOk()->json('data'))->keyBy('rate_line_id');
        $this->assertSame(0.5, $chart[$sj]['rate']);
        $this->assertSame('2026-07-01', $chart[$sj]['effective_from']);
        $this->assertSame('USD', $chart[$combed]['currency']);
        $this->as()->getJson("/api/v1/merchandise/rate-chart/{$lpp}/history")->assertJsonCount(3, 'data')->assertJsonPath('data.0.rate', 0.5);

        $line = collect($this->as()->getJson('/api/v1/erp/rate-lines')->json('data'))->firstWhere('id', $combed);
        $this->assertSame(2, $line['offices_quoted']);
        $this->assertEquals(3.1, $line['avg_rate']);
        $this->assertSame([2, 1], collect($this->as()->getJson('/api/v1/merchandise/rate-chart')->json('data'))->pluck('count')->all());

        $this->as()->putJson("/api/v1/merchandise/rate-chart/{$lpp}", ['rates' => [['rate_line_id' => $combed, 'rate' => -1, 'currency' => 'USD', 'effective_from' => '2026-01-01']]])->assertUnprocessable();
        $this->as()->postJson('/api/v1/erp/rate-lines', ['rate_group' => 'YARN', 'description' => 'Lycra Yarn'])->assertUnprocessable();
        $this->as()->postJson('/api/v1/erp/rate-lines', ['rate_group' => 'KNITTING', 'description' => 'Lycra Yarn', 'uom' => 'Kg'])->assertCreated();
    }

    public function test_a_spec_template_is_named_first_and_given_its_lines_after(): void
    {
        $id = $this->as()->postJson('/api/v1/erp/trim-spec-templates', ['code' => 'spec-lbl', 'name' => 'STOCKMANN - Care Label'])->assertCreated()->assertJsonPath('data.code', 'SPEC-LBL')->assertJsonPath('data.control_count', 0)->json('data.id');

        $lines = [
            'item_rules' => [['item_category' => 'Label', 'item_name' => 'Care Label']],
            'controls' => [['name' => 'Fibre content', 'control_type' => 'TEXT', 'required' => true], ['name' => 'Wash symbol', 'control_type' => 'SELECT', 'options' => '30°, 40°']],
        ];
        $this->as()->patchJson("/api/v1/erp/trim-spec-templates/{$id}", $lines)->assertOk()->assertJsonPath('data.control_count', 2)->assertJsonPath('data.controls.0.required', true);
        // Renaming leaves the lines alone; a Select without options is refused.
        $this->as()->patchJson("/api/v1/erp/trim-spec-templates/{$id}", ['name' => 'Care Label'])->assertOk()->assertJsonPath('data.rule_count', 1)->assertJsonPath('data.control_count', 2);
        $this->as()->patchJson("/api/v1/erp/trim-spec-templates/{$id}", ['controls' => [['name' => 'Wash', 'control_type' => 'SELECT']]])->assertUnprocessable()->assertJsonValidationErrors('controls.0.options');
        $this->as()->patchJson("/api/v1/erp/trim-spec-templates/{$id}", ['controls' => [['name' => 'A', 'control_type' => 'TEXT'], ['name' => 'A', 'control_type' => 'TEXT']]])->assertUnprocessable();
        $this->as()->postJson('/api/v1/erp/trim-spec-templates', ['code' => 'SPEC-LBL', 'name' => 'Again'])->assertUnprocessable();
    }

    public function test_tna_templates_carry_a_calendar_and_copy_with_it(): void
    {
        $tasks = collect($this->as()->getJson('/api/v1/erp/tna-tasks')->assertOk()->assertJsonCount(25, 'data')->json('data'))->keyBy('code');
        $this->as()->getJson('/api/v1/erp/tna-task-groups')->assertJsonCount(4, 'data');
        // Brickland's dialog names a group only; its code comes from the name.
        $this->as()->postJson('/api/v1/erp/tna-task-groups', ['name' => 'Washing'])->assertCreated()->assertJsonPath('data.code', 'WASHING');
        $this->as()->postJson('/api/v1/erp/tna-task-groups', ['name' => 'washing!'])->assertCreated()->assertJsonPath('data.code', 'WASHING2');

        $template = ['code' => 'tngen90', 'approved_by' => $this->owner->id, 'order_type' => 'NEW', 'gmt_type' => 'BASIC', 'lead_from' => 90, 'lead_to' => 119, 'std_days' => 90, 'before_swing' => 5, 'after_swing' => 5];
        $this->as()->postJson('/api/v1/erp/tna-templates', ['scope' => 'BUYER'] + $template)->assertUnprocessable()->assertJsonValidationErrors('buyer_id');
        $this->as()->postJson('/api/v1/erp/tna-templates', ['lead_to' => 60] + $template)->assertUnprocessable();
        $id = $this->as()->postJson('/api/v1/erp/tna-templates', $template)->assertCreated()->assertJsonPath('data.code', 'TNGEN90')->json('data.id');

        $calendar = [
            ['tna_task_id' => $tasks['TSK-001']['id'], 'offset_days' => -90, 'duration_days' => 1, 'sort_order' => 10],
            ['tna_task_id' => $tasks['TSK-019']['id'], 'offset_days' => -25, 'duration_days' => 5, 'sort_order' => 20],
            ['tna_task_id' => $tasks['TSK-025']['id'], 'offset_days' => 0, 'duration_days' => 1, 'sort_order' => 30],
        ];
        $this->as()->putJson("/api/v1/merchandise/tna-templates/{$id}/tasks", ['tasks' => $calendar])->assertOk()->assertJsonCount(3, 'data.tasks')->assertJsonPath('data.tasks.1.name', 'Cutting Start');
        // No milestone before the longest lead time; the list replaces what was there.
        $this->as()->putJson("/api/v1/merchandise/tna-templates/{$id}/tasks", ['tasks' => [['offset_days' => -150] + $calendar[0]]])->assertUnprocessable();
        $this->as()->putJson("/api/v1/merchandise/tna-templates/{$id}/tasks", ['tasks' => array_slice($calendar, 1)])->assertOk()->assertJsonCount(2, 'data.tasks');

        $copy = $this->as()->postJson("/api/v1/merchandise/tna-templates/{$id}/copy")->assertCreated()->assertJsonPath('data.code', 'TNGEN90-C1')->json('data.id');
        $this->as()->getJson("/api/v1/merchandise/tna-templates/{$copy}/tasks")->assertJsonCount(2, 'data.tasks');
        $this->as()->postJson("/api/v1/merchandise/tna-templates/{$id}/copy", ['code' => 'TNGEN90'])->assertUnprocessable();
        $this->assertSame(2, collect($this->as()->getJson('/api/v1/erp/tna-templates')->json('data'))->firstWhere('id', $copy)['task_count']);

        // A task a calendar uses stays; one no calendar uses may go.
        $this->as()->deleteJson("/api/v1/erp/tna-tasks/{$tasks['TSK-019']['id']}")->assertUnprocessable();
        $this->as()->deleteJson("/api/v1/erp/tna-tasks/{$tasks['TSK-002']['id']}")->assertNoContent();
    }

    public function test_tna_task_rights_build_on_seeing_the_task(): void
    {
        $merchant = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'merchant@delta.test');
        $rows = collect($this->as()->getJson("/api/v1/merchandise/tna-rights/{$merchant->id}")->assertOk()->assertJsonCount(25, 'data')->json('data'));
        $this->assertFalse($rows->contains('granted', true));

        $first = $rows[0]['tna_task_id'];
        $second = $rows[1]['tna_task_id'];
        $this->as()->putJson("/api/v1/merchandise/tna-rights/{$merchant->id}", ['rights' => [['tna_task_id' => $first, 'can_create' => true, 'can_update' => true], ['tna_task_id' => $second]]])->assertOk();
        $this->as()->getJson('/api/v1/merchandise/tna-rights')->assertJsonCount(2, 'data')->assertJsonPath('data.0.user', $merchant->name);
        $this->assertEqualsCanonicalizing(['CRU·', '·R··'], collect($this->as()->getJson('/api/v1/merchandise/tna-rights')->json('data'))->pluck('crud')->all());

        // Saved as a whole: what is left out is taken away.
        $this->as()->putJson("/api/v1/merchandise/tna-rights/{$merchant->id}", ['rights' => [['tna_task_id' => $second, 'can_delete' => true]]])->assertOk();
        $this->as()->getJson('/api/v1/merchandise/tna-rights')->assertJsonCount(1, 'data')->assertJsonPath('data.0.crud', '·R·D');
        $this->assertContains($merchant->id, collect($this->as()->getJson('/api/v1/merchandise/tna-rights/users')->json('data'))->pluck('value')->all());

        // Not someone of another company; and a manager who may only view cannot grant.
        $other = TenantFixture::company('Other Ltd', 'OTH');
        $stranger = TenantFixture::user($other, 'COMPANY_OWNER', 'stranger@other.test');
        TenantFixture::actingAsTenant($this->delta);
        $this->as()->putJson("/api/v1/merchandise/tna-rights/{$stranger->id}", ['rights' => []])->assertNotFound();
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($merchant, $this->delta->id, 'Test token');
        $this->withToken($plain)->getJson('/api/v1/merchandise/tna-rights')->assertOk();
        $this->withToken($plain)->putJson("/api/v1/merchandise/tna-rights/{$merchant->id}", ['rights' => []])->assertForbidden();
    }

    private function as(): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
