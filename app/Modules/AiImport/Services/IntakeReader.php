<?php

declare(strict_types=1);

namespace App\Modules\AiImport\Services;

use App\Modules\AiCore\Services\AiException;
use App\Modules\AiCore\Services\AiGateway;
use App\Modules\AiCore\Services\DocumentParts;
use App\Modules\AiImport\Models\ExtractionRule;
use App\Modules\AiImport\Models\IntakeFile;
use App\Modules\AiImport\Models\IntakeValue;
use App\Modules\AiImport\Models\Signature;
use App\Modules\AiImport\Support\ImportBook;
use App\Modules\Approval\Actions\RequestApproval;
use App\Shared\Files\Models\FileAttachment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reads one intake file with the AI service Settings → AI Services names for
 * file reading (AnnotechERP 26/01): the service says what the document is,
 * which of the company's buyers sent it, how sure it is, and the values the
 * enabled extraction rules of that family ask for. Then the file moves on —
 *
 *  - not recognised: Failed;
 *  - no buyer, or a mandatory value missing: Validating, for a person;
 *  - otherwise: Awaiting approval, raised in the Approval Center (posted at
 *    once when no approval workflow asks anyone to sign).
 *
 * A signature that fits the family and buyer is counted as matched. Nothing
 * is guessed when the service cannot answer: the file fails with the reason.
 */
class IntakeReader
{
    public function __construct(
        private readonly AiGateway $gateway,
        private readonly DocumentParts $documents,
        private readonly RequestApproval $approvals,
    ) {}

    /** Whether a file can be read at all: only an upload has bytes to send. */
    public function readable(IntakeFile $file): bool
    {
        return $this->attachment($file) !== null;
    }

    /** Reads the file and moves it on; returns what happened, in a sentence. */
    public function read(IntakeFile $file, ?string $userId = null): string
    {
        $attachment = $this->attachment($file);
        if ($attachment === null) {
            return $this->stop($file, 'QUEUED', __('aiimport.read.upload_only'));
        }
        $client = $this->gateway->clientFor('FILE_READING');
        if ($client === null) {
            return $this->stop($file, 'QUEUED', __('aiimport.read.no_service'));
        }
        $file->forceFill(['stage' => 'RECOGNISING', 'read_note' => null])->save();

        $rules = ExtractionRule::query()->whereNotNull('family')->whereNotNull('erp_column')->orderBy('family')->orderBy('field')->get();
        $buyers = ImportBook::buyers();
        try {
            $parts = $this->documents->fromBytes((string) Storage::disk($attachment->disk)->get($attachment->path), (string) $attachment->mime_type, (string) $attachment->original_name);
            $answer = $client->structured($this->system($buyers, $rules->all()), $parts, $this->schema());
        } catch (AiException $e) {
            $file->forceFill(['read_by' => $client->label(), 'read_at' => CarbonImmutable::now()])->save();

            return $this->stop($file, 'FAILED', $e->getMessage());
        }

        return DB::transaction(function () use ($file, $answer, $rules, $buyers, $client, $userId): string {
            $family = in_array($answer['family'] ?? null, ImportBook::INTAKE_FAMILIES, true) ? $answer['family'] : 'UNRECOGNISED';
            $named = Str::lower(trim((string) ($answer['buyer'] ?? '')));
            $buyerId = $named === '' ? null : (array_search($named, array_map(fn ($b) => Str::lower($b), $buyers), true) ?: null);
            $file->forceFill([
                'family' => $family,
                'buyer_id' => $buyerId,
                'confidence' => max(0, min(100, round((float) ($answer['confidence'] ?? 0), 1))),
                'summary' => mb_strimwidth(trim((string) ($answer['summary'] ?? '')), 0, 2000, '…') ?: null,
                'read_by' => $client->label(),
                'read_at' => CarbonImmutable::now(),
                'approval_status' => null,
            ])->save();

            // The values, per rule of the recognised family; a rule the answer left out is simply not found.
            IntakeValue::query()->where('intake_file_id', $file->id)->delete();
            $found = [];
            foreach ((array) ($answer['values'] ?? []) as $v) {
                $column = Str::lower(trim((string) ($v['erp_column'] ?? '')));
                if ($column !== '' && trim((string) ($v['value'] ?? '')) !== '') {
                    $found[$column] = $v;
                }
            }
            $missing = [];
            foreach ($rules->where('family', $family) as $rule) {
                $v = $found[$rule->erp_column] ?? null;
                if ($v === null) {
                    if ($rule->mandatory) {
                        $missing[] = $rule->field;
                    }

                    continue;
                }
                IntakeValue::query()->create(['intake_file_id' => $file->id, 'field' => $rule->field, 'erp_column' => $rule->erp_column, 'value' => mb_strimwidth(trim((string) $v['value']), 0, 4000, '…'), 'confidence' => max(0, min(100, round((float) ($v['confidence'] ?? 0), 1))), 'mandatory' => $rule->mandatory]);
            }

            if ($family === 'UNRECOGNISED') {
                return $this->stop($file, 'FAILED', __('aiimport.read.unrecognised'));
            }
            $this->countSignature($family, $buyerId);
            if ($buyerId === null || $missing !== []) {
                $why = array_filter([$buyerId === null ? __('aiimport.read.no_buyer') : null, $missing !== [] ? __('aiimport.read.missing', ['fields' => implode(', ', $missing)]) : null]);

                return $this->stop($file, 'VALIDATING', implode(' ', $why));
            }
            $file->forceFill(['stage' => 'AWAITING_APPROVAL', 'read_note' => null])->save();
            $this->approvals->forEntity($file, $userId);
            $file->refresh();

            return $file->stage === 'POSTED' ? __('aiimport.read.posted') : __('aiimport.read.awaiting');
        });
    }

