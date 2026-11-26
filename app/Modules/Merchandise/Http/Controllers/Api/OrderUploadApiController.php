<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OrderSize;
use App\Modules\Merchandise\Models\OrderUpload;
use App\Modules\Merchandise\Models\OrderUploadLine;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\Size;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Support\OrderNumber;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Master Data → Style & Order Upload (AnnotechERP 06/01), as Brickland works
 * it: a buyer's delimited spreadsheet (Buyer A/C Group, Buyer A/C, Buyer,
 * Style, Job#, Sub Style, Order#, Confirm DT, Ship DT, Ship Country, Item,
 * GMT Type, GMT Color, Size, Qty, Rate, Total, Production Date) is verified
 * against the register — the buyer matched by code or name, the account,
 * country, colour and size by name or code; anything it does not hold is
 * rejected rather than guessed at — and only a batch with no rejected row is
 * uploaded: each Order# of a style becomes one confirmed order (its style
 * made when new), its rows the order's colour × size breakdown. Numbered
 * UPL-00001; Records lists every order line uploaded.
 */
class OrderUploadApiController extends ApiController
{
    /** header key => [what it reads, required?] */
    private const COLUMNS = [
        'buyeracgroup' => ['buyer_ac_group', false], 'buyerac' => ['buyer_ac', false], 'buyer' => ['buyer', true], 'style' => ['style', true], 'job' => ['job_no', true],
        'substyle' => ['sub_style', false], 'order' => ['po_no', true], 'confirmdt' => ['confirm_date', false], 'shipdt' => ['ship_date', true], 'shipcountry' => ['ship_country', false],
        'item' => ['item', false], 'gmttype' => ['gmt_type', false], 'gmtcolor' => ['colour', true], 'size' => ['size', true], 'qty' => ['qty', true], 'rate' => ['rate', false],
        'total' => ['total', false], 'productiondate' => ['production_date', false],
    ];

    private const MAX_ROWS = 5000;

    /** Records: every order line uploaded, newest batch first. */
    public function index(): JsonResponse
    {
        $this->allow('merchandise.order.view');
        $batches = OrderUpload::query()->get()->keyBy('id');
        $orders = Order::query()->whereIn('id', OrderUploadLine::query()->pluck('order_id')->filter()->unique())->pluck('order_no', 'id');

        return ApiResponse::ok(OrderUploadLine::query()->orderByDesc('created_at')->orderBy('row_no')->get()->map(fn (OrderUploadLine $l): array => [
            'id' => $l->id, 'batch_no' => $batches[$l->upload_id]->batch_no ?? null, 'uploaded_at' => ($batches[$l->upload_id] ?? null)?->created_at?->toIso8601String(),
            'buyer_ac' => $l->buyer_ac, 'buyer' => $l->buyer, 'style' => $l->style, 'job_no' => $l->job_no, 'po_no' => $l->po_no, 'order_no' => $l->order_id ? ($orders[$l->order_id] ?? null) : null,
            'confirm_date' => $l->confirm_date?->toDateString(), 'ship_date' => $l->ship_date?->toDateString(), 'item' => $l->item, 'colour' => $l->colour, 'size' => $l->size,
            'qty' => $l->qty, 'rate' => $l->rate, 'total' => $l->total,
        ])->values()->all());
    }

    /** Verify File Data: every row read and checked against the register; nothing is kept. */
    public function verify(Request $request): JsonResponse
    {
        $this->allow('merchandise.order.manage');

        return ApiResponse::ok($this->checked($this->rowsOf($request)));
    }

