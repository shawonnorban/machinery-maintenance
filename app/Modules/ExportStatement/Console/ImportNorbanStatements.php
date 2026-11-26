<?php

declare(strict_types=1);

namespace App\Modules\ExportStatement\Console;

use App\Modules\ExportStatement\Models\ExportStatement;
use App\Modules\ExportStatement\Support\EsSheet;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Norban's Export Statement entries into a company (user, 2026-11-21: "import
 * the 30 entries"), from the JSON written off Norban's database. Each keeps
 * Norban's SL No. and its values; Company Name is the factory's code, Buyer
 * and Shippers Bank are matched to the company's Buyer and Bank masters
 * (ignoring case, punctuation, "&" ≈ "n" and PLC / Limited / Ltd / The) and
 * added to them when missing. An approved entry stays approved; an edit that
 * was waiting in Norban is not carried (the entry keeps its saved values).
 * Run again, an entry already there (same Company + Invoice No.) is skipped.
 */
class ImportNorbanStatements extends Command
{
    protected $signature = 'exportstatement:import-norban {company : company code} {file : the JSON exported from Norban} {--user= : who the entries are recorded as made by, when Norban\'s maker has no login here}';

    protected $description = 'Import Norban\'s Export Statement entries into a company.';

    public function handle(TenantContext $context): int
    {
        $company = Company::query()->where('code', $this->argument('company'))->firstOrFail();
        $context->set($company->id, DB::table('factories')->where('company_id', $company->id)->pluck('id')->all());
        $rows = json_decode((string) file_get_contents((string) $this->argument('file')), true, flags: JSON_THROW_ON_ERROR);
        $fallback = $this->option('user') ? User::query()->where('email', $this->option('user'))->value('id') : null;
        $sheet = new EsSheet($company->id);
        $buyers = $this->master($company->id, 'buyers');
        $banks = $this->master($company->id, 'banks');
        $made = 0;
        $skipped = 0;
        DB::transaction(function () use ($rows, $sheet, $company, $fallback, &$buyers, &$banks, &$made, &$skipped): void {
            foreach ($rows as $r) {
                $v = $r['values'];
                $serial = isset($v['excel_sl']) ? (int) $v['excel_sl'] : null;
                unset($v['excel_sl']);
                if (! empty($v['buyer_name'])) {
                    $v['buyer_name'] = $buyers[$this->key($v['buyer_name'])] ??= $this->addBuyer($company->id, $v['buyer_name']);
                }
                if (! empty($v['shippers_bank_name'])) {
                    $v['shippers_bank_name'] = $banks[$this->key($v['shippers_bank_name'])] ??= $this->addBank($company->id, $v['shippers_bank_name']);
                }
                $exists = ExportStatement::query()->whereRaw('lower("values"->>\'company_name\') = ?', [mb_strtolower((string) ($v['company_name'] ?? ''))])
                    ->whereRaw('lower("values"->>\'invoice_no\') = ?', [mb_strtolower((string) ($v['invoice_no'] ?? ''))])->exists();
                if ($exists) {
                    $skipped++;

                    continue;
                }
                try {
                    [$values, $diff] = (new EsSheet($company->id))->put([], $v, 'row');
                } catch (ValidationException $e) {
                    $this->warn("Norban #{$r['norban_id']}: ".collect($e->errors())->flatten()->first());
                    $skipped++;

                    continue;
                }
                $taken = $serial !== null && ExportStatement::withTrashed()->where('serial_no', $serial)->exists();
                $by = ($r['created_by'] ? User::query()->where('email', $r['created_by'])->value('id') : null) ?? $fallback;
                $approved = $r['status'] === 'approved';
                $s = ExportStatement::query()->create([
                    'serial_no' => $serial !== null && ! $taken ? $serial : (int) ExportStatement::withTrashed()->max('serial_no') + 1,
                    'status' => $approved ? ExportStatement::APPROVED : ExportStatement::CREATED, 'buyer_id' => $sheet->buyerId($values), 'values' => $values, 'version' => 1,
                    'approved_by' => $approved && $r['approved_by'] ? User::query()->where('email', $r['approved_by'])->value('id') : null,
                    'approved_at' => $approved ? ($r['approved_at'] ?? null) : null, 'created_by' => $by, 'updated_by' => $by,
                ]);
                $sheet->log($s, 'CREATE', $diff, (string) Str::ulid(), $by);
                $made++;
            }
        });
        $this->info("{$company->code}: {$made} imported, {$skipped} skipped.");

        return self::SUCCESS;
    }

    /** @return array<string, string> match key => the master's name */
    private function master(string $companyId, string $kind): array
    {
        $names = $kind === 'buyers'
            ? DB::table('buyers')->where('company_id', $companyId)->where('active', true)->get(['short_name', 'name'])->map(fn ($b) => $b->short_name ?: $b->name)
            : DB::table('tx_banks')->where('company_id', $companyId)->where('active', true)->pluck('name');
        $out = [];
        foreach ($names as $n) {
            $out[$this->key((string) $n)] ??= (string) $n;
        }

        return $out;
    }

    /** "C&A" = "CnA"; "Mutual Trust Bank PLC" = "MUTUAL TRUST BANK LIMITED"; "City Bank PLC" = "The City Bank Ltd". */
    private function key(string $name): string
    {
        $s = mb_strtolower($name);
        $s = preg_replace('/\b(plc|limited|ltd|the)\b\.?/u', ' ', $s);
        $s = str_replace('&', 'n', $s);

        return preg_replace('/[^a-z0-9]+/u', '', $s);
    }

    private function addBuyer(string $companyId, string $name): string
    {
        $code = Str::upper(Str::limit(preg_replace('/[^A-Za-z0-9]/', '', $name), 10, ''));
        DB::table('buyers')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $companyId, 'code' => $code ?: 'NB'.random_int(100, 999), 'name' => $name, 'short_name' => $name,
            'pi_address' => 'N/A', 'registered_from' => now()->toDateString(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->line("Buyer added: {$name}");

        return $name;
    }

    private function addBank(string $companyId, string $name): string
    {
        $code = 'NB-'.Str::upper(Str::limit(preg_replace('/[^A-Za-z]/', '', $name), 12, ''));
        DB::table('tx_banks')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $companyId, 'code' => $code, 'name' => $name, 'is_local' => true, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->line("Bank added: {$name}");

        return $name;
    }
}
