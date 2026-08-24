<?php

declare(strict_types=1);

namespace App\Modules\Settings\Database\Seeders;

use App\Modules\Settings\Models\SettingDefinition;
use Illuminate\Database\Seeder;

/**
 * The settable key catalog: SRS 53.2.
 *
 * Several of these change how money and KPIs are computed, which is why the
 * catalog is explicit and every change is audited rather than being an
 * untyped key-value store.
 */
class SettingDefinitionSeeder extends Seeder
{
    /**
     * @return list<array{key: string, value_type: string, default: mixed, levels: list<string>, name: string, description?: string, allowed?: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'metrics.planned_downtime_counts_against_availability',
                'value_type' => 'BOOL',
                'default' => false,
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Planned downtime counts against availability',
                'description' => 'Off by default. Counting scheduled maintenance against availability '
                    .'penalises a factory for maintaining its machines (ADR-049).',
            ],
            [
                'key' => 'metrics.downtime_uses_shift_calendar',
                'value_type' => 'BOOL',
                'default' => true,
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Compute downtime against the shift calendar',
                'description' => 'On by default. Wall-clock downtime records overnight hours a '
                    .'single-shift factory was never running (ADR-048).',
            ],
            [
                'key' => 'inventory.allow_negative_stock',
                'value_type' => 'BOOL',
                'default' => false,
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Allow negative stock',
            ],
            [
                'key' => 'inventory.costing_method',
                'value_type' => 'ENUM',
                'default' => 'WEIGHTED_AVERAGE',
                'allowed' => ['WEIGHTED_AVERAGE'],
                'levels' => ['COMPANY'],
                'name' => 'Inventory costing method',
                'description' => 'MVP supports weighted average only (ADR-014). FIFO is a future option.',
            ],
            [
                'key' => 'maintenance.schedule_generation_horizon_days',
                'value_type' => 'INT',
                'default' => 90,
                'levels' => ['COMPANY'],
                'name' => 'Schedule generation horizon (days)',
            ],
            [
                'key' => 'maintenance.non_working_day_policy',
                'value_type' => 'ENUM',
                'default' => 'NEXT_WORKING_DAY',
                'allowed' => ['NONE', 'NEXT_WORKING_DAY', 'PREVIOUS_WORKING_DAY'],
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Policy when maintenance falls on a non-working day',
            ],
            [
                'key' => 'maintenance.block_pm_during_active_breakdown',
                'value_type' => 'BOOL',
                'default' => true,
                'levels' => ['COMPANY'],
                'name' => 'Block preventive maintenance while a breakdown is open',
            ],
            [
                'key' => 'work_order.require_verification_for_criticality',
                'value_type' => 'LIST',
                'default' => ['CRITICAL', 'HIGH'],
                'levels' => ['COMPANY'],
                'name' => 'Criticalities that require verification before closure',
            ],
            [
                'key' => 'work_order.approval_cost_threshold',
                'value_type' => 'DECIMAL',
                'default' => '0.0000',
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Cost above which a work order needs approval',
                'description' => 'Zero means no cost-based approval. Set per contract.',
            ],
            [
                'key' => 'notification.escalation_enabled',
                'value_type' => 'BOOL',
                'default' => true,
                'levels' => ['COMPANY'],
                'name' => 'Enable notification escalation',
            ],
            [
                // SRS 50.3: "MFA is enforceable per company policy". Company
                // level only — a second factor is a property of an account, and
                // an account is not per factory, so a factory-level answer
                // could not be applied to anybody.
                'key' => 'security.require_mfa',
                'value_type' => 'BOOL',
                'default' => false,
                'levels' => ['COMPANY'],
                'name' => 'Require two-step sign-in for everybody',
                'description' => 'Company Owners always need it whatever this says. Turning it on sends anybody without it to their account screen to enrol; nobody is locked out.',
            ],
            [
                'key' => 'subscription.grace_period_days',
                'value_type' => 'INT',
                'default' => 14,
                'levels' => ['COMPANY'],
                'name' => 'Grace period before read-only (days)',
            ],
            [
                'key' => 'locale.default',
                'value_type' => 'ENUM',
                'default' => 'en',
                'allowed' => ['en', 'bn'],
                'levels' => ['COMPANY'],
                'name' => 'Default language',
            ],
            [
                'key' => 'factory.operating_mode',
                'value_type' => 'ENUM',
                'default' => 'CONTINUOUS',
                'allowed' => ['CONTINUOUS', 'SHIFT_BASED'],
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Factory operating mode',
                'description' => 'Falls back to CONTINUOUS when no calendar is configured, and '
                    .'reports must surface that fallback (SRS 47.2 rule 4).',
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::definitions() as $definition) {
            SettingDefinition::updateOrCreate(
                ['key' => $definition['key']],
                [
                    'value_type' => $definition['value_type'],
                    'allowed_values' => $definition['allowed'] ?? null,
                    // Wrapped so JSON carries scalars and lists uniformly.
                    'default_value' => ['v' => $definition['default']],
                    'scope_levels' => $definition['levels'],
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                ],
            );
        }
    }
}
