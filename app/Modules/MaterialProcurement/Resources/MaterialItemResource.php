<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Inventory\Support\UomCodes;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Support\Procurement;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Fabric Master and Trims & Accessories Master (AnnotechERP 08/01): one
 * catalogue told apart by kind. The kind is the screen's, never the
 * request's; an item code is upper-case and unique within its kind. Items are
 * switched off rather than deleted — a requisition may already name one.
 */
abstract class MaterialItemResource extends ResourceDefinition
{
    abstract protected function kind(): string;

    /** The kind's own fields beside code, name, category, sub category, colour and UOM. @return list<string> */
    abstract protected function ownFields(): array;

    public function model(): string
    {
        return MaterialItem::class;
    }

    public function viewPermission(): string
    {
        return 'material.master.view';
    }

    public function managePermission(): string
    {
        return 'material.master.manage';
    }

    public function columns(): array
    {
        return ['item_code', 'name', 'category', 'sub_category', ...$this->ownFields(), 'colour', 'uom', 'specification', 'description', 'remarks', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    /** Fabric Master / Accessories Master: "Added FAB-001 Cotton Jersey" in the audit trail. */
    public function afterSave(Model $row, bool $created): void
    {
        Procurement::audit($created ? 'CREATE' : 'UPDATE', $this->kind() === 'FABRIC' ? 'fabric-master' : 'accessories-master', $row->item_code,
            Procurement::en($created ? 'materialprocurement.audit.added' : 'materialprocurement.audit.updated', ['code' => $row->item_code, 'name' => $row->name]));
    }

    public function deletable(): bool
    {
        return false;
    }

    public function query(): Builder
    {
        return MaterialItem::query()->where('kind', $this->kind())->orderBy('item_code');
    }

    public function messages(): array
    {
        return ['item_code.required' => __('materialprocurement.master.code_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();
        $own = array_fill_keys($this->ownFields(), ['nullable', 'string', 'max:64']);
        if (array_key_exists('composition', $own)) {
            $own['composition'] = ['nullable', 'string', 'max:255'];
        }

        return [
            // Compared as stored: "fab-001" is FAB-001.
            'item_code' => [$required, 'string', 'max:64', function (string $attribute, mixed $value, \Closure $fail) use ($company, $row): void {
                $taken = MaterialItem::query()->where('company_id', $company)->where('kind', $this->kind())->where('item_code', strtoupper(trim((string) $value)))
                    ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
                if ($taken) {
                    $fail(__('materialprocurement.master.code_taken'));
                }
            }],
            'name' => [$required, 'string', 'max:255'],
            'category' => [$required, 'string', 'max:255'],
            'sub_category' => ['nullable', 'string', 'max:255'],
            ...$own,
            'colour' => ['nullable', 'string', 'max:64'],
            'uom' => [$required, 'string', 'max:16', UomCodes::rule(app(TenantContext::class)->companyId())],
            'specification' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['item_code'])) {
            $data['item_code'] = strtoupper(trim($data['item_code']));
        }
        $data['kind'] = $this->kind();
        if (array_key_exists('uom', $data)) {
            $data['uom'] = UomCodes::normalize($data['uom']);
        }

        return $data;
    }
}
