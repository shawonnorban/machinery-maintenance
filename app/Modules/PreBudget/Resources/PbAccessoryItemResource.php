<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Inventory\Support\UomCodes;
use App\Modules\PreBudget\Models\PbAccessoryItem;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pre-Budget → Master Data → Accessories Item: what a BOM line picks — code,
 * name, category and the unit it is usually bought in (UOM master). Norban
 * kept an Item list and a Name list that only said "<Item> Name"; here it is
 * one list. A blank code gets the next ACC-0001 number. Switched off, never
 * deleted.
 */
class PbAccessoryItemResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'pb-accessory-items';
    }

    public function model(): string
    {
        return PbAccessoryItem::class;
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
        return ['code', 'name', 'category', 'uom', 'description', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function deletable(): bool
    {
        return false;
    }

    public function query(): Builder
    {
        return PbAccessoryItem::query()->orderBy('code');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();
        $unique = fn (string $field, string $message) => function (string $attribute, mixed $value, \Closure $fail) use ($company, $row, $field, $message): void {
            $v = trim((string) $value);
            if ($v === '') {
                return;
            }
            $taken = PbAccessoryItem::query()->where('company_id', $company)
                ->whereRaw("lower({$field}) = ?", [mb_strtolower($v)])
                ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
            if ($taken) {
                $fail(__($message));
            }
        };

        return [
            'code' => ['nullable', 'string', 'max:48', $unique('code', 'prebudget.master.code_taken')],
            'name' => [$required, 'string', 'max:255', $unique('name', 'prebudget.master.name_taken')],
            'category' => ['nullable', 'string', 'max:120'],
            'uom' => ['nullable', 'string', 'max:16', UomCodes::rule($company)],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        foreach (['name', 'category'] as $f) {
            if (array_key_exists($f, $data)) {
                $data[$f] = trim((string) $data[$f]) ?: null;
            }
        }
        if (array_key_exists('uom', $data)) {
            $data['uom'] = $data['uom'] ? UomCodes::normalize($data['uom']) : null;
        }
        if (array_key_exists('code', $data) || ! $row) {
            $code = strtoupper(trim((string) ($data['code'] ?? '')));
            $data['code'] = $code !== '' ? $code : ($row?->code ?? $this->nextCode());
        }
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    /** The next free ACC-0001 style number of the company. */
    private function nextCode(): string
    {
        $max = PbAccessoryItem::query()->where('code', 'like', 'ACC-%')->pluck('code')
            ->map(fn (string $c) => (int) preg_replace('/\D/', '', $c))->max() ?? 0;

        return sprintf('ACC-%04d', $max + 1);
    }
}
