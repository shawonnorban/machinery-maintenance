<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\TnaTask;
use App\Modules\Merchandise\Models\TnaTaskGroup;
use App\Modules\Merchandise\Support\MerchandiseDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Task & Action Register: the groups T&A tasks are filed under. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class TnaTaskGroupResource extends MasterResource
{
    public function key(): string
    {
        return 'tna-task-groups';
    }

    public function model(): string
    {
        return TnaTaskGroup::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'name_bn', 'active'];
    }

    public function query(): Builder
    {
        MerchandiseDefaults::ensureTna($this->company());

        return TnaTaskGroup::query()->orderBy('name');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => ['nullable', 'string', 'max:16', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /** Brickland's dialog asks only for the name; a group with no code takes one from it. */
    public function beforeSave(array $data, ?Model $row): array
    {
        $data = parent::beforeSave($data, $row);
        if (blank($data['code'] ?? null) && ! $row?->getAttribute('code')) {
            $base = mb_substr(preg_replace('/[^A-Z0-9]/', '', strtoupper((string) ($data['name'] ?? ''))) ?: 'GROUP', 0, 12);
            $code = $base;
            for ($n = 2; TnaTaskGroup::query()->where('code', $code)->exists(); $n++) {
                $code = $base.$n;
            }
            $data['code'] = $code;
        } elseif (array_key_exists('code', $data) && blank($data['code'])) {
            unset($data['code']);
        }

        return $data;
    }

    /** A group still holding tasks stays; switch it off instead. */
    public function beforeDelete(Model $row): void
    {
        if (TnaTask::query()->where('tna_task_group_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['id' => __('merchandise.master.in_use')]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['task_count'] = TnaTask::query()->where('tna_task_group_id', $row->getKey())->count();

        return $out;
    }
}
