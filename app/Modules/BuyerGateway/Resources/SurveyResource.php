<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\Survey;
use App\Modules\BuyerGateway\Support\PortalBook;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Buyer Feedback Survey (AnnotechERP 25/04): surveys sent to a buyer, season
 * by season — recipients, responses and the average score (0–10). Response
 * rate decides whether a score means anything: no more responses than
 * recipients, no score without a response, and an open or closed survey has
 * been sent.
 */
class SurveyResource extends BuyerGatewayResource
{
    protected function area(): string
    {
        return 'engagement';
    }

    public function key(): string
    {
        return 'bg-surveys';
    }

    public function model(): string
    {
        return Survey::class;
    }

    public function columns(): array
    {
        return ['title', 'buyer_id', 'season', 'sent_on', 'recipients', 'responses', 'score', 'state'];
    }

    public function defaults(): array
    {
        return ['state' => 'DRAFT', 'recipients' => 0, 'responses' => 0];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $value = function (string $f) use ($row) {
            $d = request()->has($f) ? request()->input($f) : $row?->getAttribute($f);

            return $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : $d;
        };

        return [
            'title' => [$required, 'string', 'max:160'],
            'buyer_id' => $this->buyerRule(! $row),
            'season' => ['nullable', Rule::in(PortalBook::seasons())],
            'sent_on' => ['nullable', 'date_format:Y-m-d'],
            'recipients' => ['sometimes', 'integer', 'min:0', 'max:100000', function (string $attribute, mixed $v, Closure $fail) use ($value): void {
                // Lowering recipients below the responses already in is the same mistake.
                if ((int) $v < (int) $value('responses')) {
                    $fail(__('buyergateway.v.responses_over_recipients'));
                }
            }],
            'responses' => ['sometimes', 'integer', 'min:0', 'max:100000', function (string $attribute, mixed $v, Closure $fail) use ($value): void {
                if ((int) $v > (int) $value('recipients')) {
                    $fail(__('buyergateway.v.responses_over_recipients'));
                }
                if ((int) $v === 0 && (float) $value('score') > 0) {
                    $fail(__('buyergateway.v.score_without_responses'));
                }
            }],
            'score' => ['nullable', 'numeric', 'min:0', 'max:10', function (string $attribute, mixed $v, Closure $fail) use ($value): void {
                if ($v !== null && (float) $v > 0 && (int) $value('responses') === 0) {
                    $fail(__('buyergateway.v.score_without_responses'));
                }
            }],
            'state' => ['sometimes', Rule::in(PortalBook::SURVEY_STATES), function (string $attribute, mixed $v, Closure $fail) use ($value): void {
                if ($v !== 'DRAFT' && ! $value('sent_on')) {
                    $fail(__('buyergateway.v.survey_unsent'));
                }
            }],
        ];
    }

    public function messages(): array
    {
        return ['title.required' => __('buyergateway.v.survey'), 'buyer_id.required' => __('buyergateway.v.buyer')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['title'])) {
            $data['title'] = trim($data['title']);
        }

        return $data;
    }
}
