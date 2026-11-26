<?php

declare(strict_types=1);

namespace App\Modules\DevSample\Support;

use App\Modules\DevSample\Models\DsColumn;
use App\Modules\DevSample\Models\DsGroup;
use App\Shared\Formula\Formula;
use App\Shared\Support\ColumnSetupSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Dev Sample Status's starting sheet (Column Setup, user 2026-11-21): the
 * groups and columns a company begins with — Norban's fields in its order
 * and names — after which Column Setup is the company's own. Status lists
 * keep Norban's choices (`ds:<list>`, DevSampleOptions) and their colours.
 * Colours and prices are pairs columns — lines of a name and a number (Navy
 * 60 pcs; BIO $2.48): a formula reads their total (`reduce` SUM) or their
 * first line (FIRST, a price's summary level).
 */
final class DsCatalog
{
    public const TYPES = ['text', 'longtext', 'number', 'money', 'percent', 'days', 'date', 'dropdown', 'multi', 'pairs', 'image', 'system'];

    /** The master lists a column may pick from (plus `ds:<list>` and `lookup:<code>`). */
    public const SOURCES = ['buyers', 'items', 'colours', 'fabric_types', 'compositions', 'suppliers'];

    public const TONES = ['green', 'yellow', 'lightred', 'red', 'brightgreen', 'highlight', 'peach', 'blue', 'orange', 'pink', 'purple', 'gray', 'cyan'];

    public const TEXT_TONES = ['orange', 'green', 'navy', 'red'];

    public const RULE_OPS = ['any', 'blank', 'eq', 'neq', 'contains', 'lt', 'lte', 'gt', 'gte', 'between'];

    /** Norban's status pills as Column Setup colours. */
    private const PILL = ['success' => 'green', 'warning' => 'yellow', 'danger' => 'lightred', 'info' => 'blue'];

    private const STATUS_TONES = [
        'price_status' => ['OFFERED' => 'info', 'PENDING_OFFER' => 'warning', 'CONFIRMED' => 'success', 'CANCELLED' => 'danger', 'SELF_DROP' => 'danger'],
        'sample_status' => ['SUBMITTED' => 'success', 'SUBMISSION_PENDING' => 'warning', 'CLARIFICATION' => 'info', 'CANCELLED' => 'danger', 'SELF_DROP' => 'danger'],
        'order_confirmation' => [
            'CONFIRMED_WITH_DEV' => 'success', 'CONFIRMED_WITHOUT_DEV' => 'success', 'ORDER_CONFIRMED' => 'success', 'CONFIRMATION_PENDING' => 'warning',
            'NEGOTIATION' => 'info', 'PENDING_OFFER' => 'warning', 'DROP' => 'danger', 'CANCELLED' => 'danger', 'SELF_DROP' => 'danger',
        ],
    ];

    public const GROUPS = [
        ['code' => '01', 'name' => '01. Basic Information', 'tone' => 'blue'],
        ['code' => '02', 'name' => '02. Sample Details', 'tone' => 'green'],
        ['code' => '03', 'name' => '03. Quantity & Price', 'tone' => 'yellow'],
        ['code' => '04', 'name' => '04. Dates', 'tone' => 'peach'],
        ['code' => '05', 'name' => '05. Production & Sample Tracking', 'tone' => 'purple'],
        ['code' => '06', 'name' => '06. Status', 'tone' => 'pink'],
    ];

    /** The production & sample tracking dates, in Norban's order. */
    public const TRACKING = [
        'booking_date' => 'Booking Date', 'knitting_plan_date' => 'Knitting Plan Date', 'actual_grige_rcvd_date' => 'Actual Grige Rcvd Date',
        'finish_fabric_delivery_plan_date' => 'Finish Fabric Delivery Plan Date', 'finish_fabric_rcvd_date' => 'Finish Fabric Rcvd Date',
        'sample_sewing_plan_date' => 'Sample Sewing Plan Date', 'file_handover_date' => 'File Handover Date', 'pattern_ready_date' => 'Pattern Ready Date',
        'cutting_date' => 'Cutting Date', 'print_emb_date' => 'Print/EMB Date', 'sewing_start_date' => 'Sewing Start Date',
        'sewing_output_date' => 'Sewing Output Date', 'sample_submitted_date' => 'Sample Submitted Date',
    ];

    /** @return list<array<string, mixed>> */
    public static function columns(): array
    {
        $tracking = [];
        foreach (self::TRACKING as $key => $label) {
            $tracking[] = ['code' => '05', 'key' => $key, 'label' => $label, 'type' => 'date', 'width' => 55, 'vertical' => true];
        }

        return [
            ['code' => '01', 'key' => 'serial_no', 'label' => 'Serial No', 'type' => 'system', 'width' => 50],
            ['code' => '01', 'key' => 'season', 'label' => 'Season', 'type' => 'dropdown', 'source' => 'lookup:SEASON', 'width' => 90],
            ['code' => '01', 'key' => 'buyer', 'label' => 'Buyer', 'type' => 'dropdown', 'source' => 'buyers', 'width' => 110, 'required' => true],
            ['code' => '01', 'key' => 'customer_name', 'label' => 'Customer Name', 'type' => 'text', 'width' => 120],
            ['code' => '01', 'key' => 'brand', 'label' => 'Brand', 'type' => 'dropdown', 'source' => 'lookup:BRAND', 'width' => 100],
            ['code' => '01', 'key' => 'department', 'label' => 'Department', 'type' => 'dropdown', 'source' => 'lookup:GARMENT_DEPARTMENT', 'width' => 110],
            ['code' => '01', 'key' => 'division', 'label' => 'Division', 'type' => 'text', 'width' => 100],
            ['code' => '01', 'key' => 'styling', 'label' => 'Styling', 'type' => 'text', 'width' => 120],
            ['code' => '01', 'key' => 'artwork', 'label' => 'Image', 'type' => 'image', 'width' => 80],
            ['code' => '01', 'key' => 'job_no', 'label' => 'Job No.', 'type' => 'multi', 'width' => 130, 'multiple' => true],
            ['code' => '02', 'key' => 'items', 'label' => 'Item', 'type' => 'multi', 'source' => 'items', 'width' => 120],
            ['code' => '02', 'key' => 'required_category', 'label' => 'Required Category', 'type' => 'dropdown', 'source' => 'ds:required_category', 'width' => 120],
            ['code' => '02', 'key' => 'sample_type', 'label' => 'Sample Type', 'type' => 'dropdown', 'source' => 'ds:sample_type', 'width' => 110],
            ['code' => '02', 'key' => 'fabrication', 'label' => 'Fabrication', 'type' => 'longtext', 'width' => 160],
            ['code' => '02', 'key' => 'colours', 'label' => 'Color with Qty', 'type' => 'pairs', 'reduce' => 'SUM', 'decimals' => 0, 'suffix' => 'Pcs', 'width' => 170],
            ['code' => '03', 'key' => 'sample_qty', 'label' => 'Sample QTY (Buyer)', 'type' => 'number', 'decimals' => 0, 'suffix' => 'Pcs', 'width' => 100, 'kpi' => 'SUM'],
            ['code' => '03', 'key' => 'possible_qty', 'label' => 'Possible QTY', 'type' => 'number', 'decimals' => 0, 'suffix' => 'Pcs', 'width' => 100, 'kpi' => 'SUM'],
            ['code' => '03', 'key' => 'actual_order_qty', 'label' => 'Actual Order qty', 'type' => 'number', 'decimals' => 0, 'suffix' => 'Pcs', 'width' => 100, 'kpi' => 'SUM'],
            ['code' => '03', 'key' => 'target_price', 'label' => 'Buyer Target Price ($)', 'type' => 'pairs', 'reduce' => 'FIRST', 'decimals' => 2, 'prefix' => '$', 'width' => 130],
            ['code' => '03', 'key' => 'fob_price', 'label' => 'FOB Price ($)', 'type' => 'pairs', 'reduce' => 'FIRST', 'decimals' => 2, 'prefix' => '$', 'width' => 130],
            ['code' => '03', 'key' => 'fob_value', 'label' => 'FOB Value ($)', 'type' => 'money', 'decimals' => 2, 'prefix' => '$', 'width' => 110, 'formula' => '[Possible QTY] * [FOB Price ($)]', 'kpi' => 'SUM'],
            // Who entered it (values.last_price_quote_by, set on save) shows under the date, as in Norban.
            ['code' => '03', 'key' => 'last_price_quote_date', 'label' => 'Last Price Quote Date', 'type' => 'date', 'width' => 110],
            ['code' => '04', 'key' => 'tp_rcv_date', 'label' => 'TP RCV Date', 'type' => 'date', 'width' => 55, 'vertical' => true, 'text' => 'orange'],
            ['code' => '04', 'key' => 'buyer_requested_date', 'label' => 'Buyer Requested Date', 'type' => 'date', 'width' => 55, 'vertical' => true],
            ['code' => '04', 'key' => 'submission_deadline', 'label' => 'Submission Deadline', 'type' => 'date', 'width' => 55, 'vertical' => true, 'text' => 'red'],
            ['code' => '04', 'key' => 'sub_plan_date', 'label' => 'Sample Sub. Plan Date', 'type' => 'date', 'width' => 55, 'vertical' => true],
            ...$tracking,
            ['code' => '05', 'key' => 'awb_tracking', 'label' => 'AWB Tracking', 'type' => 'text', 'width' => 130],
            ['code' => '06', 'key' => 'price_status', 'label' => 'Price Status', 'type' => 'dropdown', 'source' => 'ds:price_status', 'width' => 110, 'rules' => self::rules('price_status')],
            ['code' => '06', 'key' => 'sample_status', 'label' => 'Sample Status', 'type' => 'dropdown', 'source' => 'ds:sample_status', 'width' => 120, 'rules' => self::rules('sample_status')],
            ['code' => '06', 'key' => 'order_confirmation', 'label' => 'Order Confirmation', 'type' => 'dropdown', 'source' => 'ds:order_confirmation', 'width' => 140, 'rules' => self::rules('order_confirmation')],
            ['code' => '06', 'key' => 'current_order_situation', 'label' => 'Current Order Situation', 'type' => 'dropdown', 'source' => 'ds:current_order_situation', 'width' => 130],
            ['code' => '06', 'key' => 'remarks', 'label' => 'Remarks', 'type' => 'longtext', 'width' => 200],
            ['code' => '06', 'key' => 'last_update', 'label' => 'Last Update Date', 'type' => 'system', 'width' => 34, 'vertical' => true, 'text' => 'orange'],
        ];
    }

    /** @return list<array{op: string, value: string, tone: string}> */
    private static function rules(string $list): array
    {
        $out = [];
        foreach (self::STATUS_TONES[$list] as $code => $pill) {
            $out[] = ['op' => 'eq', 'value' => DevSampleOptions::label($list, $code), 'tone' => self::PILL[$pill]];
        }

        return $out;
    }

    /** Where `erp:save-column-setup` keeps the saved Dev Sample Column Setup a new company starts from. */
    public static function snapshotPath(): string
    {
        return dirname(__DIR__).'/Database/Data/column_setup.json';
    }

    /** The company's sample sheet, made from the saved setup (else this catalog) if it has none yet. */
    public static function ensure(string $companyId): void
    {
        if (DsColumn::query()->where('company_id', $companyId)->exists()) {
            return;
        }
        DB::transaction(function () use ($companyId): void {
            // Two first page loads at once: the company row is the lock, so only one makes the sheet.
            DB::table('companies')->where('id', $companyId)->lockForUpdate()->value('id');
            if (DsColumn::query()->where('company_id', $companyId)->exists()) {
                return;
            }
            if ($saved = ColumnSetupSnapshot::read(self::snapshotPath())) {
                ColumnSetupSnapshot::restore($companyId, $saved, DsGroup::class, DsColumn::class);

                return;
            }
            $groups = [];
            foreach (self::GROUPS as $i => $g) {
                $groups[$g['code']] = DsGroup::query()->create(['company_id' => $companyId, 'code' => $g['code'], 'name' => $g['name'], 'tone' => $g['tone'] ?? null, 'sort_order' => ($i + 1) * 10, 'is_system' => true, 'active' => true])->id;
            }
            $columns = self::columns();
            $byLabel = array_column($columns, 'key', 'label');
            foreach ($columns as $i => $c) {
                DsColumn::query()->create([
                    'company_id' => $companyId, 'group_id' => $groups[$c['code']], 'key' => $c['key'], 'label' => $c['label'], 'sort_order' => ($i + 1) * 10,
                    'type' => $c['type'], 'source' => $c['source'] ?? null, 'multiple' => $c['type'] === 'multi', 'prefix' => $c['prefix'] ?? null, 'suffix' => $c['suffix'] ?? null,
                    'decimals' => $c['decimals'] ?? null, 'width' => $c['width'] ?? 130, 'vertical' => $c['vertical'] ?? false, 'frozen' => false, 'required' => $c['required'] ?? false,
                    'formula' => isset($c['formula']) ? Formula::stored($c['formula'], fn (string $label) => $byLabel[$label] ?? null) : null,
                    'color_rules' => $c['rules'] ?? null, 'text_tone' => $c['text'] ?? null, 'kpi' => $c['kpi'] ?? null, 'pair_reduce' => $c['reduce'] ?? null, 'is_system' => true,
                ]);
            }
        });
    }
}
