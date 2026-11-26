<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\MaterialProcurement\Models\SupplierLogin;
use App\Modules\MaterialProcurement\Support\Procurement;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Supplier Master (AnnotechERP 08/01): code, name, short name, Local
 * (Non-EPZ) / Local (EPZ) / Foreign, owner, goods or service, VAT and TIN.
 * Codes are upper-case and unique within the company; suppliers are switched
 * off, never deleted.
 */
class MaterialSupplierResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'material-suppliers';
    }

    public function model(): string
    {
        return MaterialSupplier::class;
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
        return ['code', 'name', 'short_name', 'supplier_type', 'owner_name', 'goods_service', 'vat_reg_no', 'tin_no', 'address', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    /** "Added SUP-14 MM Accessories" / "Updated …" in the audit trail. */
    public function afterSave(Model $row, bool $created): void
    {
        Procurement::audit($created ? 'CREATE' : 'UPDATE', 'supplier-master', $row->code,
            Procurement::en($created ? 'materialprocurement.audit.added' : 'materialprocurement.audit.updated', ['code' => $row->code, 'name' => $row->name]));
    }

    public function deletable(): bool
    {
        return false;
    }

    public function query(): Builder
    {
        return MaterialSupplier::query()->orderBy('code');
    }

    public function messages(): array
    {
        return ['code.required' => __('materialprocurement.master.supplier_code_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();

        return [
            // Compared as stored: "sup-001" is SUP-001.
            'code' => [$required, 'string', 'max:48', function (string $attribute, mixed $value, \Closure $fail) use ($company, $row): void {
                $taken = MaterialSupplier::query()->where('company_id', $company)->where('code', strtoupper(trim((string) $value)))
                    ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
                if ($taken) {
                    $fail(__('materialprocurement.master.code_taken'));
                }
            }],
            'name' => [$required, 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:64'],
            'supplier_type' => [$required, Rule::in(MaterialSupplier::TYPES)],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'goods_service' => ['nullable', 'string', 'max:255'],
            'vat_reg_no' => ['nullable', 'string', 'max:32'],
            'tin_no' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }
        if ($row === null) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        // The active portal logins Supplier Portal Access (08/07) gave this supplier.
        $out['portal_logins'] = SupplierLogin::query()->where('supplier_id', $row->getKey())->where('active', true)->count();

        return $out;
    }
}