    private function stop(IntakeFile $file, string $stage, string $note): string
    {
        $file->forceFill(['stage' => $stage, 'read_note' => $note])->save();

        return $note;
    }

    /** The upload behind a file, in this company and this intake. */
    private function attachment(IntakeFile $file): ?FileAttachment
    {
        if (! preg_match('#^/api/files/([0-9A-Za-z]{26})/download$#', (string) $file->file, $m)) {
            return null;
        }

        return FileAttachment::query()->whereRaw('lower(id) = ?', [strtolower($m[1])])->where('attachable_type', 'ai_intake_file')->first();
    }

    /** A signature of this family — the buyer's own first, else any buyer's — has matched once more. */
    private function countSignature(string $family, ?string $buyerId): void
    {
        $signature = Signature::query()->where('enabled', true)->where('family', $family)
            ->where(fn ($q) => $q->whereNull('buyer_id')->when($buyerId, fn ($q) => $q->orWhere('buyer_id', $buyerId)))
            ->orderByRaw('buyer_id IS NULL')->first();
        $signature?->forceFill(['times_matched' => $signature->times_matched + 1, 'last_matched' => CarbonImmutable::now('Asia/Dhaka')->toDateString()])->save();
    }

    /**
     * @param  array<string, string>  $buyers
     * @param  list<ExtractionRule>  $rules
     */
    private function system(array $buyers, array $rules): string
    {
        $families = implode(', ', ImportBook::INTAKE_FAMILIES);
        $buyerList = $buyers === [] ? '(none on file)' : implode(', ', array_values($buyers));
        $byFamily = [];
        foreach ($rules as $r) {
            $byFamily[$r->family][] = "- {$r->erp_column}: {$r->field} ({$r->data_type}".($r->mandatory ? ', mandatory' : '').')';
        }
        $fields = $byFamily === [] ? '(no extraction rules are set up)' : implode("\n", array_map(fn ($f, $lines) => "{$f}:\n".implode("\n", $lines), array_keys($byFamily), $byFamily));

        return <<<TXT
        You read documents sent to a garment manufacturer — purchase orders, tech packs, inquiries, bills of materials, cost sheets, commercial documents (L/Cs, invoices, packing lists) and artwork — and say what each one is so it can be routed.

        1. Decide the document's family, one of: {$families}. Use UNRECOGNISED only when the document is none of these or cannot be read.
        2. Name the buyer (the brand or retailer the document is from or for) exactly as it appears in this list, or an empty string when none of them fits: {$buyerList}.
        3. Give your confidence, 0 to 100, that the family and buyer are right.
        4. Write a one or two sentence summary of the document (what it is, its number, the main quantities or dates).
        5. For the family you chose, pull out each of these fields — erp_column, then what it is, its type and whether it is mandatory:
        {$fields}
        Return one entry per field you actually found, with the erp_column exactly as listed, the value as written in the document (dates as YYYY-MM-DD, numbers without thousands separators) and your confidence in that value, 0 to 100. Leave out a field you cannot find; never invent a value.
        TXT;
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'family' => ['type' => 'string', 'enum' => ImportBook::INTAKE_FAMILIES],
                'buyer' => ['type' => 'string'],
                'confidence' => ['type' => 'number'],
                'summary' => ['type' => 'string'],
                'values' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'properties' => ['erp_column' => ['type' => 'string'], 'value' => ['type' => 'string'], 'confidence' => ['type' => 'number']],
                    'required' => ['erp_column', 'value', 'confidence'],
                    'additionalProperties' => false,
                ]],
            ],
            'required' => ['family', 'buyer', 'confidence', 'summary', 'values'],
            'additionalProperties' => false,
        ];
    }
}
