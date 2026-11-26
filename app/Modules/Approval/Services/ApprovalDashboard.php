<?php

declare(strict_types=1);

namespace App\Modules\Approval\Services;

use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Approval Center → Request Dashboard (AnnotechERP 04/03): request counts,
 * turnaround and where approvals stall — over exactly the requests the person
 * may see in the inbox, so the dashboard never shows more than the lists do.
 */
class ApprovalDashboard
{
    private const TOP = 10;

    public function __construct(private readonly ApprovalInbox $inbox) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, bool $viewAny, string $timezone): array
    {
        $rows = collect($this->inbox->list($user, $viewAny, 'all')['rows'])->where('status', '!=', 'DRAFT')->values();
        $now = CarbonImmutable::now($timezone);
        $local = fn (?string $iso): ?CarbonImmutable => $iso ? CarbonImmutable::parse($iso)->setTimezone($timezone) : null;
        $decided = $rows->filter(fn (array $r): bool => in_array($r['status'], ['APPROVED', 'REJECTED'], true) && $r['decided_at'] && $r['date']);
        $hours = fn (array $r): float => max(0, CarbonImmutable::parse($r['date'])->diffInMinutes(CarbonImmutable::parse($r['decided_at']))) / 60;

        return [
            'tiles' => [
                'total' => $rows->count(),
                'pending' => $rows->where('status', 'PENDING')->count(),
                'pending_on_me' => $rows->where('status', 'PENDING')->where('can_act', true)->count(),
                'approved' => $rows->where('status', 'APPROVED')->count(),
                'rejected' => $rows->where('status', 'REJECTED')->count(),
                'returned' => $rows->where('status', 'RETURNED')->count(),
                'overdue' => $rows->where('overdue', true)->count(),
                'today' => $rows->filter(fn (array $r): bool => $local($r['date'])?->isSameDay($now) ?? false)->count(),
                'this_month' => $rows->filter(fn (array $r): bool => $local($r['date'])?->isSameMonth($now) ?? false)->count(),
                'avg_turnaround_hours' => $decided->isEmpty() ? null : round($decided->avg($hours), 1),
            ],
            'by_module' => $this->count($rows, fn (array $r) => $r['module'] ?? $r['entity_type']),
            'by_status' => $this->count($rows, fn (array $r) => $r['status']),
            'by_priority' => $this->count($rows, fn (array $r) => $r['priority']),
            'by_buyer' => $this->count($rows, fn (array $r) => $r['buyer']),
            'by_department' => $this->count($rows, fn (array $r) => $r['department']),
            'by_requester' => $this->count($rows, fn (array $r) => $r['requested_by']),
            // Who decided it, or who it waits on.
            'by_approver' => $this->count($rows, fn (array $r) => $r['status'] === 'PENDING' ? $r['request_to'] : $r['decided_by']),
            'rejected_reasons' => $this->count($rows->where('status', 'REJECTED'), fn (array $r) => $r['reason']),
            'revision_reasons' => $this->count($rows->where('status', 'RETURNED'), fn (array $r) => $r['reason']),
            'turnaround' => $decided->groupBy(fn (array $r) => $r['type'] ?? $r['entity_type'])
                ->map(fn (Collection $g, string $type): array => ['label' => $type, 'count' => $g->count(), 'avg_hours' => round($g->avg($hours), 1), 'max_hours' => round($g->max($hours), 1)])
                ->sortByDesc('avg_hours')->take(self::TOP)->values()->all(),
            'overdue' => $rows->where('overdue', true)->sortBy('due_at')->take(self::TOP)
                ->map(fn (array $r): array => [
                    'id' => $r['id'], 'request_no' => $r['request_no'], 'title' => $r['title'], 'request_to' => $r['request_to'],
                    'due_at' => $r['due_at'], 'days' => (int) floor(CarbonImmutable::parse($r['due_at'])->diffInDays($now)),
                ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{label: string, count: int}>
     */
    private function count(Collection $rows, callable $key): array
    {
        return $rows->map($key)->filter(fn ($v) => $v !== null && $v !== '')
            ->countBy()->sortDesc()->take(self::TOP)
            ->map(fn (int $n, string $label): array => ['label' => $label, 'count' => $n])
            ->values()->all();
    }
}
