<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Models\Store;
use App\Modules\Knitting\Models\KnitProdOrder;
use App\Modules\Knitting\Models\KnitRollInspection;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\Knitting\Models\YarnPurchaseOrder;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\Reports\KnitReports;
use App\Modules\Knitting\Support\Reports\ReportQuery;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Knit Production → Reports (AnnotechERP 15/11). Five of Brickland's report
 * screens are report sets — one report picked from the list, the screen's
 * parameter boxes, and Preview reads it from the module's tables at that
 * moment (nothing is cached); the other three are stock positions and the
 * day's output sheet, searched like a list. A report answers its columns,
 * rows and the stat tiles above its grid; one whose figures come from a
 * module not built yet (the dye house's pre-treatment, the commercial
 * desk's proforma invoices) answers an empty grid and says so.
 */
class KnitReportApiController extends ApiController
{
    /** Each report screen's set, in Brickland's order (its radio values). */
    public const SETS = [
        'knitting' => [
            'yarn-purchase-requisition-statement', 'grey-fabric-stock-summary', 'yarn-purchase-requisition-summery', 'yarn-requisition-statement-for-knitting-sample-yd-by-date',
            'yarn-requisition-statement-for-knitting-sample-solid-by-date', 'projection-order-grey-stock-summary', 'yarn-requsition-statment-for-knitting-bulk-by-date',
            'order-wise-yarn-issue-register', 'order-and-color-wise-fabric-production-statement', 'order-and-color-wise-knitting-statement-new', 'daily-knitting-production-with-value',
            'daily-mc-and-shift-wise-knitting-statement', 'party-wise-order-statement-sc-outhouse-knitting', 'party-wise-knitting-performance-summary-sc-out-house',
            'mc-wise-knitting-production-capacity-vs-achievement', 'order-and-color-wise-production-status-sc-out-house', 'daily-order-and-color-wise-knitting-production',
            'daily-roll-labelling-statement-sc-out-house', 'knitting-department-followup-report', 'knitting-program-for-sample', 'job-wise-knitting-program', 'date-wise-knit-card-report',
            'yarn-to-finish-fabric-status', 'color-wise-yarn-lot-alocation-and-lot-wise-knitting-production', 'order-and-color-wise-summery',
            'sample-grey-fabric-receive-and-delivery-status', 'sample-grey-fabric-collar-culf-receive-and-delivery-status', 'sample-grey-fabric-receive-and-delivery-status-test',
            'date-wise-sample-grey-fabric-receive-and-delivery', 'lot-wise-loose-yarn-statement', 'lot-wise-yarn-statement-report', 'mc-and-date-wise-needle-broken',
            'needle-reason-type-wise-summary', 'daily-mc-and-shift-wise-needle-statement', 'needle-stock-taking', 'mc-and-operator-wise-oil-consumption-unit-cost',
            'mc-and-shift-wise-oil-consumption-unit-cost', 'date-wise-mc-oil-consumption', 'oil-stock-taking', 'sc-party-wise-rate', 'order-and-color-wise-grey-fabric-transfer-statement',
            'sc-pre-treatment-ledger-for-all', 'monthly-grey-qc-summary-report', 'rack-wise-stock-report', 'save-fabric-quantity', 'order-wise-requisition-receive-and-issue-report',
            'physical-dyed-yarn-inventory-report', 'physical-yarn-inventory-report', 'buyer-wise-yarn-in-hand-status', 'roll-list-without-grey-fabric-receive', 'knit-loan-ledger-details',
            'knit-loan-ledger-summary', 'knit-card-wise-quality-check-status', 'knit-needle-loan-statement', 'sample-fabric-progress-report', 'knit-oil-loan-ledger-details',
            'knit-oil-loan-ledger-summary', 'knit-oil-loan-statement', 'program-wise-process-loss-yarn-vs-fabric', 'mc-wise-efficiency-downtime-and-running-cost',
            'program-wise-knitting-cost-per-kg',
        ],
        'yarn-store' => [
            'yarn-receive-statement', 'yarn-issue-statement', 'order-wise-yarn-issue-register-with-value', 'order-wise-yarn-issue-register', 'party-wise-yarn-issue-statement',
            'yarn-issue-return-statement', 'department-wise-excess-yarn-summery', 'excess-yarn-usage-statement', 'excess-yarn-statement', 'advance-yarn-report',
            'lot-and-count-wise-loose-yarn-stock', 'yarn-stock-with-value', 'yarn-stock-with-summary-value', 'loose-yarn-statement-sc-out-house', 'yarn-inventory-aging',
            'finishing-production-report-4a', 'delivery-challan-for-yarn-return-to-supplier', 'daily-and-monthly-yarn-operation-stock-and-value-report',
            'buyer-wise-yarn-stock-taking-report', 'yarn-stock-taking-report', 'lot-wise-loose-yarn-statement', 'lot-wise-yarn-statement-report', 'production-report-with-target-qty',
            'yarn-purchase-order-statement', 'yarn-proforma-invoice-statement', 'yarn-closing-stock-summary', 'dyed-yarn-receive-challan-statement', 'excess-yarn-target-report',
        ],
        'gfs' => [
            'grey-fabric-stock-detail', 'buyer-ac-wise-grey-fabric-stock', 'grey-fabric-stock-by-dia-and-yarn', 'projection-order-grey-fabric-stock-by-fin-dia',
            'grey-fabric-delivery-challan-sc-in-house', 'yarn-transfer-challan-sc-in-house', 'sc-yarn-delivery-challan-out-house', 'sc-program-cum-contract-out-house',
            'party-wise-order-statement-sc-outhouse-knitting', 'party-and-order-wise-yd-register-sc-outhouse', 'party-wise-yarn-balance-sc-outhouse-knitting',
            'party-wise-yarn-balance-sc-outhouse-yd', 'party-wise-ledger-sc-outhouse-knitting', 'supplier-wise-yarn-test-result', 'party-wise-ledger-sc-outhouse-yd', 'order-wise-yd-ledger',
            'party-wise-bill-statement-sc-out-house', 'party-wise-ledger-sc-in-house', 'party-wise-knitting-performance-by-program-sc-out-house', 'fabric-reject-summery-sc-out-house',
            'daily-trims-receive-statement', 'party-wise-order-statement-sc-in-house', 'utilization-of-left-over-grey-fabric-transfer-and-re-use-summery',
            'utilization-of-left-over-grey-fabric-re-use-with-value', 'utilization-of-left-over-grey-fabric-transfer-and-re-use', 'party-wise-stock-summery-sc-in-house',
            'party-wise-summery-sc-in-house', 'party-wise-bill-statement-sc-in-house', 'order-and-color-wise-roll-available-in-grey-store-sc-in-house',
            'order-and-color-wise-grey-fabric-transfer-statement', 'sc-pre-treatment-ledger', 'order-and-color-wise-fabric-delivery-status',
        ],
        'qc' => [
            'grey-fabric-roll-inspection', 'shift-wise-grey-fabric-roll-inspection', 'roll-list-without-grey-qc', 'order-and-color-wise-inspection-report',
            'date-wise-grey-fabric-qc-status', 'grey-fabric-delivery-challan-to-store', 'roll-wise-grey-fabric-delivery-challan-to-store', 'monthly-out-side-quality-summary',
            'daily-return-challan-for-grey-qc-deduction-fabric', 'monthly-quality-summary-report-before-dyeing',
        ],
        'flatknit' => [
            'yarn-requsition-statement-for-collarcuff', 'order-wise-flat-knit-requirement', 'daily-collarcuff-production', 'flat-knit-stock-detail',
            'daily-collarcuff-transfer-to-qc-and-store', 'buyer-ac-wise-flat-knit-stock', 'order-wise-collarcuff-stock', 'store-wise-collarcuff-stock',
            'date-and-mc-wise-collarcuff-production', 'order-wise-yarn-issue-register-for-collar-cuff-production', 'collarcuff-challan-receive-sc-out-house',
            'flat-knit-production-statement',
        ],
    ];

    /** Reports whose figures belong to modules not built yet. */
    private const PENDING = [
        'sc-pre-treatment-ledger' => 'dyeing', 'sc-pre-treatment-ledger-for-all' => 'dyeing', 'yarn-proforma-invoice-statement' => 'commercial',
    ];

    /** Everything the report screens' boxes pick from. */
    public function options(): JsonResponse
    {
        $this->allow('knitting.report.view');
        $base = KnitProduction::options();
        $yarn = YarnProcurement::options();
        $names = YarnProcurement::names();
        $pairs = YarnLot::query()->select('composition_id', 'yarn_count_id')->distinct()->get()
            ->map(fn ($l) => ['value' => $l->composition_id.'|'.$l->yarn_count_id, 'label' => trim(($names['counts'][$l->yarn_count_id] ?? '').' '.($names['compositions'][$l->composition_id] ?? ''))])
            ->sortBy('label')->values()->all();
        $lots = YarnLot::query()->get(['brand', 'colour']);

        return ApiResponse::ok([
            'company' => $base['company'], 'buyers' => $base['buyers'], 'orders' => $base['orders'], 'floors' => $base['floors'], 'shifts' => $base['shifts'], 'colours' => $base['colours'],
            'fabric_types' => $base['fabric_types'], 'employees' => $base['employees'], 'kinds' => KnitProdOrder::KINDS, 'grades' => $base['grades'],
            'qc_statuses' => KnitRollInspection::STATUSES, 'yarn_items' => $pairs, 'counts' => $yarn['counts'], 'compositions' => $yarn['compositions'],
            'stores' => Store::query()->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->values()->all(),
            'pos' => YarnPurchaseOrder::query()->where('status', '!=', 'CANCELLED')->orderByDesc('po_date')->get(['id', 'po_no'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->po_no])->values()->all(),
            'brands' => $lots->pluck('brand')->filter()->unique()->sort()->values()->all(), 'shades' => $lots->pluck('colour')->filter()->unique()->sort()->values()->all(),
            'sources' => ['LOCAL', 'SC_PARTY', 'YARN_DYED', 'INHOUSE_DYED'], 'spins' => ['Combed', 'Carded', 'Open End', 'Ring Spun', 'Vortex', 'Semi Combed'],
            'cottons' => ['Conventional', 'Organic', 'Bci', 'Recycled', 'Supima', 'Egyptian'], 'cc_req_types' => ['SOLID', 'YD'],
        ]);
    }

    /** Preview: one report of a screen's set, read now. */
    public function show(Request $request, string $set, string $report): JsonResponse
    {
        $this->allow('knitting.report.view');
        abort_unless(isset(self::SETS[$set]) && in_array($report, self::SETS[$set], true), 404);

        return ApiResponse::ok(['set' => $set, 'report' => $report] + (new KnitReports($this->query($request)))->run($report) + ['pending_module' => self::PENDING[$report] ?? null]);
    }

    /** Yarn Stock Position: yarn on hand per lot, struck on the To Date. */
    public function yarnStock(Request $request): JsonResponse
    {
        $this->allow('knitting.report.view');

        return ApiResponse::ok((new KnitReports($this->query($request)))->yarnPosition());
    }

    /** Grey Fabric Stock Position: what the store took in against each order's target. */
    public function greyStock(Request $request): JsonResponse
    {
        $this->allow('knitting.report.view');

        return ApiResponse::ok((new KnitReports($this->query($request)))->greyPosition());
    }

    /** Daily Knit Output Report: the day's production sheet. */
    public function dailyOutput(Request $request): JsonResponse
    {
        $this->allow('knitting.report.view');

        return ApiResponse::ok((new KnitReports($this->query($request)))->dailyOutput());
    }

    /** The parameter boxes, checked. */
    private function query(Request $request): ReportQuery
    {
        $id = ['nullable', 'string', 'max:40'];
        $text = ['nullable', 'string', 'max:80'];
        $f = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'yarn_item' => ['nullable', 'string', 'max:90'],
            'yr_approve' => ['nullable', Rule::in(['ALL', 'YES', 'NO'])], 'date_type' => ['nullable', Rule::in(['DOCUMENT', 'APPROVED', 'DELIVERY'])],
            'buyer' => $id, 'order' => $id, 'store' => $id, 'floor' => $id, 'shift' => $id, 'colour' => $id, 'fabric_type' => $id, 'qc_user' => $id, 'count' => $id, 'composition' => $id, 'po' => $id,
            'prod_type' => ['nullable', Rule::in(KnitProdOrder::KINDS)], 'qc_status' => ['nullable', Rule::in(KnitRollInspection::STATUSES)], 'grade' => ['nullable', Rule::in(['A', 'B', 'C', 'D'])],
            'req_type' => ['nullable', Rule::in(['SOLID', 'YD'])], 'part' => ['nullable', Rule::in(['GREY', 'COLLAR_CUFF'])], 'category' => ['nullable', Rule::in(['LOCAL', 'SC_PARTY', 'YARN_DYED', 'INHOUSE_DYED'])],
            'kc_no' => $text, 'roll_no' => ['nullable', 'string', 'max:20'], 'brand' => $text, 'lot' => $text, 'shade' => $text, 'spin' => $text, 'cotton' => $text,
            'slub' => ['nullable', 'boolean'], 'mellange' => ['nullable', 'boolean'], 'solid' => ['nullable', 'boolean'], 'yd' => ['nullable', 'boolean'], 'sc_in' => ['nullable', 'boolean'],
        ]);

        return new ReportQuery(array_filter($f, fn ($v) => $v !== null && $v !== '' && $v !== false));
    }
}
