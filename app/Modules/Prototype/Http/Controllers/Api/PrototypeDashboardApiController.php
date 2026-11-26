<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Prototype\Models\MaterialIssue;
use App\Modules\Prototype\Models\MaterialRequisition;
use App\Modules\Prototype\Models\MaterialRequisitionLine;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleAlert;
use App\Modules\Prototype\Models\SampleApproval;
use App\Modules\Prototype\Models\SampleDaySheet;
use App\Modules\Prototype\Models\SampleDaySheetLine;
use App\Modules\Prototype\Models\SampleDevelopment;
use App\Modules\Prototype\Models\SampleEvent;
use App\Modules\Prototype\Models\SampleHandover;
use App\Modules\Prototype\Models\SampleRequisition;
use App\Modules\Prototype\Models\SampleRevision;
use App\Modules\Prototype\Support\SampleLedger;
use App\Modules\Prototype\Support\SampleLifecycle;
use App\Modules\Prototype\Support\SampleWording;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Prototype Desk → Prototype Dashboard (AnnotechERP 07/02): live sample
 * status, pipeline, approvals, delays and the sample room's target against
 * achievement. The chosen period (Last 30 days by default) applies to requests, approvals, deliveries,
 * the breakdowns and the performance indicators; "open now" figures are the
 * desk as it stands. Reading it first runs the desk's alert sweep.
 */
class PrototypeDashboardApiController extends ApiController
{
    /** The desk a stage waits on. */
    private const DESK_OF = [
        'REQUESTED' => 'MERCHANDISING', 'DEVELOPMENT' => 'SAMPLE_ROOM', 'MATERIAL' => 'STORE', 'PRODUCTION' => 'PRODUCTION', 'QUALITY' => 'QC',
        'APPROVAL' => 'MERCHANDISING', 'REVISION' => 'SAMPLE_ROOM', 'HANDOVER' => 'MERCHANDISING', 'DELIVERY' => 'MERCHANDISING',
    ];

    /** The period the dashboard reads: Last 7 / 30 / 90 days, This year, All time. */
    public const RANGES = ['7d', '30d', '90d', 'year', 'all'];

