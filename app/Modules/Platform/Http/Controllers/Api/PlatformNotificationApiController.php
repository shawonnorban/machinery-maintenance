<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Api;

use App\Modules\Notification\Models\Notification;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A platform admin's own notifications (Platform API §9). New capability:
 * nothing before this exposed a platform admin's `company_id IS NULL` inbox
 * outside the web bell icon (`PlatformDeskController::notifications`).
 *
 * `whereNull('company_id')` is the whole of what makes a row a platform one
 * rather than a customer's, stated once here so no query can accidentally
 * hand a platform admin a notification meant for a company they support.
 */
class PlatformNotificationApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $filter = $request->query('filter', 'UNREAD');

        $query = $this->platformNotifications($request)
            ->when($filter === 'UNREAD', fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('created_at');

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        // The web bell icon's own badge count — a client has no other way to
        // get this figure, since the "ALL" filter's own `total` counts read
        // notifications too. Scoped the same way `platformNotifications()`
        // is: `company_id IS NULL` is the whole of what makes a row this
        // admin's own rather than a customer's.
        return ApiResponse::ok(
            collect($paginator->items())->map(fn (Notification $n): array => $this->summary($n))->values()->all(),
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'unread_count' => $this->platformNotifications($request)->whereNull('read_at')->count(),
            ],
        );
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->platformNotifications($request)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::noContent();
    }

    private function platformNotifications(Request $request): Builder
    {
        return Notification::withoutGlobalScope(TenantScope::class)
            ->whereNull('company_id')
            ->where('user_id', $request->user()->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'event_type' => $notification->event_type,
            'title' => $notification->title,
            'body' => $notification->body,
            'severity' => $notification->severity,
            'action_url' => $notification->action_url,
            'is_read' => $notification->isRead(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'read_at' => $notification->read_at?->toIso8601String(),
        ];
    }
}
