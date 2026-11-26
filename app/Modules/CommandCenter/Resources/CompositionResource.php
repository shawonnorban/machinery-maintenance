<?php

declare(strict_types=1);

namespace App\Modules\CommandCenter\Resources;

use App\Modules\CommandCenter\Models\Composition;
use App\Modules\CommandCenter\Support\CommandBook;
use App\Modules\Erp\Support\DemoResource;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Dashboard Composer (AnnotechERP 27/01): boards assembled from the KPI
 * library without asking for a new screen — a layout, a set of tiles and
 * charts, its author and the people it is shared with. A title is used once;
 * an empty composition (no tile, no chart) cannot be released.
 */
class CompositionResource extends DemoResource
{
    protected function prefix(): string
    {
        return 'commandcenter';
    }

    protected function area(): string
    {
        return 'command';
    }

    public function key(): string
    {
        return 'cc-compositions';
    }

    public function model(): string
    {
        return Composition::class;
    }

    public function columns(): array
    {
        return ['title', 'layout', 'author_id', 'tiles', 'charts', 'shared_with', 'state', 'notes'];
    }

    public function defaults(): array
    {
        return ['tiles' => 0, 'charts' => 0, 'shared_with' => 'BOARD', 'state' => 'DRAFT'];
    }

    public function rules(?Model $row): array
    {
        $value = fn (string $f) => request()->has($f) ? request()->input($f) : $row?->getAttribute($f);
        // Released means people see it: it must hold something.
        $empty = function (string $attribute, mixed $v, Closure $fail) use ($value): void {
            if ($value('state') === 'RELEASED' && (int) $value('tiles') + (int) $value('charts') === 0) {
                $fail(__('commandcenter.v.release_empty'));
            }
        };

        return [
            'title' => [$row ? 'sometimes' : 'required', 'string', 'max:160', $this->once($row, 'title', 'commandcenter.v.composition_taken')],
            'layout' => ['nullable', Rule::in(CommandBook::LAYOUTS)],
            'author_id' => $this->staffRule(),
            // Emptying a released one is the same mistake, caught from this side when the state is not sent.
            'tiles' => [...$this->count(200), fn (string $a, mixed $v, Closure $fail) => request()->has('state') ? null : $empty($a, $v, $fail)],
            'charts' => [...$this->count(200), fn (string $a, mixed $v, Closure $fail) => request()->has('state') || request()->has('tiles') ? null : $empty($a, $v, $fail)],
            'shared_with' => ['sometimes', Rule::in(CommandBook::SHARED)],
            'state' => ['sometimes', Rule::in(CommandBook::COMPOSITION_STATES), $empty],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['title.required' => __('commandcenter.v.composition')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['title'])) {
            $data['title'] = trim($data['title']);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        return parent::present($row) + ['author' => self::staff()[$row->getAttribute('author_id')] ?? null];
    }
}
