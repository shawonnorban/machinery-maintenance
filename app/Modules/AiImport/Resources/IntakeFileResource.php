<?php

declare(strict_types=1);

namespace App\Modules\AiImport\Resources;

use App\Modules\AiCore\Services\AiGateway;
use App\Modules\AiImport\Jobs\ReadIntakeFile;
use App\Modules\AiImport\Models\IntakeFile;
use App\Modules\AiImport\Models\IntakeValue;
use App\Modules\AiImport\Support\ImportBook;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Universal File Intake (AnnotechERP 26/01): any file that comes in — sheet,
 * PDF, mail or image — with how it arrived, what it was recognised as, the
 * buyer, its size, the engine's confidence and the stage it has reached. A
 * file is dropped in by name or uploaded (its size then comes from the
 * upload). Only a recognised file is extracted, validated, approved or
 * posted.
 */
class IntakeFileResource extends AiImportResource
{
    /** An uploaded file's download path. */
    private const FILE = '#^/api/files/([0-9A-Za-z]{26})/download$#';

    public function key(): string
    {
        return 'ai-intake-files';
    }

    public function model(): string
    {
        return IntakeFile::class;
    }

    public function columns(): array
    {
        return ['file', 'arrived_by', 'family', 'buyer_id', 'received_on', 'size_mb', 'confidence', 'stage'];
    }

    public function defaults(): array
    {
        return ['arrived_by' => 'MANUAL', 'confidence' => 0, 'stage' => 'QUEUED'];
    }

    public function rules(?Model $row): array
    {
        $value = fn (string $f) => request()->has($f) ? request()->input($f) : $row?->getAttribute($f);
        $today = CarbonImmutable::now('Asia/Dhaka')->toDateString();
        // Extracting, validating, approving or posting a file needs to know what it is.
        $recognised = fn (?string $family, ?string $stage) => ! in_array($stage, ImportBook::READ_STAGES, true) || ($family && $family !== 'UNRECOGNISED');

        return [
            'file' => [$row ? 'sometimes' : 'required', 'string', 'max:255', function (string $attribute, mixed $v, Closure $fail): void {
                if (str_starts_with((string) $v, '/api/') && ! $this->upload((string) $v)) {
                    $fail(__('aiimport.v.file_upload'));
                }
            }],
            'arrived_by' => ['sometimes', Rule::in(ImportBook::ARRIVALS)],
            'family' => ['nullable', Rule::in(ImportBook::INTAKE_FAMILIES), function (string $attribute, mixed $v, Closure $fail) use ($value, $recognised): void {
                if (! request()->has('stage') && ! $recognised($v, $value('stage'))) {
                    $fail(__('aiimport.v.unrecognised'));
                }
            }],
            'buyer_id' => $this->buyerRule(),
            'received_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'size_mb' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'confidence' => $this->percent(),
            'stage' => ['sometimes', Rule::in(ImportBook::STAGES), function (string $attribute, mixed $v, Closure $fail) use ($value, $recognised): void {
                if (! $recognised($value('family'), $v)) {
                    $fail(__('aiimport.v.unrecognised'));
                }
            }],
        ];
    }

    public function messages(): array
    {
        return ['file.required' => __('aiimport.v.file'), 'received_on.before_or_equal' => __('aiimport.v.future')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['file'])) {
            $data['file'] = trim($data['file']);
            // An upload knows its own size.
            if ($upload = $this->upload($data['file'])) {
                $data['size_mb'] = ImportBook::sizeMb((int) $upload->size_bytes);
            }
        }
        if (! $row) {
            $data['received_on'] ??= CarbonImmutable::now('Asia/Dhaka')->toDateString();
        }

        return $data;
    }

    /** The file by name — an upload's own name — and, for an upload, where it opens. */
    public function present(Model $row): array
    {
        $out = parent::present($row);
        $upload = $this->upload((string) $out['file']);
        $out['file_name'] = $upload->original_name ?? $out['file'];
        $out['file_url'] = $upload ? $out['file'] : null;
        // What the AI service made of it (26/01, read by the AI).
        foreach (['read_by', 'summary', 'read_note', 'approval_status'] as $f) {
            $out[$f] = $row->getAttribute($f);
        }
        $out['read_at'] = $row->getAttribute('read_at')?->toIso8601String();
        $values = IntakeValue::query()->where('intake_file_id', $row->getKey())->orderBy('created_at')->orderBy('id')->get(['field', 'value', 'confidence']);
        $out['values_count'] = $values->count();
        $out['values_text'] = $values->map(fn ($v) => "{$v->field}: {$v->value} (".number_format((float) $v->confidence, 1).'%)')->implode("\n") ?: null;

        return $out;
    }

    /**
     * A file uploaded in (or swapped for another upload) is read straight
     * away when Settings → AI Services has file reading set to run by itself.
     */
    public function afterSave(Model $row, bool $created): void
    {
        parent::afterSave($row, $created);
        if (($created || $row->wasChanged('file')) && $this->upload((string) $row->getAttribute('file')) && app(AiGateway::class)->autoRuns('FILE_READING')) {
            ReadIntakeFile::dispatch((string) $row->getKey(), $this->company(), app()->getLocale(), $this->actingUserId());
        }
    }

    /** A file uploaded to the intake, in this company. */
    private function upload(string $path): ?object
    {
        if (! preg_match(self::FILE, $path, $m)) {
            return null;
        }

        return DB::table('file_attachments')->whereRaw('lower(id) = ?', [strtolower($m[1])])->where('company_id', $this->company())->where('attachable_type', 'ai_intake_file')->first(['id', 'original_name', 'size_bytes']);
    }
}
