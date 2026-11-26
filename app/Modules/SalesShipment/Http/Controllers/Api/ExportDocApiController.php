<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Http\Controllers\Api;

use App\Modules\SalesShipment\Models\SsCertificate;
use App\Modules\SalesShipment\Models\SsExportDoc;
use App\Modules\SalesShipment\Models\SsShipment;
use App\Modules\SalesShipment\Models\SsShipmentAttachment;
use App\Modules\SalesShipment\Support\SalesBook;
use App\Modules\SalesShipment\Support\ShipmentBook;
use App\Modules\SalesShipment\Support\SsSettings;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sales & Shipment → Export Documentation (AnnotechERP 21/06): the export
 * documents filed on a shipment — EXP / Export Documents, COO / Certificate
 * of Origin, GSP / REX and Bill of Lading / AWB, one register told apart by
 * family (number unique per document type; pending → received → submitted
 * → approved, or cancelled; the expiry warned inside the settings' window)
 * — Certificate Management (typed from the Certificate Master, validity
 * worked out from the expiry) and Shipment Attachments (scans by document
 * type, PDF or image up to 5 MB). A closed or cancelled shipment takes no
 * further papers; every document change re-reads the shipment's readiness.
 */
class ExportDocApiController extends ApiController
{
    private ?SalesBook $book = null;

    public function options(): JsonResponse
    {
        $this->allow('salesshipment.exportdoc.view');
        $names = $this->book()->customerNames();

        return ApiResponse::ok([
            'shipments' => DB::table('ss_shipments as s')->join('ss_sales_orders as o', 'o.id', '=', 's.order_id')->where('s.company_id', $this->caller()->companyId)->whereNotIn('s.stage', ['CANCELLED'])
                ->orderByDesc('s.shipment_no')->get(['s.shipment_no', 's.stage', 'o.so_no', 'o.customer_id'])
                ->map(fn ($s) => ['shipment_no' => $s->shipment_no, 'so_no' => $s->so_no, 'customer' => $names[$s->customer_id] ?? '—', 'stage' => $s->stage, 'open' => $s->stage !== 'CLOSED'])->values()->all(),
            'certificate_types' => DB::table('company_documents')->where('company_id', $this->caller()->companyId)->where('status', 'ACTIVE')->orderBy('title')->get(['id', 'title', 'document_no', 'issued_by'])
                ->map(fn ($d) => ['value' => $d->id, 'label' => $d->title.($d->document_no ? ' ('.$d->document_no.')' : ''), 'title' => $d->title, 'authority' => $d->issued_by])->values()->all(),
            'families' => SsExportDoc::FAMILIES, 'attachment_types' => SsShipmentAttachment::TYPES, 'alert_days' => (int) SsSettings::get()->cert_alert_days,
        ]);
    }

    /* ── Export documents ──────────────────────────────────────────── */

    public function docs(string $family): JsonResponse
    {
        $this->allow('salesshipment.exportdoc.view');
        abort_unless(isset(SsExportDoc::FAMILIES[strtoupper($family)]), 404);
        $rows = SsExportDoc::query()->where('family', strtoupper($family))->orderByDesc('document_date')->orderByDesc('created_at')->get();
        $ships = $this->shipments($rows->pluck('shipment_id')->all());
        $names = $this->book()->customerNames();

        return ApiResponse::ok($rows->map(fn (SsExportDoc $d) => [
            'id' => $d->id, 'doc_type' => $d->doc_type, 'document_no' => $d->document_no, 'shipment_no' => $ships[$d->shipment_id]->shipment_no ?? '—', 'buyer' => $names[$ships[$d->shipment_id]->customer_id ?? ''] ?? '—',
            'document_date' => $d->document_date->toDateString(), 'issue_date' => $d->issue_date?->toDateString(), 'expiry_date' => $d->expiry_date?->toDateString(), ...$this->expiry($d->expiry_date, $d->status === 'CANCELLED'),
            'issuing_authority' => $d->issuing_authority, 'status' => $d->status, 'file_id' => $d->file_id, 'attachment' => $d->file_name, 'remarks' => $d->remarks,
            'locked' => ($ships[$d->shipment_id]->stage ?? '') === 'CLOSED',
        ])->values()->all());
    }

    public function saveDoc(Request $request, string $family, ?string $id = null): JsonResponse
    {
        $this->allow('salesshipment.exportdoc.manage');
        $family = strtoupper($family);
        abort_unless(isset(SsExportDoc::FAMILIES[$family]), 404);
        $row = $id ? SsExportDoc::query()->where('family', $family)->findOrFail($id) : null;
        $data = $request->validate([
            'shipment_no' => ['required', 'string', 'max:24'], 'doc_type' => ['required', Rule::in(SsExportDoc::FAMILIES[$family])], 'document_no' => ['required', 'string', 'max:60'],
            'document_date' => ['required', 'date'], 'issue_date' => ['nullable', 'date'], 'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'issuing_authority' => ['nullable', 'string', 'max:160'], 'status' => ['nullable', Rule::in(SsExportDoc::STATUSES)], 'remarks' => ['nullable', 'string', 'max:2000'],
        ], ['expiry_date.after_or_equal' => __('salesshipment.v.ed_expiry'), 'document_no.required' => __('salesshipment.v.ed_no')]);
        $s = $this->liveShipment($data['shipment_no']);
        if ($row) {
            $this->liveShipmentById($row->shipment_id);
        }
        $dup = SsExportDoc::query()->where('doc_type', $data['doc_type'])->where('document_no', $data['document_no'])->when($row, fn ($q) => $q->where('id', '!=', $row->id))->exists();
        if ($dup) {
            throw ValidationException::withMessages(['document_no' => __('salesshipment.v.ed_twice', ['no' => $data['document_no'], 'type' => __('salesshipment.ed.type.'.$data['doc_type'])])]);
        }
        $this->book()->assertOpen($data['document_date'], 'document_date');
        $head = ['shipment_id' => $s->id, 'family' => $family, ...collect($data)->except(['shipment_no'])->all(), 'status' => $data['status'] ?? ($row?->status ?? 'PENDING')];
        $row = DB::transaction(function () use ($row, $head, $s) {
            $old = $row?->shipment_id;
            $row ? $row->update($head) : $row = SsExportDoc::query()->create($head + ['created_by' => $this->caller()->auditUserId()]);
            ShipmentBook::restage($s->refresh());
            if ($old && $old !== $s->id) {
                ShipmentBook::restage(SsShipment::query()->findOrFail($old));
            }

            return $row;
        });

        return ApiResponse::ok(['id' => $row->id, 'document_no' => $row->document_no, 'status' => $row->status, 'stage' => $s->refresh()->stage], [], $id ? 200 : 201);
    }

    public function docAct(string $id, string $action): JsonResponse
    {
        $from = ['received' => [['PENDING'], 'RECEIVED'], 'submitted' => [['PENDING', 'RECEIVED'], 'SUBMITTED'], 'approved' => [['SUBMITTED'], 'APPROVED']];
        abort_unless(isset($from[$action]), 404);
        $this->allow('salesshipment.exportdoc.manage');
        $row = DB::transaction(function () use ($id, $action, $from) {
            $row = SsExportDoc::query()->lockForUpdate()->findOrFail($id);
            if (! in_array($row->status, $from[$action][0], true)) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.step', ['no' => $row->document_no, 'status' => __('salesshipment.ed.status.'.$row->status)])]);
            }
            $s = $this->liveShipmentById($row->shipment_id);
            $row->update(['status' => $from[$action][1]]);
            ShipmentBook::restage($s);

            return $row;
        });

        return ApiResponse::ok(['id' => $row->id, 'status' => $row->status]);
    }

    public function docDestroy(string $id): JsonResponse
    {
        $this->allow('salesshipment.exportdoc.manage');
        $row = SsExportDoc::query()->findOrFail($id);
        $s = $this->liveShipmentById($row->shipment_id);
        $row->delete();
        ShipmentBook::restage($s);

        return ApiResponse::ok(['deleted' => $row->document_no]);
    }

    /* ── Certificates ──────────────────────────────────────────────── */

    public function certificates(): JsonResponse
    {
        $this->allow('salesshipment.exportdoc.view');
        $rows = SsCertificate::query()->orderByDesc('issue_date')->orderByDesc('created_at')->get();
        $ships = $this->shipments($rows->pluck('shipment_id')->all());
        $names = $this->book()->customerNames();
        $types = DB::table('company_documents')->whereIn('id', $rows->pluck('certificate_type_id')->filter())->pluck('title', 'id');

        return ApiResponse::ok($rows->map(fn (SsCertificate $c) => [
            'id' => $c->id, 'certificate_no' => $c->certificate_no, 'certificate_name' => $c->certificate_name, 'certificate_type_id' => $c->certificate_type_id,
            'type' => $c->certificate_type_id ? ($types[$c->certificate_type_id] ?? null) : null, 'shipment_no' => $ships[$c->shipment_id]->shipment_no ?? '—',
            'buyer' => $names[$ships[$c->shipment_id]->customer_id ?? ''] ?? '—', 'so_no' => $ships[$c->shipment_id]->so_no ?? '—', 'issue_date' => $c->issue_date->toDateString(),
            'expiry_date' => $c->expiry_date?->toDateString(), ...$this->expiry($c->expiry_date, $c->status === 'CANCELLED'), 'issuing_authority' => $c->issuing_authority, 'status' => $c->status,
            'file_id' => $c->file_id, 'attachment' => $c->file_name, 'remarks' => $c->remarks, 'locked' => ($ships[$c->shipment_id]->stage ?? '') === 'CLOSED',
        ])->values()->all());
    }

    public function saveCertificate(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('salesshipment.exportdoc.manage');
        $row = $id ? SsCertificate::query()->findOrFail($id) : null;
        $data = $request->validate([
            'certificate_type_id' => ['nullable', Rule::exists('company_documents', 'id')->where('company_id', $this->caller()->companyId)], 'certificate_name' => ['nullable', 'string', 'max:160'],
            'certificate_no' => ['required', 'string', 'max:60'], 'shipment_no' => ['required', 'string', 'max:24'], 'issue_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'], 'issuing_authority' => ['nullable', 'string', 'max:160'], 'status' => ['nullable', Rule::in(SsCertificate::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], ['expiry_date.after_or_equal' => __('salesshipment.v.ed_expiry'), 'certificate_no.required' => __('salesshipment.v.ce_no')]);
        $s = $this->liveShipment($data['shipment_no']);
        if ($row) {
            $this->liveShipmentById($row->shipment_id);
        }
        if (SsCertificate::query()->where('shipment_id', $s->id)->where('certificate_no', $data['certificate_no'])->when($row, fn ($q) => $q->where('id', '!=', $row->id))->exists()) {
            throw ValidationException::withMessages(['certificate_no' => __('salesshipment.v.ce_twice', ['no' => $data['certificate_no'], 'shipment' => $s->shipment_no])]);
        }
        $type = ! empty($data['certificate_type_id']) ? DB::table('company_documents')->where('id', $data['certificate_type_id'])->first(['title', 'issued_by']) : null;
        $name = ($data['certificate_name'] ?? null) ?: $type?->title;
        if (! $name) {
            throw ValidationException::withMessages(['certificate_name' => __('salesshipment.v.ce_name')]);
        }
        $head = ['shipment_id' => $s->id, 'certificate_type_id' => $data['certificate_type_id'] ?? null, 'certificate_name' => $name, 'certificate_no' => $data['certificate_no'],
            'issue_date' => $data['issue_date'], 'expiry_date' => $data['expiry_date'] ?? null, 'issuing_authority' => ($data['issuing_authority'] ?? null) ?: $type?->issued_by,
            'status' => $data['status'] ?? ($row?->status ?? 'ACTIVE'), 'remarks' => $data['remarks'] ?? null];
        $row ? $row->update($head) : $row = SsCertificate::query()->create($head + ['created_by' => $this->caller()->auditUserId()]);

        return ApiResponse::ok(['id' => $row->id, 'certificate_no' => $row->certificate_no, 'certificate_name' => $row->certificate_name, 'issuing_authority' => $row->issuing_authority], [], $id ? 200 : 201);
    }

    public function certificateDestroy(string $id): JsonResponse
    {
        $this->allow('salesshipment.exportdoc.manage');
        $row = SsCertificate::query()->findOrFail($id);
        $this->liveShipmentById($row->shipment_id);
        $row->delete();

        return ApiResponse::ok(['deleted' => $row->certificate_no]);
    }

    /** The scan attached to an export document or a certificate. */
    public function attach(Request $request, StoreFileAttachment $files, string $kind, string $id): JsonResponse
    {
        $this->allow('salesshipment.exportdoc.manage');
        [$model, $type] = match ($kind) {
            'export-docs' => [SsExportDoc::class, 'ss_export_doc'],
            'certificates' => [SsCertificate::class, 'ss_certificate'],
            default => abort(404),
        };
        $row = $model::query()->findOrFail($id);
        $this->liveShipmentById($row->shipment_id);
        $request->validate(['upload' => ['required', 'file', 'max:10240']], ['upload.required' => __('salesshipment.v.dh_file')]);
        $upload = $request->file('upload');
        $file = $files->handle($upload, $type, $row->id, $this->caller()->auditUserId());
        $row->update(['file_id' => $file->id, 'file_name' => mb_substr($upload->getClientOriginalName(), 0, 255)]);

        return ApiResponse::ok(['id' => $row->id, 'file_id' => $file->id, 'attachment' => $row->file_name]);
    }

    /* ── Shipment attachments ──────────────────────────────────────── */

    public function attachments(): JsonResponse
    {
        $this->allow('salesshipment.exportdoc.view');
        $rows = SsShipmentAttachment::query()->orderByDesc('uploaded_at')->get();
        $ships = $this->shipments($rows->pluck('shipment_id')->all());
        $b = $this->book();

        return ApiResponse::ok($rows->map(fn (SsShipmentAttachment $a) => [
            'id' => $a->id, 'shipment_no' => $ships[$a->shipment_id]->shipment_no ?? '—', 'so_no' => $ships[$a->shipment_id]->so_no ?? '—', 'document_type' => $a->document_type, 'title' => $a->title,
            'file_id' => $a->file_id, 'file' => $a->file_name, 'uploaded_by' => $b->user($a->uploaded_by), 'uploaded_at' => $a->uploaded_at?->toIso8601String(), 'remarks' => $a->remarks,
        ])->values()->all());
    }

    /** A new attachment, or an edit (the file kept unless a new one is chosen) — multipart, through the upload route. */
    public function saveAttachment(Request $request, StoreFileAttachment $files, ?string $id = null): JsonResponse
    {
        $this->allow('salesshipment.exportdoc.manage');
        $row = $id ? SsShipmentAttachment::query()->findOrFail($id) : null;
        $data = $request->validate([
            'shipment_no' => ['required', 'string', 'max:24'], 'document_type' => ['required', Rule::in(SsShipmentAttachment::TYPES)], 'title' => ['required', 'string', 'max:160'],
            'upload' => [$row ? 'nullable' : 'required', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:5120'], 'remarks' => ['nullable', 'string', 'max:2000'],
        ], ['upload.required' => __('salesshipment.v.sa_file'), 'upload.mimes' => __('salesshipment.v.sa_type'), 'upload.max' => __('salesshipment.v.sa_size'), 'title.required' => __('salesshipment.v.sa_title')]);
        $s = $this->liveShipment($data['shipment_no']);
        $by = $this->caller()->auditUserId();
        $row = DB::transaction(function () use ($row, $data, $s, $request, $files, $by) {
            $head = ['shipment_id' => $s->id, 'document_type' => $data['document_type'], 'title' => $data['title'], 'remarks' => $data['remarks'] ?? null];
            $row ? $row->update($head) : $row = SsShipmentAttachment::query()->create($head + ['uploaded_by' => $by, 'uploaded_at' => now()]);
            if ($request->hasFile('upload')) {
                $upload = $request->file('upload');
                $file = $files->handle($upload, 'ss_attachment', $row->id, $by);
                $row->update(['file_id' => $file->id, 'file_name' => mb_substr($upload->getClientOriginalName(), 0, 255), 'uploaded_by' => $by, 'uploaded_at' => now()]);
            }

            return $row;
        });

        return ApiResponse::ok(['id' => $row->id, 'title' => $row->title, 'file' => $row->file_name], [], $id ? 200 : 201);
    }

    public function attachmentDestroy(string $id): JsonResponse
    {
        $this->allow('salesshipment.exportdoc.manage');
        $row = SsShipmentAttachment::query()->findOrFail($id);
        $row->delete();

        return ApiResponse::ok(['deleted' => $row->title]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    private function book(): SalesBook
    {
        return $this->book ??= new SalesBook($this->caller()->companyId);
    }

    /** A shipment that still takes papers — not closed, not cancelled. */
    private function liveShipment(string $no): SsShipment
    {
        return $this->guard(SsShipment::query()->where('shipment_no', $no)->firstOrFail());
    }

    private function liveShipmentById(string $id): SsShipment
    {
        return $this->guard(SsShipment::query()->findOrFail($id));
    }

    private function guard(SsShipment $s): SsShipment
    {
        if (in_array($s->stage, ['CLOSED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['shipment_no' => __('salesshipment.v.ed_closed', ['no' => $s->shipment_no, 'stage' => __('salesshipment.sp.stage.'.$s->stage)])]);
        }

        return $s;
    }

    /** Expiry: VALID, EXPIRING (inside the settings' certificate window) or EXPIRED, with the days left. @return array<string, mixed> */
    private function expiry(mixed $date, bool $cancelled): array
    {
        if (! $date || $cancelled) {
            return ['expiry' => null, 'expiry_days' => null];
        }
        $days = (int) CarbonImmutable::now('Asia/Dhaka')->startOfDay()->diffInDays(CarbonImmutable::parse(CarbonImmutable::parse($date)->toDateString(), 'Asia/Dhaka'), false);
        $window = (int) SsSettings::get()->cert_alert_days;

        return ['expiry' => $days < 0 ? 'EXPIRED' : ($days <= $window ? 'EXPIRING' : 'VALID'), 'expiry_days' => $days];
    }

    /** @return Collection<string, object> */
    private function shipments(array $ids)
    {
        return DB::table('ss_shipments as s')->join('ss_sales_orders as o', 'o.id', '=', 's.order_id')->whereIn('s.id', $ids)->get(['s.id', 's.shipment_no', 's.stage', 'o.so_no', 'o.customer_id'])->keyBy('id');
    }
}
