<?php

declare(strict_types=1);

namespace App\Modules\ExportStatement\Support;

use App\Modules\ExportStatement\Models\EsColumn;
use App\Modules\ExportStatement\Models\EsGroup;
use App\Shared\Formula\Formula;
use App\Shared\Support\ColumnSetupSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Export Statement's starting sheet (AnnotechERP 35, user 2026-11-21): Norban's
 * 61 columns of "MD Sir Export Status" in its order, in seven groups, after
 * which Column Setup is the company's own. Norban's ⚡ columns are formulas
 * here: Net = Gross − Commission, Maximum docs submission = BL date + 10 days,
 * the delay, Shortage/Over = Realized − Net, and Claim Days Remaining (180 days
 * from the Realized Date; Claimed / Not realized / Expired).
 */
final class EsCatalog
{
    public const TYPES = ['text', 'longtext', 'number', 'money', 'percent', 'days', 'date', 'dropdown', 'multi', 'image', 'system'];

    /** The master lists a column may pick from (plus `es:<list>` and `lookup:<code>`). */
    public const SOURCES = ['factories', 'buyers', 'banks'];

    public const TONES = ['green', 'yellow', 'lightred', 'red', 'brightgreen', 'highlight', 'peach', 'blue', 'orange', 'pink', 'purple', 'gray', 'cyan'];

    public const TEXT_TONES = ['orange', 'green', 'navy', 'red'];

    public const RULE_OPS = ['any', 'blank', 'eq', 'neq', 'contains', 'lt', 'lte', 'gt', 'gte', 'between'];

    public const GROUPS = [
        ['code' => '01', 'name' => '01. Invoice & Order', 'tone' => 'green'],
        ['code' => '02', 'name' => '02. Quantity & Value', 'tone' => 'yellow'],
        ['code' => '03', 'name' => '03. Shipment', 'tone' => 'blue'],
        ['code' => '04', 'name' => '04. Documents & Bank', 'tone' => 'peach'],
        ['code' => '05', 'name' => '05. Payment & Realization', 'tone' => 'cyan'],
        ['code' => '06', 'name' => '06. LC & Terms', 'tone' => 'purple'],
        ['code' => '07', 'name' => '07. Export Documents & Incentive', 'tone' => 'pink'],
    ];

    /** The columns View All starts with: Norban's default view. */
    public const DEFAULT_VIEW = [
        'serial_no', 'company_name', 'job_no', 'buyer_name', 'ex_factory_date', 'invoice_no', 'invoice_date', 'po_no', 'export_qty', 'invoice_value_gross',
        'invoice_value_net', 'bl_no', 'bl_date', 'max_docs_submission_date', 'docs_submission_date', 'docs_submission_delay_days', 'realized_value',
        'shortage_over_payment', 'due_payment', 'shippers_bank_name', 'sc_lc_no', 'status',
    ];

