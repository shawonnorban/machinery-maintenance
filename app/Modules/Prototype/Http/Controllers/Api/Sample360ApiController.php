<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Inventory\Models\Store;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Prototype\Models\Bundle;
use App\Modules\Prototype\Models\BundleCard;
use App\Modules\Prototype\Models\BundleMovement;
use App\Modules\Prototype\Models\MaterialIssue;
use App\Modules\Prototype\Models\MaterialIssueLine;
use App\Modules\Prototype\Models\MaterialRequisition;
use App\Modules\Prototype\Models\MaterialRequisitionLine;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleAlert;
use App\Modules\Prototype\Models\SampleApproval;
use App\Modules\Prototype\Models\SampleDaySheet;
use App\Modules\Prototype\Models\SampleDaySheetLine;
use App\Modules\Prototype\Models\SampleDevelopment;
use App\Modules\Prototype\Models\SampleDevelopmentTask;
use App\Modules\Prototype\Models\SampleDocument;
use App\Modules\Prototype\Models\SampleEvent;
use App\Modules\Prototype\Models\SampleFeedback;
use App\Modules\Prototype\Models\SampleHandover;
use App\Modules\Prototype\Models\SampleQualityCheck;
use App\Modules\Prototype\Models\SampleRequisition;
use App\Modules\Prototype\Models\SampleRequisitionDecision;
use App\Modules\Prototype\Models\SampleRevision;
use App\Modules\Prototype\Support\Prototype;
use App\Modules\Prototype\Support\SampleLifecycle;
use App\Modules\Prototype\Support\SampleWording;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Prototype Desk → Sample 360° View (AnnotechERP 07/02): the Sample Register
 * — every sample with where it stands along the lifecycle, how far it has
 * come, how late it is, who has it and its flags — and one sample end to
 * end: requisition and its approvals, development and revisions, material
 * asked and issued, bundles and the day's targets, tracking, QC, approvals,
 * feedback, handover, documents, alerts, its timeline and audit trail.
 */
class Sample360ApiController extends ApiController
{
    /** Sample Register. */
    public function index(): JsonResponse
    {
        $this->allow('prototype.sample360.view');
        $samples = Sample::query()->orderByDesc('sample_no')->get();
        $reqs = SampleRequisition::query()->whereIn('id', $samples->pluck('requisition_id'))->get()->keyBy('id');
        $life = SampleLifecycle::of($samples);
        $styles = Style::query()->whereIn('id', $reqs->pluck('style_id'))->get(['id', 'style_ref', 'style_ext', 'description'])->keyBy('id');
        $buyers = Buyer::query()->whereIn('id', $reqs->pluck('buyer_id'))->pluck('short_name', 'id');
        $orders = Order::query()->whereIn('id', $reqs->pluck('order_id')->filter())->pluck('order_no', 'id');
        $types = SampleType::query()->whereIn('id', $reqs->pluck('sample_type_id'))->pluck('name', 'id');
        $names = Prototype::names([...$samples->pluck('assigned_to'), ...$reqs->pluck('requested_by')]);
        // What the search box also finds a sample by: its bundles, material requisitions and handovers.
        $docs = collect()
            ->merge(Bundle::query()->join('proto_bundle_cards', 'proto_bundle_cards.id', '=', 'proto_bundles.card_id')->whereIn('proto_bundle_cards.sample_id', $samples->pluck('id'))->get(['proto_bundle_cards.sample_id', 'proto_bundles.bundle_no as no', 'proto_bundle_cards.card_no as card']))
            ->merge(MaterialRequisition::query()->whereIn('sample_id', $samples->pluck('id'))->get(['sample_id', 'mr_no as no']))
            ->merge(SampleHandover::query()->whereIn('sample_id', $samples->pluck('id'))->get(['sample_id', 'handover_no as no']))
            ->groupBy('sample_id');
        $me = $this->caller()->auditUserId();

        return ApiResponse::ok($samples->map(function (Sample $s) use ($reqs, $life, $styles, $buyers, $orders, $types, $names, $docs, $me): array {
            $r = $reqs[$s->requisition_id] ?? null;
            $style = $r ? ($styles[$r->style_id] ?? null) : null;
            $l = $life[$s->id];
            $search = ($docs[$s->id] ?? collect())->flatMap(fn ($d) => array_filter([$d->no, $d->card ?? null]))->unique()->values()->all();

            return [
                'sample_no' => $s->sample_no, 'req_no' => $r?->req_no, 'version' => $s->version, 'style_name' => $style?->description,
                'style' => $style ? trim($style->style_ref.($style->style_ext ? "-{$style->style_ext}" : '')) : null, 'buyer' => $r ? ($buyers[$r->buyer_id] ?? null) : null,
                'order_no' => $r?->order_id ? ($orders[$r->order_id] ?? null) : null, 'sample_type_id' => $r?->sample_type_id, 'sample_type' => $r ? ($types[$r->sample_type_id] ?? null) : null,
                'req_month' => $r?->req_date?->format('Y-m'), 'responsible' => $names[$s->assigned_to] ?? ($r ? ($names[$r->requested_by] ?? null) : null),
                'mine' => $me !== null && ($s->assigned_to === $me || $r?->requested_by === $me), 'docs' => $search,
            ] + $l;
        })->values()->all());
    }

