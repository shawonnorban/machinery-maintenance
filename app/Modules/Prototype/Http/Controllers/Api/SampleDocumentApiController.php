<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleDocument;
use App\Modules\Prototype\Support\Prototype;
use App\Modules\Prototype\Support\SampleCards;
use App\Modules\Prototype\Support\SampleLedger;
use App\Shared\Files\Actions\StoreFileAttachment;
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
 * Prototype Desk → Sample 360° View → Sample Documents (AnnotechERP 07/02): a
 * tech pack, measurement sheet, image or approval attached to a sample
 * (SDC-2026-00001) — a path or link typed in, or a file uploaded with it. A
 * new document of a type the sample already has is its next version (Tech
 * Pack V1, V2…), the earlier ones kept; a saved one changes only its title
 * and remarks.
 */
class SampleDocumentApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('prototype.sample360.view');

        return ApiResponse::ok(['samples' => SampleCards::options(Sample::query()->orderByDesc('sample_no')->get()), 'types' => SampleDocument::TYPES]);
    }

    public function index(): JsonResponse
    {
        $this->allow('prototype.sample360.view');

        return ApiResponse::ok($this->present(SampleDocument::query()->orderByDesc('created_at')->get()));
    }

    /** One document, with every version of its type on the sample. */
    public function show(string $document): JsonResponse
    {
        $this->allow('prototype.sample360.view');
        $row = $this->find($document);
        $out = $this->present(collect([$row]))[0];
        $out['versions'] = $this->present(SampleDocument::query()->where('sample_id', $row->sample_id)->where('doc_type', $row->doc_type)->orderByDesc('version')->get());

        return ApiResponse::ok($out);
    }

    /** A new document — the next version of its type on the sample — with its path or link, or the file sent with it. */
    public function store(Request $request, StoreFileAttachment $files): JsonResponse
    {
        $this->allow('prototype.sample360.manage');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'sample_id' => ['required', Rule::exists('proto_samples', 'id')->where('company_id', $company)],
            'doc_type' => ['required', Rule::in(SampleDocument::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'file' => ['nullable', 'string', 'max:500'],
            'upload' => ['nullable', 'file', 'max:10240'],
            'remarks' => ['nullable', 'string', 'max:4000'],
        ], [
            'sample_id.required' => __('prototype.document.sample_required'), 'doc_type.required' => __('prototype.document.type_required'), 'title.required' => __('prototype.document.title_required'),
        ]);
        if (blank($data['file'] ?? null) && ! $request->hasFile('upload')) {
            throw ValidationException::withMessages(['file' => __('prototype.document.file_required')]);
        }
        $row = DB::transaction(function () use ($data, $request, $files): SampleDocument {
            // The sample's row is the lock: two uploads of one type never take the same version.
            Sample::query()->whereKey($data['sample_id'])->lockForUpdate()->first();
            $version = 1 + (int) SampleDocument::query()->where('sample_id', $data['sample_id'])->where('doc_type', $data['doc_type'])->max('version');
            $row = SampleDocument::query()->create([
                'doc_no' => Prototype::nextNumber('SDC', 'proto_documents', 'doc_no', now()->toDateString()), 'sample_id' => $data['sample_id'], 'doc_type' => $data['doc_type'],
                'version' => $version, 'title' => $data['title'], 'file' => $data['file'] ?? null, 'remarks' => $data['remarks'] ?? null, 'uploaded_by' => $this->caller()->auditUserId(),
            ]);
            if ($request->hasFile('upload')) {
                $upload = $request->file('upload');
                $file = $files->handle($upload, 'proto_document', $row->id, $this->caller()->auditUserId());
                $row->update(['file_id' => $file->id, 'file_name' => mb_substr($upload->getClientOriginalName(), 0, 255), 'file_size' => $upload->getSize()]);
            }
            SampleLedger::log($row->sample_id, 'document_attached', ['doc' => $row->doc_no, 'title' => $row->title, 'version' => $row->version], $row->doc_no);

            return $row;
        });

        return ApiResponse::created($this->present(collect([$row->fresh()]))[0]);
    }

    /** A saved document changes only its title and remarks; a new file is a new version. */
    public function update(Request $request, string $document): JsonResponse
    {
        $this->allow('prototype.sample360.manage');
        $row = $this->find($document);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'remarks' => ['nullable', 'string', 'max:4000']], ['title.required' => __('prototype.document.title_required')]);
        $row->update(['title' => $data['title'], 'remarks' => $data['remarks'] ?? null]);

        return ApiResponse::ok($this->present(collect([$row->fresh()]))[0]);
    }

    private function find(string $document): SampleDocument
    {
        return SampleDocument::query()->where('doc_no', $document)->firstOrFail();
    }

    /** @return list<array<string, mixed>> */
    private function present(Collection $rows): array
    {
        $samples = Sample::query()->whereIn('id', $rows->pluck('sample_id'))->pluck('sample_no', 'id');
        $names = Prototype::names($rows->pluck('uploaded_by')->all());

        return $rows->map(fn (SampleDocument $d): array => [
            'id' => $d->id, 'doc_no' => $d->doc_no, 'sample_id' => $d->sample_id, 'sample_no' => $samples[$d->sample_id] ?? null, 'doc_type' => $d->doc_type, 'version' => $d->version,
            'title' => $d->title, 'file' => $d->file, 'file_id' => $d->file_id, 'file_name' => $d->file_name, 'file_size' => $d->file_size, 'remarks' => $d->remarks,
            'uploaded_by_name' => $names[$d->uploaded_by] ?? null, 'uploaded_at' => $d->created_at->toIso8601String(),
        ])->values()->all();
    }
}
