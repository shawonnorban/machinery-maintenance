<?php

declare(strict_types=1);

namespace App\Modules\Erp\Support;

use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The shared ground of the registers Brickland ships as "Demo data" screens
 * (AnnotechERP 27 onward; Buyer Gateway and AI Import keep their own copy):
 * one view and one manage permission per screen group (`{prefix}.{area}`),
 * rows in the order they were added, the company's staff and departments by
 * name, and the usual rules — a percentage, a count, a name used once.
 */
abstract class DemoResource extends ResourceDefinition
{
    /** The module's permission prefix: "commandcenter". */
    abstract protected function prefix(): string;

    /** The screen group: "command" (27/01), "kpi" (27/02) … */
    abstract protected function area(): string;

    public function viewPermission(): string
    {
        return "{$this->prefix()}.{$this->area()}.view";
    }

    public function managePermission(): string
    {
        return "{$this->prefix()}.{$this->area()}.manage";
    }

    public function query(): Builder
    {
        $model = $this->model();

        return $model::query()->orderBy('created_at')->orderBy('id');
    }

    protected function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /** @return array<string, string> the company's active staff, user id => name */
    public static function staff(): array
    {
        $company = app(TenantContext::class)->companyId();

        return DB::table('company_users as cu')->join('users as u', 'u.id', '=', 'cu.user_id')->where('cu.company_id', $company)
            ->where('cu.status', 'ACTIVE')->orderBy('u.name')->pluck('u.name', 'u.id')->all();
    }

    /** @return array<string, string> the company's departments, id => name */
    public static function departments(): array
    {
        return DB::table('departments')->where('company_id', app(TenantContext::class)->companyId())->orderBy('name')->pluck('name', 'id')->all();
    }

    /** One of the company's staff, or none. */
    protected function staffRule(): array
    {
        return ['nullable', 'string', Rule::exists('company_users', 'user_id')->where('company_id', $this->company())];
    }

    /** One of the company's departments, or none. */
    protected function departmentRule(): array
    {
        return ['nullable', 'string', Rule::exists('departments', 'id')->where('company_id', $this->company())];
    }

    protected function percent(): array
    {
        return ['sometimes', 'numeric', 'min:0', 'max:100'];
    }

    protected function count(int $max = 100000000): array
    {
        return ['sometimes', 'integer', 'min:0', "max:{$max}"];
    }

    /** A name used once in this list (case does not count). */
    protected function once(?Model $row, string $column, string $message): Closure
    {
        $model = $this->model();

        return function (string $attribute, mixed $v, Closure $fail) use ($model, $row, $column, $message): void {
            if (trim((string) $v) !== '' && $model::query()->whereRaw("lower({$column}) = ?", [Str::lower(trim((string) $v))])->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                $fail(__($message));
            }
        };
    }
}