    /** One sample, end to end. */
    public function show(string $sample): JsonResponse
    {
        $this->allow('prototype.sample360.view');
        $s = Sample::query()->where('sample_no', $sample)->firstOrFail();
        $r = SampleRequisition::query()->findOrFail($s->requisition_id);
        $life = SampleLifecycle::one($s);
        $style = Style::query()->find($r->style_id);
        $devs = SampleDevelopment::query()->where('sample_id', $s->id)->orderBy('version')->orderBy('dev_no')->get();
        $latestDev = $devs->last();
        $mrs = MaterialRequisition::query()->where('sample_id', $s->id)->orderBy('mr_no')->get();
        $cards = BundleCard::query()->where('sample_id', $s->id)->orderBy('card_no')->get();
        $bundles = Bundle::query()->whereIn('card_id', $cards->pluck('id'))->orderBy('bundle_no')->get();
        $userIds = [$r->requested_by, $s->assigned_to];
        $names = fn (array $ids) => Prototype::names($ids);
        $events = SampleEvent::query()->where('sample_id', $s->id)->orderBy('happened_at')->orderBy('id')->get();

        $out = [
            'sample_no' => $s->sample_no, 'version' => $s->version, 'company' => Company::query()->whereKey(app(TenantContext::class)->companyId())->value('name'),
            'buyer' => Buyer::query()->whereKey($r->buyer_id)->value('short_name'), 'buying_house' => $r->buyer_office_id ? BuyerOffice::query()->whereKey($r->buyer_office_id)->value('name') : null,
            'order_no' => $r->order_id ? Order::query()->whereKey($r->order_id)->value('order_no') : null,
            'style' => $style ? trim(($style->description ?? '').' — '.trim($style->style_ref.($style->style_ext ? "-{$style->style_ext}" : '')), ' —') : null, 'season' => $r->season ?? $style?->season,
            'sample_type' => SampleType::query()->whereKey($r->sample_type_id)->value('name'), 'sample_stage' => $r->sample_stage, 'qty' => $r->qty, 'priority' => $r->priority,
            'req_no' => $r->req_no, 'req_date' => $r->req_date?->toDateString(), 'requested_by' => $names($userIds)[$r->requested_by] ?? null,
            'responsible' => $names($userIds)[$s->assigned_to] ?? ($names($userIds)[$r->requested_by] ?? null),
            'development_progress' => $latestDev ? (float) $latestDev->progress : null,
        ] + $life;

        // Timeline, oldest first; the audit trail is the same, newest first, with what each event changed.
        $prev = null;
        $out['timeline'] = $events->map(function (SampleEvent $e) use (&$prev): array {
            $now = $e->stage ? "{$e->stage}|{$e->status}" : null;
            $row = ['at' => $e->happened_at->toIso8601String(), 'stage' => $e->stage, 'action' => $e->action, ...SampleWording::event($e), 'doc_no' => $e->doc_no, 'by' => $e->by_name,
                'from' => $prev !== null && $now !== null && $prev !== $now ? $prev : null, 'to' => $prev !== null && $now !== null && $prev !== $now ? $now : null];
            // Flagged overdue changes no stage, only whether the sample is on time.
            if ($e->action === 'sample_overdue') {
                [$row['from'], $row['to']] = ['ON_TIME', 'OVERDUE'];
            }
            $prev = $now ?? $prev;

            return $row;
        })->values()->all();
        $out['audit'] = array_reverse($out['timeline']);

        $decisions = SampleRequisitionDecision::query()->where('requisition_id', $r->id)->orderBy('decided_at')->get();
        $out['decisions'] = $decisions->map(fn ($d) => ['level' => $d->level, 'decision' => $d->decision, 'by' => $names($decisions->pluck('decided_by')->all())[$d->decided_by] ?? null, 'at' => $d->decided_at?->toIso8601String(), 'comments' => $d->comments ?? $d->rejection_reason])->values()->all();
        $out['checklist'] = $latestDev ? SampleDevelopmentTask::query()->where('development_id', $latestDev->id)->orderBy('sort_order')->get()->map(fn ($t) => ['task' => $t->task, 'status' => $t->status, 'completed_at' => $t->completed_at?->toIso8601String(), 'remarks' => $t->remarks])->values()->all() : [];
        $out['developments'] = $devs->map(fn ($d) => ['dev_no' => $d->dev_no, 'version' => $d->version, 'status' => $d->status, 'progress' => (float) $d->progress, 'start' => $d->start_date?->toDateString(), 'expected' => $d->expected_completion?->toDateString(), 'completed' => $d->actual_completion?->toDateString()])->values()->all();
        $out['revisions'] = SampleRevision::query()->where('sample_id', $s->id)->orderBy('new_version')->get()->map(fn ($v) => ['rev_no' => $v->rev_no, 'date' => $v->revision_date->toDateString(), 'from' => $v->previous_version, 'to' => $v->new_version, 'reason' => $v->reason, 'status' => $v->status, 'approved' => $v->approved_at !== null])->values()->all();

        $lines = MaterialRequisitionLine::query()->whereIn('requisition_id', $mrs->pluck('id'))->orderBy('sort_order')->get();
        $items = MaterialItem::query()->whereIn('id', $lines->pluck('item_id'))->pluck('name', 'id');
        $out['material'] = $lines->map(function ($l) use ($mrs, $items): array {
            $mr = $mrs->firstWhere('id', $l->requisition_id);

            return ['mr_no' => $mr->mr_no, 'mr_date' => $mr->req_date->toDateString(), 'material' => trim(($l->material_group ? __('prototype.material_group.'.$l->material_group).' — ' : '').($items[$l->item_id] ?? '').($l->color ? " · {$l->color}" : '')),
                'required' => (float) $l->req_qty, 'available' => $l->available_qty === null ? null : (float) $l->available_qty, 'issued' => (float) $l->issued_qty,
                'balance' => MaterialRequisitionApiController::balance($l), 'short' => $l->available_qty === null ? null : max(0.0, (float) $l->req_qty - (float) $l->available_qty), 'unit' => $l->unit, 'state' => $mr->state];
        })->values()->all();
        $issues = MaterialIssue::query()->whereIn('requisition_id', $mrs->pluck('id'))->orderBy('issue_no')->get();
        $issueLines = MaterialIssueLine::query()->whereIn('issue_id', $issues->pluck('id'))->get()->groupBy('issue_id');
        $stores = Store::query()->whereIn('id', $issues->pluck('store_id')->filter())->pluck('name', 'id');
        $out['issues'] = $issues->map(fn ($i) => ['issue_no' => $i->issue_no, 'date' => $i->issue_date->toDateString(), 'mr_no' => $mrs->firstWhere('id', $i->requisition_id)?->mr_no, 'store' => $stores[$i->store_id] ?? null,
            'lines' => ($issueLines[$i->id] ?? collect())->count(), 'qty' => (float) ($issueLines[$i->id] ?? collect())->sum(fn ($x) => (float) $x->qty), 'issued_by' => $names([$i->issued_by])[$i->issued_by] ?? null, 'received_by' => $i->received_by])->values()->all();

        $out['bundles'] = $bundles->map(fn ($b) => ['card_no' => $cards->firstWhere('id', $b->card_id)?->card_no, 'bundle_no' => $b->bundle_no, 'color' => $b->color, 'size' => $b->size,
            'req_qty' => $b->req_qty, 'cutting_qty' => $b->cutting_qty, 'now_at' => $b->now_at, 'bundle_status' => $cards->firstWhere('id', $b->card_id)?->bundle_status])->values()->all();
        $dayLines = SampleDaySheetLine::query()->where('sample_id', $s->id)->get();
        $sheets = SampleDaySheet::query()->whereIn('id', $dayLines->pluck('day_sheet_id'))->pluck('target_date', 'id');
        $planLines = PlanningLine::query()->whereIn('id', $dayLines->pluck('planning_line_id'))->pluck('code', 'id');
        $out['day_lines'] = $dayLines->map(fn ($l) => ['date' => $sheets[$l->day_sheet_id]?->toDateString(), 'line' => $planLines[$l->planning_line_id] ?? null, 'target' => $l->target, 'achieved' => (int) $l->actual,
            'manpower' => $l->manpower, 'wh' => $l->wh === null ? null : (float) $l->wh, 'target_hrs' => (float) $l->target_hrs, 'actual_hrs' => (float) $l->actual_hrs])->sortBy('date')->values()->all();
        $moves = BundleMovement::query()->whereIn('card_id', $cards->pluck('id'))->orderBy('scanned_at')->get();
        $out['tracking'] = $moves->map(fn ($m) => ['at' => $m->scanned_at->toIso8601String(), 'process' => $m->process, 'bundle_no' => $bundles->firstWhere('id', $m->bundle_id)?->bundle_no, 'qty' => $m->qty, 'status' => $m->status, 'delay_hours' => $m->delayHours()])->values()->all();
        $out['qc'] = SampleQualityCheck::query()->where('sample_id', $s->id)->orderBy('check_date')->get()->map(fn ($q) => ['qc_no' => $q->qc_no, 'date' => $q->check_date->toDateString(), 'version' => $q->version, 'result' => $q->result, 'severity' => $q->severity, 'defect' => $q->defect])->values()->all();
        $out['approvals'] = SampleApproval::query()->where('sample_id', $s->id)->orderBy('approval_no')->get()->map(fn ($a) => ['approval_no' => $a->approval_no, 'version' => $a->version, 'step' => $a->step, 'submitted' => $a->submitted_date->toDateString(), 'decision' => $a->decision, 'approval_date' => $a->approval_date?->toDateString(), 'by' => $a->approval_by])->values()->all();
        $out['feedback'] = SampleFeedback::query()->where('sample_id', $s->id)->orderBy('feedback_no')->get()->map(fn ($f) => ['feedback_no' => $f->feedback_no, 'date' => $f->feedback_date->toDateString(), 'type' => $f->feedback_type, 'status' => $f->status, 'comment' => $f->comment, 'due' => $f->due_date?->toDateString()])->values()->all();
        $out['handovers'] = SampleHandover::query()->where('sample_id', $s->id)->orderBy('handover_no')->get()->map(fn ($h) => ['handover_no' => $h->handover_no, 'date' => $h->handover_date->toDateString(), 'qty' => $h->qty, 'to' => $h->handover_to, 'status' => $h->status, 'courier' => trim(($h->courier ?? '').($h->tracking_no ? " / {$h->tracking_no}" : '')), 'received_by' => $h->received_by])->values()->all();
        $out['documents'] = SampleDocument::query()->where('sample_id', $s->id)->orderBy('doc_type')->orderByDesc('version')->get()->map(fn ($d) => ['doc_no' => $d->doc_no, 'type' => $d->doc_type, 'version' => $d->version, 'title' => $d->title, 'file_id' => $d->file_id, 'file' => $d->file, 'file_name' => $d->file_name, 'at' => $d->created_at->toIso8601String()])->values()->all();
        $out['alerts'] = collect(SampleAlertApiController::present(SampleAlert::query()->where('sample_id', $s->id)->orderByDesc('raised_at')->get()))->values()->all();

        return ApiResponse::ok($out);
    }
}
