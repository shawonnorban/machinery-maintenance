<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\LabdipProgram;
use App\Modules\Merchandise\Models\LabdipShade;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\Style;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Lab Dip Programme → Labdip Specification (AnnotechERP 06/10): the shades
 * on one program — colour, Pantone, the style and order it is for, fabric,
 * light source and target date — saved together. A shade keeps its id across
 * saves (what Lab Dip Submission records against it stays with it); one left
 * out is removed, unless it has already gone to the buyer. A colour is
 * entered once per style on a program.
 *
 * Lab Dip Submission saves the same rows with each shade's submission —
 * status, submitted on and the buyer's feedback; a save without them (Lab
 * Dip Programme's own panel) leaves them as they were. A verdict (approved,
 * rejected, resubmit) is dated the day it is recorded, and anything but
 * Pending needs the day the shade was submitted.
 */
class LabdipShadeApiController extends ApiController
{
    public function index(string $program): JsonResponse
    {
        $this->allow('merchandise.sample.view');
        $row = LabdipProgram::query()->whereKey($program)->firstOrFail();

        return ApiResponse::ok($this->present($row) + [
            'styles' => Style::query()->where('buyer_id', $row->buyer_id)->orderBy('style_ref')->get()->map(fn (Style $s): array => ['value' => $s->id, 'label' => "{$s->styleNo()} · {$s->description}"])->values()->all(),
            'orders' => Order::query()->where('buyer_id', $row->buyer_id)->where('status', '!=', 'CANCELLED')->orderByDesc('order_no')->get(['id', 'order_no', 'style_id', 'style_no'])
                ->map(fn (Order $o): array => ['value' => $o->id, 'label' => "{$o->order_no} · {$o->style_no}", 'style_id' => $o->style_id])->values()->all(),
            'light_sources' => LabdipShade::LIGHT_SOURCES, 'statuses' => LabdipShade::STATUSES,
        ]);
    }

    /**
     * Lab Dip Submission → Records: every shade on every program with its
     * style, order, program, the day the program was raised, its target, when
     * it went to the buyer and the buyer's verdict.
     */
    public function submissions(): JsonResponse
    {
        $this->allow('merchandise.sample.view');
        $shades = LabdipShade::query()->orderByDesc('created_at')->orderBy('sort_order')->get();
        $programs = LabdipProgram::query()->whereIn('id', $shades->pluck('program_id'))->get()->keyBy('id');
        $offices = BuyerOffice::query()->whereIn('id', $programs->pluck('buyer_office_id'))->pluck('name', 'id');
        $styles = Style::query()->whereIn('id', $shades->pluck('style_id')->filter())->get()->keyBy('id');
        $orders = Order::query()->whereIn('id', $shades->pluck('order_id')->filter())->pluck('order_no', 'id');

        return ApiResponse::ok($shades->map(function (LabdipShade $s) use ($programs, $offices, $styles, $orders): array {
            $p = $programs[$s->program_id];

            return [
                'id' => $s->id, 'program_no' => $p->program_no, 'buyer_ac' => $offices[$p->buyer_office_id] ?? null,
                'style_no' => $s->style_id ? ($styles[$s->style_id] ?? null)?->styleNo() : null, 'order_no' => $s->order_id ? ($orders[$s->order_id] ?? null) : null,
                'colour' => $s->colour, 'reg_date' => $p->request_date->toDateString(), 'target_date' => $s->target_date?->toDateString(),
                'submitted_on' => $s->submitted_on?->toDateString(), 'status' => $s->status, 'decided_on' => $s->decided_on?->toDateString(), 'feedback' => $s->feedback,
            ];
        })->values()->all());
    }

