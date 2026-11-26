<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Resources;

use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Knitting\Models\KnitLabelPrinter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Label Printer Configuration: every printer the knitting floor prints roll
 * and batch tags on — what the floor calls it, the queue the print client
 * sends to, and who it is offered to (no one listed offers it to everyone).
 * Knit Production → Master Data (AnnotechERP 15/01).
 */
class LabelPrinterResource extends KnittingResource
{
    public function key(): string
    {
        return 'knit-label-printers';
    }

    /** Brickland removes a row here; one still in use is refused (deactivate it instead). */
    public function deletable(): bool
    {
        return true;
    }

    public function model(): string
    {
        return KnitLabelPrinter::class;
    }

    public function columns(): array
    {
        return ['name', 'address', 'user_ids', 'auto_scale', 'display_order', 'active'];
    }

    public function booleans(): array
    {
        return ['auto_scale', 'active'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'auto_scale' => false];
    }

    public function query(): Builder
    {
        return KnitLabelPrinter::query()->orderBy('display_order')->orderBy('name');
    }

    public function messages(): array
    {
        return ['name.required' => __('knitting.master.printer_name_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255', $this->uniqueText(KnitLabelPrinter::class, 'name', $row, __('knitting.master.printer_taken'))],
            'address' => [$required, 'string', 'max:255'],
            'user_ids' => ['nullable'],
            'auto_scale' => ['sometimes', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('user_ids', $data) || ! $row) {
            $raw = $data['user_ids'] ?? [];
            $ids = array_values(array_unique(array_filter(is_array($raw) ? $raw : explode(',', (string) $raw))));
            $members = CompanyUser::query()->where('company_id', $this->company())->whereIn('user_id', $ids)->count();
            if ($members !== count($ids)) {
                throw ValidationException::withMessages(['user_ids' => __('validation.exists', ['attribute' => 'user_ids'])]);
            }
            $data['user_ids'] = $ids;
        }
        if (array_key_exists('display_order', $data) && $data['display_order'] === null) {
            $data['display_order'] = 0;
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $ids = $row->getAttribute('user_ids') ?? [];
        $out['user_ids'] = implode(',', $ids);
        $out['users'] = $ids ? User::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->implode(', ') : null;

        return $out;
    }
}
