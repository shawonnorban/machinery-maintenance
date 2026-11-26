<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Support;

use App\Modules\PreBudget\Models\PbColumn;
use App\Modules\PreBudget\Models\PbGroup;
use App\Shared\Formula\Formula;
use App\Shared\Support\ColumnSetupSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * The requisition sheet a company starts with (Pre-Budget, AnnotechERP
 * 33/34): Norban's Knit/Woven requisition fields and its screen formulas, as
 * Column Setup's first data — after that the company changes them there.
 *
 * Knit and Woven share one sheet (user, 2026-11-19): a Knit row is worked in
 * kg, a Woven row in yards with the same formulas. Fabrication is built from
 * Fabric Type, Fabric Composition and GSM, as Norban builds it (read-only
 * there too). Yarn Type, Yarn Count and
 * the yarn Composition are Knit columns, Fabric Mill and Width Woven ones
 * (`applies`). `required` holds on Complete, not on a draft.
 *
 * Column: code (group), key, label, type, width, and optionally source,
 * prefix, suffix, decimals, vertical, frozen, required, applies (KNIT /
 * WOVEN), formula ([Label] form), rules (colour rules), kpi (SUM / AVG in
 * the totals row).
 */
final class RequisitionCatalog
{
    public const TYPES = ['text', 'longtext', 'number', 'money', 'percent', 'days', 'date', 'dropdown', 'multi', 'colorsize', 'image', 'system'];

    /** Lists a dropdown may pick from, besides a lookup table (lookup:<CODE>) or one of Pre-Budget's own (pb:<LIST>). */
    public const SOURCES = ['entry_types', 'buyers', 'factories', 'items', 'fabric_types', 'compositions', 'suppliers', 'garment_parts', 'yarn_counts', 'colours'];

    public const TONES = ['green', 'yellow', 'lightred', 'red', 'brightgreen', 'highlight', 'peach', 'blue', 'orange', 'pink', 'purple', 'gray', 'cyan'];

    public const TEXT_TONES = ['orange', 'green', 'navy', 'red'];

    public const RULE_OPS = ['any', 'blank', 'eq', 'neq', 'contains', 'lt', 'lte', 'gt', 'gte', 'between'];

    /** A row's Entry Type as the sheet spells it → as the row stores it. */
    public const ENTRY_TYPES = ['Knit' => 'KNIT', 'Woven' => 'WOVEN'];

    public const GROUPS = [
        ['code' => '01', 'name' => '01. Order Info'],
        ['code' => '02', 'name' => '02. Fabric & Yarn'],
        ['code' => '03', 'name' => '03. Quantity & Value'],
        ['code' => '04', 'name' => '04. Consumption (Kg / Yds)'],
    ];

