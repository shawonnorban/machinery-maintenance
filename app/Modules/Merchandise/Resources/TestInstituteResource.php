<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\TestInstitute;
use Illuminate\Database\Eloquent\Model;

/**
 * Buyer Test Lab Mapping: the test labs buyers send goods to. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class TestInstituteResource extends MasterResource
{
    public function key(): string
    {
        return 'test-institutes';
    }

    public function model(): string
    {
        return TestInstitute::class;
    }

    public function columns(): array
    {
        return ['name', 'name_bn', 'address', 'active'];
    }

    protected function orderBy(): string
    {
        return 'name';
    }

    protected function upperCase(): array
    {
        return [];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) use ($row): void {
                $taken = TestInstitute::query()->where('company_id', $this->company())->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $value))])
                    ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
                if ($taken) {
                    $fail(__('merchandise.master.code_taken'));
                }
            }],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
