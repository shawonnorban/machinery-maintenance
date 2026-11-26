<?php

declare(strict_types=1);

namespace App\Modules\Approval\Console;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRequestType;
use App\Modules\Approval\Services\ApprovalInbox;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Approval Center → Request Types & Routing: a request still waiting this many
 * hours past its due date is told to the type's escalation contact, once
 * (AnnotechERP 04/03). Each company on its own, with its own context.
 */
class EscalateOverdueRequests extends Command
{
    protected $signature = 'approval:escalate-overdue';

    protected $description = 'Tell each request type\'s escalation contact about requests overdue past its threshold';

    public function handle(TenantContext $context, NotificationDispatcher $notifications, ApprovalInbox $inbox): int
    {
        $now = CarbonImmutable::now();
        $candidates = ApprovalRequest::withoutGlobalScope(TenantScope::class)
            ->where('status', 'PENDING')->whereNotNull('due_at')->whereNull('escalated_at')->whereNotNull('request_type_id')
            ->where('due_at', '<', $now)->get();

        $sent = 0;
        foreach ($candidates->groupBy('company_id') as $companyId => $requests) {
            $context->forget();
            $context->set((string) $companyId);
            foreach ($requests as $request) {
                $type = ApprovalRequestType::query()->find($request->request_type_id);
                if ($type === null || $type->escalate_to_user_id === null || CarbonImmutable::instance($request->due_at)->addHours($type->escalate_after_hours)->isFuture()) {
                    continue;
                }
                $to = User::query()->find($type->escalate_to_user_id);
                if ($to === null) {
                    continue;
                }
                try {
                    $row = $inbox->rowFor($request, $to);
                    $notifications->send(
                        recipient: $to,
                        eventType: 'APPROVAL_OVERDUE',
                        data: ['number' => (string) $row['request_no'], 'title' => (string) $row['title'], 'waiting_on' => (string) ($row['request_to'] ?? '—')],
                        severity: 'WARNING',
                        entityType: 'approval_request',
                        entityId: $request->id,
                        actionUrl: rtrim((string) config('tenancy.frontend_url'), '/').'/approval-center/all-requests',
                    );
                    $request->forceFill(['escalated_at' => $now])->save();
                    $sent++;
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }
        $context->forget();
        $this->info("Escalated {$sent} overdue request(s).");

        return self::SUCCESS;
    }
}
