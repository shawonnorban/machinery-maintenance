<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers\Web;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Support\TenantTimezone;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reading the audit trail (SRS 34).
 *
 * Scoped to the company explicitly, because the model carries no global tenant
 * scope: rows exist that belong to no company yet — a failed login for an email
 * nobody recognises — and a scope that throws on those would lose exactly the
 * rows worth keeping.
 */
class AuditLogController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantTimezone $timezone,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAudit($request);

        $logs = AuditLog::query()
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
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('audit::audit.index', [
            'logs' => $logs,
            'actions' => AuditLog::ACTIONS,
            'entityTypes' => $this->entityTypes(),
            'users' => User::whereHas('memberships', fn ($q) => $q
                ->where('company_id', $this->context->companyId()))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, AuditLog $log): View
    {
        $this->authorizeAudit($request);

        if ($log->company_id !== $this->context->companyId()) {
            // Another tenant's row, or a platform row. 404 rather than 403: its
            // existence is itself information (API 2).
            throw new NotFoundHttpException;
        }

        return view('audit::audit.show', [
            'log' => $log->load(['user:id,name,email', 'impersonator:id,name,email']),
            // Every row written by the same request. One support ticket citing
            // a request id resolves to the whole causal chain (ADR-061).
            'related' => $log->request_id === null ? collect() : AuditLog::query()
                ->forCompany($this->context->companyId())
                ->where('request_id', $log->request_id)
                ->where('id', '!=', $log->id)
                ->orderBy('created_at')
                ->limit(50)
                ->get(),
        ]);
    }

    private function authorizeAudit(Request $request): void
    {
        if (! $request->user()->can('audit.log.view')) {
            abort(403);
        }
    }

    /**
     * The entity types that actually appear, so the filter offers what exists
     * rather than every table in the schema.
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
}
