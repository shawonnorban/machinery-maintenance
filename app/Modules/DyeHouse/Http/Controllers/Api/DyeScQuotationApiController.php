<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Http\Controllers\Api;

use App\Modules\Api\Support\ApiCaller;
use App\Modules\DyeHouse\Models\DyeScQuotation;
use App\Modules\DyeHouse\Models\DyeScQuotationLine;
use App\Modules\Inventory\Support\UomCodes;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\RateLine;
use App\Modules\Merchandise\Support\MerchandiseDefaults;
use App\Modules\Settings\Models\Currency;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dye House → Dye Sub-Contract Party Rate (AnnotechERP 17/01): a subcontract
 * party's quoted rates for one rate group — a header and one line per rate
 * head — saved together. The heads offered are the Inquiry Rate Chart lines
 * filed under the group; a group with none (Yarn Twist) takes typed heads.
 * Quotation numbers are issued here when left to Auto. One table serves
 * both sides (17/10): OUT, the rates a subcontract party charges us, and IN,
 * the In-House Sub-Contract Party Rate we charge a party. A quotation is
 * addressed by its number (its id still answers, for older links).
 */
class DyeScQuotationApiController extends ApiController
{
    /** Rate group → the Inquiry Rate Chart group its heads are filed under. */
    private const RATE_CHART_GROUP = ['KNITTING' => 'KNITTING', 'YARN_DYEING' => 'YARN_DYEING', 'DYEING' => 'DYEING', 'DYEING_FINISHING' => 'DYEING_FINISHING', 'AOP' => 'AOP', 'YARN_TWIST' => null];

    public function index(Request $request): JsonResponse
    {
        $this->allowRates('view');
        $side = $request->validate(['side' => ['nullable', Rule::in(DyeScQuotation::SIDES)]])['side'] ?? 'OUT';
        $parties = MaterialSupplier::query()->pluck('name', 'id');
        $lines = DyeScQuotationLine::query()->selectRaw('quotation_id, count(*) as n')->groupBy('quotation_id')->pluck('n', 'quotation_id');

        return ApiResponse::ok(DyeScQuotation::query()->where('side', $side)->orderByDesc('quotation_date')->orderByDesc('created_at')->get()->map(fn (DyeScQuotation $q) => [
            'id' => $q->id, 'quotation_no' => $q->quotation_no, 'rate_group' => $q->rate_group, 'side' => $q->side, 'party' => $parties[$q->supplier_id] ?? null, 'party_id' => $q->supplier_id,
            'quotation_date' => $q->quotation_date?->toDateString(), 'currency' => $q->currency, 'status' => $q->status, 'lines' => (int) ($lines[$q->id] ?? 0), 'docs' => count($q->documents ?? []),
        ])->all());
    }

    public function show(string $quotation): JsonResponse
    {
        $this->allowRates('view');

        return ApiResponse::ok($this->present($this->quotation($quotation)));
    }

    /** The heads a group's lines may be for. */
    public function heads(Request $request): JsonResponse
    {
        $this->allowRates('view');
        $data = $request->validate(['group' => ['required', Rule::in(DyeScQuotation::GROUPS)]]);
        MerchandiseDefaults::ensureRateLines($this->caller()->companyId);
        $chart = self::RATE_CHART_GROUP[$data['group']];

        return ApiResponse::ok($chart === null ? [] : RateLine::query()->where('rate_group', $chart)->where('active', true)->orderBy('sort_order')->get()
            ->map(fn (RateLine $l) => ['value' => $l->id, 'label' => $l->description, 'uom' => $l->uom])->all());
    }

    public function store(Request $request): JsonResponse
    {
        $this->allowRates('manage');

        return ApiResponse::created($this->present($this->save($request, null)));
    }

    public function update(Request $request, string $quotation): JsonResponse
    {
        $this->allowRates('manage');

        return ApiResponse::ok($this->present($this->save($request, $this->quotation($quotation))));
    }

