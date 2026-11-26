<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Resources;

use App\Modules\Finishing\Models\FinBatchReceipt;
use App\Modules\Finishing\Support\FinFloor;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;

/**
 * The shared ground of Finishing Line → QC Process's lists (AnnotechERP
 * 18/04): their own view and manage permission, a company member, and the
 * card a reading or decision is taken on.
 */
abstract class FinQcResource extends FinProductionResource
{
    public function viewPermission(): string
    {
        return 'finishing.qc.view';
    }

    public function managePermission(): string
    {
        return 'finishing.qc.manage';
    }

    /** A user who belongs to this company. */
    protected function member(): Exists
    {
        return Rule::exists('company_users', 'user_id')->where('company_id', $this->company());
    }

    /** A card on the floor (not delivered nor out at a sub-contractor), or a refusal on `$field`. */
    protected function liveCard(string $id, string $field): FinBatchReceipt
    {
        $card = FinBatchReceipt::query()->findOrFail($id);
        if ($card->status !== 'ON_FLOOR' || in_array($card->stage, FinFloor::DESK_STAGES, true)) {
            $batch = FinFloor::batches([$card->batch_id])[$card->batch_id]->batch_no ?? '';
            throw ValidationException::withMessages([$field => __('finishing.qc.v.card_off_floor', ['batch' => $batch])]);
        }

        return $card;
    }

    /** Batch number, colour and receipt of a card, for a row. @return array<string, mixed> */
    protected function cardInfo(?string $cardId): array
    {
        $card = $cardId ? FinBatchReceipt::query()->find($cardId) : null;
        $b = $card ? (FinFloor::batches([$card->batch_id])[$card->batch_id] ?? null) : null;

        return ['receipt_no' => $card?->receipt_no, 'batch_no' => $b?->batch_no, 'buyer' => $b?->buyer, 'order_no' => $b?->order_no, 'style' => $b?->style, 'colour' => $b?->colour, 'stage' => $card?->stage];
    }
}