    public const COLUMNS = [
        ['code' => '01', 'key' => 'serial_no', 'label' => 'S/N', 'type' => 'system', 'width' => 50],
        ['code' => '01', 'key' => 'entry_type', 'label' => 'Entry Type', 'type' => 'dropdown', 'width' => 80, 'source' => 'entry_types', 'required' => true, 'rules' => [['op' => 'eq', 'value' => 'Woven', 'tone' => 'blue']]],
        ['code' => '01', 'key' => 'factory', 'label' => 'Factory', 'type' => 'dropdown', 'width' => 80, 'source' => 'factories', 'required' => true],
        ['code' => '01', 'key' => 'buyer', 'label' => 'Buyer', 'type' => 'dropdown', 'width' => 120, 'source' => 'buyers', 'required' => true],
        ['code' => '01', 'key' => 'brand', 'label' => 'Brand', 'type' => 'dropdown', 'width' => 120, 'source' => 'lookup:BRAND', 'required' => true],
        ['code' => '01', 'key' => 'season', 'label' => 'Season', 'type' => 'dropdown', 'width' => 100, 'source' => 'lookup:SEASON', 'required' => true],
        ['code' => '01', 'key' => 'dept', 'label' => 'Dept', 'type' => 'dropdown', 'width' => 110, 'source' => 'lookup:GARMENT_DEPARTMENT', 'required' => true],
        ['code' => '01', 'key' => 'style', 'label' => 'Style', 'type' => 'text', 'width' => 150, 'required' => true, 'frozen' => true],
        ['code' => '01', 'key' => 'order_type', 'label' => 'Order Type', 'type' => 'dropdown', 'width' => 110, 'source' => 'pb:ORDER_TYPE', 'required' => true],
        ['code' => '01', 'key' => 'job_no', 'label' => 'Job No', 'type' => 'multi', 'width' => 110, 'multiple' => true],
        ['code' => '01', 'key' => 'contract_no', 'label' => 'Contract No', 'type' => 'text', 'width' => 140],
        ['code' => '01', 'key' => 'delivery_date', 'label' => 'Delivery Date', 'type' => 'date', 'width' => 100],
        ['code' => '01', 'key' => 'order_booking_date', 'label' => 'Order Booking Date', 'type' => 'date', 'width' => 100],
        ['code' => '01', 'key' => 'po_wise_rfi_sd_date', 'label' => 'PO-wise RFI/SD Date', 'type' => 'date', 'width' => 100],
        ['code' => '01', 'key' => 'yarn_booking_date', 'label' => 'Yarn Booking Date', 'type' => 'date', 'width' => 100],
        ['code' => '01', 'key' => 'artwork', 'label' => 'Artwork', 'type' => 'image', 'width' => 90, 'required' => true],
        ['code' => '01', 'key' => 'item', 'label' => 'Item', 'type' => 'dropdown', 'width' => 110, 'source' => 'pb:ITEM', 'required' => true],
        ['code' => '01', 'key' => 'wash_type', 'label' => 'Wash Type', 'type' => 'dropdown', 'width' => 110, 'source' => 'pb:WASH_TYPE', 'required' => true],
        // Each colour of the order with its sizes (user, 2026-11-22): the colour is needed, the sizes (Size master) may wait.
        ['code' => '01', 'key' => 'order_color', 'label' => 'Order Color', 'type' => 'colorsize', 'source' => 'colours', 'width' => 150, 'required' => true],
        ['code' => '02', 'key' => 'fabric_type', 'label' => 'Fabric Type', 'type' => 'dropdown', 'width' => 130, 'source' => 'pb:FABRIC_TYPE', 'required' => true],
        ['code' => '02', 'key' => 'fabric_composition', 'label' => 'Fabric Composition', 'type' => 'dropdown', 'width' => 150, 'source' => 'pb:FABRIC_COMPOSITION'],
        ['code' => '02', 'key' => 'fabrication', 'label' => 'Fabrication', 'type' => 'longtext', 'width' => 220, 'formula' => 'JOIN(" ", [Fabric Type], [Fabric Composition], [GSM])'],
        ['code' => '02', 'key' => 'gsm', 'label' => 'GSM', 'type' => 'dropdown', 'width' => 80, 'source' => 'pb:GSM', 'required' => true],
        ['code' => '02', 'key' => 'sustainability', 'label' => 'Sustainability', 'type' => 'dropdown', 'width' => 110, 'source' => 'pb:SUSTAINABILITY'],
        ['code' => '02', 'key' => 'parts', 'label' => 'Parts', 'type' => 'dropdown', 'width' => 110, 'source' => 'pb:PARTS', 'required' => true],
        ['code' => '02', 'key' => 'yarn_type', 'label' => 'Yarn Type', 'type' => 'dropdown', 'width' => 160, 'source' => 'pb:YARN_TYPE', 'required' => true, 'applies' => 'KNIT'],
        ['code' => '02', 'key' => 'yarn_count', 'label' => 'Yarn Count', 'type' => 'dropdown', 'width' => 90, 'source' => 'pb:YARN_COUNT', 'required' => true, 'applies' => 'KNIT'],
        ['code' => '02', 'key' => 'composition', 'label' => 'Yarn Composition', 'type' => 'dropdown', 'width' => 160, 'source' => 'pb:YARN_COMPOSITION', 'required' => true, 'applies' => 'KNIT'],
        ['code' => '02', 'key' => 'fabric_mill', 'label' => 'Fabric Mill', 'type' => 'dropdown', 'width' => 140, 'source' => 'suppliers', 'applies' => 'WOVEN'],
        ['code' => '02', 'key' => 'width', 'label' => 'Width', 'type' => 'text', 'width' => 80, 'applies' => 'WOVEN'],
        ['code' => '02', 'key' => 'additional_process', 'label' => 'Additional Process', 'type' => 'dropdown', 'width' => 140, 'source' => 'pb:ADDITIONAL_PROCESS'],
        ['code' => '03', 'key' => 'quantity_ticket', 'label' => 'Quantity (Ticket)', 'type' => 'number', 'width' => 90, 'decimals' => 0, 'required' => true, 'kpi' => 'SUM'],
        ['code' => '03', 'key' => 'pac', 'label' => 'PAC', 'type' => 'number', 'width' => 60, 'decimals' => 0, 'required' => true],
        ['code' => '03', 'key' => 'order_qty', 'label' => 'Order Qty', 'type' => 'number', 'width' => 90, 'decimals' => 0, 'formula' => '[Quantity (Ticket)] * [PAC]', 'kpi' => 'SUM'],
        ['code' => '03', 'key' => 'fob_price', 'label' => 'FOB Price', 'type' => 'money', 'width' => 80, 'prefix' => '$', 'decimals' => 4, 'required' => true],
        ['code' => '03', 'key' => 'fob_value', 'label' => 'FOB Value', 'type' => 'money', 'width' => 110, 'prefix' => '$', 'decimals' => 2, 'formula' => '[Order Qty] * [FOB Price]', 'kpi' => 'SUM'],
        ['code' => '03', 'key' => 'quantity_pcs', 'label' => 'Quantity (PCS)', 'type' => 'number', 'width' => 90, 'decimals' => 0, 'required' => true, 'kpi' => 'SUM'],
        ['code' => '03', 'key' => 'cutting_percent', 'label' => 'Cutting %', 'type' => 'percent', 'width' => 70, 'suffix' => '%', 'decimals' => 2, 'required' => true],
        ['code' => '03', 'key' => 'qty_with_percent', 'label' => 'Qty with %', 'type' => 'number', 'width' => 90, 'decimals' => 2, 'formula' => '[Quantity (PCS)] * (1 + [Cutting %] / 100)', 'kpi' => 'SUM'],
        ['code' => '04', 'key' => 'cad_consumption', 'label' => 'CAD Consumption (per Dz)', 'type' => 'number', 'width' => 90, 'decimals' => 3, 'required' => true],
        ['code' => '04', 'key' => 'process_loss_percent', 'label' => 'Process Loss %', 'type' => 'percent', 'width' => 70, 'suffix' => '%', 'decimals' => 2, 'required' => true],
        ['code' => '04', 'key' => 'cad_with_loss', 'label' => 'CAD with Process Loss', 'type' => 'number', 'width' => 90, 'decimals' => 3, 'formula' => '[CAD Consumption (per Dz)] * (1 + [Process Loss %] / 100)'],
        ['code' => '04', 'key' => 'feeding_percent', 'label' => 'Feeding %', 'type' => 'percent', 'width' => 70, 'suffix' => '%', 'decimals' => 2, 'required' => true],
        ['code' => '04', 'key' => 'sample_qty', 'label' => 'Sample Qty', 'type' => 'number', 'width' => 80, 'decimals' => 3, 'kpi' => 'SUM'],
        ['code' => '04', 'key' => 'bulk_qty', 'label' => 'Bulk Qty', 'type' => 'number', 'width' => 100, 'decimals' => 3, 'formula' => '[CAD with Process Loss] / 12 * [Feeding %] / 100 * [Qty with %]', 'kpi' => 'SUM'],
        ['code' => '04', 'key' => 'total_req', 'label' => 'Total Req', 'type' => 'number', 'width' => 100, 'decimals' => 3, 'formula' => 'N([Sample Qty]) + [Bulk Qty]', 'kpi' => 'SUM', 'rules' => [['op' => 'any', 'tone' => 'blue']]],
        ['code' => '04', 'key' => 'entry_date', 'label' => 'Entry Date', 'type' => 'system', 'width' => 90],
    ];