    public function save(Request $request, string $program): JsonResponse
    {
        $this->allow('merchandise.sample.manage');
        $row = LabdipProgram::query()->whereKey($program)->firstOrFail();
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'rows' => ['present', 'array', 'max:200'],
            'rows.*.id' => ['nullable', 'string'],
            'rows.*.colour' => ['required', 'string', 'max:96'],
            'rows.*.pantone' => ['nullable', 'string', 'max:48'],
            'rows.*.style_id' => ['nullable', Rule::exists('merch_styles', 'id')->where('company_id', $company)->where('buyer_id', $row->buyer_id)],
            'rows.*.order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)->where('buyer_id', $row->buyer_id)],
            'rows.*.fabric' => ['nullable', 'string', 'max:160'],
            'rows.*.light_source' => ['nullable', Rule::in(LabdipShade::LIGHT_SOURCES)],
            'rows.*.target_date' => ['nullable', 'date'],
            'rows.*.status' => ['sometimes', Rule::in(LabdipShade::STATUSES)],
            'rows.*.submitted_on' => ['nullable', 'date', 'after_or_equal:'.$row->request_date->toDateString(), 'before_or_equal:today'],
            'rows.*.feedback' => ['nullable', 'string', 'max:2000'],
        ], [
            'rows.*.colour.required' => __('merchandise.labdip.colour_required'),
            'rows.*.style_id.exists' => __('merchandise.labdip.other_buyer'),
            'rows.*.order_id.exists' => __('merchandise.labdip.other_buyer'),
            'rows.*.submitted_on.after_or_equal' => __('merchandise.labdip.submitted_before_request'),
            'rows.*.submitted_on.before_or_equal' => __('merchandise.labdip.submitted_ahead'),
        ]);
        foreach ($data['rows'] as $i => $r) {
            if (($r['status'] ?? 'PENDING') !== 'PENDING' && empty($r['submitted_on'])) {
                throw ValidationException::withMessages(["rows.{$i}.submitted_on" => __('merchandise.labdip.submitted_needed', ['colour' => trim($r['colour'])])]);
            }
        }
        $seen = [];
        foreach ($data['rows'] as $r) {
            $key = mb_strtoupper(trim($r['colour'])).'|'.($r['style_id'] ?? '');
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['rows' => __('merchandise.labdip.colour_twice', ['colour' => trim($r['colour'])])]);
            }
            $seen[$key] = true;
        }
        $existing = LabdipShade::query()->where('program_id', $row->id)->get()->keyBy('id');
        foreach ($data['rows'] as $r) {
            if (! empty($r['id']) && ! $existing->has($r['id'])) {
                throw ValidationException::withMessages(['rows' => __('merchandise.labdip.shade_gone')]);
            }
        }
        $kept = collect($data['rows'])->pluck('id')->filter()->all();
        $sent = $existing->reject(fn (LabdipShade $s): bool => in_array($s->id, $kept, true) || $s->status === 'PENDING')->first();
        if ($sent) {
            throw ValidationException::withMessages(['rows' => __('merchandise.labdip.shade_sent', ['colour' => $sent->colour])]);
        }

        DB::transaction(function () use ($row, $data, $existing): void {
            $kept = collect($data['rows'])->pluck('id')->filter()->all();
            LabdipShade::query()->where('program_id', $row->id)->whereNotIn('id', $kept)->delete();
            $text = fn (array $r, string $k): ?string => isset($r[$k]) && trim((string) $r[$k]) !== '' ? trim((string) $r[$k]) : null;
            foreach (array_values($data['rows']) as $i => $r) {
                $values = [
                    'style_id' => $r['style_id'] ?? null, 'order_id' => $r['order_id'] ?? null, 'colour' => trim($r['colour']), 'pantone' => $text($r, 'pantone'),
                    'fabric' => $text($r, 'fabric'), 'light_source' => $r['light_source'] ?? null, 'target_date' => $r['target_date'] ?? null, 'sort_order' => ($i + 1) * 10,
                ];
                if (array_key_exists('status', $r)) {
                    $before = empty($r['id']) ? null : $existing[$r['id']];
                    $verdict = in_array($r['status'], LabdipShade::VERDICTS, true);
                    $values += [
                        'status' => $r['status'], 'submitted_on' => $r['submitted_on'] ?? null, 'feedback' => $text($r, 'feedback'),
                        'decided_on' => $verdict ? ($before && $before->status === $r['status'] ? $before->decided_on : now()->toDateString()) : null,
                    ];
                }
                empty($r['id']) ? LabdipShade::query()->create($values + ['program_id' => $row->id]) : $existing[$r['id']]->update($values);
            }
        });

        return ApiResponse::ok($this->present($row));
    }

    /** @return array{program: array<string, mixed>, rows: list<array<string, mixed>>} */
    private function present(LabdipProgram $row): array
    {
        $shades = LabdipShade::query()->where('program_id', $row->id)->orderBy('sort_order')->get();
        $styles = Style::query()->whereIn('id', $shades->pluck('style_id')->filter())->get()->keyBy('id');
        $orders = Order::query()->whereIn('id', $shades->pluck('order_id')->filter())->pluck('order_no', 'id');

        return [
            'program' => ['id' => $row->id, 'program_no' => $row->program_no, 'buyer_id' => $row->buyer_id, 'request_date' => $row->request_date->toDateString()],
            'rows' => $shades->map(fn (LabdipShade $s): array => [
                'id' => $s->id, 'colour' => $s->colour, 'pantone' => $s->pantone, 'style_id' => $s->style_id, 'style_no' => $s->style_id ? ($styles[$s->style_id] ?? null)?->styleNo() : null,
                'order_id' => $s->order_id, 'order_no' => $s->order_id ? ($orders[$s->order_id] ?? null) : null, 'fabric' => $s->fabric, 'light_source' => $s->light_source,
                'target_date' => $s->target_date?->toDateString(), 'status' => $s->status, 'submitted_on' => $s->submitted_on?->toDateString(),
                'decided_on' => $s->decided_on?->toDateString(), 'feedback' => $s->feedback,
            ])->values()->all(),
        ];
    }
}
