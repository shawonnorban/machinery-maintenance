<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\SampleBatch;
use App\Modules\Merchandise\Models\SampleFeedback;
use App\Modules\Merchandise\Models\SampleLine;
use App\Modules\Merchandise\Models\SampleProgram;
use App\Modules\Merchandise\Models\SampleType;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Lab Dip & Sample → Buyer Sample Feedback → Records, New feedback and Edit
 * (AnnotechERP 06/10). A feedback names the job / order and sample type,
 * when the batch was submitted, the buyer's verdict and comments, and — a
 * separate decision from the sample being accepted — whether the bulk shade
 * is approved. The buyer account follows the order when none is picked; the
 * Batch# is the latest submitted production batch that made that order's
 * sample of that type.
 */
class SampleFeedbackResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'merch-sample-feedback';
    }

    public function model(): string
    {
        return SampleFeedback::class;
    }

    public function viewPermission(): string
    {
        return 'merchandise.sample.view';
    }

    public function managePermission(): string
    {
        return 'merchandise.sample.manage';
    }

    public function columns(): array
    {
        return ['order_id', 'buyer_office_id', 'sample_type_id', 'submit_date', 'status', 'bulk_shade_approved', 'comments'];
    }

    public function defaults(): array
    {
        return ['status' => 'SUBMITTED'];
    }

    public function booleans(): array
    {
        return ['bulk_shade_approved'];
    }

    public function query(): Builder
    {
        return SampleFeedback::query()->latest('submit_date')->latest();
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
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'buyer_office_id' => ['nullable', Rule::exists('buyer_offices', 'id')->where('company_id', $company)],
            'sample_type_id' => ['nullable', Rule::exists('sample_types', 'id')->where('company_id', $company)],
            'submit_date' => [$required, 'date'],
            'status' => ['nullable', Rule::in(SampleFeedback::STATUSES)],
            'bulk_shade_approved' => ['sometimes', 'boolean'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['submit_date.required' => __('merchandise.feedback.date_required')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('comments', $data)) {
            $data['comments'] = is_string($data['comments']) && trim($data['comments']) !== '' ? trim($data['comments']) : null;
        }
        $order = array_key_exists('order_id', $data) ? $data['order_id'] : $row?->getAttribute('order_id');
        $type = array_key_exists('sample_type_id', $data) ? $data['sample_type_id'] : $row?->getAttribute('sample_type_id');
        if ($order && empty($data['buyer_office_id']) && ! $row?->getAttribute('buyer_office_id')) {
            $data['buyer_office_id'] = Order::query()->whereKey($order)->value('buyer_office_id');
        }
        if (array_key_exists('order_id', $data) || array_key_exists('sample_type_id', $data) || $row === null) {
            $data['batch_id'] = $this->batchOf($order, $type);
        }
        if ($row === null) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $order = $row->getAttribute('order_id') ? Order::query()->whereKey($row->getAttribute('order_id'))->first(['order_no', 'style_no', 'work_style_no', 'job_no']) : null;
        $out['order_no'] = $order?->order_no;
        // Brickland's Job#: the order's work style, else its style.
        $out['job'] = $order ? ($order->work_style_no ?: $order->style_no) : null;
        $out['buyer_ac'] = $row->getAttribute('buyer_office_id') ? BuyerOffice::query()->whereKey($row->getAttribute('buyer_office_id'))->value('name') : null;
        $out['type'] = $row->getAttribute('sample_type_id') ? SampleType::query()->whereKey($row->getAttribute('sample_type_id'))->value('name') : null;
        $out['batch_no'] = $row->getAttribute('batch_id') ? SampleBatch::query()->whereKey($row->getAttribute('batch_id'))->value('batch_no') : null;

        return $out;
    }

    /** The latest submitted production batch that made this order's sample of this type, or null. */
    private function batchOf(?string $order, ?string $type): ?string
    {
        if (! $order || ! $type) {
            return null;
        }
        $programs = SampleProgram::query()->where('order_id', $order)->pluck('id');
        $batches = SampleLine::query()->whereIn('program_id', $programs)->where('sample_type_id', $type)->whereNotNull('batch_id')->pluck('batch_id');

        return SampleBatch::query()->whereIn('id', $batches)->where('status', 'SUBMITTED')->orderByDesc('batch_date')->orderByDesc('batch_no')->value('id');
    }
}
