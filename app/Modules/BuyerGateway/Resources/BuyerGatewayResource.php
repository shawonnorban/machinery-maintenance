<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Support\PortalBook;
use App\Modules\Erp\Support\ResourceDefinition;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * The shared ground of Buyer Gateway's Access & Onboarding lists (AnnotechERP
 * 25/01): one view and one manage permission, the company's buyers, and a
 * row shown with its buyer by name.
 */
abstract class BuyerGatewayResource extends ResourceDefinition
{
    /** The screen group whose permissions open the list: access (25/01), visibility (25/02) … */
    protected function area(): string
    {
        return 'access';
    }

    public function viewPermission(): string
    {
        return "buyergateway.{$this->area()}.view";
    }

    public function managePermission(): string
    {
        return "buyergateway.{$this->area()}.manage";
    }

    public function query(): Builder
    {
        $model = $this->model();

        return $model::query()->orderBy('created_at')->orderBy('id');
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        if (array_key_exists('buyer_id', $out)) {
            $out['buyer'] = PortalBook::buyers()[$row->getAttribute('buyer_id')] ?? null;
        }

        return $out;
    }

    public function afterSave(Model $row, bool $created): void
    {
        PortalBook::forget();
    }

    /** The year's next number of a reference column: CMP-2026-001, -002 … */
    protected function nextNumber(string $code, string $column): string
    {
        $prefix = $code.'-'.date('Y').'-';
        $model = $this->model();
        $last = $model::query()->where($column, 'like', $prefix.'%')->pluck($column)->map(fn ($r) => (int) substr((string) $r, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }

    protected function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /** A buyer of the company. */
    protected function buyerRule(bool $required): array
    {
        return [$required ? 'required' : 'sometimes', 'string', Rule::exists('buyers', 'id')->where('company_id', $this->company())];
    }

    /** One row per combination (a role per buyer, an event per buyer): `$fields` must not repeat another row's. */
    protected function unique(?Model $row, array $fields, string $message): Closure
    {
        $model = $this->model();

        return function (string $attribute, mixed $value, Closure $fail) use ($model, $row, $fields, $message): void {
            $values = [];
            foreach ($fields as $f) {
                $values[$f] = request()->has($f) ? request()->input($f) : $row?->getAttribute($f);
            }
            if (in_array(null, $values, true)) {
                return;
            }
            $taken = $model::query()->where($values)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
            if ($taken) {
                $fail(__($message, ['buyer' => PortalBook::buyers()[$values['buyer_id'] ?? ''] ?? '']));
            }
        };
    }
}
