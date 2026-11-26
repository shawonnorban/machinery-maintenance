<?php

declare(strict_types=1);

namespace App\Modules\AiImport\Resources;

use App\Modules\AiImport\Models\Signature;
use App\Modules\AiImport\Support\ImportBook;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Document Recognition Engine (AnnotechERP 26/01): the signatures that tell
 * what kind of document a file is and which buyer sent it — the family, the
 * buyer (or any), the marker it looks for, how often it hits and when it last
 * matched. A signature is named once; one switched off is not used.
 */
class SignatureResource extends AiImportResource
{
    public function key(): string
    {
        return 'ai-signatures';
    }

    public function model(): string
    {
        return Signature::class;
    }

    public function columns(): array
    {
        return ['name', 'family', 'buyer_id', 'marker', 'hit_rate', 'times_matched', 'last_matched', 'enabled'];
    }

    public function booleans(): array
    {
        return ['enabled'];
    }

    public function defaults(): array
    {
        return ['hit_rate' => 0, 'times_matched' => 0, 'enabled' => true];
    }

    public function rules(?Model $row): array
    {
        return [
            'name' => [$row ? 'sometimes' : 'required', 'string', 'max:160', function (string $attribute, mixed $v, Closure $fail) use ($row): void {
                if (Signature::query()->whereRaw('lower(name) = ?', [Str::lower(trim((string) $v))])->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                    $fail(__('aiimport.v.signature_taken'));
                }
            }],
            'family' => ['nullable', Rule::in(ImportBook::FAMILIES)],
            'buyer_id' => $this->buyerRule(),
            'marker' => ['nullable', Rule::in(ImportBook::MARKERS)],
            'hit_rate' => $this->percent(),
            'times_matched' => $this->count(),
            'last_matched' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::now('Asia/Dhaka')->toDateString()],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => __('aiimport.v.signature'), 'last_matched.before_or_equal' => __('aiimport.v.future')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        return $data;
    }
}
