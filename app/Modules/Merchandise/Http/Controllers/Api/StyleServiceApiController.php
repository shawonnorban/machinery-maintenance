<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleColour;
use App\Modules\Merchandise\Models\StyleProcess;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Item/Combo/Model → Services (AnnotechERP 06/05): per mapped colour its AOP,
 * yarn-dyed, print, embroidery and garment-wash type, and the style's process
 * details (process, type, supplier, remarks, picture). Both are saved at once;
 * a style with no mapped colour still keeps its process details.
 */
class StyleServiceApiController extends ApiController
{
    /** The service types offered for each colour column (Brickland's lists). */
    public const SERVICES = [
        'aop_type' => ['AOP_PIGMENT', 'AOP_REACTIVE', 'AOP_DISCHARGE', 'AOP_SUBLIMATION'],
        'yd_type' => ['STRIPE', 'CHECK', 'FEEDER', 'ENGINEERED_STRIPE', 'JACQUARD'],
        'print_type' => ['RUBBER', 'PLASTISOL', 'DISCHARGE', 'FOIL', 'HIGH_DENSITY', 'GLITTER', 'PUFF', 'DIGITAL_DTG'],
        'embroidery_type' => ['FLAT', 'PUFF_3D', 'APPLIQUE', 'SEQUIN', 'CHAIN_STITCH'],
        'wash_type' => ['ENZYME', 'SILICON', 'GARMENT_DYE', 'NORMAL', 'STONE', 'ACID', 'SOFTENER'],
    ];

    public function index(string $style): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $row = Style::query()->whereKey($style)->firstOrFail();

        return ApiResponse::ok($this->present($row) + [
            'services' => self::SERVICES,
            'processes' => StyleProcess::PROCESSES,
            'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($s): array => ['value' => $s->id, 'label' => $s->name])->values()->all(),
        ]);
    }

    public function save(Request $request, string $style): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = Style::query()->whereKey($style)->firstOrFail();
        $company = $this->caller()->companyId;
        $rules = [
            'colours' => ['present', 'array', 'max:100'],
            'colours.*.id' => ['required', 'distinct', Rule::exists('merch_style_colours', 'id')->where('style_id', $row->id)],
            'processes' => ['present', 'array', 'max:50'],
            'processes.*.process' => ['required', Rule::in(StyleProcess::PROCESSES)],
            'processes.*.type' => ['nullable', 'string', 'max:128'],
            'processes.*.supplier_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'processes.*.remarks' => ['nullable', 'string', 'max:500'],
            'processes.*.image_id' => ['nullable', 'ulid'],
        ];
        foreach (self::SERVICES as $field => $values) {
            $rules["colours.*.{$field}"] = ['nullable', Rule::in($values)];
        }
        $data = $request->validate($rules, [
            'processes.*.process.required' => __('merchandise.style_service.process_required'),
        ]);

        DB::transaction(function () use ($row, $data): void {
            foreach ($data['colours'] as $c) {
                StyleColour::query()->where('style_id', $row->id)->whereKey($c['id'])->update(
                    collect(array_keys(self::SERVICES))->mapWithKeys(fn (string $f): array => [$f => $c[$f] ?? null])->all(),
                );
            }
            StyleProcess::query()->where('style_id', $row->id)->delete();
            foreach (array_values($data['processes']) as $i => $p) {
                StyleProcess::query()->create([
                    'style_id' => $row->id,
                    'process' => $p['process'],
                    'type' => $this->blank($p['type'] ?? null),
                    'supplier_id' => $p['supplier_id'] ?? null,
                    'remarks' => $this->blank($p['remarks'] ?? null),
                    'image_id' => $p['image_id'] ?? null,
                    'sort_order' => ($i + 1) * 10,
                ]);
            }
        });

        return ApiResponse::ok($this->present($row));
    }

    /** @return array{colours: list<array<string, mixed>>, rows: list<array<string, mixed>>} */
    private function present(Style $style): array
    {
        $names = Colour::query()->pluck('name', 'id');

        return [
            'colours' => StyleColour::query()->where('style_id', $style->id)->orderBy('sort_order')->get()->map(fn (StyleColour $c): array => [
                'id' => $c->id, 'colour' => $names[$c->colour_id] ?? null, 'colour_type' => $c->colour_type,
            ] + collect(array_keys(self::SERVICES))->mapWithKeys(fn (string $f): array => [$f => $c->{$f}])->all())->values()->all(),
            'rows' => StyleProcess::query()->where('style_id', $style->id)->orderBy('sort_order')->get()->map(fn (StyleProcess $p): array => [
                'id' => $p->id, 'process' => $p->process, 'type' => $p->type, 'supplier_id' => $p->supplier_id, 'remarks' => $p->remarks,
                'image_id' => $p->image_id, 'image' => $p->image_id ? "/api/files/{$p->image_id}/download" : null,
            ])->values()->all(),
        ];
    }

    private function blank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
