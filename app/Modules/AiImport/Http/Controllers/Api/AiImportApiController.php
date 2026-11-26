<?php

declare(strict_types=1);

namespace App\Modules\AiImport\Http\Controllers\Api;

use App\Modules\AiCore\Services\AiGateway;
use App\Modules\AiCore\Services\DocumentParts;
use App\Modules\AiImport\Jobs\ReadIntakeFile;
use App\Modules\AiImport\Models\IntakeFile;
use App\Modules\AiImport\Services\IntakeReader;
use App\Modules\AiImport\Support\ImportBook;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * AI File & PO Import (AnnotechERP 26): what its screens' pickers offer, a
 * file dropped into the intake, and a file sent to the AI service to be read
 * (again). The lists themselves are ERP resources (`/erp/ai-*`).
 */
class AiImportApiController extends ApiController
{
    /** The largest file the intake takes: an AI service reads up to about this much in one request. */
    public const MAX_BYTES = 20 * 1024 * 1024;

    public function options(AiGateway $gateway): JsonResponse
    {
        $this->allowEither('aiimport.intake.view', 'aiimport.intake.manage');
        $buyers = ImportBook::buyers();
        $service = $gateway->serviceFor('FILE_READING');

        return ApiResponse::ok([
            'buyers' => array_map(fn ($k, $v) => ['value' => (string) $k, 'label' => $v], array_keys($buyers), array_values($buyers)),
            'reader' => $service ? ['provider' => $service->provider, 'model' => $service->model, 'auto' => $gateway->autoRuns('FILE_READING')] : null,
        ]);
    }

    /**
     * Universal File Intake: a file dropped in — PDF, image, Excel or CSV,
     * Word, an e-mail or an Outlook message; its download path goes on the
     * intake row.
     */
    public function intakeFile(Request $request, StoreFileAttachment $files): JsonResponse
    {
        $this->allow('aiimport.intake.manage');
        $request->validate(['file' => ['required', 'file', 'max:'.intdiv(self::MAX_BYTES, 1024)]], ['file.max' => __('aiimport.v.file_size')]);
        $file = $files->handle($request->file('file'), 'ai_intake_file', $this->caller()->companyId, $this->caller()->auditUserId(), DocumentParts::readableTypes(), self::MAX_BYTES);

        return ApiResponse::created(['id' => $file->id, 'path' => "/api/files/{$file->id}/download", 'name' => $file->original_name, 'size_mb' => ImportBook::sizeMb((int) $file->size_bytes)]);
    }

    /** Sends an intake file to the AI service to be read (again); the row moves on when it is done. */
    public function read(string $id, IntakeReader $reader, AiGateway $gateway): JsonResponse
    {
        $this->allow('aiimport.intake.manage');
        $file = IntakeFile::query()->findOrFail($id);
        if (! $reader->readable($file)) {
            throw ValidationException::withMessages(['file' => __('aiimport.read.upload_only')]);
        }
        if ($gateway->serviceFor('FILE_READING') === null) {
            throw ValidationException::withMessages(['file' => __('aiimport.read.no_service')]);
        }
        if (in_array($file->stage, ['AWAITING_APPROVAL', 'POSTED'], true)) {
            throw ValidationException::withMessages(['file' => __('aiimport.read.settled')]);
        }
        $file->forceFill(['stage' => 'QUEUED', 'read_note' => null])->save();
        ReadIntakeFile::dispatch($file->id, $this->caller()->companyId, app()->getLocale(), $this->caller()->auditUserId());

        return ApiResponse::ok(['message' => __('aiimport.read.started')]);
    }
}
