<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Controllers\Api;

use App\Modules\Tenancy\Models\CompanyDocument;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Company Management → Company Documents (AnnotechERP 01/01): trade licence,
 * TIN, BIN, bond licence and the like, each with its number, dates and a
 * scanned copy (a FileAttachment, attachable_type 'company_document').
 */
class CompanyDocumentApiController extends ApiController
{
    public const PERMISSION = 'settings.company.manage';

    public const ATTACHABLE = 'company_document';

    public function __construct(private readonly TenantContext $context) {}

    public function index(): JsonResponse
    {
        $this->allow(self::PERMISSION);

        $documents = CompanyDocument::query()->orderBy('expiry_date')->orderBy('title')->get();
        $fileCounts = FileAttachment::query()
            ->where('attachable_type', self::ATTACHABLE)
            ->whereIn('attachable_id', $documents->pluck('id'))
            ->selectRaw('attachable_id, count(*) as n')
            ->groupBy('attachable_id')
            ->pluck('n', 'attachable_id');

        return ApiResponse::ok($documents->map(fn (CompanyDocument $document): array => $this->summary($document) + [
            'files_count' => (int) ($fileCounts[$document->id] ?? 0),
        ])->all());
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::PERMISSION);

        $document = CompanyDocument::create($this->validated($request) + ['company_id' => $this->context->companyId()]);

        return ApiResponse::created($this->summary($document->fresh()));
    }

    public function update(Request $request, CompanyDocument $document): JsonResponse
    {
        $this->allow(self::PERMISSION);

        $document->update($this->validated($request));

        return ApiResponse::ok($this->summary($document->fresh()));
    }

    public function destroy(CompanyDocument $document): JsonResponse
    {
        $this->allow(self::PERMISSION);

        $document->delete();

        return ApiResponse::noContent();
    }

    public function files(CompanyDocument $document): JsonResponse
    {
        $this->allow(self::PERMISSION);

        $files = FileAttachment::where('attachable_type', self::ATTACHABLE)
            ->where('attachable_id', $document->id)
            ->latest()
            ->get();

        return ApiResponse::ok($files->map(fn (FileAttachment $file): array => $file->toApiSummary())->all());
    }

    public function attach(Request $request, CompanyDocument $document, StoreFileAttachment $files): JsonResponse
    {
        $this->allow(self::PERMISSION);

        $request->validate(['file' => ['required', 'file']]);

        $attachment = $files->handle($request->file('file'), self::ATTACHABLE, $document->id, $this->caller()->auditUserId());

        return ApiResponse::created($attachment->toApiSummary());
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'document_type' => ['required', Rule::in(CompanyDocument::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'document_no' => ['nullable', 'string', 'max:64'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'issued_by' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]) + ['status' => 'ACTIVE'];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(CompanyDocument $document): array
    {
        return [
            'id' => $document->id,
            'document_type' => $document->document_type,
            'title' => $document->title,
            'document_no' => $document->document_no,
            'issue_date' => $document->issue_date?->format('Y-m-d'),
            'expiry_date' => $document->expiry_date?->format('Y-m-d'),
            'issued_by' => $document->issued_by,
            'status' => $document->status,
        ];
    }
}
