<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\PreBudget\Models\PbListValue;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One of Pre-Budget's own short lists (Master Data): a name, a note and its
 * place in the dropdown. Names are unique within the list, compared without
 * case; a value is switched off, never deleted, so a requisition that picked
 * it keeps it.
 */
abstract class PbListResource extends ResourceDefinition
{
    /** The list this screen holds, one of PbListValue::LISTS. */
    abstract public function list(): string;

    public function model(): string
    {
        return PbListValue::class;
    }

    public function viewPermission(): string
    {
        return 'prebudget.master.view';
    }

    public function managePermission(): string
    {
        return 'prebudget.master.manage';
    }

    public function columns(): array
    {
        return ['name', 'description', 'sort_order', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'sort_order' => 0];
    }

    public function deletable(): bool
    {
        return false;
    }

    public function query(): Builder
    {
        return PbListValue::query()->where('list', $this->list())->orderBy('sort_order')->orderBy('name');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();

        return [
            'name' => [$required, 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) use ($company, $row): void {
                $taken = PbListValue::query()->where('company_id', $company)->where('list', $this->list())
                    ->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $value))])
                    ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
                if ($taken) {
                    $fail(__('prebudget.master.name_taken'));
                }
            }],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $data['list'] = $this->list();
        if (isset($data['name'])) {
            $data['name'] = trim((string) $data['name']);
        }
        if (array_key_exists('sort_order', $data) && $data['sort_order'] === null) {
            $data['sort_order'] = 0;
        }
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }
}
