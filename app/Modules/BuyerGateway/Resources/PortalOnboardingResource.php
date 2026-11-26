<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\PortalOnboarding;
use App\Modules\BuyerGateway\Support\PortalBook;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Buyer Onboarding Workflow (AnnotechERP 25/01): a new buyer taken from signed
 * account to working portal login through eight steps. Progress is the share
 * of the steps completed (worked out from the step); reaching Live completes
 * it. The buyer is named as typed — it may not be in the buyer master yet.
 */
class PortalOnboardingResource extends BuyerGatewayResource
{
    public function key(): string
    {
        return 'bg-onboardings';
    }

    public function model(): string
    {
        return PortalOnboarding::class;
    }

    public function columns(): array
    {
        return ['buyer_name', 'step', 'owner_id', 'started_on', 'target_live', 'progress', 'users_invited', 'state'];
    }

    public function defaults(): array
    {
        return ['step' => 'CONTRACT_SIGNED', 'state' => 'ON_TRACK', 'users_invited' => 0];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'buyer_name' => [$required, 'string', 'max:120', function (string $attribute, mixed $value, Closure $fail) use ($row): void {
                $taken = PortalOnboarding::query()->whereRaw('lower(buyer_name) = ?', [Str::lower(trim((string) $value))])->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
                if ($taken) {
                    $fail(__('buyergateway.v.onboarding_taken'));
                }
            }],
            'step' => ['sometimes', Rule::in(PortalBook::STEPS)],
            'owner_id' => ['nullable', 'string', Rule::exists('company_users', 'user_id')->where('company_id', $this->company())],
            'started_on' => ['nullable', 'date_format:Y-m-d'],
            'target_live' => ['nullable', 'date_format:Y-m-d', function (string $attribute, mixed $value, Closure $fail) use ($row): void {
                $start = request()->has('started_on') ? request()->input('started_on') : $row?->started_on?->format('Y-m-d');
                if ($value && $start && $value < $start) {
                    $fail(__('buyergateway.v.target_before_start'));
                }
            }],
            'users_invited' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'state' => ['sometimes', Rule::in(PortalBook::ONBOARDING_STATES)],
        ];
    }

    public function messages(): array
    {
        return ['buyer_name.required' => __('buyergateway.v.buyer_name')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['buyer_name'])) {
            $data['buyer_name'] = trim($data['buyer_name']);
        }
        $step = $data['step'] ?? $row?->step ?? 'CONTRACT_SIGNED';
        $data['progress'] = PortalBook::progress($step);
        if ($step === 'LIVE') {
            $data['state'] = 'COMPLETE';
        } elseif (($data['state'] ?? $row?->state) === 'COMPLETE') {
            // Not live yet, so not complete.
            $data['state'] = 'ON_TRACK';
        }
        if (! $row && empty($data['started_on'])) {
            $data['started_on'] = CarbonImmutable::now('Asia/Dhaka')->toDateString();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        return parent::present($row) + ['owner' => PortalBook::staff()[$row->getAttribute('owner_id')] ?? null];
    }
}
