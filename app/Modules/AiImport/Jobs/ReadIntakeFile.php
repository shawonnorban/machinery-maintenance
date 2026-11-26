<?php

declare(strict_types=1);

namespace App\Modules\AiImport\Jobs;

use App\Modules\AiImport\Models\IntakeFile;
use App\Modules\AiImport\Services\IntakeReader;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads one intake file with the company's AI service, away from the request
 * (a document can take a minute or more). Never retried: each try is a paid
 * call, and a person can always press Read again.
 */
class ReadIntakeFile implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        private readonly string $fileId,
        private readonly string $companyId,
        private readonly string $locale,
        private readonly ?string $userId = null,
    ) {}

    public function handle(TenantContext $context, IntakeReader $reader): void
    {
        $context->forget();
        $context->set($this->companyId);
        App::setLocale($this->locale);
        $file = IntakeFile::query()->find($this->fileId);
        if ($file !== null) {
            $reader->read($file, $this->userId);
        }
    }

    public function failed(Throwable $e): void
    {
        IntakeFile::withoutGlobalScope(TenantScope::class)->where('id', $this->fileId)->where('stage', 'RECOGNISING')
            ->update(['stage' => 'FAILED', 'read_note' => Str::limit($e->getMessage(), 500)]);
    }
}
