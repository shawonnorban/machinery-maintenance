<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Actions\SeedGlDefaults;
use App\Modules\Finance\Models\GlEventMapping;
use App\Modules\Finance\Models\GlJournalEntry;
use App\Modules\Finance\Models\GlJournalLine;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Writes the ledger entry a business event implies (Shared Systems §2).
 *
 * Two rules the spec sets, and this service keeps:
 *
 *  - **GL posting is a side effect, not the main flow.** "GRN Complete /
 *    Landed Cost Post / Issue-to-Production সবই GL ছাড়াই সম্পূর্ণ কাজ করবে."
 *    So nothing here throws: a mapping that is missing, switched off or
 *    half-filled means no entry, logged and moved past. Stock and costing
 *    are already correct by the time this runs, and refusing to complete a
 *    receipt because an accountant has not finished the chart of accounts
 *    would stop a factory's gate over a bookkeeping question.
 *  - **The mapping is read, never hardcoded.** Which accounts move is a
 *    row in `gl_event_mappings`; this service only knows event names.
 *
 * Posting is keyed on the source document, so completing a receipt twice
 * — or retrying a request that timed out after the commit — cannot double
 * the books.
 */
class GlPoster
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SeedGlDefaults $defaults,
    ) {}

    /**
     * `reverse_sides` swaps debit and credit for an event whose direction
     * depends on what happened: a count that finds more stock than the
     * system expected moves the same two accounts as one that finds less,
     * only the other way round, and that is one mapping rather than two.
     *
     * @param  array<string, mixed>  $context  source_type, source_id, source_no?, factory_id?, currency?, memo?, user_id?, date?, reverse_sides?
     */
    public function post(string $eventType, string $amount, array $context): ?GlJournalEntry
    {
        // Nothing moved, nothing to post. A zero-value receipt is a real
        // thing (a free replacement, a sample) and it belongs in stock,
        // not in the books.
        if (bccomp($amount, '0', 4) !== 1) {
            return null;
        }

        $companyId = $this->context->companyId();
        $mapping = $this->mappingFor($companyId, $eventType);

        if ($mapping === null || ! $mapping->isPostable()) {
            Log::info('GL event not posted: no usable mapping.', [
                'event_type' => $eventType,
                'company_id' => $companyId,
                'source_type' => $context['source_type'] ?? null,
                'source_id' => $context['source_id'] ?? null,
            ]);

            return null;
        }

        $splits = $this->splitCredits($amount, $context);

        try {
            return DB::transaction(function () use ($eventType, $amount, $context, $mapping, $companyId, $splits): GlJournalEntry {
                $entry = GlJournalEntry::create([
                    'company_id' => $companyId,
                    'factory_id' => $context['factory_id'] ?? null,
                    'event_type' => $eventType,
                    'entry_date' => $context['date'] ?? CarbonImmutable::today(),
                    'amount' => $amount,
                    'currency' => $context['currency'] ?? 'BDT',
                    'source_type' => (string) $context['source_type'],
                    'source_id' => (string) $context['source_id'],
                    'source_no' => $context['source_no'] ?? null,
                    'memo' => $context['memo'] ?? null,
                    'posted_by' => $context['user_id'] ?? null,
                ]);

                $flipped = (bool) ($context['reverse_sides'] ?? false);

                $this->line(
                    $entry,
                    $flipped ? $mapping->credit_account_id : $mapping->debit_account_id,
                    'DEBIT',
                    $amount,
                    $context['memo'] ?? null,
                );

                foreach ($splits as [$accountSlot, $share]) {
                    if (bccomp($share, '0', 4) !== 1) {
                        continue;
                    }

                    $this->line(
                        $entry,
                        $flipped && $accountSlot === 'credit_account_id'
                            ? $mapping->debit_account_id
                            : $mapping->{$accountSlot},
                        'CREDIT',
                        $share,
                        $context['memo'] ?? null,
                    );
                }

                return $entry;
            });
        } catch (QueryException $e) {
            // The unique key on (company, event, source) did its job: this
            // document is already in the books. Posting twice is the one
            // failure mode worth swallowing quietly.
            if ($this->isDuplicate($e)) {
                return GlJournalEntry::where('event_type', $eventType)
                    ->where('source_type', (string) $context['source_type'])
                    ->where('source_id', (string) $context['source_id'])
                    ->first();
            }

            throw $e;
        }
    }

    /**
     * Settlement credits two accounts: what an advance covered, and what
     * the bank actually paid. Every other event credits one.
     *
     * @param  array<string, mixed>  $context
     * @return list<array{0: string, 1: string}>
     */
    private function splitCredits(string $amount, array $context): array
    {
        $fromAdvance = isset($context['from_advance'])
            ? (string) $context['from_advance']
            : null;

        if ($fromAdvance === null) {
            return [['credit_account_id', $amount]];
        }

        $fromBank = bcsub($amount, $fromAdvance, 4);

        return [
            ['credit_account_id', $fromAdvance],
            ['secondary_credit_account_id', $fromBank],
        ];
    }

    private function line(GlJournalEntry $entry, ?string $accountId, string $side, string $amount, ?string $memo): void
    {
        GlJournalLine::create([
            'company_id' => $entry->company_id,
            'gl_journal_entry_id' => $entry->id,
            // Null only when a mapping names no secondary account; the row
            // is still written, because an unallocated credit is a question
            // for finance rather than a number to drop.
            'gl_account_id' => $accountId,
            'side' => $side,
            'amount' => $amount,
            'memo' => $memo,
        ]);
    }

    /**
     * An event with no mapping row gets the placeholder one, so nothing is
     * silently lost while the chart of accounts is pending (spec §5.1).
     * Turning an event off is `active = false`, which keeps the row — so a
     * decision to stop posting something is never undone by this.
     */
    private function mappingFor(string $companyId, string $eventType): ?GlEventMapping
    {
        $mapping = GlEventMapping::where('event_type', $eventType)->first();

        if ($mapping !== null) {
            return $mapping;
        }

        // No row for this event at all — either the company has never
        // posted, or an event was added after it was set up. Seeding fills
        // only what is missing, so a finance team's own rows are untouched;
        // an event they switched off keeps its row and is not resurrected.
        $this->defaults->forCompany($companyId);

        return GlEventMapping::where('event_type', $eventType)->first();
    }

    private function isDuplicate(QueryException $e): bool
    {
        return ($e->getCode() === '23505')
            || str_contains($e->getMessage(), 'gl_journal_entries_source_unique');
    }
}
