<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Resources;

use App\Modules\Accounting\Models\AccGlSource;
use App\Modules\Accounting\Models\AccGlSourceLine;
use App\Modules\Accounting\Support\LedgerSources;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Ledger Integration Setup's GL sources (Access Control → Configuration,
 * AnnotechERP 02/03): Source Name (English/Bangla), GL Mapping Type, Link Ref,
 * Final Bill Collection (bank bill sources only), Is Editable From GL, Active.
 * The system's own sources (Machinery's costs) are offered from the first
 * visit; their Link Ref is fixed, as the code posts by it.
 */
class GlSourceResource extends AccountingResource
{
    public function key(): string
    {
        return 'acc-gl-sources';
    }

    public function model(): string
    {
        return AccGlSource::class;
    }

    public function deletable(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return ['name', 'name_bn', 'mapping_type', 'link_ref', 'final_bill_collection', 'editable_from_gl', 'active'];
    }

    public function booleans(): array
    {
        return ['final_bill_collection', 'editable_from_gl', 'active'];
    }

    public function defaults(): array
    {
        return ['mapping_type' => 'GENERAL', 'final_bill_collection' => false, 'editable_from_gl' => false, 'active' => true];
    }

    public function query(): Builder
    {
        LedgerSources::ensure($this->company());

        return AccGlSource::query()->orderBy('name');
    }

    public function messages(): array
    {
        return ['name.required' => __('access.cfg.gl_source_name_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255', $this->uniqueText(AccGlSource::class, 'name', $row, __('access.cfg.gl_source_taken'))],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'mapping_type' => [$required, Rule::in(AccGlSource::MAPPING_TYPES)],
            'link_ref' => ['nullable', 'string', 'max:64'],
            'final_bill_collection' => ['sometimes', 'boolean'],
            'editable_from_gl' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        // The system posts by these Link Refs: they stay as they are.
        $system = array_keys(LedgerSources::MACHINERY);
        if ($row && in_array($row->getAttribute('link_ref'), $system, true)) {
            unset($data['link_ref']);
        } elseif (isset($data['link_ref']) && in_array(strtoupper(trim((string) $data['link_ref'])), $system, true)) {
            throw ValidationException::withMessages(['link_ref' => __('access.cfg.gl_link_ref_reserved')]);
        }
        // Final Bill Collection is for bank bill sources only.
        $type = $data['mapping_type'] ?? $row?->getAttribute('mapping_type');
        if ($type !== 'BANK_BILL_COLLECTION') {
            $data['final_bill_collection'] = false;
        }

        return $data;
    }

    public function beforeDelete(Model $row): void
    {
        if (in_array($row->getAttribute('link_ref'), array_keys(LedgerSources::MACHINERY), true)
            || DB::table('acc_ledger_postings')->where('gl_source_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['id' => __('access.cfg.gl_source_in_use')]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $lines = AccGlSourceLine::query()->where('gl_source_id', $row->getKey())->get(['side']);
        $out['lines'] = $lines->count();
        $out['ready'] = $lines->contains('side', 'DEBIT') && $lines->contains('side', 'CREDIT');
        $out['system'] = in_array($row->getAttribute('link_ref'), array_keys(LedgerSources::MACHINERY), true);

        return $out;
    }
}
