<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\StockClosing;
use App\Modules\Merchandise\Models\StockClosingException;
use App\Modules\Merchandise\Support\StockPeriods;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Trims & Accessories → the six closings (Purchased Fabric, Finished Fabric,
 * Trims Stock, Batch Fabric Stock, Grey and Finished Fabric Leftover;
 * AnnotechERP 06/06), as Brickland works them: Month Close checks the month
 * for documents still open; each run rebuilds that list (Show Exception) and
 * records when it was checked. With none, the month closes — by whom, when —
 * and from then on its documents are refused; with some, closing is refused.
 */
class StockClosingApiController extends ApiController
{
    /** Records and the Periods list: every month checked, newest first. */
    public function index(string $kind): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $this->assertKind($kind);
        $rows = StockClosing::query()->where('kind', $kind)->orderByDesc('period')->get();
        $users = User::query()->whereIn('id', $rows->pluck('closed_by')->filter())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(fn (StockClosing $c): array => [
            'period' => $c->period, 'label' => StockPeriods::label($c->period), 'checked_at' => $c->checked_at?->toIso8601String(), 'exceptions' => $c->exceptions,
            'closed_at' => $c->closed_at?->toIso8601String(), 'closed_by' => $c->closed_by ? ($users[$c->closed_by] ?? null) : null, 'remarks' => $c->remarks, 'status' => $c->status,
        ])->values()->all());
    }

    /** Show Exception: the documents the last Month Close found standing in the month's way. */
    public function exceptions(string $kind, string $period): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $this->assertKind($kind);
        $row = StockClosing::query()->where('kind', $kind)->where('period', $period)->firstOrFail();

        return ApiResponse::ok(StockClosingException::query()->where('closing_id', $row->id)->orderBy('sort_order')->get()
            ->map(fn (StockClosingException $e): array => ['document' => $e->document, 'doc_no' => $e->doc_no, 'doc_date' => $e->doc_date->toDateString(), 'status' => $e->status])->values()->all());
    }

    /** Month Close: rebuild the month's exceptions; close it when there are none, refuse when there are. */
    public function close(Request $request, string $kind): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $this->assertKind($kind);
        $data = $request->validate(['period' => ['required', 'string', 'max:10'], 'remarks' => ['nullable', 'string', 'max:2000']], [
            'period.required' => __('merchandise.acc_po.required', ['field' => __('merchandise.master.clo.f.period')]),
        ]);
        $period = StockPeriods::parse($data['period']);
        if ($period === null) {
            throw ValidationException::withMessages(['period' => __('merchandise.clo.period_format')]);
        }
        $row = StockClosing::query()->firstOrNew(['kind' => $kind, 'period' => $period]);
        if ($row->status === 'CLOSED') {
            throw ValidationException::withMessages(['period' => __('merchandise.clo.already_closed', ['period' => StockPeriods::label($period)])]);
        }
        $open = StockPeriods::openDocuments($kind, $period);
        DB::transaction(function () use ($row, $open, $data): void {
            $closed = $open === [];
            $row->fill([
                'status' => $closed ? 'CLOSED' : 'OPEN', 'checked_at' => now(), 'exceptions' => count($open), 'remarks' => trim((string) ($data['remarks'] ?? '')) ?: null,
                'closed_at' => $closed ? now() : null, 'closed_by' => $closed ? $this->caller()->auditUserId() : null,
            ])->save();
            StockClosingException::query()->where('closing_id', $row->id)->delete();
            foreach ($open as $i => $e) {
                StockClosingException::query()->create($e + ['closing_id' => $row->id, 'sort_order' => $i + 1]);
            }
        });
        if ($open !== []) {
            throw ValidationException::withMessages(['period' => trans_choice('merchandise.clo.refused', count($open), ['period' => StockPeriods::label($period), 'n' => count($open)])]);
        }

        return ApiResponse::ok(['period' => $period, 'label' => StockPeriods::label($period), 'status' => 'CLOSED']);
    }

    private function assertKind(string $kind): void
    {
        abort_unless(in_array($kind, StockPeriods::KINDS, true), 404);
    }
}
