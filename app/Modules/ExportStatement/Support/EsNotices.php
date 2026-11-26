<?php

declare(strict_types=1);

namespace App\Modules\ExportStatement\Support;

use App\Modules\Erp\Support\SheetNotices;
use App\Modules\ExportStatement\Models\EsChange;
use App\Modules\ExportStatement\Models\ExportStatement;
use Carbon\Carbon;

/**
 * Export Statement's notifications (user, 2026-11-22). Edits waiting for
 * approval are told by the Approval Center itself (APPROVAL_REQUESTED to the
 * signer, APPROVAL_DECIDED back to the editor); these are the rest:
 *
 *  - ES_STATEMENTS_CHANGED: statements added, changed (saved at once) or
 *    deleted — told to the commercial heads (`exportstatement.approve`) and
 *    to each statement's author, one notification per save.
 *  - ES_PENDING_WORK: each morning, to those who keep the statements
 *    (`exportstatement.update`) — shipped invoices whose documents are still
 *    to submit with the 10-day limit passed or within 2 days, and realized
 *    invoices whose cash incentive is still unclaimed with 30 days or less
 *    of the claim window left (critical when documents are late).
 */
final class EsNotices
{
    public const LIST = '/export-statement/list';

    /** Days before the documents limit that it is told. */
    public const DOCS_WARN_DAYS = 2;

    /** Days left in the cash incentive claim window that it is told. */
    public const CLAIM_WARN_DAYS = 30;

    public static function batch(string $batch): void
    {
        $changes = EsChange::query()->where('batch', $batch)->where('kind', '!=', 'RECALC')->get(['statement_id', 'kind', 'column_key', 'changed_by']);
        if ($changes->isEmpty()) {
            return;
        }
        $actor = $changes->first()->changed_by;
        $kinds = $changes->pluck('kind')->unique();
        $kind = $kinds->contains('DELETE') ? 'DELETE' : ($kinds->contains('CREATE') ? 'CREATE' : 'EDIT');
        $statements = ExportStatement::withTrashed()->whereKey($changes->pluck('statement_id')->unique())->orderBy('serial_no')->get(['id', 'serial_no', 'created_by', 'values']);
        $heads = SheetNotices::holders('exportstatement.approve');
        $view = SheetNotices::holders('exportstatement.view')->flip();

        $mine = [];
        foreach ($statements as $s) {
            $people = collect($heads->all());
            if ($s->created_by && isset($view[$s->created_by])) {
                $people = $people->push($s->created_by);
            }
            foreach ($people->unique() as $u) {
                $mine[$u][] = $s;
            }
        }
        $by = SheetNotices::name($actor);
        $cells = $changes->whereNotNull('column_key')->groupBy('statement_id')->map->count();
        SheetNotices::send(array_keys($mine), 'ES_STATEMENTS_CHANGED', function (string $locale, string $u) use ($mine, $by, $kind, $cells) {
            $list = collect($mine[$u]);

            return [
                'by' => $by, 'action' => __("notification.sheet.kind.{$kind}", [], $locale), 'n' => $list->count(),
                'invoices' => SheetNotices::list($list->map(fn (ExportStatement $s) => (string) ($s->values['invoice_no'] ?? '#'.$s->serial_no))),
                'value' => '$'.number_format((float) $list->sum(fn (ExportStatement $s) => (float) ($s->values['invoice_value_net'] ?? 0)), 2),
                'cells' => (int) $list->sum(fn (ExportStatement $s) => $cells[$s->id] ?? 0),
            ];
        }, self::LIST, 'INFO', $actor);
    }

    /**
     * What is due today for one company (its context set).
     *
     * @return array{docs_overdue: list<string>, docs_soon: list<string>, claim_soon: list<string>}
     */
    public static function due(): array
    {
        $today = Carbon::parse(EsSheet::today())->startOfDay();
        $out = ['docs_overdue' => [], 'docs_soon' => [], 'claim_soon' => []];
        foreach (ExportStatement::query()->orderBy('serial_no')->get(['id', 'serial_no', 'values']) as $s) {
            $v = $s->values ?? [];
            $invoice = (string) ($v['invoice_no'] ?? '#'.$s->serial_no);
            if (($bl = self::date($v['bl_date'] ?? null)) && self::date($v['docs_submission_date'] ?? null) === null) {
                $left = (int) $today->diffInDays($bl->copy()->addDays(EsOptions::DOCS_SUBMISSION_DAYS), false);
                if ($left < 0) {
                    $out['docs_overdue'][] = $invoice;
                } elseif ($left <= self::DOCS_WARN_DAYS) {
                    $out['docs_soon'][] = $invoice;
                }
            }
            if (($realized = self::date($v['realized_date'] ?? null)) && self::date($v['cash_incentive_claim_date'] ?? null) === null) {
                $left = (int) $today->diffInDays($realized->copy()->addDays(EsOptions::CLAIM_DAYS), false);
                if ($left >= 0 && $left <= self::CLAIM_WARN_DAYS) {
                    $out['claim_soon'][] = $invoice;
                }
            }
        }

        return $out;
    }

    /** The morning's notice for one company (its context set). */
    public static function pendingWork(): int
    {
        $due = self::due();
        if ($due['docs_overdue'] === [] && $due['docs_soon'] === [] && $due['claim_soon'] === []) {
            return 0;
        }

        return SheetNotices::send(SheetNotices::holders('exportstatement.update'), 'ES_PENDING_WORK', fn () => [
            'docs_overdue' => count($due['docs_overdue']), 'docs_soon' => count($due['docs_soon']), 'claim_soon' => count($due['claim_soon']),
            'invoices' => SheetNotices::list(array_merge($due['docs_overdue'], $due['docs_soon'], $due['claim_soon'])),
        ], self::LIST, $due['docs_overdue'] === [] ? 'WARNING' : 'CRITICAL');
    }

    private static function date(mixed $v): ?Carbon
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}/', $v) ? Carbon::parse(substr($v, 0, 10))->startOfDay() : null;
    }
}
