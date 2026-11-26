<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Support;

use App\Modules\Prototype\Models\BundleCard;
use App\Modules\Prototype\Models\MaterialRequisition;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleHandover;
use App\Modules\Prototype\Models\SampleRequisition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Where a sample stands along Brickland's lifecycle — Requested →
 * Development → Material → Production → Quality → Approval → Revision →
 * Handover → Delivery → Completed — read off its phase and state and the
 * material and bundle documents raised for it; its status within the stage,
 * progress (the stage's position), whether it is late, material pending and
 * the flags the register shows.
 */
final class SampleLifecycle
{
    public const STAGES = ['REQUESTED', 'DEVELOPMENT', 'MATERIAL', 'PRODUCTION', 'QUALITY', 'APPROVAL', 'REVISION', 'HANDOVER', 'DELIVERY', 'COMPLETED'];

    public const STATUSES = ['DRAFT', 'PENDING', 'APPROVED', 'IN_PROGRESS', 'ON_HOLD', 'COMPLETED', 'REJECTED', 'CANCELLED', 'DELAYED'];

    /** Material requisitions still waiting to be issued in full. */
    private const MR_OPEN = ['SUBMITTED', 'DEPT_APPROVED', 'STORE_VERIFIED', 'APPROVED', 'PARTIALLY_ISSUED'];

    /** @return array<string, array<string, mixed>> sample id => lifecycle */
    public static function of(Collection $samples): array
    {
        $ids = $samples->pluck('id');
        $reqs = SampleRequisition::query()->whereIn('id', $samples->pluck('requisition_id'))->get(['id', 'required_date', 'priority'])->keyBy('id');
        $mrs = MaterialRequisition::query()->whereIn('sample_id', $ids)->whereNotIn('state', ['DRAFT', 'CANCELLED', 'REJECTED'])->get(['sample_id', 'state'])->groupBy('sample_id');
        $cards = BundleCard::query()->whereIn('sample_id', $ids)->where('bundle_status', '!=', 'CANCELLED')->get(['sample_id', 'bundle_status'])->groupBy('sample_id');
        $received = SampleHandover::query()->whereIn('sample_id', $ids)->where('status', 'RECEIVED')->pluck('sample_id')->flip();
        $today = CarbonImmutable::today();

        return $samples->mapWithKeys(function (Sample $s) use ($reqs, $mrs, $cards, $received, $today): array {
            $req = $reqs[$s->requisition_id] ?? null;
            $myMrs = $mrs[$s->id] ?? collect();
            $myCards = $cards[$s->id] ?? collect();
            $materialPending = $myMrs->contains(fn ($m) => in_array($m->state, self::MR_OPEN, true));
            $stage = match (true) {
                $s->phase === 'HANDOVER' && $s->state === 'DELIVERED' => isset($received[$s->id]) ? 'COMPLETED' : 'DELIVERY',
                $s->phase === 'HANDOVER' => 'HANDOVER',
                $s->phase === 'APPROVAL' && $s->state === 'REVISION_REQUIRED', $s->phase === 'REVISION' => 'REVISION',
                $s->phase === 'APPROVAL' => 'APPROVAL',
                $s->phase === 'QUALITY' => 'QUALITY',
                $myCards->isNotEmpty() => 'PRODUCTION',
                $myMrs->isNotEmpty() => 'MATERIAL',
                $s->phase === 'DEVELOPMENT' => 'DEVELOPMENT',
                default => 'REQUESTED',
            };
            $status = match (true) {
                $s->state === 'CANCELLED' => 'CANCELLED',
                $stage === 'COMPLETED' || $s->state === 'DELIVERED' => 'COMPLETED',
                $s->state === 'REJECTED' => 'REJECTED',
                $stage === 'PRODUCTION' && $myCards->contains('bundle_status', 'HOLD') => 'ON_HOLD',
                $stage === 'MATERIAL' => $materialPending ? 'PENDING' : 'IN_PROGRESS',
                in_array($s->state, ['APPROVED', 'ASSIGNED'], true) => 'APPROVED',
                in_array($s->state, ['PENDING', 'FAILED', 'REWORK', 'REVISION_REQUIRED', 'READY'], true) => 'PENDING',
                default => 'IN_PROGRESS',
            };
            $closed = in_array($status, ['COMPLETED', 'CANCELLED'], true) || $stage === 'DELIVERY';
            $required = $req?->required_date;
            $late = ! $closed && $required !== null && $required->lt($today) ? (int) $required->diffInDays($today) : 0;
            // Late and still moving: Delayed. On Hold and Rejected say more, so they stay.
            if ($late > 0 && in_array($status, ['PENDING', 'IN_PROGRESS', 'APPROVED'], true)) {
                $status = 'DELAYED';
            }
            $priority = $req?->priority;

            return [$s->id => [
                'stage' => $stage, 'status' => $status, 'progress' => (int) round(array_search($stage, self::STAGES, true) / (count(self::STAGES) - 1) * 100),
                'required_date' => $required?->toDateString(), 'days_late' => $late, 'overdue' => $late > 0, 'material_pending' => $materialPending, 'priority' => $priority,
                'open' => ! $closed, 'flags' => array_values(array_filter([$late > 0 ? 'OVERDUE' : null, $materialPending ? 'MATERIAL' : null, in_array($priority, ['URGENT', 'CRITICAL'], true) ? $priority : null])),
            ]];
        })->all();
    }

    /** One sample's lifecycle. @return array<string, mixed> */
    public static function one(Sample $sample): array
    {
        return self::of(collect([$sample]))[$sample->id];
    }
}
