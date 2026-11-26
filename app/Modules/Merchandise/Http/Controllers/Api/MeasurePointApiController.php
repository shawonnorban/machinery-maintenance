<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\MeasureChart;
use App\Modules\Merchandise\Models\MeasurePoint;
use App\Modules\Merchandise\Models\Style;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Collar & Cuff Measurement Sheet → Add Measurement (AnnotechERP 06/10): one
 * chart's points of measure per size. Add measurement keeps the point at
 * once; a size has each point of measure once ("That point already
 * exists…", as Brickland says). The value, tolerance and unit are then
 * changed in place and saved together, and a point left out is removed.
 */
class MeasurePointApiController extends ApiController
{
    public function index(string $chart): JsonResponse
    {
        $this->allow('merchandise.sample.view');

        return ApiResponse::ok($this->present(MeasureChart::query()->whereKey($chart)->firstOrFail()));
    }

    public function add(Request $request, string $chart): JsonResponse
    {
        $this->allow('merchandise.sample.manage');
        $row = MeasureChart::query()->whereKey($chart)->firstOrFail();
        $data = $request->validate([
            'size' => ['required', 'string', 'max:32'],
            'point' => ['required', 'string', 'max:160'],
        ] + $this->valueRules(''), [
            'size.required' => __('merchandise.measure.size_required'),
            'point.required' => __('merchandise.measure.point_required'),
        ] + $this->valueMessages(''));
        $size = trim($data['size']);
        $point = trim($data['point']);
        $twice = MeasurePoint::query()->where('chart_id', $row->id)->whereRaw('upper(size) = ?', [mb_strtoupper($size)])->whereRaw('upper(point) = ?', [mb_strtoupper($point)])->exists();
        if ($twice) {
            throw ValidationException::withMessages(['point' => __('merchandise.measure.point_twice')]);
        }
        MeasurePoint::query()->create($this->values($data) + [
            'chart_id' => $row->id, 'size' => $size, 'point' => $point, 'sort_order' => 10 + (int) MeasurePoint::query()->where('chart_id', $row->id)->max('sort_order'),
        ]);

        return ApiResponse::ok($this->present($row) + ['added' => "{$size} · {$point}"]);
    }

    public function save(Request $request, string $chart): JsonResponse
    {
        $this->allow('merchandise.sample.manage');
        $row = MeasureChart::query()->whereKey($chart)->firstOrFail();
        $data = $request->validate(['rows' => ['present', 'array', 'max:500'], 'rows.*.id' => ['required', 'string']] + $this->valueRules('rows.*.'), $this->valueMessages('rows.*.'));
        $existing = MeasurePoint::query()->where('chart_id', $row->id)->get()->keyBy('id');
        $kept = collect($data['rows'])->pluck('id')->all();
        foreach ($kept as $id) {
            if (! $existing->has($id)) {
                throw ValidationException::withMessages(['rows' => __('merchandise.measure.point_gone')]);
            }
        }
        DB::transaction(function () use ($row, $data, $existing, $kept): void {
            MeasurePoint::query()->where('chart_id', $row->id)->whereNotIn('id', $kept)->delete();
            foreach ($data['rows'] as $r) {
                $existing[$r['id']]->update($this->values($r));
            }
        });

        return ApiResponse::ok($this->present($row));
    }

    /** @return array<string, list<mixed>> */
    private function valueRules(string $prefix): array
    {
        return [
            $prefix.'value' => ['required', 'numeric', 'min:0', 'max:99999999'],
            $prefix.'tolerance' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            $prefix.'uom' => ['nullable', 'string', fn (string $attribute, mixed $value, \Closure $fail) => in_array(strtoupper(trim((string) $value)), MeasurePoint::UNITS, true) ? null : $fail(__('validation.in', ['attribute' => 'uom']))],
        ];
    }

    /** @return array<string, string> */
    private function valueMessages(string $prefix): array
    {
        return [$prefix.'value.required' => __('merchandise.measure.value_required')];
    }

    /** @return array<string, mixed> */
    private function values(array $r): array
    {
        return ['value' => $r['value'], 'tolerance' => $r['tolerance'] ?? null, 'uom' => isset($r['uom']) && trim((string) $r['uom']) !== '' ? strtoupper(trim((string) $r['uom'])) : null];
    }

    /** @return array{chart: array<string, mixed>, rows: list<array<string, mixed>>} */
    private function present(MeasureChart $row): array
    {
        $style = Style::query()->whereKey($row->style_id)->first();

        return [
            // Brickland's "PV27-DCCH56 · Collar".
            'chart' => ['id' => $row->id, 'label' => trim(($style?->styleNo() ?? '—').' · '.$row->item_name)],
            'rows' => MeasurePoint::query()->where('chart_id', $row->id)->orderBy('sort_order')->get()->map(fn (MeasurePoint $p): array => [
                'id' => $p->id, 'size' => $p->size, 'point' => $p->point, 'value' => $p->value, 'tolerance' => $p->tolerance, 'uom' => $p->uom,
            ])->values()->all(),
        ];
    }
}
