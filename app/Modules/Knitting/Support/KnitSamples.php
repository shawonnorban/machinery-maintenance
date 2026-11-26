<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support;

use App\Modules\Knitting\Models\KnitDirectBooking;
use App\Modules\Knitting\Models\KnitSamplePlan;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * What Sample Knit Plan and Direct Knit Fabric Booking (AnnotechERP 15/04)
 * pick from: buyers, their styles (style ref — description) and the orders
 * under them, fabric types, colours, the registered yarn the stores have
 * held, and the booking categories.
 */
final class KnitSamples
{
    /** @return array<string, mixed> */
    public static function options(): array
    {
        $company = app(TenantContext::class)->companyId();
        $o = KnitProduction::options();
        $t = YarnTesting::options();
        $orders = Order::query()->where('status', '!=', 'CANCELLED')->orderByDesc('created_at')->limit(500)
            ->get(['id', 'order_no', 'job_no', 'style_no', 'work_style_no', 'buyer_id', 'style_id']);

        return collect($o)->only(['company', 'buyers', 'categories', 'fabric_types', 'colours'])->all()
            + collect($t)->only(['stores', 'colour_groups', 'uoms', 'lots', 'yarn_names', 'req_types'])->all() + [
                'styles' => DB::table('merch_styles')->where('company_id', $company)->orderBy('style_ref')->limit(1000)->get(['id', 'style_ref', 'description', 'buyer_id'])
                    ->map(fn ($s) => ['value' => $s->id, 'label' => trim($s->style_ref.' — '.$s->description, ' —'), 'buyer_id' => $s->buyer_id])->values()->all(),
                'orders' => $orders->map(fn (Order $r) => ['value' => $r->id, 'label' => KnitProduction::orderLabel($r), 'buyer_id' => $r->buyer_id, 'style_id' => $r->style_id,
                    'order_no' => $r->order_no, 'job' => $r->work_style_no ?: $r->style_no ?: $r->job_no])->values()->all(),
                'plan_statuses' => KnitSamplePlan::STATUSES, 'booking_statuses' => KnitDirectBooking::STATUSES,
            ];
    }
}
