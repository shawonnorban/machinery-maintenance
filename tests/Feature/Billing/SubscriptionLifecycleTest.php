<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Modules\Asset\Models\Asset;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Billing\Services\SubscriptionLifecycle;
use App\Modules\Billing\Services\UsageMeter;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The subscription lifecycle (ADR-029, SRS 40, SRS 49.3).
 *
 * The shape is what matters: a customer who misses a payment loses the ability
 * to write, not their history, and paying gives it straight back. Every
 * assertion here is about not punishing a factory harder than the contract
 * says.
 */
class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private SubscriptionContract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        CarbonImmutable::setTestNow('2026-06-15 09:00:00');

        $this->contract = SubscriptionContract::create([
            'contract_number' => 'SUB-2026-0001',
            'status' => 'ACTIVE',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'billing_cycle' => 'MONTHLY',
            'amount' => '25000.0000',
            'currency' => 'BDT',
            'grace_period_days' => 14,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function invoice(string $dueDate, string $status = 'ISSUED'): SubscriptionInvoice
    {
        return SubscriptionInvoice::create([
            'subscription_contract_id' => $this->contract->id,
            'invoice_number' => 'INV-'.$dueDate,
            'issue_date' => $dueDate,
            'due_date' => $dueDate,
            'subtotal' => '25000.0000',
            'total' => '25000.0000',
            'balance_due' => '25000.0000',
            'currency' => 'BDT',
            'status' => $status,
        ]);
    }

    public function test_an_overdue_invoice_moves_a_contract_to_past_due(): void
    {
        $this->invoice('2026-06-10');

        $lifecycle = app(SubscriptionLifecycle::class);

        $this->assertSame('PAST_DUE', $lifecycle->advance($this->contract));
    }

    public function test_the_grace_period_is_measured_from_the_due_date(): void
    {
        // Twenty days late against a fourteen-day grace period.
        $this->invoice('2026-05-26');

        $lifecycle = app(SubscriptionLifecycle::class);

        $this->assertSame('READ_ONLY', $lifecycle->advance($this->contract->fresh()));
        $this->assertNotNull($this->contract->fresh()->read_only_at);
    }

    public function test_narrowing_walks_through_every_state_rather_than_jumping(): void
    {
        $this->invoice('2026-05-26');

        app(SubscriptionLifecycle::class)->advance($this->contract);

        $states = AuditLog::where('action', 'SUBSCRIPTION_CHANGED')
            ->orderBy('created_at')
            ->get()
            ->map(fn (AuditLog $log) => $log->new_values_json['to'])
            ->all();

        // A contract several steps behind because the scheduler was down must
        // not leave a trail claiming the customer went from ACTIVE to READ_ONLY
        // overnight.
        $this->assertSame(['PAST_DUE', 'GRACE', 'READ_ONLY'], $states);
    }

    public function test_paying_restores_the_subscription(): void
    {
        $invoice = $this->invoice('2026-05-26');

        $lifecycle = app(SubscriptionLifecycle::class);
        $lifecycle->advance($this->contract);

        $this->assertSame('READ_ONLY', $this->contract->fresh()->status);

        $invoice->forceFill(['status' => 'PAID', 'balance_due' => '0.0000'])->save();

        // A lifecycle that only ever narrows would make paying up pointless.
        $this->assertSame('ACTIVE', $lifecycle->advance($this->contract->fresh()));
        $this->assertNull($this->contract->fresh()->read_only_at);
    }

    public function test_nothing_is_deleted_when_a_subscription_lapses(): void
    {
        WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $this->invoice('2026-05-26');

        app(SubscriptionLifecycle::class)->advance($this->contract);

        // Automatic hard deletion on payment failure is prohibited (SRS 49.3).
        // Archiving is as far as any of this goes.
        $this->assertSame(1, Asset::count());
        $this->assertSame('READ_ONLY', $this->contract->fresh()->status);
    }

    public function test_a_cancelled_contract_is_not_dragged_back_by_the_calendar(): void
    {
        $lifecycle = app(SubscriptionLifecycle::class);

        $lifecycle->transition($this->contract, 'CANCELLED', 'Customer left');

        $this->invoice('2026-05-26');

        // The customer decided. An unpaid invoice does not overrule that.
        $this->assertSame('CANCELLED', $lifecycle->advance($this->contract->fresh()));
    }

    public function test_an_impossible_transition_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        app(SubscriptionLifecycle::class)->transition($this->contract, 'ARCHIVED');
        app(SubscriptionLifecycle::class)->transition($this->contract->fresh(), 'ACTIVE');
    }

    public function test_every_transition_is_audited(): void
    {
        app(SubscriptionLifecycle::class)->transition($this->contract, 'CANCELLED', 'Moved to a competitor');

        $log = AuditLog::where('action', 'SUBSCRIPTION_CHANGED')->firstOrFail();

        // A customer locked out of writes has to be able to be told exactly
        // when and why.
        $this->assertSame('ACTIVE', $log->new_values_json['from']);
        $this->assertSame('CANCELLED', $log->new_values_json['to']);
        $this->assertSame('Moved to a competitor', $log->new_values_json['reason']);
        $this->assertSame('SUB-2026-0001', $log->entity_label);
    }

    public function test_a_trial_becomes_active_when_it_ends(): void
    {
        $this->contract->forceFill([
            'status' => 'TRIAL',
            'trial_end' => '2026-06-01',
        ])->save();

        $this->assertSame('ACTIVE', app(SubscriptionLifecycle::class)->advance($this->contract->fresh()));
    }

    public function test_usage_is_measured_against_what_is_in_service(): void
    {
        WorkOrderFixture::runningAsset($this->delta, $this->dhaka, 'SEW-DHK-00412');
        $scrapped = WorkOrderFixture::runningAsset($this->delta, $this->dhaka, 'SEW-DHK-00413');

        $scrapped->forceFill(['status' => 'SCRAPPED'])->save();

        $this->contract->forceFill(['included_assets' => 5])->save();

        $usage = app(UsageMeter::class)->measure($this->delta->id)->keyBy('metric');

        // Charging for a machine that was sold two years ago is the fastest way
        // to lose both the argument and the customer.
        $this->assertSame(1.0, (float) $usage['ACTIVE_ASSETS']->value);
        $this->assertFalse($usage['ACTIVE_ASSETS']->exceeded);
        $this->assertSame(5.0, (float) $usage['ACTIVE_ASSETS']->limit_value);
    }

    public function test_a_contract_with_no_limit_is_never_over_it(): void
    {
        WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $usage = app(UsageMeter::class)->measure($this->delta->id)->keyBy('metric');

        // Null is not zero. Treating the absence of a limit as a limit of
        // nothing would mark every customer as over.
        $this->assertNull($usage['ACTIVE_ASSETS']->limit_value);
        $this->assertFalse($usage['ACTIVE_ASSETS']->exceeded);
    }

    public function test_only_a_blocking_policy_actually_blocks(): void
    {
        $this->contract->forceFill(['included_assets' => 2, 'overage_policy' => 'WARN_ONLY'])->save();

        $meter = app(UsageMeter::class);

        // A factory commissioning its 413th machine at 2am should not be
        // stopped by a billing rule nobody is awake to relax.
        $this->assertFalse($meter->wouldExceed($this->contract->fresh(), 'ACTIVE_ASSETS', 2));

        $this->contract->forceFill(['overage_policy' => 'BLOCK'])->save();

        $this->assertTrue($meter->wouldExceed($this->contract->fresh(), 'ACTIVE_ASSETS', 2));
    }
}
