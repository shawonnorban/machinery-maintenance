<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Resources;

use App\Modules\Accounting\Models\AccGlSourceLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Ledger Integration Setup → GL Configuration Entry → COA Detail Data: the
 * Chart of Account ledgers a GL source debits and credits, each with an
 * optional cost centre. A posting uses the first debit and the first credit.
 */
class GlSourceLineResource extends AccountingResource
{
    public function key(): string
    {
        return 'acc-gl-source-lines';
    }

    public function model(): string
    {
        return AccGlSourceLine::class;
    }

    public function deletable(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return ['gl_source_id', 'side', 'account_id', 'cost_center_id', 'note', 'sort_order'];
    }

    public function query(): Builder
    {
        return AccGlSourceLine::query()->orderBy('gl_source_id')->orderBy('sort_order');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $c = $this->company();

        return [
            'gl_source_id' => [$required, Rule::exists('acc_gl_sources', 'id')->where('company_id', $c)],
            'side' => [$required, Rule::in(AccGlSourceLine::SIDES)],
            'account_id' => [$required, Rule::exists('acc_accounts', 'id')->where('company_id', $c)->where('postable', true)->where('active', true)],
            'cost_center_id' => ['nullable', Rule::exists('acc_cost_centers', 'id')->where('company_id', $c)],
            'note' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (! $row && empty($data['sort_order'])) {
            $data['sort_order'] = (int) AccGlSourceLine::query()->where('gl_source_id', $data['gl_source_id'])->max('sort_order') + 1;
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $account = DB::table('acc_accounts')->where('id', $row->getAttribute('account_id'))->first(['code', 'name']);
        $out['account'] = $account ? $account->code.' — '.$account->name : null;
        $centerId = $row->getAttribute('cost_center_id');
        $center = $centerId ? DB::table('acc_cost_centers')->where('id', $centerId)->first(['code', 'name']) : null;
        $out['cost_center'] = $center ? $center->code.' — '.$center->name : null;

        return $out;
    }
}
