<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\HrPayroll\Support\HrDefaults;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;

/**
 * The shared ground of HR & Payroll's master lists (AnnotechERP 24/01): one
 * view and one manage permission; rows are deactivated, never deleted, unless
 * a screen says otherwise.
 */
abstract class HrResource extends ResourceDefinition
{
    public function viewPermission(): string
    {
        return 'hrpayroll.master.view';
    }

    public function managePermission(): string
    {
        return 'hrpayroll.master.manage';
    }

    public function deletable(): bool
    {
        return false;
    }

    protected function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    protected function ensureDefaults(): void
    {
        HrDefaults::ensure($this->company());
    }

    /** A row of one of the company's own tables. */
    protected function own(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('company_id', $this->company());
    }

    /** Case-blind uniqueness within a scope — Postgres compares text exactly. */
    protected function uniqueText(string $model, string $column, ?Model $row, string $message, array $scope = []): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($model, $column, $row, $message, $scope): void {
            $taken = $model::query()->where($scope)->whereRaw("upper(trim({$column})) = ?", [strtoupper(trim((string) $value))])
                ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
            if ($taken) {
                $fail($message);
            }
        };
    }

    /** The parent a row had before this save, so it can become a leaf again. */
    private ?string $formerParent = null;

    /**
     * A tree row's level is one below its parent's; a parent may not be the
     * row itself or anything filed under it.
     *
     * @param  class-string<Model>  $model
     */
    protected function placeInTree(string $model, array $data, ?Model $row): array
    {
        $this->formerParent = $row?->getAttribute('parent_id');
        if (! array_key_exists('parent_id', $data) && $row) {
            return $data;
        }
        $parent = ($data['parent_id'] ?? null) ? $model::query()->find($data['parent_id']) : null;
        if ($row && $parent) {
            for ($node = $parent; $node; $node = $node->getAttribute('parent_id') ? $model::query()->find($node->getAttribute('parent_id')) : null) {
                if ($node->getKey() === $row->getKey()) {
                    throw ValidationException::withMessages(['parent_id' => __('hrpayroll.master.parent_cycle')]);
                }
            }
        }
        $data['parent_id'] = $parent?->getKey();
        $data['level'] = $parent ? (int) $parent->getAttribute('level') + 1 : 1;

        return $data;
    }

    /**
     * A node stops being a leaf the moment it gets a child, and becomes one
     * again when its last child goes (Brickland's Employee Type Information).
     */
    protected function parentIsBranch(string $model, Model $row): void
    {
        foreach (array_unique(array_filter([$row->getAttribute('parent_id'), $this->formerParent])) as $parent) {
            $model::query()->whereKey($parent)->update(['is_leaf' => ! $model::query()->where('parent_id', $parent)->exists()]);
        }
    }

    protected static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10);
    }
}