    /** @return list<array<string, mixed>> */
    public static function columns(): array
    {
        $usd = ['type' => 'money', 'prefix' => '$', 'decimals' => 2];
        $claim = 'ADD_DAYS([Realized Date], '.EsOptions::CLAIM_DAYS.')';

        return [
            ['code' => '01', 'key' => 'serial_no', 'label' => 'SL No.', 'type' => 'system', 'width' => 55],
            ['code' => '01', 'key' => 'company_name', 'label' => 'Company Name', 'type' => 'dropdown', 'source' => 'factories', 'width' => 100, 'required' => true],
            ['code' => '01', 'key' => 'job_no', 'label' => 'Job No.', 'type' => 'multi', 'width' => 90, 'multiple' => true],
            ['code' => '01', 'key' => 'buyer_name', 'label' => 'Buyer Name', 'type' => 'dropdown', 'source' => 'buyers', 'width' => 130],
            ['code' => '01', 'key' => 'ex_factory_date', 'label' => 'Ex-Factory date', 'type' => 'date', 'width' => 100],
            ['code' => '01', 'key' => 'invoice_no', 'label' => 'Invoice No.', 'type' => 'text', 'width' => 110, 'required' => true],
            ['code' => '01', 'key' => 'invoice_date', 'label' => 'Invoice Date', 'type' => 'date', 'width' => 100],
            ['code' => '01', 'key' => 'exp_no', 'label' => 'EXP No.', 'type' => 'text', 'width' => 140],
            ['code' => '01', 'key' => 'exp_date', 'label' => 'EXP Date', 'type' => 'date', 'width' => 100],
            ['code' => '01', 'key' => 'po_no', 'label' => 'PO No.', 'type' => 'text', 'width' => 110],
            ['code' => '01', 'key' => 'style_no', 'label' => 'Style No.', 'type' => 'text', 'width' => 100],
            ['code' => '02', 'key' => 'export_qty', 'label' => 'Export qty / PC', 'type' => 'number', 'decimals' => 0, 'suffix' => 'Pcs', 'width' => 95, 'kpi' => 'SUM'],
            ['code' => '02', 'key' => 'invoice_value_gross', 'label' => 'Invoice Value In US$ (Gross)', ...$usd, 'width' => 120, 'kpi' => 'SUM'],
            ['code' => '02', 'key' => 'commission', 'label' => 'Commision/ Deduction /Discount', ...$usd, 'width' => 115, 'kpi' => 'SUM'],
            ['code' => '02', 'key' => 'invoice_value_net', 'label' => 'Invoice Value In US$ (Net)', ...$usd, 'width' => 120, 'kpi' => 'SUM',
                'formula' => 'N([Invoice Value In US$ (Gross)]) - N([Commision/ Deduction /Discount])'],
            ['code' => '02', 'key' => 'freight_charge', 'label' => 'Freight Charge', ...$usd, 'width' => 95, 'kpi' => 'SUM'],
            ['code' => '02', 'key' => 'total_ctns', 'label' => 'Total Ctns', 'type' => 'number', 'decimals' => 0, 'suffix' => 'Ctns', 'width' => 80, 'kpi' => 'SUM'],
            ['code' => '02', 'key' => 'gross_weight', 'label' => 'Gross Weight', 'type' => 'number', 'decimals' => 2, 'suffix' => 'Kgs', 'width' => 95, 'kpi' => 'SUM'],
            ['code' => '02', 'key' => 'net_weight', 'label' => 'Net weight', 'type' => 'number', 'decimals' => 2, 'suffix' => 'Kgs', 'width' => 95, 'kpi' => 'SUM'],
            ['code' => '02', 'key' => 'total_cbm', 'label' => 'Total Cbm', 'type' => 'number', 'decimals' => 2, 'suffix' => 'CBM', 'width' => 80, 'kpi' => 'SUM'],
            ['code' => '03', 'key' => 'shipping_bill_no', 'label' => 'Bill of Export No. (Shipping Bill)', 'type' => 'text', 'width' => 120],
            ['code' => '03', 'key' => 'shipping_bill_date', 'label' => 'Bill of Export Date (Shipping Bill)', 'type' => 'date', 'width' => 115],
            ['code' => '03', 'key' => 'forwarder', 'label' => 'Forwarder/ Shipping Line', 'type' => 'text', 'width' => 170],
            ['code' => '03', 'key' => 'bl_no', 'label' => 'BL/Cargo/AWB No.', 'type' => 'text', 'width' => 120],
            ['code' => '03', 'key' => 'bl_date', 'label' => 'BL/Cargo/AWB Date/ ETD', 'type' => 'date', 'width' => 110],
            ['code' => '03', 'key' => 'eta', 'label' => 'ETA', 'type' => 'date', 'width' => 100],
            ['code' => '04', 'key' => 'max_docs_submission_date', 'label' => 'Maximum docs submission within 10 days', 'type' => 'date', 'width' => 125,
                'formula' => 'ADD_DAYS([BL/Cargo/AWB Date/ ETD], '.EsOptions::DOCS_SUBMISSION_DAYS.')'],
            ['code' => '04', 'key' => 'docs_submission_date', 'label' => 'Buyer/Forwarder/ Bank docs submission date', 'type' => 'date', 'width' => 130],
            ['code' => '04', 'key' => 'docs_submission_delay_days', 'label' => 'Documents submission delay day', 'type' => 'days', 'decimals' => 0, 'suffix' => 'Days', 'width' => 105,
                'formula' => 'DAYS([Buyer/Forwarder/ Bank docs submission date], [Maximum docs submission within 10 days])', 'rules' => [['op' => 'gt', 'value' => '0', 'tone' => 'lightred']]],
            ['code' => '04', 'key' => 'bank_ref_no', 'label' => 'Bank Ref/ Bill No.', 'type' => 'text', 'width' => 115],
            ['code' => '04', 'key' => 'bank_ref_date', 'label' => 'Bank Ref/ Bill No. Date', 'type' => 'date', 'width' => 105],
            ['code' => '04', 'key' => 'courier', 'label' => 'Courier Name & No', 'type' => 'text', 'width' => 150],
            ['code' => '04', 'key' => 'payment_acceptance_date', 'label' => 'Payment Acceptance date', 'type' => 'date', 'width' => 110],
            ['code' => '04', 'key' => 'tt_received_date', 'label' => 'Payment recevd date (T.T)', 'type' => 'date', 'width' => 110],
            ['code' => '05', 'key' => 'realized_value', 'label' => 'Realized Value', ...$usd, 'width' => 110, 'kpi' => 'SUM'],
            ['code' => '05', 'key' => 'realized_date', 'label' => 'Realized Date', 'type' => 'date', 'width' => 100],
            ['code' => '05', 'key' => 'shortage_over_payment', 'label' => 'Shortage/ Over Payment', ...$usd, 'width' => 110, 'kpi' => 'SUM',
                'formula' => 'N([Realized Value]) - N([Invoice Value In US$ (Net)])', 'rules' => [['op' => 'lt', 'value' => '0', 'tone' => 'lightred']]],
            ['code' => '05', 'key' => 'due_payment', 'label' => 'Due Payment US$', 'type' => 'dropdown', 'source' => 'es:due_payment', 'width' => 85,
                'rules' => [['op' => 'eq', 'value' => 'DUE', 'tone' => 'lightred'], ['op' => 'eq', 'value' => 'PAID', 'tone' => 'green']]],
            ['code' => '05', 'key' => 'bl_surrender_date', 'label' => 'B/L Surrender date', 'type' => 'date', 'width' => 105],
            ['code' => '05', 'key' => 'shippers_bank_name', 'label' => 'Shippers Bank Name', 'type' => 'dropdown', 'source' => 'banks', 'width' => 140],
            ['code' => '06', 'key' => 'sc_lc_no', 'label' => 'SC/LC No.', 'type' => 'text', 'width' => 150],
            ['code' => '06', 'key' => 'sc_lc_date', 'label' => 'SC/LC No. Date', 'type' => 'date', 'width' => 100],
            ['code' => '06', 'key' => 'ud_no', 'label' => 'UD No.', 'type' => 'text', 'width' => 100],
            ['code' => '06', 'key' => 'ud_date', 'label' => 'UD Date', 'type' => 'date', 'width' => 100],
            ['code' => '06', 'key' => 'terms_of_payment', 'label' => 'Terms of Payment', 'type' => 'text', 'width' => 95],
            ['code' => '06', 'key' => 'incoterms', 'label' => 'Incoterms', 'type' => 'dropdown', 'source' => 'es:incoterms', 'width' => 100],
            ['code' => '06', 'key' => 'port_of_loading', 'label' => 'Port of Loading', 'type' => 'text', 'width' => 120],
            ['code' => '06', 'key' => 'ship_mode', 'label' => 'Ship Mode', 'type' => 'dropdown', 'source' => 'es:ship_mode', 'width' => 90],
            ['code' => '06', 'key' => 'port_of_discharge', 'label' => 'Port of Discharge', 'type' => 'text', 'width' => 120],
            ['code' => '06', 'key' => 'country_of_destination', 'label' => 'Country of Destination', 'type' => 'text', 'width' => 120],
            ['code' => '07', 'key' => 'rex_no', 'label' => 'REX/ GSP/ CoO NO.', 'type' => 'text', 'width' => 120],
            ['code' => '07', 'key' => 'rex_submission_date', 'label' => 'REX Submission date', 'type' => 'date', 'width' => 110],
            ['code' => '07', 'key' => 'exp_duplicate_status', 'label' => 'EXP Duplicate status', 'type' => 'text', 'width' => 105],
            ['code' => '07', 'key' => 'prc_status', 'label' => 'PRC status', 'type' => 'text', 'width' => 95],
            ['code' => '07', 'key' => 'cash_incentive_claim_amount', 'label' => 'Cash Incentive claim amount', ...$usd, 'width' => 115, 'kpi' => 'SUM'],
            ['code' => '07', 'key' => 'cash_incentive_claim_date', 'label' => 'Cash Incentive claim date', 'type' => 'date', 'width' => 115],
            ['code' => '07', 'key' => 'audit_firm', 'label' => 'Audit Firm', 'type' => 'text', 'width' => 150],
            ['code' => '07', 'key' => 'claim_days_remaining', 'label' => 'Claim Days Remaining', 'type' => 'text', 'width' => 110,
                'formula' => 'IF(NOT(ISBLANK([Cash Incentive claim date])), "Claimed", IF(ISBLANK([Realized Date]), "Not realized", IF(DAYS('.$claim.', TODAY()) < 0, "Expired", DAYS('.$claim.', TODAY()))))',
                'rules' => [['op' => 'eq', 'value' => 'Expired', 'tone' => 'lightred'], ['op' => 'eq', 'value' => 'Claimed', 'tone' => 'green']]],
            ['code' => '07', 'key' => 'remarks', 'label' => 'Remarks', 'type' => 'longtext', 'width' => 220],
            ['code' => '07', 'key' => 'status', 'label' => 'Status', 'type' => 'system', 'width' => 120,
                'rules' => [['op' => 'eq', 'value' => 'Pending Approval', 'tone' => 'yellow'], ['op' => 'eq', 'value' => 'Approved', 'tone' => 'green']]],
        ];
    }

