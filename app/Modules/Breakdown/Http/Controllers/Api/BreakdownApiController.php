<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Http\Controllers\Api;

use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Actions\RaiseBreakdownWorkOrder;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Breakdown\Actions\TransitionBreakdown;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\DowntimeReasonCode;
use App\Modules\Breakdown\Models\DowntimeRecord;
use App\Modules\Breakdown\Models\FailureCategory;
use App\Modules\Breakdown\Models\FailureCode;
use App\Modules\Breakdown\Models\RootCause;
use App\Modules\Breakdown\Services\BreakdownScopeGuard;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Support\TenantTimezone;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Breakdowns (API 12).
 *
 * Reporting one is the single most time-critical write in the product: a line
 * is stopped while somebody is filling in a form. So the endpoint asks for one
 * thing — which machine, and what is wrong — and lets the action supply
 * everything that can be derived. Priority comes from the machine's own
 * criticality unless the caller overrides it; the failure taxonomy can be
 * filled in later by whoever repairs it, and demanding it now would make the
 * fastest path through the system the slowest.
 *
 * Reporting is idempotent. A tablet on factory wifi that appears to hang gets
 * pressed again, and two breakdown numbers for one stoppage halve the MTBF of
 * a machine that broke once.
 */
class BreakdownApiController extends ApiController
{
    private const FILTERS = ['status', 'priority', 'severity', 'asset_id', 'factory_id'];

    private const SORTS = ['reported_at', 'failure_at', 'priority', 'status'];

    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('breakdown.breakdown.view_any');