    /** Upload: the batch, only when no row is rejected. */
    public function upload(Request $request): JsonResponse
    {
        $this->allow('merchandise.order.manage');
        $file = $request->file('file');
        $checked = $this->checked($this->rowsOf($request));
        if ($checked['rejected'] > 0) {
            throw ValidationException::withMessages(['file' => trans_choice('merchandise.oup.blocked', $checked['rejected'], ['n' => $checked['rejected']])]);
        }

        $batch = DB::transaction(function () use ($checked, $file): OrderUpload {
            $batch = OrderUpload::query()->create([
                'batch_no' => $this->nextNumber(), 'file_name' => $file->getClientOriginalName(), 'rows' => count($checked['rows']), 'uploaded_by' => $this->caller()->auditUserId(),
            ]);
            $styles = 0;
            $orders = 0;
            foreach (collect($checked['rows'])->groupBy(fn (array $r) => mb_strtoupper($r['style'])) as $group) {
                $first = $group->first();
                $style = $first['style_id'] ? Style::query()->find($first['style_id']) : null;
                if ($style === null) {
                    $style = Style::query()->create([
                        'buyer_id' => $first['buyer_id'], 'buyer_office_id' => $first['buyer_office_id'], 'style_ref' => $first['style'], 'description' => $first['item'] ?: $first['style'],
                        'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'RUNNING', 'created_by' => $this->caller()->auditUserId(),
                    ]);
                    $styles++;
                }
                foreach ($group->groupBy(fn (array $r) => mb_strtoupper($r['job_no'].'|'.$r['po_no'])) as $lines) {
                    $o = $lines->first();
                    $qty = (int) $lines->sum('qty');
                    $value = (float) $lines->sum(fn (array $r) => $r['qty'] * (float) ($r['rate'] ?? 0));
                    $order = Order::query()->create([
                        'order_no' => OrderNumber::next($o['buyer_id']), 'job_no' => $o['job_no'], 'style_id' => $style->id, 'style_no' => $style->styleNo(), 'work_style_no' => $style->styleNo(),
                        'buyer_id' => $o['buyer_id'], 'buyer_office_id' => $o['buyer_office_id'] ?? $style->buyer_office_id, 'po_no' => $o['po_no'], 'sub_style' => $o['sub_style'],
                        'order_type' => 'BULK_NEW', 'item' => $o['item'], 'gmt_type' => $o['gmt_type'], 'order_date' => $o['confirm_date'], 'conf_date' => $o['confirm_date'],
                        'ship_date' => $o['ship_date'], 'ship_country_id' => $o['country_id'], 'total_qty' => $qty, 'unit' => 'PCS',
                        'unit_price' => $qty > 0 && $lines->contains(fn (array $r) => $r['rate'] !== null) ? round($value / $qty, 4) : null, 'currency' => 'USD', 'status' => 'CONFIRMED',
                        'created_by' => $this->caller()->auditUserId(),
                    ]);
                    $orders++;
                    foreach ($lines->values() as $i => $r) {
                        OrderSize::query()->create(['order_id' => $order->id, 'colour' => $r['colour_name'], 'size' => $r['size_name'], 'qty' => $r['qty'], 'sort_order' => $i + 1]);
                        OrderUploadLine::query()->create(collect($r)->only([
                            'row_no', 'buyer_ac_group', 'buyer_ac', 'buyer', 'style', 'job_no', 'sub_style', 'po_no', 'confirm_date', 'ship_date', 'ship_country', 'item', 'gmt_type',
                            'colour', 'size', 'qty', 'rate', 'total', 'production_date',
                        ])->all() + ['upload_id' => $batch->id, 'style_id' => $style->id, 'order_id' => $order->id]);
                    }
                }
            }
            $batch->update(['styles' => $styles, 'orders' => $orders]);

            return $batch;
        });

        return ApiResponse::created(['batch_no' => $batch->batch_no, 'rows' => $batch->rows, 'styles' => $batch->styles, 'orders' => $batch->orders]);
    }