    /** Where `erp:save-column-setup` keeps the saved Export Statement Column Setup a new company starts from. */
    public static function snapshotPath(): string
    {
        return dirname(__DIR__).'/Database/Data/column_setup.json';
    }

    /** The company's statement sheet, made from the saved setup (else this catalog) if it has none yet. */
    public static function ensure(string $companyId): void
    {
        if (EsColumn::query()->where('company_id', $companyId)->exists()) {
            return;
        }
        DB::transaction(function () use ($companyId): void {
            DB::table('companies')->where('id', $companyId)->lockForUpdate()->value('id');
            if (EsColumn::query()->where('company_id', $companyId)->exists()) {
                return;
            }
            if ($saved = ColumnSetupSnapshot::read(self::snapshotPath())) {
                ColumnSetupSnapshot::restore($companyId, $saved, EsGroup::class, EsColumn::class);

                return;
            }
            $groups = [];
            foreach (self::GROUPS as $i => $g) {
                $groups[$g['code']] = EsGroup::query()->create(['company_id' => $companyId, 'code' => $g['code'], 'name' => $g['name'], 'tone' => $g['tone'] ?? null, 'sort_order' => ($i + 1) * 10, 'is_system' => true, 'active' => true])->id;
            }
            $columns = self::columns();
            $byLabel = array_column($columns, 'key', 'label');
            foreach ($columns as $i => $c) {
                EsColumn::query()->create([
                    'company_id' => $companyId, 'group_id' => $groups[$c['code']], 'key' => $c['key'], 'label' => $c['label'], 'sort_order' => ($i + 1) * 10,
                    'type' => $c['type'], 'source' => $c['source'] ?? null, 'multiple' => $c['type'] === 'multi', 'prefix' => $c['prefix'] ?? null, 'suffix' => $c['suffix'] ?? null,
                    'decimals' => $c['decimals'] ?? null, 'width' => $c['width'] ?? 130, 'vertical' => $c['vertical'] ?? false, 'frozen' => false, 'required' => $c['required'] ?? false,
                    'formula' => isset($c['formula']) ? Formula::stored($c['formula'], fn (string $label) => $byLabel[$label] ?? null) : null,
                    'color_rules' => $c['rules'] ?? null, 'text_tone' => $c['text'] ?? null, 'kpi' => $c['kpi'] ?? null, 'is_system' => true,
                ]);
            }
        });
    }
}