        $query = Breakdown::query()
            ->with(['asset:id,asset_code,name', 'factory:id,name'])
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if ($request->query('open') === 'true') {
            // The question a dashboard actually asks, answered by the model
            // rather than by a client hard-coding the list of open statuses.
            $query->whereIn('status', Breakdown::OPEN_STATUSES);
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'reported_at');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (Breakdown $breakdown): array => $this->summary($breakdown),
        );
    }

    /**
     * The KPI strip `BreakdownController::index()`'s Blade view shows above
     * its own status pills — dropped when this list was first ported and
     * restored here rather than folded into `index()`'s response, the same
     * reasoning as `WorkOrderApiController::counts()`.
     *
     * @return array<string, int>
     */
    public function counts(): JsonResponse
    {
        $this->allow('breakdown.breakdown.view_any');

        $factoryIds = $this->context->accessibleFactoryIds();
        $base = fn () => Breakdown::query()->whereIn('factory_id', $factoryIds);

        return ApiResponse::ok([
            'open' => $base()->whereIn('status', Breakdown::OPEN_STATUSES)->count(),
            'unacknowledged' => $base()->where('status', 'REPORTED')->count(),
            'in_repair' => $base()->where('status', 'IN_REPAIR')->count(),
            'awaiting_closure' => $base()->whereIn('status', ['REPAIRED', 'PRODUCTION_RESUMED'])->count(),
        ]);
    }

    public function show(Breakdown $breakdown): JsonResponse
    {
        $this->allow('breakdown.breakdown.view');
        $this->assertReachable($breakdown);

        $breakdown->load([
            'asset:id,asset_code,name', 'factory:id,name',
            'failureCategory:id,name', 'failureCode:id,name', 'rootCause:id,name',
            'assignedTechnician:id,name',
        ]);

        return ApiResponse::ok($this->detail($breakdown) + [
            // The repair work this breakdown raised, if any — so a single
            // screen can show its labor/parts/checklist alongside the
            // breakdown's own status, rather than sending the technician to
            // a separate work order screen to find it (raised automatically
            // once a technician is assigned; see `TransitionBreakdown::assign`).
            'work_order' => $this->workOrderSummary($breakdown->activeWorkOrder()),
        ]);
    }

    /**
     * Everything the report form's own optional fields need — mirrors
     * `BreakdownController::formOptions()` (its `create()` action's private
     * helper) exactly: assets, production lines, failure codes, and reason
     * codes. A separate endpoint from `formOptions(Breakdown $breakdown)`
     * below because there is no breakdown yet at report time — that one
     * needs an existing record to scope technicians to its factory, this
     * one doesn't. Gated on `breakdown.breakdown.create`, matching the web
     * `create()` action's own single-permission gate for all of this data.
     */
    public function createFormOptions(): JsonResponse
    {
        $this->allow('breakdown.breakdown.create');

        $companyId = $this->context->companyId();

        $assets = Asset::query()
            ->whereIn('current_factory_id', $this->context->accessibleFactoryIds())
            ->whereNotIn('status', ['SCRAPPED', 'RETIRED', 'LOST', 'DRAFT']);

        // A Line Chief or technician restricted to their own line (see
        // `BreakdownScopeGuard`) should never see a machine they would then
        // be refused for reporting on submit — the dropdown reflects the
        // same coverage the write path enforces, not a wider list.
        $user = $this->caller()->user;
        $coverage = $user === null || BreakdownScopeGuard::isExempt($user)
            ? null
            : BreakdownScopeGuard::coverageFor($user);

        if ($coverage !== null) {
            $assets->whereHas('location', function ($query) use ($coverage): void {
                if ($coverage['production_line_id'] !== null) {
                    $query->where('production_line_id', $coverage['production_line_id']);
                } elseif ($coverage['department_id'] !== null) {
                    $query->where('department_id', $coverage['department_id']);
                }
            });
        }

        return ApiResponse::ok([
            'assets' => $assets->orderBy('asset_code')
                ->get(['id', 'asset_code', 'name', 'current_factory_id'])
                ->all(),
            'production_lines' => ProductionLine::orderBy('name')->get(['id', 'name'])->all(),
            'failure_codes' => FailureCode::availableTo($companyId)->where('active', true)
                ->orderBy('name')->get(['id', 'name'])->all(),
            'reason_codes' => DowntimeReasonCode::availableTo($companyId)->where('active', true)
                ->orderBy('name')->get(['id', 'name', 'downtime_class'])->all(),
        ]);
    }

    /**
     * The technician and failure-taxonomy dropdowns the assign/close/hold
     * forms need — mirrors the web `BreakdownController::show()`'s own
     * `technicians`/`failureCategories`/`failureCodes`/`rootCauses` exactly
     * (`hold_reasons` is a fixed enum, not a lookup, included here anyway
     * so the frontend has one source for it). Gated on `breakdown.
     * breakdown.view` — the broadest permission anyone reaching this
     * screen already holds — rather than on each action's own stricter
     * permission (`.assign`/`.close`/`.repair`), which still gate the
     * writes themselves.
     */
    public function formOptions(Breakdown $breakdown): JsonResponse
    {
        $this->allow('breakdown.breakdown.view');
        $this->assertReachable($breakdown);

        $companyId = $this->context->companyId();

        return ApiResponse::ok([
            'technicians' => Technician::where('factory_id', $breakdown->factory_id)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id'])
                ->all(),
            'failure_categories' => FailureCategory::availableTo($companyId)->where('active', true)
                ->orderBy('name')->get(['id', 'name'])->all(),
            'failure_codes' => FailureCode::availableTo($companyId)->where('active', true)
                ->orderBy('name')->get(['id', 'name', 'failure_category_id'])->all(),
            'root_causes' => RootCause::availableTo($companyId)->where('active', true)
                ->orderBy('name')->get(['id', 'name'])->all(),
            'hold_reasons' => Breakdown::HOLD_REASONS,
        ]);
    }

    public function store(Request $request, ReportBreakdown $action, StoreFileAttachment $files): JsonResponse
    {
        $this->allow('breakdown.breakdown.create');

        $data = $request->validate([
            'asset_id' => ['required', 'string', 'size:26'],
            'problem_description' => ['required', 'string', 'max:5000'],
            'severity' => ['nullable', 'string', 'max:32'],
            'priority' => ['nullable', 'string', 'max:32'],
            'failure_at' => ['nullable', 'date'],
            'reported_at' => ['nullable', 'date'],
            'production_line_id' => ['nullable', 'string', 'size:26'],
            'failure_category_id' => ['nullable', 'string', 'size:26'],
            'failure_code_id' => ['nullable', 'string', 'size:26'],
            'downtime_reason_code_id' => ['nullable', 'string', 'size:26'],
            'production_order_reference' => ['nullable', 'string', 'max:255'],
            // A photo taken with the report, not uploaded separately — the
            // offline queue (frontend/src/lib/offline/queue.js) sends this
            // whole payload as one JSON body through `/api/offline-relay`,
            // so a photo attached at report time has to travel as base64
            // inside it rather than as a second, separately-retried
            // multipart request. `POST /breakdowns/{breakdown}/attachments`
            // (`BreakdownAttachmentApiController`) is the normal, online-only
            // path for anything added after the report exists.
            'photo_base64' => ['nullable', 'string'],
            'photo_filename' => ['nullable', 'string', 'max:255'],
        ]);

        // A caller sending a bare "2026-08-18T21:50" (no offset) means that
        // wall time on their own clock, not UTC — the same boundary
        // `ReportBreakdownRequest::payload()` (the web form's own request
        // class) applies via `ParsesLocalDateTimes` (SRS 47.2). An ISO
        // string that already carries an offset is left alone either way.
        foreach (['failure_at', 'reported_at'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = app(TenantTimezone::class)->parseFlexible($data[$field]);
            }
        }

        $breakdown = $action->handle($data, $this->caller()->auditUserId());

        if (! empty($data['photo_base64'])) {
            $this->attachPhoto($breakdown, $data['photo_base64'], $data['photo_filename'] ?? 'photo.jpg', $files);
        }

        return ApiResponse::created($this->detail($breakdown));
    }

    /**
     * Decodes the report form's inline photo and stores it the same way
     * `BreakdownAttachmentApiController::store()` does. The breakdown row
     * itself is already committed by the time this runs — a malformed or
     * over-size photo is logged and dropped rather than failing the whole
     * report, since a stopped machine with no photo is still a long way
     * better than a stopped machine with no report at all.
     */
    private function attachPhoto(Breakdown $breakdown, string $base64, string $filename, StoreFileAttachment $files): void
    {
        // A `data:image/jpeg;base64,...` URL, as `FileReader.readAsDataURL()`
        // produces client-side — strip the prefix if present, decode raw
        // base64 either way.
        if (str_starts_with($base64, 'data:') && str_contains($base64, ',')) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }

        $bytes = base64_decode($base64, true);

        if ($bytes === false) {
            Log::warning('Breakdown report photo was not valid base64; skipped.', ['breakdown_id' => $breakdown->id]);

            return;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'brk-photo-');
        file_put_contents($tmpPath, $bytes);

        try {
            $upload = new UploadedFile($tmpPath, $filename, mime_content_type($tmpPath) ?: null, null, true);
            $files->handle($upload, 'breakdown', $breakdown->id, $this->caller()->auditUserId());
        } catch (ValidationException $e) {
            Log::warning('Breakdown report photo was rejected.', [
                'breakdown_id' => $breakdown->id,
                'error' => $e->getMessage(),
            ]);
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Somebody has seen it (API 12).
     *
     * Acknowledgement is a named step rather than a status the client sets,
     * because it is what stops the escalation chain: a list being opened is
     * not the same as somebody taking responsibility for the machine.
     */
    public function acknowledge(Breakdown $breakdown, TransitionBreakdown $action): JsonResponse
    {
        return $this->step(
            $breakdown,
            'breakdown.breakdown.acknowledge',
            fn (string $userId) => $action->acknowledge($breakdown, $userId),
        );
    }

    public function assign(Request $request, Breakdown $breakdown, TransitionBreakdown $action): JsonResponse
    {
        $data = $request->validate([
            'technician_id' => ['required', 'string', 'size:26'],
        ]);

        return $this->step(
            $breakdown,
            'breakdown.breakdown.assign',
            fn (string $userId) => $action->assign($breakdown, $data['technician_id'], $userId),
        );
    }

    /**
     * The walk to the machine is response time; the work on it is repair
     * time (mirrors the web `BreakdownTransitionController::arrive`, which
     * this delegates to unchanged per ADR-003). A team that arrives fast
     * and repairs slowly has a different problem from one that does the
     * reverse, and one combined figure hides which.
     */
    public function arrive(Breakdown $breakdown, TransitionBreakdown $action): JsonResponse
    {
        return $this->step(
            $breakdown,
            'breakdown.breakdown.repair',
            fn (string $userId) => $action->recordArrival($breakdown, $userId),
        );
    }

    public function startRepair(Breakdown $breakdown, TransitionBreakdown $action): JsonResponse
    {
        return $this->step(
            $breakdown,
            'breakdown.breakdown.repair',
            fn (string $userId) => $action->startRepair($breakdown, $userId),
        );
    }

    public function completeRepair(Breakdown $breakdown, TransitionBreakdown $action): JsonResponse
    {
        return $this->step(
            $breakdown,
            'breakdown.breakdown.repair',
            fn (string $userId) => $action->completeRepair($breakdown, $userId),
        );
    }

    /**
     * Paused rather than progressing — waiting on parts, a vendor, an
     * approval, or simply the shift ending (mirrors the web
     * `BreakdownTransitionController::hold`, which this delegates to
     * unchanged per ADR-003). `hold_minutes` on the eventual downtime
     * record is what this exists to make honest: a repair paused for a
     * part to arrive is not the technician sitting idle.
     */
    public function hold(Request $request, Breakdown $breakdown, TransitionBreakdown $action): JsonResponse
    {
        $data = $request->validate([
            'reason_code' => ['required', Rule::in(Breakdown::HOLD_REASONS)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->step(
            $breakdown,
            'breakdown.breakdown.repair',
            fn (string $userId) => $action->hold($breakdown, $data['reason_code'], $userId, $data['notes'] ?? null),
        );
    }

    public function resume(Breakdown $breakdown, TransitionBreakdown $action): JsonResponse
    {
        return $this->step(
            $breakdown,
            'breakdown.breakdown.repair',
            fn (string $userId) => $action->resume($breakdown, $userId),
        );
    }

    /**
     * The machine is making product again.
     *
     * Kept separate from "the repair is finished" because the two are minutes
     * to hours apart, and downtime is measured to this one. A repair that ends
     * at 3am on a line that restarts at 6 cost the factory three more hours.
     */
    public function resumeProduction(Breakdown $breakdown, TransitionBreakdown $action): JsonResponse
    {
        return $this->step(
            $breakdown,
            'breakdown.breakdown.repair',
            fn (string $userId) => $action->resumeProduction($breakdown, $userId),
        );
    }

    /**
     * Closing needs a cause, not just a repair (ERD Section 10 rule 3;
     * mirrors the web `BreakdownTransitionController::close`, which this
     * delegates to unchanged per ADR-003).
     */
    public function close(Request $request, Breakdown $breakdown, TransitionBreakdown $action): JsonResponse
    {
        $this->allow('breakdown.breakdown.close');
        $this->assertReachable($breakdown);

        $data = $request->validate([
            'failure_code_id' => ['required', 'string', 'size:26'],
            'root_cause_id' => ['required', 'string', 'size:26'],
            'failure_category_id' => ['nullable', 'string', 'size:26'],
            'corrective_action' => ['nullable', 'string', 'max:5000'],
            'preventive_action' => ['nullable', 'string', 'max:5000'],
            'closure_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $userId = $this->caller()->auditUserId();

        if ($userId === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        try {
            $closed = $action->close($breakdown, $data, $userId);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        return ApiResponse::ok($this->detail($closed));
    }

    /**
     * The report itself was wrong — a false alarm, a duplicate, a machine
     * that turned out to be fine (mirrors the web `BreakdownTransition
     * Controller::cancel`, which this delegates to unchanged per ADR-003).
     * Gated the same as `close()`: cancelling is the other way a breakdown
     * stops being open, not a lesser action than closing it.
     */
    public function cancel(Request $request, Breakdown $breakdown, TransitionBreakdown $action): JsonResponse
    {
        $this->allow('breakdown.breakdown.close');
        $this->assertReachable($breakdown);

        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:255'],
        ]);

        $userId = $this->caller()->auditUserId();

        if ($userId === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        try {
            $cancelled = $action->cancel($breakdown, $data['cancellation_reason'], $userId);
        } catch (ValidationException $e) {
            $status = $e->status ?? 422;
            $code = $status === 409 ? ErrorCode::CONFLICT : ErrorCode::VALIDATION_ERROR;

            throw ApiException::of($code, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        return ApiResponse::ok($this->detail($cancelled));
    }

    /**
     * Raises an *additional* repair job against this breakdown (mirrors
     * the web `BreakdownTransitionController::raiseWorkOrder`, which this
     * delegates to unchanged per ADR-003). The first is already raised
     * automatically on assignment (`TransitionBreakdown::assign`) — this
     * is for the second pass a repair sometimes needs, not a replacement
     * for that. Gated on `work_order.work_order.create` rather than any
     * breakdown-specific permission, same as the web form: raising work is
     * a Work Order capability, not a Breakdown one.
     */
    public function raiseWorkOrder(Breakdown $breakdown, RaiseBreakdownWorkOrder $action): JsonResponse
    {
        $this->allow('work_order.work_order.create');
        $this->assertReachable($breakdown);

        $userId = $this->caller()->auditUserId();

        if ($userId === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        try {
            $workOrder = $action->handle($breakdown, $userId);
        } catch (ValidationException $e) {
            $status = $e->status ?? 422;
            $code = $status === 409 ? ErrorCode::CONFLICT : ErrorCode::VALIDATION_ERROR;

            throw ApiException::of($code, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        return ApiResponse::created($this->workOrderSummary($workOrder));
    }

    /**
     * One chain timestamp, corrected without changing status (mirrors the
     * web `correctTimestamp` — the only endpoint that mutates a breakdown
     * outside a transition or full closure, per ADR-003). Unlike the web
     * form, the value is a real timestamp already, not a factory-local
     * string with no offset — an API caller is expected to send one.
     */
    public function update(Request $request, Breakdown $breakdown, TransitionBreakdown $action): JsonResponse
    {
        $this->allow('breakdown.breakdown.repair');
        $this->assertReachable($breakdown);

        $data = $request->validate([
            'field' => ['required', Rule::in(Breakdown::TIMESTAMP_CHAIN)],
            'value' => ['required', 'date'],
        ]);

        $userId = $this->caller()->auditUserId();

        if ($userId === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        try {
            $updated = $action->correctTimestamp(
                $breakdown,
                $data['field'],
                CarbonImmutable::parse($data['value']),
                $userId,
            );
        } catch (ValidationException $e) {
            $status = $e->status ?? 422;
            $code = $status === 409 ? ErrorCode::CONFLICT : ErrorCode::VALIDATION_ERROR;

            throw ApiException::of($code, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        return ApiResponse::ok($this->detail($updated));
    }

    /**
     * The current downtime figures — the latest calculated version, the
     * same one `BreakdownController::show()` displays. Never edited in
     * place (SRS 17.3): a recalculation writes a new version, so a factory
     * manager can still reproduce last quarter's availability number even
     * after the rules change.
     */
    public function downtime(Breakdown $breakdown): JsonResponse
    {
        $this->allow('breakdown.breakdown.view');
        $this->assertReachable($breakdown);

        $record = $breakdown->currentDowntime();

        return ApiResponse::ok($record === null ? null : $this->downtimeSummary($record));
    }

    /**
     * The failure-analysis slice of this breakdown: what it was, why it
     * happened, and what was done about it. Not a lookup of every possible
     * root cause — that catalog belongs to Master Data, and this endpoint
     * answers about one breakdown, the same as `show()` narrowed to just
     * this part of it.
     */
    public function rootCause(Breakdown $breakdown): JsonResponse
    {
        $this->allow('breakdown.breakdown.view');
        $this->assertReachable($breakdown);

        $breakdown->load(['failureCategory:id,name', 'failureCode:id,name', 'rootCause:id,name']);

        return ApiResponse::ok([
            'failure_category' => $breakdown->failureCategory === null ? null : [
                'id' => $breakdown->failure_category_id, 'name' => $breakdown->failureCategory->name,
            ],
            'failure_code' => $breakdown->failureCode === null ? null : [
                'id' => $breakdown->failure_code_id, 'name' => $breakdown->failureCode->name,
            ],
            'root_cause' => $breakdown->rootCause === null ? null : [
                'id' => $breakdown->root_cause_id, 'name' => $breakdown->rootCause->name,
            ],
            'corrective_action' => $breakdown->corrective_action,
            'preventive_action' => $breakdown->preventive_action,
            'closure_notes' => $breakdown->closure_notes,
        ]);
    }

    /**
     * Runs one transition and answers with the record it produced.
     *
     * A machine caller has no user id, and the actions want one: a step taken
     * by an integration is recorded against the integration, which is why
     * every one of these needs a caller that can say who it is.
     */
    private function step(Breakdown $breakdown, string $permission, callable $transition): JsonResponse
    {
        $this->allow($permission);
        $this->assertReachable($breakdown);

        $userId = $this->caller()->auditUserId();

        if ($userId === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        return ApiResponse::ok($this->detail($transition($userId)));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Breakdown $breakdown): array
    {
        return [
            'id' => $breakdown->id,
            'breakdown_number' => $breakdown->breakdown_number,
            'status' => $breakdown->status,
            'priority' => $breakdown->priority,
            'severity' => $breakdown->severity,
            'asset' => [
                'id' => $breakdown->asset_id,
                'asset_code' => $breakdown->asset?->asset_code,
                'name' => $breakdown->asset?->name,
            ],
            'factory' => ['id' => $breakdown->factory_id, 'name' => $breakdown->factory?->name],
            // Echoed on every response, the same reason Asset and Work
            // Order do: a client's next PATCH/correction needs it without a
            // separate round trip.
            'version' => $breakdown->version,
            'reported_at' => $breakdown->reported_at?->toIso8601String(),
            'failure_at' => $breakdown->failure_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Breakdown $breakdown): array
    {
        return $this->summary($breakdown) + [
            'problem_description' => $breakdown->problem_description,
            'failure_category' => $breakdown->failureCategory?->name,
            'failure_code' => $breakdown->failureCode?->name,
            'root_cause' => $breakdown->rootCause?->name,
            'corrective_action' => $breakdown->corrective_action,
            'preventive_action' => $breakdown->preventive_action,
            'assigned_technician' => $breakdown->assignedTechnician?->name,
            'production_order_reference' => $breakdown->production_order_reference,
            // A second report against a machine already down is the same event,
            // and the client is told which one it was folded into rather than
            // being left to wonder why its number looks familiar.
            'is_recurrence_of' => $breakdown->is_recurrence_of_breakdown_id,
            'is_open' => $breakdown->isOpen(),
            // The seven-timestamp chain (SRS 17), keyed by field name so the
            // Timeline tab can render and correct each one without a
            // hardcoded list of its own duplicating `Breakdown::
            // TIMESTAMP_CHAIN` — mirrors the web `_chain.blade.php` partial.
            'timestamps' => collect(Breakdown::TIMESTAMP_CHAIN)
                ->mapWithKeys(fn (string $field) => [$field => $breakdown->{$field}?->toIso8601String()])
                ->all(),
            'is_terminal' => $breakdown->isTerminal(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function workOrderSummary(?WorkOrder $workOrder): ?array
    {
        if ($workOrder === null) {
            return null;
        }

        return [
            'id' => $workOrder->id,
            'work_order_number' => $workOrder->work_order_number,
            'status' => $workOrder->status,
        ];
    }

    private function assertReachable(Breakdown $breakdown): void
    {
        if (! $this->context->canAccessFactory((string) $breakdown->factory_id)) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function downtimeSummary(DowntimeRecord $record): array
    {
        return [
            'response_minutes' => $record->response_minutes,
            'repair_minutes' => $record->repair_minutes,
            'total_downtime_minutes' => $record->total_downtime_minutes,
            'hold_minutes' => $record->hold_minutes,
            'downtime_class' => $record->downtime_class,
            'counts_against_availability' => $record->counts_against_availability,
            'needs_review' => $record->needs_review,
            'calculation_version' => $record->calculation_version,
            'calculated_at' => $record->calculated_at?->toIso8601String(),
            'failure_at' => $record->failure_at?->toIso8601String(),
            'reported_at' => $record->reported_at?->toIso8601String(),
            'acknowledged_at' => $record->acknowledged_at?->toIso8601String(),
            'technician_arrival_at' => $record->technician_arrival_at?->toIso8601String(),
            'repair_started_at' => $record->repair_started_at?->toIso8601String(),
            'repair_completed_at' => $record->repair_completed_at?->toIso8601String(),
            'production_resumed_at' => $record->production_resumed_at?->toIso8601String(),
        ];
    }
}