    public function index(Request $request): JsonResponse
    {
        $this->allow('prototype.dashboard.view');
        SampleLedger::sweep();
        $today = CarbonImmutable::today();
        $range = in_array($request->query('range'), self::RANGES, true) ? $request->query('range') : '30d';
        $since = match ($range) {
            '7d' => $today->subDays(7), '90d' => $today->subDays(90), 'year' => $today->startOfYear(), 'all' => CarbonImmutable::create(2000), default => $today->subDays(30),
        };

        $samples = Sample::query()->get();
        $life = SampleLifecycle::of($samples);
        $reqs = SampleRequisition::query()->get()->keyBy('id');
        $recent = $samples->filter(fn (Sample $s) => $s->created_at->gte($since));
        $open = $samples->filter(fn (Sample $s) => $life[$s->id]['open'] && $s->state !== 'CANCELLED');
        $count = fn (string $stage) => $open->filter(fn ($s) => $life[$s->id]['stage'] === $stage)->count();
        $approvals = SampleApproval::query()->whereDate('approval_date', '>=', $since)->get();
        $buyerFinal = $approvals->where('step', 'BUYER_APPROVAL');
        $handovers = SampleHandover::query()->whereIn('status', SampleHandover::ARRIVED)->get();
        $delayed = $open->filter(fn ($s) => $life[$s->id]['overdue']);

        $tiles = [
            ['key' => 'total', 'value' => $recent->count(), 'scope' => 'period'],
            ['key' => 'new_requests', 'value' => $reqs->filter(fn ($r) => $r->created_at->gte($since))->count(), 'scope' => 'period'],
            ['key' => 'pending_requests', 'value' => $reqs->whereIn('status', ['SUBMITTED', 'PENDING_APPROVAL'])->count(), 'scope' => 'open'],
            ['key' => 'approved_requests', 'value' => $recent->count(), 'scope' => 'period'],
            ['key' => 'in_development', 'value' => $count('DEVELOPMENT'), 'scope' => 'open'],
            ['key' => 'material_pending', 'value' => $open->filter(fn ($s) => $life[$s->id]['material_pending'])->count(), 'scope' => 'open'],
            ['key' => 'material_issued', 'value' => MaterialIssue::query()->whereDate('issue_date', '>=', $since)->count(), 'scope' => 'period'],
            ['key' => 'in_production', 'value' => $count('PRODUCTION'), 'scope' => 'open'],
            ['key' => 'pending_qc', 'value' => $count('QUALITY'), 'scope' => 'open'],
            ['key' => 'pending_approval', 'value' => $count('APPROVAL'), 'scope' => 'open'],
            ['key' => 'approved_samples', 'value' => $buyerFinal->whereIn('decision', SampleApproval::APPROVED)->count(), 'scope' => 'period'],
            ['key' => 'rejected_samples', 'value' => $approvals->where('decision', 'REJECTED')->count(), 'scope' => 'period'],
            ['key' => 'revision_required', 'value' => $count('REVISION'), 'scope' => 'open'],
            ['key' => 'delayed', 'value' => $delayed->count(), 'scope' => 'open'],
            ['key' => 'completed_today', 'value' => $handovers->filter(fn ($h) => $h->received_date?->isSameDay($today) || $h->actual_delivery?->isSameDay($today))->count(), 'scope' => 'today'],
            ['key' => 'delivered', 'value' => $handovers->filter(fn ($h) => ($h->actual_delivery ?? $h->handover_date)->gte($since))->count(), 'scope' => 'period'],
        ];

        $by = fn (Collection $rows, callable $key) => $rows->groupBy($key)->map->count()->sortDesc();
        $styles = Style::query()->whereIn('id', $reqs->pluck('style_id'))->pluck('description', 'id');
        $buyers = Buyer::query()->whereIn('id', $reqs->pluck('buyer_id'))->pluck('short_name', 'id');
        $types = SampleType::query()->whereIn('id', $reqs->pluck('sample_type_id'))->pluck('name', 'id');
        $reqOf = fn (Sample $s) => $reqs[$s->requisition_id] ?? null;

        // Sheets of the period, oldest first.
        $sheets = SampleDaySheet::query()->whereDate('target_date', '>=', $since)->orderBy('target_date')->get();
        $sheetLines = SampleDaySheetLine::query()->whereIn('day_sheet_id', $sheets->pluck('id'))->get()->groupBy('day_sheet_id');
        $days = $sheets->map(function ($sh) use ($sheetLines): array {
            $f = SampleDaySheetApiController::figures($sheetLines[$sh->id] ?? collect(), (float) $sh->available_manpower * (float) $sh->shift_hours);

            return ['date' => $sh->target_date->toDateString(), 'room' => $sh->sample_room, 'shift' => $sh->shift, 'state' => $sh->state] + $f;
        });
        $latest = $days->last();

        $mrs = MaterialRequisition::query()->whereNotIn('state', ['DRAFT', 'CANCELLED'])->whereDate('req_date', '>=', $since)->get();
        $shortMrs = MaterialRequisitionLine::query()->whereIn('requisition_id', $mrs->pluck('id'))->whereNotNull('available_qty')->whereColumn('available_qty', '<', 'req_qty')->distinct()->count('requisition_id');
        $completedDevs = SampleDevelopment::query()->where('status', 'COMPLETED')->whereDate('actual_completion', '>=', $since)->get();
        $revised = SampleRevision::query()->whereIn('sample_id', $recent->pluck('id'))->get();
        $decidedBuyer = $buyerFinal->filter(fn ($a) => $a->isDecided());
        $approvedBuyer = $decidedBuyer->whereIn('decision', SampleApproval::APPROVED);
        $finalDecided = $approvals->filter(fn ($a) => $a->isDecided());
        $completedSamples = $recent->filter(fn ($s) => in_array($life[$s->id]['stage'], ['DELIVERY', 'COMPLETED'], true));
        $onTime = $completedSamples->filter(function ($s) use ($reqOf, $handovers): bool {
            $h = $handovers->where('sample_id', $s->id)->sortBy('actual_delivery')->first();
            $req = $reqOf($s);

            return $h && $req?->required_date && ($h->actual_delivery ?? $h->handover_date)->lte($req->required_date);
        });
        $approvalHours = $approvedBuyer->map(fn ($a) => $a->submitted_date->diffInDays($a->approval_date))->filter(fn ($d) => $d !== null);
        $devDays = $completedDevs->filter(fn ($d) => $d->start_date && $d->actual_completion)->map(fn ($d) => $d->start_date->diffInDays($d->actual_completion));
        $target = (int) $days->sum('total_target');
        $actual = (int) $days->sum('total_actual');
        $pct = fn ($a, $b) => $b > 0 ? round($a / $b * 100, 1) : null;
        $kpis = [
            ['key' => 'completion_rate', 'value' => $pct($completedSamples->count(), $recent->count()), 'better' => 'higher', 'a' => $completedSamples->count(), 'b' => $recent->count()],
            ['key' => 'on_time', 'value' => $pct($onTime->count(), $completedSamples->count()), 'better' => 'higher', 'a' => $onTime->count(), 'b' => $completedSamples->count()],
            ['key' => 'avg_development', 'value' => $devDays->isEmpty() ? null : round($devDays->avg(), 1), 'better' => 'lower', 'a' => $devDays->count(), 'unit' => 'days'],
            ['key' => 'avg_approval', 'value' => $approvalHours->isEmpty() ? null : round($approvalHours->avg(), 1), 'better' => 'lower', 'a' => $approvalHours->count(), 'unit' => 'days'],
            ['key' => 'avg_revisions', 'value' => $recent->count() ? round($revised->count() / $recent->count(), 2) : null, 'better' => 'lower', 'a' => $revised->count(), 'b' => $recent->count()],
            ['key' => 'first_time', 'value' => $pct($approvedBuyer->filter(fn ($a) => ! SampleRevision::query()->where('sample_id', $a->sample_id)->exists())->count(), $approvedBuyer->count()), 'better' => 'higher', 'a' => $approvedBuyer->filter(fn ($a) => ! SampleRevision::query()->where('sample_id', $a->sample_id)->exists())->count(), 'b' => $approvedBuyer->count()],
            ['key' => 'rejection_rate', 'value' => $pct($finalDecided->where('decision', 'REJECTED')->count(), $finalDecided->count()), 'better' => 'lower', 'a' => $finalDecided->where('decision', 'REJECTED')->count(), 'b' => $finalDecided->count()],
            ['key' => 'delay_rate', 'value' => $pct($recent->filter(fn ($s) => $life[$s->id]['overdue'])->count(), $recent->count()), 'better' => 'lower', 'a' => $recent->filter(fn ($s) => $life[$s->id]['overdue'])->count(), 'b' => $recent->count()],
            ['key' => 'shortage_rate', 'value' => $pct($shortMrs, $mrs->count()), 'better' => 'lower', 'a' => $shortMrs, 'b' => $mrs->count()],
            ['key' => 'target_achievement', 'value' => $pct($actual, $target), 'better' => 'higher', 'a' => $actual, 'b' => $target, 'c' => $days->count()],
            ['key' => 'efficiency', 'value' => $pct($days->sum('earned_hrs'), $days->sum('actual_hrs')), 'better' => 'higher', 'a' => round($days->sum('earned_hrs'), 1), 'b' => round($days->sum('actual_hrs'), 1)],
            ['key' => 'buyer_approval', 'value' => $pct($approvedBuyer->count(), $decidedBuyer->count()), 'better' => 'higher', 'a' => $approvedBuyer->count(), 'b' => $decidedBuyer->count()],
        ];

        $months = collect(range(11, 0))->map(fn ($n) => $today->startOfMonth()->subMonths($n));
        $performance = $months->map(fn (CarbonImmutable $m) => [
            'date' => $m->toDateString(),
            'requested' => $reqs->filter(fn ($r) => $r->created_at->format('Y-m') === $m->format('Y-m'))->count(),
            'completed' => SampleDevelopment::query()->where('status', 'COMPLETED')->whereYear('actual_completion', $m->year)->whereMonth('actual_completion', $m->month)->count(),
            'approved' => SampleApproval::query()->where('step', 'BUYER_APPROVAL')->whereIn('decision', SampleApproval::APPROVED)->whereYear('approval_date', $m->year)->whereMonth('approval_date', $m->month)->count(),
        ])->values()->all();

        $openAlerts = SampleAlert::query()->whereNull('resolved_at')->get()->sortBy(fn ($a) => array_search($a->severity, SampleAlert::SEVERITIES, true).'|'.(9999999999 - $a->raised_at->timestamp))->values();
        $recentEvents = SampleEvent::query()->whereNotNull('sample_id')->orderByDesc('happened_at')->limit(8)->get();
        $sampleNos = Sample::query()->whereIn('id', $recentEvents->pluck('sample_id'))->pluck('sample_no', 'id');

        return ApiResponse::ok([
            'range' => $range, 'since' => $range === 'all' ? null : $since->toDateString(), 'updated_at' => now()->toIso8601String(), 'tiles' => $tiles,
            'by_status' => $by($recent, fn ($s) => $life[$s->id]['status']), 'by_stage' => $by($recent, fn ($s) => $life[$s->id]['stage']),
            'approval_rate' => ['rate' => $pct($approvedBuyer->count(), $decidedBuyer->count()), 'a' => $approvedBuyer->count(), 'b' => $decidedBuyer->count()],
            'revision_rate' => ['rate' => $pct($revised->pluck('sample_id')->unique()->count(), $recent->count()), 'a' => $revised->pluck('sample_id')->unique()->count(), 'b' => $recent->count()],
            'capacity' => $latest ? ['date' => $latest['date'], 'room' => $latest['room'], 'shift' => $latest['shift'], 'state' => $latest['state'], 'planned' => $latest['planned_hrs'], 'capacity' => $latest['capacity_hrs'], 'over' => $latest['over_capacity'],
                'used' => $latest['capacity_hrs'] > 0 ? round($latest['planned_hrs'] / $latest['capacity_hrs'] * 100, 1) : 0.0] : null,
            'daily' => ['days' => $days->map(fn ($d) => ['date' => $d['date'], 'target' => $d['total_target'], 'achieved' => $d['total_actual']])->values()->all(), 'target' => $target, 'achieved' => $actual, 'pct' => $pct($actual, $target)],
            'by_desk' => $by($open, fn ($s) => self::DESK_OF[$life[$s->id]['stage']] ?? 'DESK'),
            'by_buyer' => $recent->groupBy(fn ($s) => $reqOf($s)?->buyer_id)->map(fn ($g, $b) => ['label' => $buyers[$b] ?? '—', 'samples' => $g->count(),
                'approved' => $g->filter(fn ($s) => $buyerFinal->where('sample_id', $s->id)->whereIn('decision', SampleApproval::APPROVED)->isNotEmpty())->count()])->sortByDesc('samples')->take(8)->values()->all(),
            'by_style' => $recent->groupBy(fn ($s) => $reqOf($s)?->style_id)->map(fn ($g, $st) => ['label' => $styles[$st] ?? '—', 'buyer' => $buyers[$reqOf($g->first())?->buyer_id] ?? null, 'samples' => $g->count()])->sortByDesc('samples')->take(8)->values()->all(),
            'by_type' => $by($recent, fn ($s) => $types[$reqOf($s)?->sample_type_id] ?? '—'),
            'by_sample_stage' => $by($recent, fn ($s) => $reqOf($s)?->sample_stage ?? '—'),
            'delay' => ['count' => $delayed->count(), 'by_stage' => $by($delayed, fn ($s) => $life[$s->id]['stage']),
                'by_reason' => $by($delayed, fn ($s) => SampleDevelopment::query()->where('sample_id', $s->id)->whereNotNull('delay_reason')->orderByDesc('dev_no')->value('delay_reason') ?? '—')],
            'performance' => $performance, 'kpis' => $kpis,
            'alerts' => ['critical' => $openAlerts->where('severity', 'CRITICAL')->count(), 'open' => $openAlerts->count(), 'top' => SampleAlertApiController::present($openAlerts->take(5))],
            'timeline' => $recentEvents->map(fn ($e) => ['sample_no' => $sampleNos[$e->sample_id] ?? null, 'stage' => $e->stage, ...SampleWording::event($e), 'by' => $e->by_name, 'at' => $e->happened_at->toIso8601String()])->values()->all(),
            'delayed' => $delayed->sortByDesc(fn ($s) => $life[$s->id]['days_late'])->map(fn ($s) => ['sample_no' => $s->sample_no, 'style' => $styles[$reqOf($s)?->style_id] ?? null, 'buyer' => $buyers[$reqOf($s)?->buyer_id] ?? null,
                'stage' => $life[$s->id]['stage'], 'required' => $life[$s->id]['required_date'], 'days_late' => $life[$s->id]['days_late'],
                'reason' => SampleDevelopment::query()->where('sample_id', $s->id)->whereNotNull('delay_reason')->orderByDesc('dev_no')->value('delay_reason')])->values()->all(),
        ]);
    }
}
