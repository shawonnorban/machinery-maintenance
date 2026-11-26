<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Support;

use App\Modules\OrderSummary\Models\OsColumn;
use App\Modules\OrderSummary\Models\OsGroup;
use App\Shared\Formula\Formula;
use App\Shared\Support\ColumnSetupSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Order Summary's starting sheet (AnnotechERP 32): Norban's 18 groups and 160
 * columns (config/order_summary.php there), with the formulas Norban's
 * screen worked out — written here in the formula language, so from now on
 * they are the company's data, changed in Column Setup, never in code.
 * Generated from Norban's config; Column Setup owns them after the first
 * read (a company gets them the first time it opens Order Summary).
 *
 * Column: code (group), key, label, type, width, and optionally source,
 * multiple, prefix, suffix, decimals, vertical, formula ([Label] form),
 * rules (colour rules), text (a fixed text colour), kpi (SUM / AVG in the
 * totals row over the filtered rows, like Excel's SUBTOTAL).
 */
final class ColumnCatalog
{
    public const TYPES = ['text', 'longtext', 'number', 'money', 'percent', 'days', 'date', 'dropdown', 'multi', 'pairs', 'image', 'system'];

    /** Lists a dropdown / multi column may pick from, besides any lookup table (lookup:<CODE>). */
    public const SOURCES = ['buyers', 'factories', 'items', 'colours', 'fabric_types', 'compositions', 'suppliers', 'merchandisers'];

    public const TONES = ['green', 'yellow', 'lightred', 'red', 'brightgreen', 'highlight', 'peach', 'blue', 'orange', 'pink', 'purple', 'gray', 'cyan'];

    /** A column's own text colour, whatever its value (Excel's fixed font colours: TP Rcvd orange, Shipout green). */
    public const TEXT_TONES = ['orange', 'green', 'navy', 'red'];

    public const RULE_OPS = ['any', 'blank', 'eq', 'neq', 'contains', 'lt', 'lte', 'gt', 'gte', 'between'];

    public const GROUPS = [
        ['code' => '01', 'name' => '01. Style Detail'],
        ['code' => '02', 'name' => '02. PO Detail'],
        ['code' => '03', 'name' => '03. Order Qty'],
        ['code' => '04', 'name' => '04. Delay Summary', 'tone' => 'cyan'],
        ['code' => '05', 'name' => '05. Shipment Date'],
        ['code' => '06', 'name' => '06. Styling Detail'],
        ['code' => '07', 'name' => '07. Fabric Details'],
        ['code' => '08', 'name' => '08. Marketing Lead Time'],
        ['code' => '09', 'name' => '09. Price and Purchase %'],
        ['code' => '10', 'name' => '10. Yarn Status'],
        ['code' => '11', 'name' => '11. Fabric Status'],
        ['code' => '12', 'name' => '12. Trims Status'],
        ['code' => '13', 'name' => '13. Sample Status', 'tone' => 'pink'],
        ['code' => '14', 'name' => '14. Production File Status', 'tone' => 'pink'],
        ['code' => '15', 'name' => '15. Bulk Hanger Status', 'tone' => 'pink'],
        ['code' => '16', 'name' => '16. Production Status', 'tone' => 'pink'],
        ['code' => '17', 'name' => '17. Shipment Status'],
        ['code' => '18', 'name' => '18. Merchandising Team'],
    ];

    public const COLUMNS = [
        ['code' => '01', 'key' => 'serial_no', 'label' => 'Serial No', 'type' => 'system', 'width' => 70],
        ['code' => '01', 'key' => 'factory', 'label' => 'Factory', 'type' => 'dropdown', 'width' => 100, 'source' => 'factories'],
        ['code' => '01', 'key' => 'buyer', 'label' => 'Buyer', 'type' => 'dropdown', 'width' => 140, 'source' => 'buyers'],
        ['code' => '01', 'key' => 'department', 'label' => 'Department', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:GARMENT_DEPARTMENT'],
        ['code' => '01', 'key' => 'brand', 'label' => 'Brand', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:BRAND'],
        ['code' => '01', 'key' => 'class', 'label' => 'Class', 'type' => 'dropdown', 'width' => 100, 'source' => 'lookup:OS_CLASS'],
        ['code' => '01', 'key' => 'order_type', 'label' => 'Order Type', 'type' => 'dropdown', 'width' => 34, 'source' => 'pb:ORDER_TYPE', 'vertical' => true, 'rules' => [['op' => 'contains', 'value' => 'Liability', 'tone' => 'red'], ['op' => 'contains', 'value' => 'Cancel', 'tone' => 'lightred'], ['op' => 'contains', 'value' => 'Block', 'tone' => 'yellow'], ['op' => 'contains', 'value' => 'Confirmed', 'tone' => 'green']]],
        ['code' => '01', 'key' => 'tp_rcvd_date', 'label' => 'TP Rcvd Date', 'type' => 'date', 'width' => 34, 'vertical' => true, 'text' => 'orange'],
        ['code' => '01', 'key' => 'artwork', 'label' => 'Artwork', 'type' => 'image', 'width' => 80],
        ['code' => '01', 'key' => 'item', 'label' => 'Item', 'type' => 'dropdown', 'width' => 140, 'source' => 'pb:ITEM'],
        ['code' => '01', 'key' => 'smv', 'label' => 'SMV', 'type' => 'number', 'width' => 120, 'decimals' => 2],
        ['code' => '01', 'key' => 'season_quarter', 'label' => 'Season / Quarter', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:SEASON'],
        ['code' => '01', 'key' => 'style_number_name', 'label' => 'Style Number / Name', 'type' => 'multi', 'width' => 140, 'multiple' => false, 'frozen' => true],
        ['code' => '01', 'key' => 'job_no', 'label' => 'JOB No', 'type' => 'multi', 'width' => 140, 'multiple' => false, 'bold' => true],
        ['code' => '01', 'key' => 'last_update', 'label' => 'Last Update Date', 'type' => 'system', 'width' => 34, 'vertical' => true, 'text' => 'orange'],
        ['code' => '02', 'key' => 'commercial_contract_number', 'label' => 'Commercial Contract Number', 'type' => 'multi', 'width' => 140, 'multiple' => true],
        ['code' => '02', 'key' => 'po_number', 'label' => 'PO Number', 'type' => 'multi', 'width' => 180, 'multiple' => true],
        ['code' => '02', 'key' => 'shipment_id_number', 'label' => 'Shipment ID Number', 'type' => 'multi', 'width' => 140, 'multiple' => true],
        // Colours with their quantity, as Dev Sample's Color with Qty (user, 2026-11-22).
        ['code' => '02', 'key' => 'colors', 'label' => 'Colors', 'type' => 'pairs', 'reduce' => 'SUM', 'decimals' => 0, 'suffix' => 'Pcs', 'width' => 170, 'source' => 'colours'],
        ['code' => '03', 'key' => 'quantity_ticket', 'label' => 'Quantity (Ticket)', 'type' => 'number', 'width' => 160, 'suffix' => 'Tkt', 'decimals' => 0, 'kpi' => 'SUM', 'bold' => true],
        ['code' => '03', 'key' => 'pac', 'label' => 'PAC', 'type' => 'number', 'width' => 100, 'suffix' => 'Pac', 'decimals' => 0],
        ['code' => '03', 'key' => 'quantity_pcs', 'label' => 'Quantity (PCS)', 'type' => 'number', 'width' => 120, 'suffix' => 'Pcs', 'decimals' => 0, 'formula' => '[Quantity (Ticket)] * [PAC]', 'kpi' => 'SUM', 'bold' => true],
        ['code' => '04', 'key' => 'delay_planning_inspection', 'label' => 'Delay Planning Inspection', 'type' => 'text', 'width' => 140],
        ['code' => '04', 'key' => 'delay_rfi_proposed', 'label' => 'Delay RFI Proposed', 'type' => 'date', 'width' => 130],
        ['code' => '04', 'key' => 'delay_ldd_proposed', 'label' => 'Delay LDD Proposed', 'type' => 'date', 'width' => 130],
        ['code' => '04', 'key' => 'buyer_feedback_on_delay', 'label' => 'Buyer Feedback On Delay', 'type' => 'longtext', 'width' => 140],
        ['code' => '04', 'key' => 'reprint_date', 'label' => 'Reprint Date', 'type' => 'date', 'width' => 130, 'text' => 'green'],
        ['code' => '04', 'key' => 'reprint_ldd_etd', 'label' => 'Reprint LDD/ETD', 'type' => 'date', 'width' => 130],
        ['code' => '04', 'key' => 'reprint_rfi_sd', 'label' => 'Reprint RFI/SD', 'type' => 'date', 'width' => 130, 'formula' => 'ADD_DAYS([Reprint LDD/ETD], -10)', 'rules' => [['op' => 'any', 'tone' => 'blue']]],
        ['code' => '04', 'key' => 'reprint_days_left', 'label' => 'Reprint Days Left', 'type' => 'days', 'width' => 140, 'suffix' => 'Days', 'decimals' => 0, 'formula' => 'DAYS([Reprint RFI/SD], TODAY())', 'rules' => [['op' => 'lte', 'value' => '0', 'tone' => 'red'], ['op' => 'lt', 'value' => '15', 'tone' => 'lightred'], ['op' => 'lt', 'value' => '30', 'tone' => 'highlight']]],
        ['code' => '04', 'key' => 'reprint_shipment_mode', 'label' => 'Reprint Shipment Mode', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_SHIPMENT_MODE'],
        ['code' => '04', 'key' => 'discount_penalty_percent', 'label' => 'Discount/Penalty %', 'type' => 'percent', 'width' => 140, 'suffix' => '%', 'decimals' => 2, 'kpi' => 'AVG'],
        ['code' => '04', 'key' => 'discount_value', 'label' => 'Discount Value', 'type' => 'money', 'width' => 160, 'prefix' => '$', 'decimals' => 2, 'formula' => '[FOB Value] * [Discount/Penalty %] / 100', 'kpi' => 'SUM'],
        ['code' => '05', 'key' => 'po_wise_ldd_etd', 'label' => 'PO wise LDD/ETD', 'type' => 'date', 'width' => 34, 'vertical' => true],
        ['code' => '05', 'key' => 'po_wise_rfi_sd', 'label' => 'PO wise RFI/SD', 'type' => 'date', 'width' => 130, 'formula' => 'ADD_DAYS([PO wise LDD/ETD], -10)', 'rules' => [['op' => 'any', 'tone' => 'blue']]],
        ['code' => '05', 'key' => 'days_left_in_hand', 'label' => 'Days Left In Hand', 'type' => 'days', 'width' => 34, 'suffix' => 'Days', 'decimals' => 0, 'vertical' => true, 'formula' => 'DAYS([PO wise RFI/SD], TODAY())', 'rules' => [['op' => 'lte', 'value' => '0', 'tone' => 'red'], ['op' => 'lt', 'value' => '15', 'tone' => 'lightred'], ['op' => 'lt', 'value' => '30', 'tone' => 'highlight']]],
        ['code' => '05', 'key' => 'shipment_mode', 'label' => 'Shipment Mode', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_SHIPMENT_MODE'],
        ['code' => '05', 'key' => 'shipout_date', 'label' => 'Shipout Date', 'type' => 'date', 'width' => 130, 'text' => 'green'],
        ['code' => '05', 'key' => 'shipout_condition', 'label' => 'Shipout Condition', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_SHIPOUT_CONDITION'],
        ['code' => '05', 'key' => 'shipment_status', 'label' => 'Shipment Status', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_SHIPMENT_STATUS', 'rules' => [['op' => 'contains', 'value' => 'Shipment Pending', 'tone' => 'lightred'], ['op' => 'contains', 'value' => 'Ship Out', 'tone' => 'brightgreen']]],
        ['code' => '06', 'key' => 'style_description', 'label' => 'Style Description', 'type' => 'longtext', 'width' => 220],
        ['code' => '06', 'key' => 'embellishments', 'label' => 'Embellishments', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_EMBELLISHMENT'],
        ['code' => '06', 'key' => 'zipper', 'label' => 'Zipper', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_ZIPPER'],
        ['code' => '06', 'key' => 'button', 'label' => 'Button', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_BUTTON'],
        ['code' => '06', 'key' => 'woven_trim', 'label' => 'Woven Trim', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_WOVEN_TRIM'],
        ['code' => '06', 'key' => 'import_item', 'label' => 'Import Item', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_IMPORT_ITEM'],
        ['code' => '06', 'key' => 'fabric_category', 'label' => 'Fabric Category', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_FABRIC_CATEGORY'],
        ['code' => '06', 'key' => 'fabric_type', 'label' => 'Fabric Type', 'type' => 'dropdown', 'width' => 140, 'source' => 'pb:FABRIC_TYPE'],
        ['code' => '07', 'key' => 'gsm', 'label' => 'GSM', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_GSM'],
        ['code' => '07', 'key' => 'composition', 'label' => 'Composition', 'type' => 'dropdown', 'width' => 140, 'source' => 'pb:FABRIC_COMPOSITION'],
        ['code' => '07', 'key' => 'fabric_details', 'label' => 'Fabric Details', 'type' => 'longtext', 'width' => 280, 'formula' => 'JOIN(" ", [Fabric Type], [Composition], [GSM])'],
        ['code' => '07', 'key' => 'fabric_description', 'label' => 'Fabric Description', 'type' => 'longtext', 'width' => 200],
        ['code' => '07', 'key' => 'dye_type_solid_or_yd', 'label' => 'Dye Type Solid Or YD', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_DYE_TYPE'],
        ['code' => '07', 'key' => 'fabric_finish_type', 'label' => 'Fabric Finish Type', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_FINISH_TYPE'],
        ['code' => '07', 'key' => 'aop', 'label' => 'AOP', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_AOP'],
        ['code' => '07', 'key' => 'wash_info_at_garmenting', 'label' => 'Wash Info At Garmenting', 'type' => 'dropdown', 'width' => 140, 'source' => 'pb:WASH_TYPE'],
        ['code' => '07', 'key' => 'sustainability', 'label' => 'Sustainability', 'type' => 'dropdown', 'width' => 140, 'source' => 'pb:SUSTAINABILITY'],
        ['code' => '08', 'key' => 'nego_order_confm_date', 'label' => 'Nego. Order Confm. Date', 'type' => 'date', 'width' => 130],
        ['code' => '08', 'key' => 'yarn_lead_time_days', 'label' => 'Yarn Lead Time (Days)', 'type' => 'days', 'width' => 120, 'suffix' => 'Days', 'decimals' => 0],
        ['code' => '08', 'key' => 'yarn_inhouse_req', 'label' => 'Yarn Inhouse Req.', 'type' => 'date', 'width' => 130, 'formula' => 'ADD_DAYS([Nego. Order Confm. Date], [Yarn Lead Time (Days)])'],
        ['code' => '08', 'key' => 'textile_lead_time_days', 'label' => 'Textile Lead Time (Days)', 'type' => 'days', 'width' => 120, 'suffix' => 'Days', 'decimals' => 0],
        ['code' => '08', 'key' => 'fab_inhouse_req', 'label' => 'Fab. Inhouse Req.', 'type' => 'date', 'width' => 130, 'formula' => 'ADD_DAYS([Yarn Inhouse Req.], [Textile Lead Time (Days)])'],
        ['code' => '08', 'key' => 'print_embr_time_days', 'label' => 'Print/Embr. Time (Days)', 'type' => 'days', 'width' => 120, 'suffix' => 'Days', 'decimals' => 0],
        ['code' => '08', 'key' => 'garment_lead_time_days', 'label' => 'Garment Lead Time (Days)', 'type' => 'days', 'width' => 120, 'suffix' => 'Days', 'decimals' => 0],
        ['code' => '08', 'key' => 'add_ldd_rfi_time_days', 'label' => 'Add LDD-RFI Time (Days)', 'type' => 'days', 'width' => 120, 'suffix' => 'Days', 'decimals' => 0],
        ['code' => '08', 'key' => 'total_lead_time_days', 'label' => 'Total Lead Time (Days)', 'type' => 'days', 'width' => 120, 'suffix' => 'Days', 'decimals' => 0, 'formula' => 'SUM([Yarn Lead Time (Days)], [Textile Lead Time (Days)], [Print/Embr. Time (Days)], [Garment Lead Time (Days)], [Add LDD-RFI Time (Days)])'],
        ['code' => '08', 'key' => 'proposed_ldd_etd', 'label' => 'Proposed LDD/ETD', 'type' => 'date', 'width' => 130, 'formula' => 'ADD_DAYS([Nego. Order Confm. Date], [Total Lead Time (Days)])'],
        ['code' => '08', 'key' => 'actual_order_confirm_date', 'label' => 'Actual Order Confirm Date', 'type' => 'date', 'width' => 130],
        ['code' => '08', 'key' => 'actual_leadtime_vs_confirm', 'label' => 'Actual Leadtime vs Confirm', 'type' => 'days', 'width' => 140, 'suffix' => 'Days', 'decimals' => 0, 'formula' => 'DAYS([PO wise RFI/SD], [Actual Order Confirm Date])', 'rules' => [['op' => 'lt', 'value' => '0', 'tone' => 'lightred']]],
        ['code' => '08', 'key' => 'po_need_date_vs_act_rfi', 'label' => 'PO Need Date vs Act RFI', 'type' => 'date', 'width' => 130, 'formula' => 'ADD_DAYS([PO wise RFI/SD], -[Total Lead Time (Days)])'],
        ['code' => '08', 'key' => 'actual_po_issue_date', 'label' => 'Actual PO Issue Date', 'type' => 'date', 'width' => 130],
        ['code' => '08', 'key' => 'act_leadtime_vs_po', 'label' => 'Act. Leadtime vs PO', 'type' => 'days', 'width' => 160, 'suffix' => 'Days', 'decimals' => 0, 'formula' => 'DAYS([PO wise RFI/SD], [Actual PO Issue Date])', 'rules' => [['op' => 'lt', 'value' => '0', 'tone' => 'lightred']]],
        ['code' => '09', 'key' => 'fob', 'label' => 'FOB', 'type' => 'money', 'width' => 120, 'prefix' => '$', 'decimals' => 2, 'bold' => true],
        ['code' => '09', 'key' => 'fob_value', 'label' => 'FOB Value', 'type' => 'money', 'width' => 160, 'prefix' => '$', 'decimals' => 2, 'formula' => '[FOB] * [Quantity (PCS)]', 'kpi' => 'SUM', 'bold' => true],
        ['code' => '09', 'key' => 'price_status', 'label' => 'Price Status', 'type' => 'dropdown', 'width' => 34, 'source' => 'lookup:OS_PRICE_STATUS', 'vertical' => true, 'rules' => [['op' => 'eq', 'value' => 'OFFER PENDING', 'tone' => 'red'], ['op' => 'eq', 'value' => 'NOT CONFIRMED', 'tone' => 'lightred'], ['op' => 'eq', 'value' => 'CONFIRMED', 'tone' => 'green']]],
        ['code' => '09', 'key' => 'retail_price', 'label' => 'Retail Price', 'type' => 'money', 'width' => 140, 'prefix' => '$', 'decimals' => 2],
        ['code' => '09', 'key' => 'avg_yarn_price_per_kg', 'label' => 'Avg Yarn Price/Kg', 'type' => 'money', 'width' => 140, 'prefix' => '$', 'decimals' => 2],
        ['code' => '09', 'key' => 'yarn_value_as_per_requirement', 'label' => 'Yarn Value As Per Requirement', 'type' => 'money', 'width' => 160, 'prefix' => '$', 'decimals' => 2, 'formula' => '[Avg Yarn Price/Kg] * [Total Yarn Requirement (KG)]', 'kpi' => 'SUM'],
        ['code' => '09', 'key' => 'yarn_value_percent', 'label' => 'Yarn Value %', 'type' => 'percent', 'width' => 120, 'suffix' => '%', 'decimals' => 2, 'formula' => '[Yarn Value As Per Requirement] / [FOB Value] * 100'],
        ['code' => '09', 'key' => 'yarn_purchase_balance_value', 'label' => 'Yarn Purchase Balance Value', 'type' => 'money', 'width' => 160, 'prefix' => '$', 'decimals' => 2, 'formula' => '[Avg Yarn Price/Kg] * [Yarn Purchase Balance (KG)]', 'kpi' => 'SUM'],
        ['code' => '09', 'key' => 'trims_cost_per_pc', 'label' => 'Trims Cost/Pc', 'type' => 'money', 'width' => 120, 'prefix' => '$', 'decimals' => 2],
        ['code' => '09', 'key' => 'trims_value', 'label' => 'Trims Value', 'type' => 'money', 'width' => 120, 'prefix' => '$', 'decimals' => 2, 'formula' => '[Trims Cost/Pc] * [Quantity (PCS)]', 'kpi' => 'SUM'],
        ['code' => '09', 'key' => 'trims_value_percent', 'label' => 'Trims Value %', 'type' => 'percent', 'width' => 120, 'suffix' => '%', 'decimals' => 2, 'formula' => '[Trims Value] / [FOB Value] * 100', 'kpi' => 'AVG'],
        ['code' => '09', 'key' => 'short_order_status', 'label' => 'Short Order Status', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_SHORT_ORDER_STATUS'],
        ['code' => '10', 'key' => 'yarn_count_yarn_composition', 'label' => 'Yarn Count & Yarn Composition', 'type' => 'text', 'width' => 140],
        ['code' => '10', 'key' => 'gross_consump_kg_doz', 'label' => 'Gross Consump. (KG/Doz)', 'type' => 'number', 'width' => 140, 'suffix' => 'Kg/Doz', 'decimals' => 2],
        ['code' => '10', 'key' => 'knitting_dyeing_process_loss_percent', 'label' => 'Knitting Dyeing Process Loss %', 'type' => 'percent', 'width' => 140, 'suffix' => '%', 'decimals' => 2],
        ['code' => '10', 'key' => 'cutting_percent', 'label' => 'Cutting %', 'type' => 'percent', 'width' => 100, 'suffix' => '%', 'decimals' => 2],
        ['code' => '10', 'key' => 'cutting_req_quantity_pcs', 'label' => 'Cutting Req. Quantity (PCS)', 'type' => 'number', 'width' => 160, 'suffix' => 'pcs', 'decimals' => 0, 'formula' => '[Quantity (PCS)] + [Quantity (PCS)] * [Cutting %] / 100', 'kpi' => 'SUM'],
        ['code' => '10', 'key' => 'total_yarn_requirement_kg', 'label' => 'Total Yarn Requirement (KG)', 'type' => 'number', 'width' => 140, 'suffix' => 'Kg', 'decimals' => 2, 'formula' => '[Gross Consump. (KG/Doz)] * (1 + N([Knitting Dyeing Process Loss %]) / 100) * [Cutting Req. Quantity (PCS)] / 12', 'kpi' => 'SUM'],
        ['code' => '10', 'key' => 'yarn_purchased_quantity_kg', 'label' => 'Yarn Purchased Quantity (KG)', 'type' => 'number', 'width' => 160, 'suffix' => 'Kg', 'decimals' => 2, 'kpi' => 'SUM'],
        ['code' => '10', 'key' => 'yarn_purchase_balance_kg', 'label' => 'Yarn Purchase Balance (KG)', 'type' => 'number', 'width' => 140, 'suffix' => 'Kg', 'decimals' => 2, 'formula' => '[Yarn Purchased Quantity (KG)] - [Total Yarn Requirement (KG)]', 'kpi' => 'SUM'],
        ['code' => '10', 'key' => 'yarn_mill_name', 'label' => 'Yarn Mill Name', 'type' => 'dropdown', 'width' => 140, 'source' => 'suppliers'],
        ['code' => '10', 'key' => 'yarn_booking_date', 'label' => 'Yarn Booking Date', 'type' => 'date', 'width' => 130],
        ['code' => '10', 'key' => 'yarn_status', 'label' => 'Yarn Status', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_YARN_STATUS', 'rules' => [['op' => 'contains', 'value' => 'Allocation Done', 'tone' => 'green'], ['op' => 'contains', 'value' => 'Inhouse Pending', 'tone' => 'yellow'], ['op' => 'contains', 'value' => 'Booking Pending', 'tone' => 'lightred']]],
        ['code' => '11', 'key' => 'woven_fab_booking_date', 'label' => 'Woven Fab. Booking Date', 'type' => 'date', 'width' => 160],
        ['code' => '11', 'key' => 'woven_fabric_mill_name', 'label' => 'Woven Fabric Mill Name', 'type' => 'dropdown', 'width' => 140, 'source' => 'suppliers'],
        ['code' => '11', 'key' => 'woven_inhouse_plan_date', 'label' => 'Woven Inhouse Plan Date', 'type' => 'date', 'width' => 130],
        ['code' => '11', 'key' => 'woven_inhouse_actual_date', 'label' => 'Woven Inhouse Actual Date', 'type' => 'date', 'width' => 130],
        ['code' => '11', 'key' => 'fabric_booking_date', 'label' => 'Fabric Booking Date', 'type' => 'date', 'width' => 130],
        ['code' => '11', 'key' => 'fabric_status', 'label' => 'Fabric Status', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_FABRIC_STATUS', 'rules' => [['op' => 'contains', 'value' => 'Delivery Done', 'tone' => 'green'], ['op' => 'contains', 'value' => 'FFP APPROVED', 'tone' => 'green'], ['op' => 'contains', 'value' => 'Booking DONE', 'tone' => 'yellow'], ['op' => 'contains', 'value' => 'Booking PENDING', 'tone' => 'lightred']]],
        ['code' => '11', 'key' => 'fabric_closing_plan_date', 'label' => 'Fabric Closing Plan Date', 'type' => 'date', 'width' => 130],
        ['code' => '11', 'key' => 'fabric_delay_from_pcd_days', 'label' => 'Fabric Delay From PCD (Days)', 'type' => 'days', 'width' => 140, 'suffix' => 'Days', 'decimals' => 0, 'formula' => 'DAYS([Fabric Closing Plan Date], [PCD])', 'rules' => [['op' => 'lt', 'value' => '0', 'tone' => 'lightred']]],
        ['code' => '12', 'key' => 'trims_booking_date', 'label' => 'Trims Booking Date', 'type' => 'date', 'width' => 130],
        ['code' => '12', 'key' => 'trims_status', 'label' => 'Trims Status', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_TRIM_STATUS', 'rules' => [['op' => 'contains', 'value' => 'Detail Pending', 'tone' => 'purple'], ['op' => 'contains', 'value' => 'Alert', 'tone' => 'red'], ['op' => 'contains', 'value' => 'ALL Trims Inhouse', 'tone' => 'green'], ['op' => 'contains', 'value' => 'Trims Booking DONE', 'tone' => 'yellow'], ['op' => 'contains', 'value' => 'PENDING Trims Booking', 'tone' => 'lightred'], ['op' => 'contains', 'value' => 'Pending Booking', 'tone' => 'lightred'], ['op' => 'contains', 'value' => 'Booking DONE', 'tone' => 'green']]],
        ['code' => '13', 'key' => 'base_hanger_submit_date', 'label' => 'Base Hanger Submit Date', 'type' => 'date', 'width' => 130],
        ['code' => '13', 'key' => 'nis_submit_date', 'label' => 'NIS Submit Date', 'type' => 'date', 'width' => 130],
        ['code' => '13', 'key' => 'base_hanger_test_date', 'label' => 'Base Hanger Test Date', 'type' => 'date', 'width' => 130],
        ['code' => '13', 'key' => 'base_hanger_approv_date', 'label' => 'Base Hanger Approve Date', 'type' => 'date', 'width' => 130],
        ['code' => '13', 'key' => 'base_hanger_status', 'label' => 'Base Hanger Status', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_BASE_HANGER_STATUS', 'rules' => [['op' => 'contains', 'value' => 'RESUBMITTED', 'tone' => 'peach'], ['op' => 'contains', 'value' => 'NOT Submitted', 'tone' => 'lightred'], ['op' => 'contains', 'value' => 'COMMENTS PENDING', 'tone' => 'yellow'], ['op' => 'contains', 'value' => 'REJECTED', 'tone' => 'red'], ['op' => 'contains', 'value' => 'APPROVED', 'tone' => 'green']]],
        ['code' => '13', 'key' => 'first_sample_submit_date', 'label' => '1st Sample Submit Date', 'type' => 'date', 'width' => 130],
        ['code' => '13', 'key' => 'first_sample_comnts_date', 'label' => '1st Sample Comments Date', 'type' => 'date', 'width' => 130],
        ['code' => '13', 'key' => 'proto_status', 'label' => '1st/Proto Status', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_PROTO_STATUS', 'rules' => [['op' => 'contains', 'value' => 'RESUBMITTED', 'tone' => 'peach'], ['op' => 'contains', 'value' => 'NOT Submitted', 'tone' => 'lightred'], ['op' => 'contains', 'value' => 'COMMENTS PENDING', 'tone' => 'yellow'], ['op' => 'contains', 'value' => 'REJECTED', 'tone' => 'red'], ['op' => 'contains', 'value' => 'APPROVED', 'tone' => 'green']]],
        ['code' => '13', 'key' => 'print_embr_soff_submit_date', 'label' => 'Print/Embr. S/Off Submit Date', 'type' => 'date', 'width' => 130],
        ['code' => '13', 'key' => 'print_embr_soff_comets_date', 'label' => 'Print/Embr. S/Off Comments Date', 'type' => 'date', 'width' => 130],
        ['code' => '13', 'key' => 'print_embr_soff_status', 'label' => 'Print/Embr. S/Off Status', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_SOFF_STATUS', 'rules' => [['op' => 'contains', 'value' => 'RESUBMITTED', 'tone' => 'peach'], ['op' => 'contains', 'value' => 'NOT Submitted', 'tone' => 'lightred'], ['op' => 'contains', 'value' => 'COMMENTS PENDING', 'tone' => 'yellow'], ['op' => 'contains', 'value' => 'REJECTED', 'tone' => 'red'], ['op' => 'contains', 'value' => 'APPROVED', 'tone' => 'green']]],
        ['code' => '13', 'key' => 'black_ss_fit_submit_date', 'label' => 'Black SS/Fit Submit Date', 'type' => 'date', 'width' => 130],
        ['code' => '13', 'key' => 'black_ss_fit_approv_date', 'label' => 'Black SS/Fit Approve Date', 'type' => 'date', 'width' => 130],
        ['code' => '13', 'key' => 'black_ss_fit_status', 'label' => 'Black SS/Fit Status', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_FIT_STATUS', 'rules' => [['op' => 'contains', 'value' => 'RESUBMITTED', 'tone' => 'peach'], ['op' => 'contains', 'value' => 'NOT Submitted', 'tone' => 'lightred'], ['op' => 'contains', 'value' => 'COMMENTS PENDING', 'tone' => 'yellow'], ['op' => 'contains', 'value' => 'REJECTED', 'tone' => 'red'], ['op' => 'contains', 'value' => 'APPROVED', 'tone' => 'green']]],
        ['code' => '13', 'key' => 'test_sample_status', 'label' => 'Test Sample Status', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_TEST_SAMPLE_STATUS', 'rules' => [['op' => 'contains', 'value' => 'RESUBMITTED', 'tone' => 'peach'], ['op' => 'contains', 'value' => 'NOT Submitted', 'tone' => 'lightred'], ['op' => 'contains', 'value' => 'COMMENTS PENDING', 'tone' => 'yellow'], ['op' => 'contains', 'value' => 'REJECTED', 'tone' => 'red'], ['op' => 'contains', 'value' => 'APPROVED', 'tone' => 'green']]],
        ['code' => '13', 'key' => 'gss_pp_submit_date', 'label' => 'GSS/PP Submit Date', 'type' => 'date', 'width' => 130],
        ['code' => '13', 'key' => 'gss_pp_comments_date', 'label' => 'GSS/PP Comments Date', 'type' => 'date', 'width' => 130],
        ['code' => '13', 'key' => 'gss_pp_status', 'label' => 'GSS/PP Status', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_GSS_PP_STATUS', 'rules' => [['op' => 'contains', 'value' => 'RESUBMITTED', 'tone' => 'peach'], ['op' => 'contains', 'value' => 'NOT Submitted', 'tone' => 'lightred'], ['op' => 'contains', 'value' => 'COMMENTS PENDING', 'tone' => 'yellow'], ['op' => 'contains', 'value' => 'REJECTED', 'tone' => 'red'], ['op' => 'contains', 'value' => 'APPROVED', 'tone' => 'green']]],
        ['code' => '13', 'key' => 'photo_press_send_date', 'label' => 'Photo/Press Send Date', 'type' => 'date', 'width' => 130],
        ['code' => '13', 'key' => 'shipping_sample_send_date', 'label' => 'Shipping Sample Send Date', 'type' => 'date', 'width' => 130],
        ['code' => '13', 'key' => 'ffp_status', 'label' => 'FFP Status', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_FFP_STATUS', 'rules' => [['op' => 'contains', 'value' => 'RESUBMITTED', 'tone' => 'peach'], ['op' => 'contains', 'value' => 'NOT Submitted', 'tone' => 'lightred'], ['op' => 'contains', 'value' => 'COMMENTS PENDING', 'tone' => 'yellow'], ['op' => 'contains', 'value' => 'REJECTED', 'tone' => 'red'], ['op' => 'contains', 'value' => 'APPROVED', 'tone' => 'green']]],
        ['code' => '13', 'key' => 'ffp_date', 'label' => 'FFP Date', 'type' => 'date', 'width' => 130],
        ['code' => '14', 'key' => 'prod_file_target', 'label' => 'Prod. File Target', 'type' => 'date', 'width' => 140, 'formula' => 'ADD_DAYS([PO wise RFI/SD], -35)'],
        ['code' => '14', 'key' => 'prod_file_handover_date', 'label' => 'Prod. File H/O Date', 'type' => 'date', 'width' => 130],
        ['code' => '14', 'key' => 'prod_file_status', 'label' => 'Prod. File Status', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_PROD_FILE_STATUS', 'rules' => [['op' => 'contains', 'value' => 'Given', 'tone' => 'green']]],
        ['code' => '15', 'key' => 'bulk_hangr_submit_date', 'label' => 'Bulk Hanger Submit Date', 'type' => 'date', 'width' => 130],
        ['code' => '15', 'key' => 'bulk_hangr_approv_date', 'label' => 'Bulk Hanger Approve Date', 'type' => 'date', 'width' => 130],
        ['code' => '15', 'key' => 'bulk_hangr_status', 'label' => 'Bulk Hanger Status', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_BULK_HANGER_STATUS', 'rules' => [['op' => 'contains', 'value' => 'RESUBMITTED', 'tone' => 'peach'], ['op' => 'contains', 'value' => 'NOT Submitted', 'tone' => 'lightred'], ['op' => 'contains', 'value' => 'COMMENTS PENDING', 'tone' => 'yellow'], ['op' => 'contains', 'value' => 'REJECTED', 'tone' => 'red'], ['op' => 'contains', 'value' => 'APPROVED', 'tone' => 'green']]],
        ['code' => '16', 'key' => 'pcd', 'label' => 'PCD', 'type' => 'date', 'width' => 130],
        ['code' => '16', 'key' => 'psd', 'label' => 'PSD', 'type' => 'date', 'width' => 130],
        ['code' => '16', 'key' => 'ped', 'label' => 'PED', 'type' => 'date', 'width' => 130],
        ['code' => '16', 'key' => 'garment_production_status', 'label' => 'Garment Production Status', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_GARMENT_PROD_STATUS', 'rules' => [['op' => 'contains', 'value' => 'Sewing ShortFALL', 'tone' => 'highlight'], ['op' => 'contains', 'value' => 'ShortFALL', 'tone' => 'red'], ['op' => 'contains', 'value' => 'Alert', 'tone' => 'red'], ['op' => 'contains', 'value' => 'Full Completed', 'tone' => 'green'], ['op' => 'contains', 'value' => 'Sewing Complete', 'tone' => 'green'], ['op' => 'contains', 'value' => 'Running', 'tone' => 'yellow'], ['op' => 'contains', 'value' => 'Not Started', 'tone' => 'lightred']]],
        ['code' => '16', 'key' => 'rfi_plan_date', 'label' => 'RFI Plan Date', 'type' => 'date', 'width' => 130],
        ['code' => '16', 'key' => 'rfi_diff_in_plan', 'label' => 'RFI Diff. In Plan', 'type' => 'days', 'width' => 160, 'suffix' => 'Days', 'decimals' => 0, 'formula' => 'DAYS([PO wise RFI/SD], [RFI Plan Date])', 'rules' => [['op' => 'lt', 'value' => '0', 'tone' => 'lightred']]],
        ['code' => '16', 'key' => 'rfi_plan_remarks', 'label' => 'RFI Plan Remarks', 'type' => 'longtext', 'width' => 220],
        ['code' => '16', 'key' => 'shipout_quantity_ticket', 'label' => 'Shipout Quantity (Ticket)', 'type' => 'number', 'width' => 160, 'suffix' => 'Tkt', 'decimals' => 0, 'kpi' => 'SUM'],
        ['code' => '16', 'key' => 'shipout_quantity_pcs', 'label' => 'Shipout Quantity (PCS)', 'type' => 'number', 'width' => 160, 'suffix' => 'pcs', 'decimals' => 0, 'kpi' => 'SUM'],
        ['code' => '16', 'key' => 'short_excess_ticket', 'label' => 'Short/Excess (Ticket)', 'type' => 'number', 'width' => 140, 'suffix' => 'Tkt', 'decimals' => 0, 'formula' => '[Quantity (Ticket)] - [Shipout Quantity (Ticket)]', 'kpi' => 'SUM'],
        ['code' => '17', 'key' => 'short_excess_pcs', 'label' => 'Short/Excess (PCS)', 'type' => 'number', 'width' => 140, 'suffix' => 'pcs', 'decimals' => 0, 'formula' => '[Quantity (PCS)] - [Shipout Quantity (PCS)]', 'kpi' => 'SUM'],
        ['code' => '17', 'key' => 'shipout_value_usd', 'label' => 'Shipout Value (USD)', 'type' => 'money', 'width' => 160, 'prefix' => '$', 'decimals' => 2, 'formula' => '[Shipout Quantity (Ticket)] * [FOB]', 'kpi' => 'SUM'],
        ['code' => '17', 'key' => 'final_discount_penalty_percent', 'label' => 'Final Discount/Penalty %', 'type' => 'percent', 'width' => 120, 'suffix' => '%', 'decimals' => 2],
        ['code' => '17', 'key' => 'discounted_fob_price', 'label' => 'Discounted FOB Price', 'type' => 'money', 'width' => 140, 'prefix' => '$', 'decimals' => 2, 'formula' => '[FOB] * (1 - [Final Discount/Penalty %] / 100)'],
        ['code' => '17', 'key' => 'discounted_shipout_value', 'label' => 'Discounted Shipout Value', 'type' => 'money', 'width' => 160, 'prefix' => '$', 'decimals' => 2, 'formula' => '[Shipout Quantity (Ticket)] * [Discounted FOB Price]', 'kpi' => 'SUM'],
        ['code' => '17', 'key' => 'total_discounted_value', 'label' => 'Total Discounted Value', 'type' => 'money', 'width' => 160, 'prefix' => '$', 'decimals' => 2, 'formula' => '[Discounted Shipout Value] - [Shipout Value (USD)]', 'kpi' => 'SUM'],
        ['code' => '17', 'key' => 'short_excess_ship_value', 'label' => 'Short/Excess Ship Value', 'type' => 'money', 'width' => 160, 'prefix' => '$', 'decimals' => 2, 'formula' => '[Short/Excess (Ticket)] * [FOB]', 'kpi' => 'SUM'],
        ['code' => '17', 'key' => 'reason_of_discount_penalty', 'label' => 'Reason of Discount/Penalty', 'type' => 'longtext', 'width' => 140],
        ['code' => '17', 'key' => 'shipout_commercial_contract_number', 'label' => 'Shipout Commercial Contract No', 'type' => 'text', 'width' => 140],
        ['code' => '17', 'key' => 'shipment_invoice_number', 'label' => 'Shipment Invoice Number', 'type' => 'text', 'width' => 140],
        ['code' => '17', 'key' => 'eta_date', 'label' => 'ETA Date', 'type' => 'date', 'width' => 130],
        ['code' => '17', 'key' => 'payment_rcvd_date', 'label' => 'Payment Rcvd Date', 'type' => 'date', 'width' => 130],
        ['code' => '17', 'key' => 'payment_rcvd_status', 'label' => 'Payment Rcvd Status', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_PAYMENT_STATUS', 'rules' => [['op' => 'contains', 'value' => 'Partial', 'tone' => 'yellow'], ['op' => 'contains', 'value' => 'Received', 'tone' => 'green'], ['op' => 'contains', 'value' => 'Pending', 'tone' => 'lightred']]],
        ['code' => '18', 'key' => 'marketing_merchandiser', 'label' => 'Marketing Merchandiser', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_MERCHANDISER'],
        ['code' => '18', 'key' => 'production_merchandiser', 'label' => 'Production Merchandiser', 'type' => 'dropdown', 'width' => 140, 'source' => 'lookup:OS_MERCHANDISER'],
        ['code' => '18', 'key' => 'buyer_merchandiser', 'label' => 'Buyer Merchandiser', 'type' => 'text', 'width' => 140], // typed in (user, 2026-11-22)
        ['code' => '18', 'key' => 'remarks', 'label' => 'Remarks', 'type' => 'longtext', 'width' => 220],
        // Where the order stands in Pre-Budget, worked out each time (OrderStatus; user, 2026-11-24).
        ['code' => '18', 'key' => 'budget_status', 'label' => 'Budget Status', 'type' => 'system', 'width' => 130, 'rules' => [['op' => 'contains', 'value' => 'Cancelled', 'tone' => 'red'], ['op' => 'contains', 'value' => 'Approved', 'tone' => 'green'], ['op' => 'contains', 'value' => 'Submitted', 'tone' => 'yellow'], ['op' => 'contains', 'value' => 'Returned', 'tone' => 'lightred'], ['op' => 'contains', 'value' => 'Draft', 'tone' => 'blue'], ['op' => 'contains', 'value' => 'Requisition', 'tone' => 'gray']]],
    ];

    /** Where `erp:save-column-setup` keeps the saved Column Setup a new company's sheet starts from. */
    public static function snapshotPath(): string
    {
        return dirname(__DIR__).'/Database/Data/column_setup.json';
    }

    /** The company's sheet, made from the saved setup (else this catalog) if it has none yet. */
    public static function ensure(string $companyId): void
    {
        if (OsColumn::query()->where('company_id', $companyId)->exists()) {
            return;
        }
        DB::transaction(function () use ($companyId): void {
            // Two first page loads at once: the company row is the lock, so only one makes the sheet.
            DB::table('companies')->where('id', $companyId)->lockForUpdate()->value('id');
            if (OsColumn::query()->where('company_id', $companyId)->exists()) {
                return;
            }
            // The factory's saved Column Setup, when there is one, rather than this catalog.
            if ($saved = ColumnSetupSnapshot::read(self::snapshotPath())) {
                ColumnSetupSnapshot::restore($companyId, $saved, OsGroup::class, OsColumn::class);

                return;
            }
            $groups = [];
            foreach (self::GROUPS as $i => $g) {
                $groups[$g['code']] = OsGroup::query()->create(['company_id' => $companyId, 'code' => $g['code'], 'name' => $g['name'], 'tone' => $g['tone'] ?? null, 'sort_order' => ($i + 1) * 10, 'is_system' => true, 'active' => true])->id;
            }
            $byLabel = array_column(self::COLUMNS, 'key', 'label');
            foreach (self::COLUMNS as $i => $c) {
                OsColumn::query()->create([
                    'company_id' => $companyId, 'group_id' => $groups[$c['code']], 'key' => $c['key'], 'label' => $c['label'], 'sort_order' => ($i + 1) * 10,
                    'type' => $c['type'], 'source' => $c['source'] ?? null, 'multiple' => $c['multiple'] ?? false, 'prefix' => $c['prefix'] ?? null,
                    'suffix' => $c['suffix'] ?? null, 'decimals' => $c['decimals'] ?? null, 'width' => $c['width'] ?? 130, 'vertical' => $c['vertical'] ?? false, 'frozen' => $c['frozen'] ?? false,
                    'formula' => isset($c['formula']) ? Formula::stored($c['formula'], fn (string $label) => $byLabel[$label] ?? null) : null,
                    'color_rules' => $c['rules'] ?? null, 'text_tone' => $c['text'] ?? null, 'bold' => $c['bold'] ?? false, 'kpi' => $c['kpi'] ?? null, 'pair_reduce' => $c['reduce'] ?? null, 'is_system' => true,
                ]);
            }
        });
    }
}
