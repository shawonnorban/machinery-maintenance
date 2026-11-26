<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Resources;

use App\Modules\TradeExport\Models\TxReferenceEntry;
use App\Modules\TradeExport\Support\TradeExportDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Document Reference Types: the eight lists the commercial documents pick from
 * — Inco Term, Payment Term, Delivery Place, Transit Port, L/C Type, Shipment
 * Mode, Garments Business Association and Forwarders. The code is generated
 * from the short name on save. Trade & Export → Master Data (AnnotechERP 22/01).
 */
class ReferenceEntryResource extends TradeExportResource
{
    public function key(): string
    {
        return 'tx-reference-entries';
    }

    public function model(): string
    {
        return TxReferenceEntry::class;
    }

    public function columns(): array
    {
        return ['list', 'code', 'name', 'name_bn', 'short_name', 'display_order', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function query(): Builder
    {
        TradeExportDefaults::references($this->company());

        return TxReferenceEntry::query()->orderBy('list')->orderBy('display_order')->orderBy('name');
    }

    public function messages(): array
    {
        return ['name.required' => __('tradeexport.master.name_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $list = request()->input('list', $row?->getAttribute('list'));

        return [
            'list' => [$row ? 'prohibited' : 'required', Rule::in(TxReferenceEntry::LISTS)],
            'name' => [$required, 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'short_name' => [$required, 'string', 'max:32', $this->uniqueText(TxReferenceEntry::class, 'short_name', $row, __('tradeexport.master.short_taken'), ['list' => $list])],
            'display_order' => [$required, 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (! $row) {
            $data['code'] = self::nextCode(TxReferenceEntry::class, ['list' => $data['list']], $data['short_name']);
        }

        return $data;
    }

    /** A code made from the short name — upper-cased, a suffix added while it is taken. */
    public static function nextCode(string $model, array $scope, string $base, int $max = 24): string
    {
        $stem = substr(preg_replace('/[^A-Z0-9-]+/', '-', strtoupper(trim($base))) ?: 'X', 0, $max - 3);
        $code = $stem;
        for ($i = 2; $model::query()->where($scope)->where('code', $code)->exists(); $i++) {
            $code = "{$stem}-{$i}";
        }

        return $code;
    }
}