    /** Where `erp:save-column-setup` keeps the saved Requisition Column Setup. */
    public static function snapshotPath(): string
    {
        return dirname(__DIR__).'/Database/Data/requisition_column_setup.json';
    }

    /** The company's requisition sheet, made from the saved setup (else this catalog) if it has none yet. */
    public static function ensure(string $companyId): void
    {
        if (PbColumn::query()->where('company_id', $companyId)->where('sheet', 'REQ')->exists()) {
            return;
        }
        DB::transaction(function () use ($companyId): void {
            // Two first page loads at once: the company row is the lock, so only one makes the sheet.
            DB::table('companies')->where('id', $companyId)->lockForUpdate()->value('id');
            if (PbColumn::query()->where('company_id', $companyId)->where('sheet', 'REQ')->exists()) {
                return;
            }
            // The factory's saved Column Setup, when there is one, rather than this catalog.
            if ($saved = ColumnSetupSnapshot::read(self::snapshotPath())) {
                ColumnSetupSnapshot::restore($companyId, $saved, PbGroup::class, PbColumn::class, 'REQ');

                return;
            }
            $groups = [];
            foreach (self::GROUPS as $i => $g) {
                $groups[$g['code']] = PbGroup::query()->create(['company_id' => $companyId, 'code' => $g['code'], 'name' => $g['name'], 'tone' => $g['tone'] ?? null, 'sort_order' => ($i + 1) * 10, 'is_system' => true, 'active' => true])->id;
            }
            $byLabel = array_column(self::COLUMNS, 'key', 'label');
            foreach (self::COLUMNS as $i => $c) {
                PbColumn::query()->create([
                    'company_id' => $companyId, 'group_id' => $groups[$c['code']], 'key' => $c['key'], 'label' => $c['label'], 'sort_order' => ($i + 1) * 10,
                    'type' => $c['type'], 'source' => $c['source'] ?? null, 'multiple' => false, 'prefix' => $c['prefix'] ?? null, 'suffix' => $c['suffix'] ?? null,
                    'decimals' => $c['decimals'] ?? null, 'width' => $c['width'] ?? 130, 'vertical' => $c['vertical'] ?? false, 'frozen' => $c['frozen'] ?? false,
                    'required' => $c['required'] ?? false, 'applies_to' => $c['applies'] ?? null,
                    'formula' => isset($c['formula']) ? Formula::stored($c['formula'], fn (string $label) => $byLabel[$label] ?? null) : null,
                    'color_rules' => $c['rules'] ?? null, 'text_tone' => $c['text'] ?? null, 'kpi' => $c['kpi'] ?? null, 'is_system' => true,
                ]);
            }
        });
    }
}
