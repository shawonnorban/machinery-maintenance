<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Resources;

use App\Modules\GeneralStore\Models\GsSupplier;
use Illuminate\Database\Eloquent\Model;

/**
 * Supplier: the suppliers the store buys from — contact person, mobile, email
 * and address. General Store → Master Data (AnnotechERP 10/01).
 */
class StoreSupplierResource extends StoreMasterResource
{
    public function key(): string
    {
        return 'store-suppliers';
    }

    public function model(): string
    {
        return GsSupplier::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'contact_person', 'mobile', 'email', 'address', 'active'];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:32', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
