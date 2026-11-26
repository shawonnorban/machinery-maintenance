<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\PortalUser;
use App\Modules\BuyerGateway\Support\PortalBook;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Portal User Accounts (AnnotechERP 25/01): the buyer-side people who hold a
 * login — name, e-mail (one login per address), buyer, role, country, last
 * seen and state — and whether their login is on. A suspended or locked user
 * cannot log in: saving that state switches the login off, and switching it
 * back on is refused until the user is active or invited again.
 */
class PortalUserResource extends BuyerGatewayResource
{
    public function key(): string
    {
        return 'bg-portal-users';
    }

    public function model(): string
    {
        return PortalUser::class;
    }

    public function columns(): array
    {
        return ['name', 'email', 'buyer_id', 'role', 'country_id', 'last_seen', 'state', 'login_enabled'];
    }

    public function booleans(): array
    {
        return ['login_enabled'];
    }

    public function defaults(): array
    {
        return ['state' => 'INVITED', 'login_enabled' => true];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();

        return [
            'name' => [$required, 'string', 'max:120'],
            'email' => [$required, 'string', 'email', 'max:190', function (string $attribute, mixed $value, Closure $fail) use ($row): void {
                $taken = PortalUser::query()->whereRaw('lower(email) = ?', [Str::lower(trim((string) $value))])->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
                if ($taken) {
                    $fail(__('buyergateway.v.email_taken'));
                }
            }],
            'buyer_id' => $this->buyerRule(! $row),
            'role' => ['nullable', Rule::in(PortalBook::ROLES)],
            'country_id' => ['nullable', 'string', Rule::exists('country_configs', 'id')->where('company_id', $company)],
            'last_seen' => ['nullable', 'date_format:Y-m-d'],
            'state' => ['sometimes', Rule::in(PortalBook::USER_STATES)],
            'login_enabled' => ['sometimes', 'boolean', function (string $attribute, mixed $value, Closure $fail) use ($row): void {
                $state = request()->input('state', $row?->state ?? 'INVITED');
                if ($value && in_array($state, PortalBook::NO_LOGIN, true) && ! request()->has('state')) {
                    $fail(__('buyergateway.v.login_blocked', ['name' => $row?->name ?? '', 'state' => __('buyergateway.state.'.$state)]));
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('buyergateway.v.user_name'), 'email.required' => __('buyergateway.v.email'), 'email.email' => __('buyergateway.v.email_format'),
            'buyer_id.required' => __('buyergateway.v.buyer'),
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['email'])) {
            $data['email'] = Str::lower(trim($data['email']));
        }
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }
        $state = $data['state'] ?? $row?->state ?? 'INVITED';
        if (in_array($state, PortalBook::NO_LOGIN, true)) {
            $data['login_enabled'] = false;
        }
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        return parent::present($row) + ['country' => PortalBook::countries()[$row->getAttribute('country_id')] ?? null];
    }
}
