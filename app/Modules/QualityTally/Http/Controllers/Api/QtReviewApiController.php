<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Http\Controllers\Api;

use App\Modules\QualityTally\Http\Controllers\Concerns\GuardsSections;
use App\Modules\QualityTally\Models\QtEntry;
use App\Modules\QualityTally\Models\QtJob;
use App\Modules\QualityTally\Models\QtRefusedTap;
use App\Modules\QualityTally\Support\QtBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Review (AnnotechERP 37, phase 3; plan §৩ 3a, §৫.৬): what the manager must
 * look at in a section — the taps the server REFUSED for good (closed job,
 * another line, too old…), with their reason, and the taps made with no
 * network that were KEPT with a Check mark (no target, nothing ready then).
 * Each is marked seen once looked at. Read by those who see the section,
 * marked by those who manage it.
 */
class QtReviewApiController extends ApiController
{
    use GuardsSections;

    public function index(Request $request): JsonResponse
    {
        $q = $request->validate([
            'section' => ['required', Rule::in(QtBook::SECTIONS)],
            'status' => ['nullable', Rule::in(['open', 'all'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $this->allowView($q['section']);
        $c = $this->caller()->companyId;
        $open = ($q['status'] ?? 'open') === 'open';
        $to = CarbonImmutable::parse($q['to'] ?? QtBook::today())->endOfDay();
        $from = CarbonImmutable::parse($q['from'] ?? $to->subDays(13)->toDateString())->startOfDay();

        $refused = QtRefusedTap::query()->where('section', $q['section'])->whereBetween('created_at', [$from, $to])
            ->when($open, fn ($w) => $w->whereNull('seen_at'))->orderByDesc('created_at')->limit(500)->get();
        $kept = QtEntry::query()->where('section', $q['section'])->whereNotNull('review')->whereBetween('created_at', [$from, $to])
            ->when($open, fn ($w) => $w->whereNull('review_seen_at'))->orderByDesc('created_at')->limit(500)->get();

        $lines = QtBook::allLines($c);
        $jobs = QtJob::query()->whereIn('id', $refused->pluck('job_id')->merge($kept->pluck('job_id'))->filter()->unique())->get()->keyBy('id');
        $users = DB::table('users')->whereIn('id', $refused->pluck('user_id')->merge($kept->pluck('user_id'))->merge($refused->pluck('seen_by'))->merge($kept->pluck('review_seen_by'))->filter()->unique())->pluck('name', 'id');
        $phones = DB::table('qt_phones')->whereIn('id', $refused->pluck('phone_id')->merge($kept->pluck('phone_id'))->filter()->unique())->pluck('name', 'id');
        $tz = (string) config('app.timezone');
        $row = fn (string $kind, $id, ?string $line, ?string $job, ?string $action, $at, ?string $user, ?string $phone, string $why, $seenAt, ?string $seenBy, $arrived) => [
            'kind' => $kind, 'id' => $id, 'line' => $line ? (QtBook::label($lines->get($q['section'].':'.$line)) ?? '—') : '—',
            'job_no' => $job ? $jobs->get($job)?->job_no : null, 'style' => $job ? $jobs->get($job)?->style : null, 'action' => $action,
            'occurred_at' => $at?->copy()->setTimezone($tz)->toIso8601String(), 'arrived_at' => $arrived?->copy()->setTimezone($tz)->toIso8601String(),
            'qc' => $user ? ($users[$user] ?? null) : null, 'phone' => $phone ? ($phones[$phone] ?? null) : null, 'reason' => $why,
            'seen_at' => $seenAt?->toIso8601String(), 'seen_by' => $seenBy ? ($users[$seenBy] ?? null) : null,
        ];

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = $refused->map(fn (QtRefusedTap $r) => $row('REFUSED', $r->id, $r->line_id, $r->job_id, $r->action, $r->occurred_at, $r->user_id, $r->phone_id, $r->message, $r->seen_at, $r->seen_by, $r->created_at))
            ->merge($kept->map(fn (QtEntry $e) => $row('KEPT', $e->id, $e->line_id, $e->job_id, $e->action, $e->occurred_at, $e->user_id, $e->phone_id, __('qtally.phone.review_'.$e->review), $e->review_seen_at ? CarbonImmutable::parse($e->review_seen_at) : null, $e->review_seen_by, $e->created_at)))
            ->sortByDesc('arrived_at')->values();

        return ApiResponse::ok([
            'section' => $q['section'], 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'status' => $open ? 'open' : 'all',
            'rows' => $rows->all(),
            'open' => [
                'refused' => QtRefusedTap::query()->where('section', $q['section'])->whereNull('seen_at')->count(),
                'kept' => QtEntry::query()->where('section', $q['section'])->whereNotNull('review')->whereNull('review_seen_at')->count(),
            ],
        ]);
    }

    /** Marks taps of the section seen (the manager has looked at them). */
    public function seen(Request $request): JsonResponse
    {
        $d = $request->validate([
            'section' => ['required', Rule::in(QtBook::SECTIONS)],
            'items' => ['required', 'array', 'max:500'],
            'items.*.kind' => ['required', Rule::in(['REFUSED', 'KEPT'])],
            'items.*.id' => ['required', 'string', 'size:26'],
        ]);
        $this->allowManage($d['section']);
        $me = $this->caller()->auditUserId();
        $items = collect($d['items']);
        $n = QtRefusedTap::query()->where('section', $d['section'])->whereIn('id', $items->where('kind', 'REFUSED')->pluck('id'))->whereNull('seen_at')
            ->update(['seen_at' => now(), 'seen_by' => $me]);
        $n += QtEntry::query()->where('section', $d['section'])->whereNotNull('review')->whereIn('id', $items->where('kind', 'KEPT')->pluck('id'))->whereNull('review_seen_at')
            ->update(['review_seen_at' => now(), 'review_seen_by' => $me]);

        return ApiResponse::ok(['seen' => $n]);
    }
}
