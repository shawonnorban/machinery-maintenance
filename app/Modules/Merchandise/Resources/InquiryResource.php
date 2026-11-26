<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Inquiry;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Price Quotation Entry → Records, New inquiry and Edit inquiry (AnnotechERP
 * 06/05). A new inquiry is numbered INQ-<year><0001> and starts as a draft;
 * moving it along (send, revise, confirm, cancel) is the Quotation panel's
 * work. A confirmed or cancelled inquiry is history and is no longer edited.
 */
class InquiryResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'merch-inquiries';
    }

    public function model(): string
    {
        return Inquiry::class;
    }

    public function viewPermission(): string
    {
        return 'merchandise.inquiry.view';
    }

    public function managePermission(): string
    {
        return 'merchandise.inquiry.manage';
    }

    public function columns(): array
    {
        return ['inquiry_no', 'buyer_id', 'inco_term', 'lc_term', 'cons_unit', 'calc_unit', 'currency', 'exchange_rate', 'style_no', 'style_ext', 'style_desc', 'inquiry_date', 'quoted_fob', 'remarks', 'revision', 'status'];
    }

    public function defaults(): array
    {
        return ['inco_term' => 'FOB', 'cons_unit' => 'KG', 'calc_unit' => 'DZ', 'currency' => 'USD', 'exchange_rate' => 0];
    }

    public function query(): Builder
    {
        return Inquiry::query()->latest('inquiry_date')->latest('inquiry_no');
    }

    public function deletable(): bool
    {
        return true;
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();

        return [
            'buyer_id' => [$required, Rule::exists('buyers', 'id')->where('company_id', $company)],
            'inco_term' => ['nullable', Rule::in(Inquiry::INCO_TERMS)],
            'lc_term' => ['nullable', Rule::in(Inquiry::LC_TERMS)],
            'cons_unit' => ['nullable', Rule::in(Inquiry::CONS_UNITS)],
            'calc_unit' => ['nullable', Rule::in(Inquiry::CALC_UNITS)],
            'currency' => ['nullable', Rule::in(Inquiry::CURRENCIES)],
            'exchange_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'style_no' => [$required, 'string', 'max:64'],
            'style_ext' => ['nullable', 'string', 'max:32'],
            'style_desc' => [$required, 'string', 'max:255'],
            'inquiry_date' => [$required, 'date'],
            'quoted_fob' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'buyer_id.required' => __('merchandise.inquiry.buyer_required'),
            'style_no.required' => __('merchandise.inquiry.style_no_required'),
            'style_desc.required' => __('merchandise.inquiry.style_desc_required'),
            'inquiry_date.required' => __('merchandise.inquiry.date_required'),
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row && in_array($row->getAttribute('status'), ['CONFIRMED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['status' => __('merchandise.inquiry.closed', ['no' => $row->getAttribute('inquiry_no'), 'status' => __('merchandise.inquiry.status.'.$row->getAttribute('status'))])]);
        }
        foreach (['style_no', 'style_ext', 'style_desc'] as $column) {
            if (isset($data[$column]) && is_string($data[$column])) {
                $data[$column] = trim($data[$column]);
            }
        }
        $data['exchange_rate'] ??= $row ? $row->getAttribute('exchange_rate') : 0;
        if ($row === null) {
            $data['inquiry_no'] = $this->nextNumber();
            $data['status'] = 'DRAFT';
            $data['revision'] = 0;
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['buyer'] = Buyer::query()->whereKey($row->getAttribute('buyer_id'))->value('short_name');
        $out['exchange_rate'] = (float) $row->getAttribute('exchange_rate');
        $out['quoted_fob'] = $row->getAttribute('quoted_fob') === null ? null : (float) $row->getAttribute('quoted_fob');

        return $out;
    }

    /** INQ-20260001: the year and the next free number in the company. */
    private function nextNumber(): string
    {
        $prefix = 'INQ-'.now()->format('Y');
        // Held to the end of the save's transaction, so two clerks never take one number.
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_inquiries:'.app(TenantContext::class)->companyId()]);
        $last = Inquiry::query()->where('inquiry_no', 'like', $prefix.'%')->orderByDesc('inquiry_no')->value('inquiry_no');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
