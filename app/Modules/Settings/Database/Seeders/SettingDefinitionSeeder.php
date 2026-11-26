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
                'key' => 'inspection.four_point_acceptance',
                'value_type' => 'INT',
                'default' => 28,
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Four-point acceptance limit',
                'description' => 'Points per 100 square yards a lot may carry and still be '
                    .'accepted. A commercial term, not a physical one: buyers set it, and '
                    .'each inspection copies the figure in force on the day (Inventory spec §4.3).',
            ],
            [
                'key' => 'inspection.sampling_first_percent',
                'value_type' => 'INT',
                'default' => 10,
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'First-pass inspection sample',
                'description' => 'How much of a delivered lot the first pass covers.',
            ],
            [
                'key' => 'inspection.sampling_second_percent',
                'value_type' => 'INT',
                'default' => 15,
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Second-pass inspection sample',
                'description' => 'The wider sample opened when the first pass fails. Failing '
                    .'again means the whole lot is opened, or it goes back to the supplier.',
            ],
            [
                'key' => 'procurement.allow_over_receipt',
                'value_type' => 'BOOL',
                'default' => false,
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Allow receiving more than was ordered',
                'description' => 'Off by default: a delivery larger than the purchase order is '
                    .'refused at the gate until the order is revised (Procurement spec §9).',
            ],
            [
                'key' => 'inventory.lot_expiry_notice_days',
                'value_type' => 'INT',
                'default' => 30,
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Days of notice before a lot expires',
                'description' => 'A chemical or dye lot still in stock and expiring within this many '
                    .'days is reported to the store team (Inventory spec §12). Zero turns the sweep off.',
            ],
            [
                'key' => 'inventory.count_stale_days',
                'value_type' => 'INT',
                'default' => 3,
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Days before an open stock count is chased',
                'description' => 'A count sheet left open this long is reported to the store team '
                    .'(Inventory spec §12). The system quantities on it were frozen when it was '
                    .'opened, so the longer it sits the less the sheet describes the shelf. '
                    .'Zero turns the sweep off.',
            ],
            [
                'key' => 'procurement.shipment_eta_notice_days',
                'value_type' => 'INT',
                'default' => 7,
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Days of notice before a shipment lands',
                'description' => 'A consignment due within this many days is reported to the store '
                    .'team, and one past its ETA is reported every day until it arrives '
                    .'(Procurement spec §10). Zero turns the sweep off.',
            ],
            [
                'key' => 'procurement.lc_expiry_notice_days',
                'value_type' => 'INT',
                'default' => 15,
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Days of notice before a letter of credit expires',
                'description' => 'An open letter of credit expiring within this many days is '
                    .'reported, because amending one costs a bank charge while letting it '
                    .'lapse costs the whole opening (Procurement spec §10).',
            ],
            [
                'key' => 'production.bom_variance_tolerance_percent',
                'value_type' => 'INT',
                'default' => 5,
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'BOM variance tolerance (%)',
                'description' => 'How far a cutting programme may go over its bill of materials before '
                    .'the issue needs an approval. The stock does not move until it has one '
                    .'(Inventory spec §9). The spec leaves the number to each factory; 5% is a '
                    .'starting point, not an agreed figure.',
            ],
            [
                'key' => 'requisition.returnable_overdue_days',
                'value_type' => 'INT',
                'default' => 7,
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Days before a borrowed item is chased',
                'description' => 'A returnable item issued to the floor and not back within this '
                    .'many days is reported to the store team (Floor/Store Requisition spec §7).',
            ],
            [
                'key' => 'requisition.earmark_reminder_days',
                'value_type' => 'INT',
                'default' => 14,
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Days before a waiting claim is chased',
                'description' => 'A shortage claim still waiting on stock after this many days is '
                    .'reported, so a purchase nobody chased does not quietly hold the requisition open.',
            ],
            [
                'key' => 'fabric.length_unit',
                'value_type' => 'ENUM',
                'default' => 'YDS',
                'allowed' => ['YDS', 'MTR'],
                'levels' => ['COMPANY'],
                'name' => 'Fabric length unit',
                'description' => 'Fabric is counted by length from the grey fabric on — in yards or metres — with its weight (kg) beside it for dyeing.',
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
                'key' => 'subscription.grace_period_days',
                'value_type' => 'INT',
                'default' => 14,
                'levels' => ['COMPANY'],
                'name' => 'Grace period before read-only (days)',
            ],
            // Settings → General Preferences (AnnotechERP 03/03).
            [
                'key' => 'general.working_days',
                'value_type' => 'LIST',
                'default' => ['SAT', 'SUN', 'MON', 'TUE', 'WED', 'THU'],
                'allowed' => ['SAT', 'SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI'],
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Working days of the week',
                'description' => 'The days the floor works; the others are the weekly off-days.',
            ],
            [
                'key' => 'general.fiscal_year_start_month',
                'value_type' => 'ENUM',
                'default' => '7',
                'allowed' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'],
                'levels' => ['COMPANY'],
                'name' => 'Fiscal year starts in',
                'description' => 'July in Bangladesh; reports that run by fiscal year start here.',
            ],
            [
                'key' => 'general.landing_page',
                'value_type' => 'STRING',
                'default' => '/',
                'levels' => ['COMPANY'],
                'name' => 'First page after sign-in',
            ],
            [
                'key' => 'general.page_size',
                'value_type' => 'ENUM',
                'default' => '10',
                'allowed' => ['10', '25', '50', '100'],
                'levels' => ['COMPANY'],
                'name' => 'Rows per page in lists',
            ],
            // Settings → Date, Currency & Localization (AnnotechERP 03/03).
            [
                'key' => 'locale.date_format',
                'value_type' => 'ENUM',
                'default' => 'DD-MMM-YYYY',
                'allowed' => ['DD-MMM-YYYY', 'DD/MM/YYYY', 'YYYY-MM-DD', 'MM/DD/YYYY'],
                'levels' => ['COMPANY'],
                'name' => 'Date format',
            ],
            [
                'key' => 'locale.time_format',
                'value_type' => 'ENUM',
                'default' => '12H',
                'allowed' => ['12H', '24H'],
                'levels' => ['COMPANY'],
                'name' => 'Time format',
            ],
            [
                'key' => 'locale.timezone',
                'value_type' => 'ENUM',
                'default' => 'Asia/Dhaka',
                'allowed' => ['Asia/Dhaka', 'UTC', 'Asia/Kolkata', 'Asia/Karachi', 'Asia/Shanghai', 'Asia/Ho_Chi_Minh', 'Europe/London'],
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Time zone',
            ],
            [
                'key' => 'currency.base',
                'value_type' => 'ENUM',
                'default' => 'BDT',
                'allowed' => ['BDT', 'USD', 'EUR', 'GBP', 'CNY', 'INR'],
                'levels' => ['COMPANY'],
                'name' => 'Base currency',
                'description' => 'Every amount is kept in this currency; others convert at their exchange rate.',
            ],
            [
                'key' => 'number.decimal_places',
                'value_type' => 'ENUM',
                'default' => '2',
                'allowed' => ['0', '2', '3', '4'],
                'levels' => ['COMPANY'],
                'name' => 'Decimal places for amounts',
            ],
            [
                'key' => 'number.grouping',
                'value_type' => 'ENUM',
                'default' => 'LAKH',
                'allowed' => ['LAKH', 'THOUSAND'],
                'levels' => ['COMPANY'],
                'name' => 'Digit grouping',
                'description' => 'Lakh-crore (12,34,567) or thousands (1,234,567).',
            ],
            // Settings → Print & Letterhead (AnnotechERP 03/03).
            [
                'key' => 'print.header_title',
                'value_type' => 'STRING',
                'default' => '',
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Letterhead title',
                'description' => 'Blank prints the company name.',
            ],
            [
                'key' => 'print.header_address',
                'value_type' => 'STRING',
                'default' => '',
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Letterhead address',
            ],
            [
                'key' => 'print.header_contact',
                'value_type' => 'STRING',
                'default' => '',
                'levels' => ['COMPANY', 'FACTORY'],
                'name' => 'Letterhead contact line',
            ],
            [
                'key' => 'print.footer_text',
                'value_type' => 'STRING',
                'default' => '',
                'levels' => ['COMPANY'],
                'name' => 'Footer text',
            ],
            [
                'key' => 'print.show_logo',
                'value_type' => 'BOOL',
                'default' => true,
                'levels' => ['COMPANY'],
                'name' => 'Print the company logo',
            ],
            [
                'key' => 'print.show_print_time',
                'value_type' => 'BOOL',
                'default' => true,
                'levels' => ['COMPANY'],
                'name' => 'Print who printed it and when',
            ],
            [
                'key' => 'print.signatures',
                'value_type' => 'LIST',
                'default' => ['Prepared By', 'Checked By', 'Approved By'],
                'levels' => ['COMPANY'],
                'name' => 'Signature boxes',
                'description' => 'One box per entry, left to right, at the foot of a printed document.',
            ],
            [
                'key' => 'print.paper_size',
                'value_type' => 'ENUM',
                'default' => 'A4',
                'allowed' => ['A4', 'LETTER', 'LEGAL'],
                'levels' => ['COMPANY'],
                'name' => 'Paper size',
            ],
            [
                'key' => 'print.margin_mm',
                'value_type' => 'INT',
                'default' => 12,
                'levels' => ['COMPANY'],
                'name' => 'Page margin (mm)',
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
