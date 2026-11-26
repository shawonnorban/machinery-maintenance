<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\Fiber;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\FiberCompositionItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Fiber Composition: blends totalling 100%, described from their parts. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class FiberCompositionResource extends MasterResource
{
    public function key(): string
    {
        return 'fiber-compositions';
    }

    public function model(): string
    {
        return FiberComposition::class;
    }

    public function columns(): array
    {
        return ['code', 'description', 'active'];
    }

    /** @var list<array{fiber_id: string, percent: float}>|null the parts of the row being saved */
    private ?array $items = null;

    public function query(): Builder
    {
        return FiberComposition::query()->orderBy('code');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:32', $this->uniqueCode($row)],
            'active' => ['sometimes', 'boolean'],
            'items' => [$required, 'array', 'min:1', 'max:8'],
            'items.*.fiber_id' => ['required', 'distinct', Rule::exists('fibers', 'id')->where('company_id', $this->company())],
            'items.*.percent' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return ['items.required' => __('merchandise.master.pick_fibers'), 'items.min' => __('merchandise.master.pick_fibers')];
    }

    /**
     * The parts must total exactly 100%; the description is written from them,
     * largest share first: "60% Cotton 40% Polyester".
     */
    public function beforeSave(array $data, ?Model $row): array
    {
        $data = parent::beforeSave($data, $row);
        if (array_key_exists('items', $data)) {
            $items = collect($data['items'])->map(fn (array $i): array => ['fiber_id' => $i['fiber_id'], 'percent' => round((float) $i['percent'], 2)]);
            if (abs($items->sum('percent') - 100) > 0.001) {
                throw ValidationException::withMessages(['items' => __('merchandise.master.total_100', ['total' => $items->sum('percent')])]);
            }
            $names = Fiber::query()->whereIn('id', $items->pluck('fiber_id'))->pluck('name', 'id');
            $data['description'] = $items->sortByDesc('percent')
                ->map(fn (array $i): string => rtrim(rtrim(number_format($i['percent'], 2, '.', ''), '0'), '.').'% '.$names[$i['fiber_id']])
                ->implode(' ');
            $this->items = $items->values()->all();
            unset($data['items']);
        }

        return $data;
    }

    public function afterSave(Model $row, bool $created): void
    {
        if ($this->items === null) {
            return;
        }
        FiberCompositionItem::query()->where('fiber_composition_id', $row->getKey())->delete();
        foreach ($this->items as $item) {
            FiberCompositionItem::query()->create(['company_id' => $row->getAttribute('company_id'), 'fiber_composition_id' => $row->getKey()] + $item);
        }
        $this->items = null;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $items = FiberCompositionItem::query()->where('fiber_composition_id', $row->getKey())->orderByDesc('percent')->get();
        $names = Fiber::query()->whereIn('id', $items->pluck('fiber_id'))->pluck('name', 'id');
        $out['items'] = $items->map(fn (FiberCompositionItem $i): array => ['fiber_id' => $i->fiber_id, 'fiber' => $names[$i->fiber_id] ?? null, 'percent' => (float) $i->percent])->all();
        $out['fiber_types'] = $items->map(fn (FiberCompositionItem $i): ?string => $names[$i->fiber_id] ?? null)->filter()->implode(', ');
        $out['total'] = (float) $items->sum('percent');

        return $out;
    }
}