    /**
     * The file's rows, keyed by what each column reads; the header row names the columns.
     *
     * @return list<array<string, mixed>>
     */
    private function rowsOf(Request $request): array
    {
        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,tsv']], [
            'file.required' => __('merchandise.oup.file_required'),
            'file.mimes' => __('merchandise.oup.file_type'),
        ]);
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $text = (string) file_get_contents($file->getRealPath());
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $lines = preg_split('/\r\n|\n|\r/', trim($text));
        $header = $lines[0] ?? '';
        // Comma, semicolon or tab — whichever splits the header most.
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn (string $d) => count(str_getcsv($header, $d)))->first();
        $keys = array_map(fn (string $h): string => preg_replace('/[^a-z]/', '', strtolower($h)), str_getcsv($header, $delimiter));
        $missing = collect(self::COLUMNS)->filter(fn (array $c, string $k) => $c[1] && ! in_array($k, $keys, true))->keys();
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['file' => __('merchandise.oup.missing_columns', ['columns' => $missing->map(fn (string $k) => __("merchandise.master.oup.col.{$k}"))->implode(', ')])]);
        }
        $rows = [];
        foreach (array_slice($lines, 1) as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter);
            $row = ['row_no' => $i + 2];
            foreach ($keys as $c => $key) {
                if (isset(self::COLUMNS[$key])) {
                    $row[self::COLUMNS[$key][0]] = trim((string) ($cells[$c] ?? ''));
                }
            }
            $rows[] = $row;
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => __('merchandise.oup.no_rows')]);
        }
        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => __('merchandise.oup.too_many', ['max' => self::MAX_ROWS])]);
        }

        return $rows;
    }

    /**
     * Each row matched against the register, with why it is rejected (if it is).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{rows: list<array<string, mixed>>, accepted: int, rejected: int}
     */
    private function checked(array $rows): array
    {
        $norm = fn (?string $v): string => mb_strtoupper(trim((string) $v));
        $index = fn (Collection $items, array $fields): array => $items->flatMap(fn ($m) => collect($fields)->mapWithKeys(fn (string $f) => [$norm($m->{$f}) => $m]))->except([''])->all();
        $buyers = $index(Buyer::query()->get(['id', 'code', 'name', 'short_name']), ['code', 'name', 'short_name']);
        $offices = $index(BuyerOffice::query()->get(['id', 'name']), ['name']);
        $countries = $index(OriginCountry::query()->get(['id', 'code', 'name']), ['code', 'name']);
        $colours = $index(Colour::query()->get(['id', 'code', 'name']), ['code', 'name']);
        $sizes = $index(Size::query()->get(['id', 'code', 'name']), ['code', 'name']);
        // A style number is the company's once, whoever the buyer.
        $styles = Style::query()->get(['id', 'buyer_id', 'style_ref', 'style_ext'])->keyBy(fn (Style $s) => $norm($s->styleNo()));
        $taken = Order::query()->whereNotNull('po_no')->get(['style_id', 'po_no'])->map(fn (Order $o) => $o->style_id.'|'.$norm($o->po_no))->flip();
        $seen = [];
        $styleBuyer = [];
        $out = [];
        foreach ($rows as $r) {
            $why = [];
            $buyer = $buyers[$norm($r['buyer'] ?? '')] ?? null;
            if ($buyer === null) {
                $why[] = __('merchandise.oup.no_buyer', ['value' => $r['buyer'] ?? '']);
            }
            $office = ($r['buyer_ac'] ?? '') !== '' ? ($offices[$norm($r['buyer_ac'])] ?? null) : null;
            if (($r['buyer_ac'] ?? '') !== '' && $office === null) {
                $why[] = __('merchandise.oup.no_account', ['value' => $r['buyer_ac']]);
            }
            foreach (['style', 'job_no', 'po_no'] as $k) {
                if (($r[$k] ?? '') === '') {
                    $why[] = __('merchandise.oup.blank', ['column' => __('merchandise.master.oup.f.'.$k)]);
                }
            }
            $country = ($r['ship_country'] ?? '') !== '' ? ($countries[$norm($r['ship_country'])] ?? null) : null;
            if (($r['ship_country'] ?? '') !== '' && $country === null) {
                $why[] = __('merchandise.oup.no_country', ['value' => $r['ship_country']]);
            }
            $colour = $colours[$norm($r['colour'] ?? '')] ?? null;
            if ($colour === null) {
                $why[] = __('merchandise.oup.no_colour', ['value' => $r['colour'] ?? '']);
            }
            $size = $sizes[$norm($r['size'] ?? '')] ?? null;
            if ($size === null) {
                $why[] = __('merchandise.oup.no_size', ['value' => $r['size'] ?? '']);
            }
            $qty = $r['qty'] ?? '';
            if (! preg_match('/^\d+$/', str_replace(',', '', $qty)) || (int) str_replace(',', '', $qty) <= 0) {
                $why[] = __('merchandise.oup.bad_qty', ['value' => $qty]);
            }
            $qty = (int) str_replace(',', '', $qty);
            $num = fn (string $k): ?float => ($r[$k] ?? '') === '' ? null : (is_numeric(str_replace(',', '', $r[$k])) ? (float) str_replace(',', '', $r[$k]) : NAN);
            $rate = $num('rate');
            $total = $num('total');
            if (($rate !== null && is_nan($rate)) || ($total !== null && is_nan($total))) {
                $why[] = __('merchandise.oup.bad_number');
            } elseif ($rate !== null && $total !== null && abs($qty * $rate - $total) > 0.01) {
                $why[] = __('merchandise.oup.bad_total', ['total' => $r['total'], 'qty' => $qty, 'rate' => $r['rate']]);
            }
            $dates = [];
            foreach (['confirm_date', 'ship_date', 'production_date'] as $k) {
                $dates[$k] = $this->date($r[$k] ?? '');
                if (($r[$k] ?? '') !== '' && $dates[$k] === null) {
                    $why[] = __('merchandise.oup.bad_date', ['column' => __('merchandise.master.oup.f.'.$k), 'value' => $r[$k]]);
                }
            }
            if (($r['ship_date'] ?? '') === '') {
                $why[] = __('merchandise.oup.blank', ['column' => __('merchandise.master.oup.f.ship_date')]);
            }
            if ($dates['confirm_date'] && $dates['ship_date'] && $dates['ship_date'] < $dates['confirm_date']) {
                $why[] = __('merchandise.oup.ship_before');
            }
            $style = ($r['style'] ?? '') !== '' ? ($styles[$norm($r['style'])] ?? null) : null;
            if ($style && $buyer && $style->buyer_id !== $buyer->id) {
                $why[] = __('merchandise.oup.other_buyer', ['style' => $r['style']]);
            }
            // Within the file too, one style is one buyer's.
            if ($buyer && ($r['style'] ?? '') !== '') {
                $first = $styleBuyer[$norm($r['style'])] ??= $buyer->id;
                if ($first !== $buyer->id) {
                    $why[] = __('merchandise.oup.other_buyer', ['style' => $r['style']]);
                }
            }
            if ($style && isset($taken[$style->id.'|'.$norm($r['po_no'] ?? '')])) {
                $why[] = __('merchandise.oup.on_register', ['order' => $r['po_no'], 'style' => $r['style']]);
            }
            $key = $norm(($buyer?->id).'|'.($r['style'] ?? '').'|'.($r['job_no'] ?? '').'|'.($r['po_no'] ?? '').'|'.($colour?->id).'|'.($size?->id));
            if (isset($seen[$key])) {
                $why[] = __('merchandise.oup.twice', ['row' => $seen[$key]]);
            }
            $seen[$key] ??= $r['row_no'];

            $out[] = array_merge($r, [
                'qty' => $qty, 'rate' => is_float($rate) && ! is_nan($rate) ? $rate : null, 'total' => is_float($total) && ! is_nan($total) ? $total : ($rate !== null && ! is_nan($rate) ? round($qty * $rate, 2) : null),
                'confirm_date' => $dates['confirm_date'], 'ship_date' => $dates['ship_date'], 'production_date' => $dates['production_date'],
                'buyer_id' => $buyer?->id, 'buyer_office_id' => $office?->id, 'country_id' => $country?->id, 'style_id' => $style?->id, 'new_style' => $buyer !== null && $style === null,
                'colour_name' => $colour?->name, 'size_name' => $size?->name, 'status' => $why === [] ? 'OK' : 'REJECTED', 'reasons' => $why,
                'sub_style' => ($r['sub_style'] ?? '') ?: null, 'item' => ($r['item'] ?? '') ?: null, 'gmt_type' => ($r['gmt_type'] ?? '') ?: null,
                'buyer_ac' => ($r['buyer_ac'] ?? '') ?: null, 'buyer_ac_group' => ($r['buyer_ac_group'] ?? '') ?: null, 'ship_country' => ($r['ship_country'] ?? '') ?: null,
            ]);
        }
        $rejected = count(array_filter($out, fn (array $r) => $r['status'] === 'REJECTED'));

        return ['rows' => $out, 'accepted' => count($out) - $rejected, 'rejected' => $rejected];
    }

    /** 2026-11-30, 30/11/2026, 30-Nov-2026 or 30-Nov-26 → 2026-11-30; null when it is none of them. */
    private function date(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y', '!d-M-Y', '!d-M-y', '!d M Y', '!j/n/Y'] as $format) {
            try {
                $d = CarbonImmutable::createFromFormat($format, $value);
                if ($d !== false && $d->format(ltrim($format, '!')) === $value) {
                    return $d->toDateString();
                }
            } catch (Throwable) {
            }
        }

        return null;
    }

    /** UPL-00001: the company's next upload batch; held to the end of the save. */
    private function nextNumber(): string
    {
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_order_uploads:'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) OrderUpload::query()->pluck('batch_no')->map(fn (string $v): int => (int) substr($v, 4))->max();

        return 'UPL-'.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
