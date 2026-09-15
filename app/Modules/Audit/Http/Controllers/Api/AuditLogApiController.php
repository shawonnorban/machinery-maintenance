<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers\Api;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Support\TenantTimezone;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The audit trail, over the wire (API 26, SRS 34; mirrors the web
 * `AuditLogController`) — read-only, per the spec.
 *
 * `AuditLog` carries no tenant global scope (a failed login belongs to no
 * company yet), so every query here scopes itself explicitly with
 * `forCompany()`, same as the web screen.
 */
class AuditLogApiController extends ApiController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantTimezone $timezone,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('audit.log.view');

        $query = AuditLog::query()
            ->forCompany($this->context->companyId())
            ->with('user:id,name,email')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', $request->string('entity_type')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->string('user_id')))
            ->when($request->filled('entity_id'), fn ($q) => $q->where('entity_id', $request->string('entity_id')))
            ->when($request->filled('request_id'), fn ($q) => $q->where('request_id', $request->string('request_id')))
            ->when(
                $request->filled('from'),
                fn ($q) => $q->where('created_at', '>=', $this->timezone->toUtc($request->string('from').' 00:00:00')),
            )
            ->when(
                $request->filled('to'),
                fn ($q) => $q->where('created_at', '<=', $this->timezone->toUtc($request->string('to').' 23:59:59')),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        // The filter options the web index reads off its own screen-local
        // variables (`$actions`, `$entityTypes`, `$users`) — a client has no
        // other way to populate the same dropdowns, since none of the three
        // is a master-data list of its own. `users` is bundled in here
        // rather than left to `GET /users` deliberately: AUDITOR holds
        // `audit.log.view` but not `admin.user.manage` (RoleSeeder grants it
        // only every `.view`/`.view_any` permission plus this one), so an
        // auditor filtering by "who" would hit exactly the permission gap
        // Teams' own `formOptions()` was built to avoid.
        return ApiResponse::ok(
            collect($paginator->items())->map(fn (AuditLog $log): array => $this->summary($log))->values()->all(),
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'filters' => [
                    'actions' => AuditLog::ACTIONS,
                    'entity_types' => $this->entityTypes(),
                    'users' => User::whereHas('memberships', fn ($q) => $q
                        ->where('company_id', $this->context->companyId()))
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->all(),
                ],
            ],
        );
    }

    public function show(AuditLog $log): JsonResponse
    {
        $this->allow('audit.log.view');

        // Another tenant's row, or a platform row. 404 rather than 403: its
        // existence is itself information (API 2).
        if ($log->company_id !== $this->context->companyId()) {
            abort(404);
        }

        $log->load(['user:id,name,email', 'impersonator:id,name,email']);

        $related = $log->request_id === null ? [] : AuditLog::query()
            ->forCompany($this->context->companyId())
            ->where('request_id', $log->request_id)
            ->where('id', '!=', $log->id)
            ->with('user:id,name,email')
            ->orderBy('created_at')
            ->limit(50)
            ->get()
            ->map(fn (AuditLog $l): array => $this->summary($l))
            ->all();

        return ApiResponse::ok($this->detail($log) + ['related' => $related]);
    }

    /**
     * The entity types that actually appear, so the filter offers what
     * exists rather than every table in the schema — same query as the web
     * index's own `entityTypes()`.
     *
     * @return list<string>
     */
    private function entityTypes(): array
    {
        return AuditLog::query()
            ->forCompany($this->context->companyId())
            ->whereNotNull('entity_type')
            ->where('created_at', '>=', CarbonImmutable::now()->subYear())
            ->distinct()
            ->orderBy('entity_type')
            ->pluck('entity_type')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'entity_label' => $log->entity_label,
            'user' => $log->user === null ? null : ['id' => $log->user->id, 'name' => $log->user->name],
            'context' => $log->context,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(AuditLog $log): array
    {
        return $this->summary($log) + [
            'changed_fields' => $log->diff(),
            // A create has nothing to diff against — `changed_fields` is
            // empty for it — so the web falls back to showing what was
            // actually written; a client needs the same raw values to do
            // the same.
            'new_values' => $log->diff() === [] ? $log->new_values_json : null,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'request_id' => $log->request_id,
            'impersonated_by' => $log->impersonator === null ? null : [
                'id' => $log->impersonator->id, 'name' => $log->impersonator->name,
            ],
        ];
    }
}
