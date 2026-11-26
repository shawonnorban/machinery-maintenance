<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeSciProgramme;
use App\Modules\DyeHouse\Models\DyeSciReceipt;
use App\Modules\DyeHouse\Support\DyeBatches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sub-Contract Order Register: a dyeing and finishing programme taken in from
 * another party (DFSCI-YYYYMM####) — the party, its buyer and order (the
 * buyer filled from the order when left blank), and the processes taken on,
 * at least one. A programme fabric has been received against stays.
 */
class DyeSciProgrammeResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-sci-programmes';
    }

    public function model(): string
    {
        return DyeSciProgramme::class;
    }

    public function viewPermission(): string
    {
        return 'dyehouse.sc.view';
    }

    public function managePermission(): string
    {
        return 'dyehouse.sc.manage';
    }

    public function columns(): array
    {
        return ['program_no', 'prog_date', 'supplier_id', 'buyer_id', 'order_id', 'dyeing', 'dyeing_finishing', 'status', 'remarks'];
    }

    public function booleans(): array
    {
        return ['dyeing', 'dyeing_finishing'];
    }

    public function query(): Builder
    {
        return DyeSciProgramme::query()->orderByDesc('prog_date')->orderByDesc('program_no');
    }

    public function messages(): array
    {
        return ['prog_date.required' => __('dyehouse.sci.v.prog_date'), 'supplier_id.required' => __('dyehouse.sci.v.party'), 'status.required' => __('dyehouse.sci.v.status')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'prog_date' => [$required, 'date'],
            'supplier_id' => [$required, $this->own('mp_suppliers')],
            'buyer_id' => ['nullable', $this->own('buyers')],
            'order_id' => ['nullable', $this->own('merch_orders')],
            'dyeing' => ['boolean'],
            'dyeing_finishing' => ['boolean'],
            'status' => [$required, Rule::in(DyeSciProgramme::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $get = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        if (! $get('dyeing') && ! $get('dyeing_finishing')) {
            throw ValidationException::withMessages(['dyeing' => __('dyehouse.sci.v.process')]);
        }
        if ($get('order_id')) {
            $buyer = DB::table('merch_orders')->where('id', $get('order_id'))->value('buyer_id');
            if (blank($get('buyer_id'))) {
                $data['buyer_id'] = $buyer;
            } elseif ($buyer !== $get('buyer_id')) {
                throw ValidationException::withMessages(['order_id' => __('dyehouse.sci.v.order_of_buyer')]);
            }
        }
        unset($data['program_no']);
        if (! $row) {
            $data['program_no'] = DyeBatches::nextNumber('DFSCI-', 'dye_sci_programmes', 'program_no', (string) $get('prog_date'), 'Ym');
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function beforeDelete(Model $row): void
    {
        if (DyeSciReceipt::query()->where('programme_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['program_no' => __('dyehouse.sci.v.programme_received', ['no' => $row->getAttribute('program_no')])]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $n = $this->names();
        [$style, $order] = $this->styleAndOrder($row->getAttribute('order_id'));
        $out['supplier'] = $n['suppliers'][$row->getAttribute('supplier_id')] ?? null;
        $out['buyer'] = $n['buyers'][$row->getAttribute('buyer_id')] ?? null;
        $out['order_no'] = $order;
        $out['style'] = $style;
        $out['received_kg'] = round((float) DyeSciReceipt::query()->where('programme_id', $row->getKey())->sum('qty_kg'), 3);

        return $out;
    }
}