    private function save(Request $request, ?DyeScQuotation $row): DyeScQuotation
    {
        $company = $this->caller()->companyId;
        $group = $row?->rate_group ?? $request->input('rate_group');
        $data = $request->validate([
            'rate_group' => [$row ? 'prohibited' : 'required', Rule::in(DyeScQuotation::GROUPS)],
            'side' => [$row ? 'prohibited' : 'nullable', Rule::in(DyeScQuotation::SIDES)],
            'quotation_no' => ['nullable', 'string', 'max:32', Rule::unique('dye_sc_quotations', 'quotation_no')->where('company_id', $company)->ignore($row?->id)],
            'auto' => ['sometimes', 'boolean'],
            'supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'quotation_date' => ['required', 'date'],
            'currency' => ['required', Rule::in(Currency::CODES)],
            'status' => ['required', Rule::in(DyeScQuotation::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            // Brickland's Selected Docs: the papers recorded by name.
            'documents' => ['nullable', 'array', 'max:20'],
            'documents.*' => ['required', 'string', 'max:255', 'distinct'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.rate_line_id' => ['nullable', Rule::exists('rate_lines', 'id')->where('company_id', $company)->where('rate_group', self::RATE_CHART_GROUP[$group] ?? '~')],
            'lines.*.head' => ['nullable', 'string', 'max:255'],
            'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($this->caller()->companyId)],
            'lines.*.rate' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ], ['rate_group.required' => __('dyehouse.master.pick_group'), 'lines.required' => __('dyehouse.master.needs_lines'), 'lines.min' => __('dyehouse.master.needs_lines')]);

        $heads = RateLine::query()->whereIn('id', array_filter(array_column($data['lines'], 'rate_line_id')))->get()->keyBy('id');
        $lines = [];
        foreach (array_values($data['lines']) as $i => $line) {
            $headId = $line['rate_line_id'] ?? null;
            $head = $headId ? $heads[$headId]->description : trim((string) ($line['head'] ?? ''));
            if ($head === '') {
                throw ValidationException::withMessages(["lines.{$i}.head" => __('dyehouse.master.line_needs_head')]);
            }
            $lines[] = ['rate_line_id' => $headId, 'head' => $head, 'uom' => UomCodes::normalize($line['uom'] ?? null) ?? UomCodes::normalize($headId ? $heads[$headId]->uom : null), 'rate' => $line['rate'], 'sort_order' => ($i + 1) * 10];
        }
        if (count(array_unique(array_column($lines, 'head'))) !== count($lines)) {
            throw ValidationException::withMessages(['lines' => __('dyehouse.master.head_repeated')]);
        }

        return DB::transaction(function () use ($row, $data, $lines, $company): DyeScQuotation {
            $number = trim((string) ($data['quotation_no'] ?? ''));
            if ($number === '' && ($row === null || ($data['auto'] ?? false))) {
                $number = $row?->quotation_no ?? $this->nextNumber();
            }
            $header = ['company_id' => $company, 'supplier_id' => $data['supplier_id'], 'quotation_date' => $data['quotation_date'], 'currency' => $data['currency'], 'status' => $data['status'], 'remarks' => $data['remarks'] ?? null, 'documents' => array_values($data['documents'] ?? []) ?: null, 'quotation_no' => $number !== '' ? strtoupper($number) : $row?->quotation_no];
            if ($row === null) {
                $row = DyeScQuotation::query()->create($header + ['rate_group' => $data['rate_group'], 'side' => $data['side'] ?? 'OUT', 'created_by' => $this->caller()->auditUserId()]);
            } else {
                $row->update($header);
            }
            DyeScQuotationLine::query()->where('quotation_id', $row->id)->delete();
            foreach ($lines as $line) {
                DyeScQuotationLine::query()->create($line + ['company_id' => $company, 'quotation_id' => $row->id]);
            }

            return $row->fresh();
        });
    }

    /** A quotation by its number, or its id for links made before numbers were used. */
    private function quotation(string $ref): DyeScQuotation
    {
        return DyeScQuotation::query()->where('quotation_no', strtoupper($ref))->first() ?? DyeScQuotation::query()->whereKey($ref)->firstOrFail();
    }

    /** DSQ-<year>-<0001>, the next free in the company. */
    private function nextNumber(): string
    {
        $prefix = 'DSQ-'.now()->format('Y').'-';
        $last = DyeScQuotation::query()->where('quotation_no', 'like', $prefix.'%')->orderByDesc('quotation_no')->value('quotation_no');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed> */
    private function present(DyeScQuotation $q): array
    {
        return [
            'id' => $q->id, 'quotation_no' => $q->quotation_no, 'rate_group' => $q->rate_group, 'side' => $q->side, 'supplier_id' => $q->supplier_id,
            'quotation_date' => $q->quotation_date?->toDateString(), 'currency' => $q->currency, 'status' => $q->status, 'remarks' => $q->remarks, 'documents' => $q->documents ?? [],
            'lines' => DyeScQuotationLine::query()->where('quotation_id', $q->id)->orderBy('sort_order')->get()
                ->map(fn (DyeScQuotationLine $l) => ['rate_line_id' => $l->rate_line_id, 'head' => $l->head, 'uom' => $l->uom, 'rate' => (float) $l->rate])->all(),
        ];
    }

    /**
     * The same quotations serve Dye House's and Knit Production's Sub-Contract
     * Party Rate screens (Brickland shows one document set on both), so either
     * module's master permission opens them.
     */
    private function allowRates(string $action): void
    {
        $caller = app(ApiCaller::class);
        if (! $caller->can("dyehouse.master.{$action}") && ! $caller->can("knitting.master.{$action}")) {
            $this->allow("dyehouse.master.{$action}");
        }
    }
}
