<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Inventory\Models\Store;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\Prototype\Models\MaterialIssue;
use App\Modules\Prototype\Models\MaterialIssueLine;
use App\Modules\Prototype\Models\MaterialRequisition;
use App\Modules\Prototype\Models\MaterialRequisitionLine;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Support\Prototype;
use App\Modules\Prototype\Support\SampleLedger;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Prototype Desk → Prototype Material Requisition → Material Issue
 * (AnnotechERP 07/03): material released against an approved or partially
 * issued requisition (MI-2026-00001), line by line — never more than is
 * asked, nor than the store verified it holds. An issue is never edited or
 * removed; the requisition becomes Partially or Fully Issued accordingly.
 */
class MaterialIssueApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('prototype.material.view');
        $mrs = MaterialRequisition::query()->whereIn('state', MaterialRequisition::ISSUABLE)->orderByDesc('mr_no')->get();
        $samples = Sample::query()->whereIn('id', $mrs->pluck('sample_id'))->pluck('sample_no', 'id');
        $lines = MaterialRequisitionLine::query()->whereIn('requisition_id', $mrs->pluck('id'))->orderBy('sort_order')->get()->groupBy('requisition_id');
        $items = MaterialItem::query()->whereIn('id', $lines->flatten()->pluck('item_id'))->get(['id', 'item_code', 'name'])->keyBy('id');

        return ApiResponse::ok([
            'stores' => Store::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->all(),
            'requisitions' => $mrs->map(fn (MaterialRequisition $m): array => [
                'value' => $m->id, 'label' => "{$m->mr_no} — ".($samples[$m->sample_id] ?? '').' · '.__('prototype.mr_state.'.$m->state), 'store_id' => $m->requisition_to_id,
                // Each line labelled with what is still to issue on it.
                'lines' => ($lines[$m->id] ?? collect())->map(function (MaterialRequisitionLine $l) use ($m, $items): array {
                    $item = ($i = $items[$l->item_id] ?? null) ? "{$i->name} ({$i->item_code})" : '';
                    $balance = MaterialRequisitionApiController::balance($l);

                    return ['value' => $l->id, 'balance' => $balance, 'unit' => $l->unit,
                        'label' => implode(' · ', array_filter([$m->mr_no, $item, $l->color, 'bal '.rtrim(rtrim(number_format($balance, 4, '.', ''), '0'), '.').($l->unit ? " {$l->unit}" : '')]))];
                })->values()->all(),
            ])->all(),
        ]);
    }

    public function index(): JsonResponse
    {
        $this->allow('prototype.material.view');

        return ApiResponse::ok($this->present(MaterialIssue::query()->orderByDesc('issue_date')->orderByDesc('issue_no')->get()));
    }

    public function show(string $issue): JsonResponse
    {
        $this->allow('prototype.material.view');

        return ApiResponse::ok($this->present(collect([MaterialIssue::query()->where('issue_no', $issue)->firstOrFail()]), true)[0]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('prototype.material.issue');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'requisition_id' => ['required', Rule::exists('proto_material_requisitions', 'id')->where('company_id', $company)],
            'issue_date' => ['required', 'date'],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $company)],
            'received_by' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:4000'],
            'lines' => ['nullable', 'array'],
            'lines.*.line_id' => ['required', Rule::exists('proto_mr_lines', 'id')->where('company_id', $company)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:500'],
        ], [
            'requisition_id.required' => __('prototype.issue.requisition_required'), 'issue_date.required' => __('prototype.issue.date_required'),
            'lines.*.line_id.required' => __('prototype.issue.line_required'), 'lines.*.qty.required' => __('prototype.issue.qty_required'), 'lines.*.qty.gt' => __('prototype.issue.qty_required'),
        ]);
        $lines = array_values($data['lines'] ?? []);
        $mr = MaterialRequisition::query()->findOrFail($data['requisition_id']);
        if (! in_array($mr->state, MaterialRequisition::ISSUABLE, true)) {
            throw ValidationException::withMessages(['requisition_id' => __('prototype.issue.not_issuable')]);
        }
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('prototype.issue.lines_required')]);
        }

        $issue = DB::transaction(function () use ($data, $lines, $mr): MaterialIssue {
            $own = MaterialRequisitionLine::query()->where('requisition_id', $mr->id)->lockForUpdate()->get()->keyBy('id');
            $asked = [];
            foreach ($lines as $i => $line) {
                $target = $own[$line['line_id']] ?? null;
                if (! $target) {
                    throw ValidationException::withMessages(['lines' => __('prototype.issue.line_other', ['row' => $i + 1])]);
                }
                $asked[$target->id] = ($asked[$target->id] ?? 0) + (float) $line['qty'];
                $left = MaterialRequisitionApiController::balance($target);
                if ($asked[$target->id] > $left + 0.00001) {
                    throw ValidationException::withMessages(['lines' => __('prototype.issue.over', ['row' => $i + 1, 'left' => rtrim(rtrim(number_format($left, 4, '.', ''), '0'), '.'), 'unit' => $target->unit])]);
                }
            }
            $issue = MaterialIssue::query()->create([
                'issue_no' => Prototype::nextNumber('MI', 'proto_material_issues', 'issue_no', $data['issue_date']), 'requisition_id' => $mr->id, 'issue_date' => $data['issue_date'],
                'store_id' => $data['store_id'] ?? $mr->requisition_to_id, 'received_by' => $data['received_by'] ?? null, 'remarks' => $data['remarks'] ?? null, 'issued_by' => $this->caller()->auditUserId(),
            ]);
            foreach ($lines as $i => $line) {
                MaterialIssueLine::query()->create(['issue_id' => $issue->id, 'line_id' => $line['line_id'], 'qty' => $line['qty'], 'remarks' => $line['remarks'] ?? null, 'sort_order' => $i + 1]);
                $own[$line['line_id']]->increment('issued_qty', (float) $line['qty']);
            }
            // Fully issued once every line has released all it can.
            $done = $own->every(fn (MaterialRequisitionLine $l) => MaterialRequisitionApiController::balance($l->fresh()) <= 0.00001);
            $mr->update(['state' => $done ? 'FULLY_ISSUED' : 'PARTIALLY_ISSUED']);
            SampleLedger::log($mr->sample_id, 'material_issued', ['issue' => $issue->issue_no, 'mr' => $mr->mr_no, 'lines' => count($lines)], $issue->issue_no);
            SampleLedger::raise('MATERIAL_ISSUED', 'SUCCESS', $mr->sample_id, 'SAMPLE_ROOM', $issue->issue_no, ['issue' => $issue->issue_no, 'mr' => $mr->mr_no]);
            if ($done) {
                SampleLedger::resolve(['MATERIAL_REQUIRED', 'MATERIAL_SHORTAGE'], $mr->mr_no);
            }

            return $issue;
        });

        return ApiResponse::created($this->present(collect([$issue->fresh()]), true)[0]);
    }

    /** @return list<array<string, mixed>> */
    private function present(Collection $rows, bool $detail = false): array
    {
        $mrs = MaterialRequisition::query()->whereIn('id', $rows->pluck('requisition_id'))->get(['id', 'mr_no', 'sample_id'])->keyBy('id');
        $samples = Sample::query()->whereIn('id', $mrs->pluck('sample_id'))->pluck('sample_no', 'id');
        $stores = Store::query()->whereIn('id', $rows->pluck('store_id')->filter())->get(['id', 'name', 'code'])->keyBy('id');
        $issueLines = MaterialIssueLine::query()->whereIn('issue_id', $rows->pluck('id'))->orderBy('sort_order')->get()->groupBy('issue_id');
        $reqLines = MaterialRequisitionLine::query()->whereIn('id', $issueLines->flatten()->pluck('line_id'))->get()->keyBy('id');
        $items = MaterialItem::query()->whereIn('id', $reqLines->pluck('item_id'))->get(['id', 'item_code', 'name'])->keyBy('id');
        $names = Prototype::names($rows->pluck('issued_by')->all());

        return $rows->map(function (MaterialIssue $r) use ($mrs, $samples, $stores, $issueLines, $reqLines, $items, $names, $detail): array {
            $list = $issueLines[$r->id] ?? collect();
            $mr = $mrs[$r->requisition_id] ?? null;
            $out = [
                'id' => $r->id, 'issue_no' => $r->issue_no, 'issue_date' => $r->issue_date->toDateString(), 'requisition_id' => $r->requisition_id, 'mr_no' => $mr?->mr_no,
                'sample_no' => $mr ? ($samples[$mr->sample_id] ?? null) : null, 'store_id' => $r->store_id, 'store' => ($s = $stores[$r->store_id] ?? null) ? "{$s->name} ({$s->code})" : null,
                'received_by' => $r->received_by, 'remarks' => $r->remarks, 'issued_by_name' => $names[$r->issued_by] ?? null,
                'lines_count' => $list->count(), 'total_qty' => round((float) $list->sum(fn ($l) => (float) $l->qty), 4),
            ];
            if ($detail) {
                $out['lines'] = $list->map(function (MaterialIssueLine $l) use ($reqLines, $items, $mr): array {
                    $rl = $reqLines[$l->line_id] ?? null;
                    $item = $rl && ($i = $items[$rl->item_id] ?? null) ? "{$i->name} ({$i->item_code})" : null;

                    return ['line_id' => $l->line_id, 'qty' => (float) $l->qty, 'remarks' => $l->remarks, 'unit' => $rl?->unit,
                        'label' => implode(' · ', array_filter([$mr?->mr_no, $item, $rl?->color]))];
                })->values()->all();
            }

            return $out;
        })->values()->all();
    }
}
