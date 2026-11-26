<?php

declare(strict_types=1);

namespace App\Modules\CommandCenter\Resources;

use App\Modules\CommandCenter\Models\Board;
use App\Modules\CommandCenter\Support\CommandBook;
use App\Modules\Erp\Support\DemoResource;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Departmental Performance Boards (AnnotechERP 27/01): one board per
 * department, showing only what that department answers for — its tiles, how
 * often it refreshes, who sees it and how healthy its data is. A draft board
 * is visible only to its owner; a board with no tiles cannot be published.
 */
class BoardResource extends DemoResource
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
        return 'cc-boards';
    }

    public function model(): string
    {
        return Board::class;
    }

    public function columns(): array
    {
        return ['name', 'department_id', 'owner_id', 'tiles', 'refresh', 'audience', 'data_health', 'published'];
    }

    public function booleans(): array
    {
        return ['published'];
    }

    public function defaults(): array
    {
        return ['tiles' => 0, 'refresh' => 'DAILY', 'audience' => 'DEPT_HEADS', 'data_health' => 100, 'published' => false];
    }

    public function rules(?Model $row): array
    {
        $value = fn (string $f) => request()->has($f) ? request()->input($f) : $row?->getAttribute($f);

        return [
            'name' => [$row ? 'sometimes' : 'required', 'string', 'max:160'],
            'department_id' => [...$this->departmentRule(), function (string $attribute, mixed $v, Closure $fail) use ($row): void {
                // One board per department.
                if ($v && Board::query()->where('department_id', $v)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                    $fail(__('commandcenter.v.board_taken', ['department' => self::departments()[$v] ?? '']));
                }
            }],
            'owner_id' => $this->staffRule(),
            'tiles' => [...$this->count(200), function (string $attribute, mixed $v, Closure $fail) use ($value): void {
                if ((int) $v === 0 && filter_var($value('published'), FILTER_VALIDATE_BOOLEAN)) {
                    $fail(__('commandcenter.v.publish_empty'));
                }
            }],
            'refresh' => ['sometimes', Rule::in(CommandBook::REFRESH)],
            'audience' => ['sometimes', Rule::in(CommandBook::AUDIENCES)],
            'data_health' => $this->percent(),
            'published' => ['sometimes', 'boolean', function (string $attribute, mixed $v, Closure $fail) use ($value): void {
                if ($v && (int) $value('tiles') === 0) {
                    $fail(__('commandcenter.v.publish_empty'));
                }
            }],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => __('commandcenter.v.board')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['department'] = self::departments()[$row->getAttribute('department_id')] ?? null;
        $out['owner'] = self::staff()[$row->getAttribute('owner_id')] ?? null;

        return $out;
    }
}
