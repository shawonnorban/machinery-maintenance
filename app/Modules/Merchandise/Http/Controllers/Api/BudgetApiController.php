<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Support\UomCodes;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Budget;
use App\Modules\Merchandise\Models\BudgetEvent;
use App\Modules\Merchandise\Models\BudgetLine;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\Inquiry;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\FabricBooking;
use App\Modules\Merchandise\Models\FabricBookingLine;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\GarmentPart;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleBom;
use App\Modules\Merchandise\Models\StyleBomLine;
use App\Modules\Merchandise\Models\StyleBomVersion;
use App\Modules\Merchandise\Support\BudgetItemPrices;
use App\Shared\Fabric\FabricLength;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Costing & Budget → Order Budget Sheet (AnnotechERP 06/09). An order has one
 * budget, per piece in its currency: the bill of material by section, the
 * fabric process heads and the overall heads up to FOB. A sheet not yet
 * saved opens on what the order already says — its quantity in pieces, its
 * price per piece as FOB, its currency and rate, and the quoted FOB of the
 * style's inquiry to compare against. Once approved on the Budget Sheet
 * Workflow, the sheet is locked for costing until it is unlocked again.
 */
class BudgetApiController extends ApiController
{
    /** The budget section a Style BOM category falls in. */
    private const BOM_SECTIONS = [
        'MAIN_FABRIC' => 'PURCHASED_FABRIC', 'SEWING_ACCESSORIES' => 'SEWING_ACC', 'TRIMS' => 'SEWING_ACC', 'LABELS' => 'SEWING_ACC',
        'PACKAGING' => 'FINISHING_ACC', 'ACCESSORIES' => 'GENERAL_ACC', 'OTHERS' => 'GENERAL_ACC',
    ];

