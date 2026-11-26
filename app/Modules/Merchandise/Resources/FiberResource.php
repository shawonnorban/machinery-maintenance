<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\Fiber;
use App\Modules\Merchandise\Models\FiberCompositionItem;
use App\Modules\Merchandise\Support\MerchandiseDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Fiber Master: the fibers yarn is spun from. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class FiberResource extends MasterResource
{
    public function key(): string
    {
        return 'fibers';
    }

    public function model(): string
    {
        return Fiber::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'short_name', 'sort_order', 'active'];
    }

    protected function orderBy(): string
    {
        return 'sort_order';
    }

    public function query(): Builder
    {
        MerchandiseDefaults::ensure($this->company());

        return parent::query();
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:32', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:32'],
            'sort_order' => [$required, 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        // "Used By": how many compositions blend this fiber.
        $out['used_by'] = FiberCompositionItem::query()->where('fiber_id', $row->getKey())->count();

        return $out;
    }

    public function beforeDelete(Model $row): void
    {
        if (FiberCompositionItem::query()->where('fiber_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['id' => __('merchandise.master.in_use')]);
        }
    }
}
