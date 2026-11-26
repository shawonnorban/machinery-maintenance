<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\SampleProgress;
use App\Modules\BuyerGateway\Support\PortalBook;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Sample Progress View (AnnotechERP 25/02): where every sample stands as the
 * buyer reads it — style, buyer, sample type, version, sent, decision due,
 * courier and stage. The buyer's view only: internal rework rounds are not
 * published. One row per style, type and version; once past In development
 * it has a sent date, and the decision is not due before it was sent.
 */
class SampleProgressResource extends BuyerGatewayResource
{
    protected function area(): string
    {
        return 'visibility';
    }

    public function key(): string
    {
        return 'bg-sample-progress';
    }

    public function model(): string
    {
        return SampleProgress::class;
    }

    public function columns(): array
    {
        return ['style', 'buyer_id', 'sample_type', 'stage', 'sent_on', 'decision_due', 'version', 'courier'];
    }

    public function defaults(): array
    {
        return ['stage' => 'IN_DEVELOPMENT', 'version' => 1];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $value = fn (string $f) => request()->has($f) ? request()->input($f) : $row?->getAttribute($f);
        $date = fn (string $f) => ($d = $value($f)) instanceof \DateTimeInterface ? $d->format('Y-m-d') : $d;

        return [
            'style' => [$required, 'string', 'max:60', function (string $attribute, mixed $v, Closure $fail) use ($row, $value): void {
                $taken = SampleProgress::query()->whereRaw('upper(style) = ?', [Str::upper(trim((string) $v))])->where('sample_type', $value('sample_type'))->where('version', (int) ($value('version') ?? 1))
                    ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
                if ($taken) {
                    $fail(__('buyergateway.v.sample_taken'));
                }
            }],
            'buyer_id' => $this->buyerRule(! $row),
            'sample_type' => ['nullable', Rule::in(PortalBook::SAMPLE_TYPES)],
            'stage' => ['sometimes', Rule::in(PortalBook::SAMPLE_STAGES), function (string $attribute, mixed $v, Closure $fail) use ($date): void {
                if ($v !== 'IN_DEVELOPMENT' && ! $date('sent_on')) {
                    $fail(__('buyergateway.v.sent_required'));
                }
            }],
            'sent_on' => ['nullable', 'date_format:Y-m-d'],
            'decision_due' => ['nullable', 'date_format:Y-m-d', function (string $attribute, mixed $v, Closure $fail) use ($date): void {
                $sent = $date('sent_on');
                if ($v && $sent && $v < $sent) {
                    $fail(__('buyergateway.v.due_before_sent'));
                }
            }],
            'version' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'courier' => ['nullable', Rule::in(PortalBook::COURIERS)],
        ];
    }

    public function messages(): array
    {
        return ['style.required' => __('buyergateway.v.style'), 'buyer_id.required' => __('buyergateway.v.buyer')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['style'])) {
            $data['style'] = Str::upper(trim($data['style']));
        }

        return $data;
    }
}