    public function options(): JsonResponse
    {
        $this->allow('merchandise.costing.view');

        return ApiResponse::ok([
            'offices' => BuyerOffice::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($o): array => ['value' => $o->id, 'label' => $o->name])->values()->all(),
            'styles' => Style::query()->orderBy('style_ref')->get()->map(fn (Style $s): array => ['value' => $s->id, 'label' => "{$s->styleNo()} · {$s->description}", 'name' => "{$s->styleNo()} · {$s->description}", 'buyer_office_id' => $s->buyer_office_id])->values()->all(),
            'orders' => Order::query()->where('status', '!=', 'CANCELLED')->orderByDesc('order_no')->get(['id', 'order_no', 'style_id', 'style_no', 'work_style_no'])
                ->map(fn (Order $o): array => ['value' => $o->id, 'label' => "{$o->order_no} · {$o->style_no} (".($o->work_style_no ?: $o->style_no).')', 'order_no' => $o->order_no, 'style_id' => $o->style_id])->values()->all(),
        ]);
    }

    /** The order's budget, or the sheet it would open on. */
    public function show(string $order): JsonResponse
    {
        $this->allow('merchandise.costing.view');
        $row = Order::query()->whereKey($order)->firstOrFail();

        return ApiResponse::ok($this->present($row));
    }

    public function save(Request $request, string $order): JsonResponse
    {
        $this->allow('merchandise.costing.manage');
        $row = Order::query()->whereKey($order)->firstOrFail();
        if (Budget::query()->where('order_id', $row->id)->whereNotNull('approved_at')->exists()) {
            throw ValidationException::withMessages(['budget' => __('merchandise.budget.locked')]);
        }
        $company = $this->caller()->companyId;
        $heads = fn (array $keys): array => collect($keys)->mapWithKeys(fn (string $k): array => [$k => ['nullable', 'numeric', 'min:0', 'max:99999999']])->all();
        $data = $request->validate([
            'buyer_office_id' => ['nullable', Rule::exists('buyer_offices', 'id')->where('company_id', $company)],
            'currency' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:300'],
            'lines.*.section' => ['required', Rule::in(Budget::SECTIONS)],
            // Accessory lines (2026-11-17) name an item of the trims master and the supplier the rate is from.
            'lines.*.item_id' => ['nullable', Rule::exists('mp_items', 'id')->where('company_id', $company)],
            'lines.*.supplier_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'lines.*.description' => ['required_without:lines.*.item_id', 'nullable', 'string', 'max:255'],
            'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($this->caller()->companyId)],
            // Consumption per dozen and wastage % (2026-11-17): given, they set the piece's quantity.
            'lines.*.cons_dzn' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.wastage_pct' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'lines.*.qty' => ['required_without:lines.*.cons_dzn', 'nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.rate' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.source' => ['nullable', Rule::in(BudgetLine::SOURCES)],
            'process' => ['present', 'array'],
            'overall' => ['present', 'array'],
        ] + collect($heads(Budget::PROCESS_HEADS))->mapWithKeys(fn ($r, $k) => ["process.{$k}" => $r])->all()
          + collect($heads(Budget::OVERALL_HEADS))->mapWithKeys(fn ($r, $k) => ["overall.{$k}" => $r])->all(), [
              'lines.*.description.required_without' => __('merchandise.budget.description_required'),
          ]);

        DB::transaction(function () use ($row, $data): void {
            $budget = Budget::query()->where('order_id', $row->id)->first();
            $values = [
                'style_id' => $row->style_id,
                'buyer_office_id' => $data['buyer_office_id'] ?? null,
                'currency' => strtoupper($data['currency']),
                'exchange_rate' => $data['exchange_rate'],
                'process_costs' => collect(Budget::PROCESS_HEADS)->mapWithKeys(fn (string $k): array => [$k => (float) ($data['process'][$k] ?? 0)])->all(),
                'overall_costs' => collect(Budget::OVERALL_HEADS)->mapWithKeys(fn (string $k): array => [$k => (float) ($data['overall'][$k] ?? 0)])->all(),
                'remarks' => isset($data['remarks']) && trim($data['remarks']) !== '' ? trim($data['remarks']) : null,
            ];
            if ($budget) {
                $budget->update($values);
            } else {
                $budget = Budget::query()->create($values + ['order_id' => $row->id, 'budget_no' => $this->nextNumber(), 'created_by' => $this->caller()->auditUserId()]);
            }
            BudgetLine::query()->where('budget_id', $budget->id)->delete();
            $items = MaterialItem::query()->whereIn('id', collect($data['lines'])->pluck('item_id')->filter())->get(['id', 'item_code', 'name', 'uom'])->keyBy('id');
            foreach (array_values($data['lines']) as $i => $l) {
                $item = isset($l['item_id']) ? ($items[$l['item_id']] ?? null) : null;
                $description = trim((string) ($l['description'] ?? ''));
                BudgetLine::query()->create([
                    'budget_id' => $budget->id, 'section' => $l['section'], 'item_id' => $item?->id, 'item_code' => $item?->item_code,
                    'description' => $description !== '' ? $description : (string) $item?->name, 'supplier_id' => $l['supplier_id'] ?? null,
                    'uom' => UomCodes::normalize(($l['uom'] ?? null) ?: $item?->uom),
                    'cons_dzn' => $l['cons_dzn'] ?? null, 'wastage_pct' => $l['wastage_pct'] ?? null,
                    'qty' => isset($l['cons_dzn']) ? BudgetLine::qtyOf((float) $l['cons_dzn'], isset($l['wastage_pct']) ? (float) $l['wastage_pct'] : null) : $l['qty'],
                    'rate' => $l['rate'], 'source' => $l['source'] ?? null, 'sort_order' => ($i + 1) * 10,
                ]);
            }
        });

        return ApiResponse::ok($this->present($row));
    }

    /** @return array<string, mixed> */
    private function present(Order $order): array
    {
        $budget = Budget::query()->where('order_id', $order->id)->first();
        $style = $order->style_id ? Style::query()->whereKey($order->style_id)->first() : null;
        $perUnit = $order->unit === 'PCS' ? 1 : max(1, (int) $order->pcs_per_unit);
        $inquiry = $style?->inquiry_id ? Inquiry::query()->whereKey($style->inquiry_id)->first() : ($order->inquiry_id ? Inquiry::query()->whereKey($order->inquiry_id)->first() : null);
        $unitPrice = $order->unit_price === null ? 0.0 : (float) $order->unit_price / $perUnit;

        return [
            'order' => [
                'id' => $order->id, 'order_no' => $order->order_no, 'style_id' => $order->style_id, 'style_name' => $style ? "{$style->styleNo()} · {$style->description}" : $order->style_no,
                'currency' => $order->currency, 'order_qty' => $order->total_qty * $perUnit, 'order_value' => round($order->total_qty * (float) ($order->unit_price ?? 0), 2),
                'unit_price' => round($unitPrice, 4), 'inquiry_no' => $inquiry?->inquiry_no,
            ],
            // The quote is per its calculation unit; a dozen is twelve pieces.
            'inquiry' => ['fob' => $inquiry?->quoted_fob === null ? 0 : round((float) $inquiry->quoted_fob / ($inquiry->calc_unit === 'DZ' ? 12 : 1), 4)],
            'budget' => $budget ? [
                'budget_no' => $budget->budget_no, 'approved_at' => $budget->approved_at?->toIso8601String(), 'buyer_office_id' => $budget->buyer_office_id, 'currency' => $budget->currency, 'exchange_rate' => $budget->exchange_rate,
                'process' => $budget->process_costs ?? [], 'overall' => $budget->overall_costs ?? [], 'remarks' => $budget->remarks,
                'lines' => BudgetLine::query()->where('budget_id', $budget->id)->orderBy('sort_order')->get(['section', 'item_id', 'item_code', 'description', 'uom', 'cons_dzn', 'wastage_pct', 'qty', 'rate', 'supplier_id', 'source'])->map->toArray()->values()->all(),
            ] : [
                'budget_no' => null, 'approved_at' => null, 'buyer_office_id' => $style?->buyer_office_id ?? $order->buyer_office_id, 'currency' => $order->currency, 'exchange_rate' => $order->exchange_rate ?? 1,
                'process' => [], 'overall' => ['fob' => round($unitPrice, 4)], 'remarks' => null, 'lines' => [],
            ],
            'sections' => Budget::SECTIONS, 'process_heads' => Budget::PROCESS_HEADS, 'overall_heads' => Budget::OVERALL_HEADS,
            // A new line's unit by its group: yarn by the kilo, purchased fabric by the company's length, the rest by the piece.
            'section_uoms' => ['YARN' => 'KG', 'PURCHASED_FABRIC' => FabricLength::unit()] + array_fill_keys(BudgetLine::ACCESSORY_SECTIONS, 'PCS'),
            'accessory_sections' => BudgetLine::ACCESSORY_SECTIONS,
            // The trims master and the suppliers an accessory line picks from.
            'catalog' => [
                'items' => MaterialItem::query()->where('kind', 'TRIMS')->where('active', true)->orderBy('name')->get(['id', 'item_code', 'name', 'uom'])
                    ->map(fn (MaterialItem $i): array => ['value' => $i->id, 'label' => $i->item_code ? "{$i->name} ({$i->item_code})" : $i->name, 'code' => $i->item_code, 'name' => $i->name, 'uom' => UomCodes::normalize($i->uom)])->values()->all(),
                'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn (MaterialSupplier $s): array => ['value' => $s->id, 'label' => $s->name])->values()->all(),
            ],
        ];
    }

    /**
     * One trims item on an accessory line of the order's sheet (2026-11-17):
     * its code and unit, its consumption and wastage from the style's BOM, and
     * what each supplier asks for it in the sheet's currency (the sheet's own
     * currency and rate as keyed, not yet saved) — with the supplier and rate
     * to take: the BOM's supplier when it has a price, else the best price.
     */
    public function item(Request $request, string $order): JsonResponse
    {
        $this->allow('merchandise.costing.view');
        $row = Order::query()->whereKey($order)->firstOrFail();
        $data = $request->validate([
            'item_id' => ['required', Rule::exists('mp_items', 'id')->where('company_id', $this->caller()->companyId)],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'rate' => ['nullable', 'numeric', 'gt:0'],
        ]);
        $item = MaterialItem::query()->whereKey($data['item_id'])->firstOrFail();
        $currency = strtoupper($data['currency'] ?? (string) $row->currency ?: 'USD');
        $offers = BudgetItemPrices::offers([$item->id], $currency, (float) ($data['rate'] ?? $row->exchange_rate ?? 1))[$item->id] ?? [];
        $bom = $this->bomLines($row)->first(fn (StyleBomLine $l): bool => $this->sameItem($l, $item));
        $bomPart = $bom ? $this->bomFigures($bom) : null;

        return ApiResponse::ok([
            'item' => ['id' => $item->id, 'code' => $item->item_code, 'name' => $item->name, 'uom' => UomCodes::normalize($item->uom)],
            'bom' => $bomPart, 'offers' => $offers,
            'pick' => $this->pickOffer($offers, BudgetItemPrices::supplierNamed($bom?->supplier), $bomPart['rate'] ?? null),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, StyleBomLine> the style BOM's lines (the version in use, else the newest) */
    private function bomLines(Order $row): \Illuminate\Support\Collection
    {
        $bom = $row->style_id ? StyleBom::query()->where('style_id', $row->style_id)->first() : null;
        $version = $bom ? (StyleBomVersion::query()->where('bom_id', $bom->id)->where('status', StyleBomVersion::APPROVED)->orderByDesc('version')->first()
            ?? StyleBomVersion::query()->where('bom_id', $bom->id)->orderByDesc('version')->first()) : null;
        if (! $version) {
            return collect();
        }

        return StyleBomLine::query()->where('version_id', $version->id)->orderBy('sort_order')->get()->each(fn (StyleBomLine $l) => $l->setAttribute('addl_pct', $version->addl_pct));
    }

    /** @return array{cons_dzn: float, wastage_pct: float, rate: float} a BOM line per dozen, with its allowance (and addl % when ticked) */
    private function bomFigures(StyleBomLine $l): array
    {
        return ['cons_dzn' => round((float) $l->consumption / ((float) $l->per_gmt ?: 1) * 12, 4),
            'wastage_pct' => round((float) $l->allow_pct + ($l->apply_addl ? (float) $l->getAttribute('addl_pct') : 0), 3), 'rate' => round((float) $l->rate, 4)];
    }

    /** A BOM line is the item when its code is the item's, or (no code) its name is. */
    private function sameItem(StyleBomLine $l, MaterialItem $item): bool
    {
        $code = mb_strtoupper(trim((string) $l->item_code));

        return $code !== '' ? $code === mb_strtoupper((string) $item->item_code) : mb_strtolower(trim((string) $l->item_name)) === mb_strtolower(trim((string) $item->name));
    }

    /**
     * The supplier and rate an accessory line takes: the named supplier's price when it has one, else the
     * best priced offer, else the named supplier at the fallback rate.
     *
     * @param  list<array<string, mixed>>  $offers
     * @return array{supplier_id: ?string, rate: ?float}
     */
    private function pickOffer(array $offers, ?string $supplierId, ?float $fallback): array
    {
        $priced = array_values(array_filter($offers, fn (array $o): bool => $o['price'] !== null));
        $mine = $supplierId ? collect($priced)->firstWhere('supplier_id', $supplierId) : null;
        $pick = $mine ?? ($priced[0] ?? null);

        return $pick ? ['supplier_id' => $pick['supplier_id'], 'rate' => $pick['price']] : ['supplier_id' => $supplierId, 'rate' => $fallback];
    }

    /**
     * Bill of Material lines the order's own records already say, to load onto
     * the sheet (2026-11-17) - every figure per piece:
     *  - the order's bulk fabric booking (not cancelled or rejected): each fabric
     *    and part, its consumption over the pieces of this order it covers, and
     *    the cutting wastage. Knit fabric and knit trims are made of yarn, so they
     *    land in Yarn by the kilo (a length put into kilos through GSM and width)
     *    with the process loss added, their rate left for the yarn's price; woven
     *    fabric is bought, so it lands in Purchased Fabric at the booking's rate;
     *  - the style's BOM (the version in use, else the newest): each material by
     *    its category, consumption per garment, allowance (and the BOM's addl %
     *    when ticked) and rate. Its main fabric is left out when the booking
     *    already gave the fabric.
     */
    public function sources(string $order): JsonResponse
    {
        $this->allow('merchandise.costing.view');
        $row = Order::query()->whereKey($order)->firstOrFail();
        $pieces = (float) $row->total_qty * ($row->unit === 'PCS' ? 1 : max(1, (int) $row->pcs_per_unit));
        $codes = UomCodes::codes($this->caller()->companyId);
        $uom = function (?string $u) use ($codes): ?string {
            $code = UomCodes::normalize($u);

            return in_array($code, $codes, true) ? $code : null;
        };
        $lines = [];
        $notes = [];

        // -- Fabric booking ---------------------------------------------------
        $bookings = FabricBooking::query()->where('booking_type', 'BULK')->whereNotIn('status', ['CANCELLED', 'REJECTED'])
            ->where(fn ($q) => $q->where('order_id', $row->id)->orWhere('style_id', $row->style_id ?? '__none__'))->get()->keyBy('id');
        $fabrics = FabricType::query()->pluck('name', 'id');
        $parts = GarmentPart::query()->pluck('name', 'id');
        $groups = [];
        $used = [];
        if ($pieces > 0) {
            foreach (FabricBookingLine::query()->whereIn('booking_id', $bookings->keys())->orderBy('disp_order')->get() as $l) {
                $booking = $bookings[$l->booking_id];
                if ($booking->order_id !== $row->id && ! $l->all_orders) {
                    continue;
                }
                $mine = FabricBookingApiController::piecesFor($booking, $l->only(['all_orders', 'gmt_style_id', 'gmt_colour_id', 'size_group']), $row->id);
                if ($mine <= 0) {
                    continue;
                }
                $used[$booking->booking_no] = true;
                $perPiece = (float) $l->consumption / ($l->cons_per === 'DZ' ? 12 : 1);
                $unit = strtoupper((string) $l->cons_unit);
                $yarn = $l->fabric_kind !== 'WOVEN';
                $name = trim(($fabrics[$l->fabric_type_id] ?? '').($l->garment_part_id && isset($parts[$l->garment_part_id]) ? ' · '.$parts[$l->garment_part_id] : ''));
                if ($yarn && $unit !== 'KG') {
                    $width = $l->finish_dia === null ? null : ($l->dia_unit === 'CM' ? (float) $l->finish_dia / 2.54 : (float) $l->finish_dia);
                    $kg = FabricLength::metresToKg(FabricLength::toMetres($perPiece, $unit), $l->gsm, $width);
                    if ($kg === null) {
                        $notes[] = __('merchandise.master.bgt.load.no_gsm', ['fabric' => $name, 'unit' => $unit]);
                    } else {
                        [$perPiece, $unit] = [$kg, 'KG'];
                    }
                }
                $extra = (1 + (float) $l->cutting_wastage / 100) * ($yarn ? 1 + (float) $l->process_loss / 100 : 1);
                $section = $yarn ? 'YARN' : 'PURCHASED_FABRIC';
                $key = implode('|', [$section, $l->fabric_type_id, $l->garment_part_id, $unit]);
                $g = $groups[$key] ?? ['section' => $section, 'description' => $yarn ? __('merchandise.master.bgt.load.yarn_for', ['fabric' => $name]) : $name,
                    'unit' => $unit, 'base' => 0.0, 'final' => 0.0, 'cost' => 0.0];
                $g['base'] += $perPiece * $mine;
                $g['final'] += $perPiece * $mine * $extra;
                $g['cost'] += $yarn ? 0 : $perPiece * $mine * $extra * (float) $l->rate;
                $groups[$key] = $g;
            }
        }
        foreach ($groups as $g) {
            if ($g['base'] <= 0) {
                continue;
            }
            $consDzn = round($g['base'] / $pieces * 12, 4);
            $wastage = round(($g['final'] / $g['base'] - 1) * 100, 3);
            $lines[] = ['section' => $g['section'], 'description' => mb_substr($g['description'], 0, 255), 'uom' => $uom($g['unit']), 'cons_dzn' => $consDzn,
                'wastage_pct' => $wastage, 'qty' => BudgetLine::qtyOf($consDzn, $wastage), 'rate' => round($g['cost'] / $g['final'], 4), 'source' => 'BOOKING'];
        }
        $fromBooking = $lines !== [];

        // -- Style BOM --------------------------------------------------------
        // An accessory takes its item from the trims master (by code, else name) and its rate from the supplier.
        $bomLines = $this->bomLines($row);
        $trims = MaterialItem::query()->where('kind', 'TRIMS')->get(['id', 'item_code', 'name', 'uom']);
        $budget = Budget::query()->where('order_id', $row->id)->first();
        $matched = $bomLines->mapWithKeys(fn (StyleBomLine $l): array => [$l->id => $trims->first(fn (MaterialItem $i): bool => $this->sameItem($l, $i))]);
        $prices = BudgetItemPrices::offers($matched->filter()->pluck('id')->all(), (string) ($budget->currency ?? $row->currency ?? 'USD'), (float) ($budget->exchange_rate ?? $row->exchange_rate ?? 1));
        foreach ($bomLines as $l) {
            if ($l->category === 'MAIN_FABRIC' && $fromBooking) {
                continue;
            }
            $f = $this->bomFigures($l);
            $section = self::BOM_SECTIONS[$l->category] ?? 'GENERAL_ACC';
            $item = in_array($section, BudgetLine::ACCESSORY_SECTIONS, true) ? $matched[$l->id] : null;
            $pick = $item ? $this->pickOffer($prices[$item->id] ?? [], BudgetItemPrices::supplierNamed($l->supplier), $f['rate'])
                : ['supplier_id' => null, 'rate' => $f['rate']];
            $lines[] = ['section' => $section, 'item_id' => $item?->id, 'item_code' => $item?->item_code ?? ($l->item_code ?: null),
                'description' => mb_substr($item?->name ?? trim($l->item_name.($l->item_code ? " ({$l->item_code})" : '')), 0, 255),
                'uom' => $uom($l->cons_unit) ?? $uom($item?->uom), 'cons_dzn' => $f['cons_dzn'], 'wastage_pct' => $f['wastage_pct'], 'qty' => BudgetLine::qtyOf($f['cons_dzn'], $f['wastage_pct']),
                'rate' => $pick['rate'] ?? 0, 'supplier_id' => $pick['supplier_id'], 'source' => 'BOM'];
        }
        $bom = $row->style_id ? StyleBom::query()->where('style_id', $row->style_id)->first() : null;
        $version = $bomLines->isNotEmpty() ? StyleBomVersion::query()->whereKey($bomLines->first()->version_id)->first() : null;

        return ApiResponse::ok([
            'lines' => $lines, 'notes' => array_values(array_unique($notes)), 'bookings' => array_keys($used),
            'bom' => $version ? ['bom_no' => $bom->bom_no, 'version' => $version->version, 'status' => $version->status] : null,
        ]);
    }

    /** Budget vs Actual Costing → Records: every budget sheet with its order's quantity, price and value. */
    public function index(): JsonResponse
    {
        $this->allow('merchandise.costing.view');
        $budgets = Budget::query()->orderByDesc('budget_no')->get();
        $orders = Order::query()->whereIn('id', $budgets->pluck('order_id'))->get()->keyBy('id');
        $offices = BuyerOffice::query()->whereIn('id', $budgets->pluck('buyer_office_id')->filter())->pluck('name', 'id');

        return ApiResponse::ok($budgets->map(function (Budget $b) use ($orders, $offices): array {
            $o = $orders[$b->order_id];
            $perUnit = $o->unit === 'PCS' ? 1 : max(1, (int) $o->pcs_per_unit);

            return [
                'id' => $b->id, 'budget_no' => $b->budget_no, 'buyer_ac' => $b->buyer_office_id ? ($offices[$b->buyer_office_id] ?? null) : null,
                'style_no' => $o->style_no, 'order_no' => $o->order_no, 'order_qty' => $o->total_qty * $perUnit,
                'unit_price' => $o->unit_price === null ? null : round((float) $o->unit_price / $perUnit, 4), 'order_value' => round($o->total_qty * (float) ($o->unit_price ?? 0), 2),
                'currency' => $b->currency,
            ];
        })->values()->all());
    }

    /**
     * Budget Sheet Workflow: every budget sheet on its approval route — the
     * order's buyer, job, style, PO and shipment, its cost per piece against
     * FOB, and where it stands (waiting, or approved and locked).
     */
    public function workflow(): JsonResponse
    {
        $this->allow('merchandise.costing.view');
        $budgets = Budget::query()->orderByDesc('budget_no')->get();
        $orders = Order::query()->whereIn('id', $budgets->pluck('order_id'))->get()->keyBy('id');
        $buyers = Buyer::query()->whereIn('id', $orders->pluck('buyer_id')->filter())->get()->keyBy('id');
        $offices = BuyerOffice::query()->whereIn('id', $budgets->pluck('buyer_office_id')->filter())->pluck('name', 'id');
        $sums = BudgetLine::query()->whereIn('budget_id', $budgets->pluck('id'))->get(['budget_id', 'qty', 'rate'])->groupBy('budget_id')->map(fn ($ls): float => (float) $ls->sum(fn ($l) => $l->qty * $l->rate));
        $events = BudgetEvent::query()->whereIn('budget_id', $budgets->pluck('id'))->orderByDesc('created_at')->orderByDesc('id')->get()->unique('budget_id')->keyBy('budget_id');
        $users = User::query()->whereIn('id', $budgets->pluck('approved_by')->merge($events->pluck('user_id'))->filter()->unique())->pluck('name', 'id');

        return ApiResponse::ok($budgets->map(function (Budget $b) use ($orders, $buyers, $offices, $sums, $events, $users): array {
            $o = $orders[$b->order_id];
            $buyer = $buyers[$o->buyer_id] ?? null;
            $overall = array_map('floatval', $b->overall_costs ?? []);
            $cost = ($sums[$b->id] ?? 0) + array_sum(array_map('floatval', $b->process_costs ?? [])) + array_sum($overall) - ($overall['fob'] ?? 0);
            $last = $events[$b->id] ?? null;

            return [
                'id' => $b->id, 'budget_no' => $b->budget_no, 'buyer' => $buyer?->short_name ?: $buyer?->name, 'buyer_ac' => $b->buyer_office_id ? ($offices[$b->buyer_office_id] ?? null) : null,
                'job_no' => $o->job_no, 'style_no' => $o->style_no, 'order_no' => $o->order_no, 'po_no' => $o->po_no,
                'ship_date' => $o->ship_date?->toDateString(), 'budget_date' => $b->created_at?->toDateString(),
                'order_qty' => $o->total_qty * ($o->unit === 'PCS' ? 1 : max(1, (int) $o->pcs_per_unit)), 'currency' => $b->currency,
                'cost' => round($cost, 4), 'fob' => round((float) ($overall['fob'] ?? 0), 4),
                'status' => $b->approved_at ? 'APPROVED' : 'PENDING', 'approved_at' => $b->approved_at?->toIso8601String(), 'approved_by' => $b->approved_by ? ($users[$b->approved_by] ?? null) : null,
                'last_action' => $last?->action, 'last_note' => $last?->note, 'last_by' => $last?->user_id ? ($users[$last->user_id] ?? null) : null,
            ];
        })->values()->all());
    }

    /**
     * Approve a waiting budget sheet — it is locked for costing — or unlock
     * an approved one so it can be revised; unlocking says why.
     */
    public function decide(Request $request, string $budget): JsonResponse
    {
        $this->allow('merchandise.costing.approve');
        $row = Budget::query()->whereKey($budget)->firstOrFail();
        $data = $request->validate([
            'move' => ['required', Rule::in(['approve', 'unlock'])],
            'note' => [Rule::requiredIf($request->input('move') === 'unlock'), 'nullable', 'string', 'max:2000'],
        ], ['note.required' => __('merchandise.budget.unlock_note')]);
        if (($data['move'] === 'approve') === ($row->approved_at !== null)) {
            throw ValidationException::withMessages(['move' => __($row->approved_at ? 'merchandise.budget.already_approved' : 'merchandise.budget.not_approved')]);
        }
        $user = $this->caller()->auditUserId();
        DB::transaction(function () use ($row, $data, $user): void {
            $row->update($data['move'] === 'approve' ? ['approved_at' => now(), 'approved_by' => $user] : ['approved_at' => null, 'approved_by' => null]);
            $note = isset($data['note']) && trim($data['note']) !== '' ? trim($data['note']) : null;
            BudgetEvent::query()->create(['budget_id' => $row->id, 'action' => $data['move'] === 'approve' ? 'APPROVED' : 'UNLOCKED', 'note' => $note, 'user_id' => $user]);
        });

        return ApiResponse::ok(['status' => $row->approved_at ? 'APPROVED' : 'PENDING']);
    }

    /** One group of a budget's cost heads: what was budgeted per piece, and what production actually spent. */
    public function actuals(string $budget, string $group): JsonResponse
    {
        $this->allow('merchandise.costing.view');
        $row = Budget::query()->whereKey($budget)->firstOrFail();

        return ApiResponse::ok($this->actualsOf($row, $group));
    }

    public function saveActuals(Request $request, string $budget, string $group): JsonResponse
    {
        $this->allow('merchandise.costing.manage');
        $row = Budget::query()->whereKey($budget)->firstOrFail();
        $heads = Budget::ACTUAL_GROUPS[$group];
        $data = $request->validate(['actual' => ['present', 'array']] + collect($heads)->mapWithKeys(fn (string $h): array => ["actual.{$h}" => ['nullable', 'numeric', 'min:0', 'max:99999999']])->all());
        $actual = $row->actual_costs ?? [];
        foreach ($heads as $h) {
            $actual[$h] = (float) ($data['actual'][$h] ?? 0);
        }
        $row->update(['actual_costs' => $actual]);

        return ApiResponse::ok($this->actualsOf($row->refresh(), $group));
    }

    /** @return array{budget_no: string, currency: string, rows: list<array{head: string, budget: float, actual: float}>} */
    private function actualsOf(Budget $budget, string $group): array
    {
        $lines = BudgetLine::query()->where('budget_id', $budget->id)->get(['section', 'qty', 'rate']);
        $section = fn (string $s): float => round((float) $lines->where('section', $s)->sum(fn ($l) => $l->qty * $l->rate), 4);
        $budgeted = [
            'yarn' => $section('YARN'), 'purchased_fabric' => $section('PURCHASED_FABRIC'), 'sewing_acc' => $section('SEWING_ACC'), 'finishing_acc' => $section('FINISHING_ACC'),
            'general_acc' => $section('GENERAL_ACC'), 'special_acc' => $section('SPECIAL_ACC'),
        ] + array_map('floatval', $budget->process_costs ?? []) + array_map('floatval', $budget->overall_costs ?? []);
        $actual = $budget->actual_costs ?? [];

        return [
            'budget_no' => $budget->budget_no, 'currency' => $budget->currency,
            'rows' => array_map(fn (string $h): array => ['head' => $h, 'budget' => (float) ($budgeted[$h] ?? 0), 'actual' => (float) ($actual[$h] ?? 0)], Budget::ACTUAL_GROUPS[$group]),
        ];
    }

    /** BGT-2026-000001: the year and the company's next budget; held to the end of the save. */
    private function nextNumber(): string
    {
        $head = 'BGT-'.now()->format('Y').'-';
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_budgets:'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) Budget::query()->where('budget_no', 'like', $head.'%')->pluck('budget_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
