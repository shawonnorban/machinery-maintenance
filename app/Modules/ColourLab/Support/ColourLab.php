<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Support;

use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * What Colour Lab's screens share (AnnotechERP 16/01): numbering in sequence
 * for the month, and the names its ids stand for — the company's people
 * (Apply By, Trial Carried Out By, Handed By), its floors (the lab), and the
 * masters a certificate is filed against.
 */
final class ColourLab
{
    public static function company(): string
    {
        return (string) app(TenantContext::class)->companyId();
    }

    /**
     * The next number in the month, "CIR-2026110001". Called inside the save's
     * transaction: the lock holds to its end, so a refused save burns nothing.
     */
    public static function nextNumber(string $prefix, string $table, string $column, string $date): string
    {
        $head = $prefix.'-'.CarbonImmutable::parse($date)->format('Ym');
        $company = self::company();
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ["{$table}:{$head}:{$company}"]);
        $n = 1 + (int) DB::table($table)->where('company_id', $company)->where($column, 'like', $head.'%')->pluck($column)
            ->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /** The company's members, as Brickland's user pickers name them: "Name (email)". @return array<string, string> */
    public static function users(): array
    {
        return DB::table('users')->join('company_users', 'company_users.user_id', '=', 'users.id')->where('company_users.company_id', self::company())
            ->where('company_users.status', 'ACTIVE')->orderBy('users.name')->get(['users.id', 'users.name', 'users.email'])
            ->mapWithKeys(fn ($u) => [$u->id => "{$u->name} ({$u->email})"])->all();
    }

    /** Short names (no email), for grids. @return array<string, string> */
    public static function userNames(): array
    {
        return DB::table('users')->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public static function floors(): array
    {
        return DB::table('floors')->where('company_id', self::company())->orderBy('name')->get(['id', 'name', 'code'])
            ->mapWithKeys(fn ($f) => [$f->id => $f->code ? "{$f->name} ({$f->code})" : $f->name])->all();
    }

    /** A certificate type as Brickland lists it: "OEKO-TEX Standard 100 — OEKO-TEX". @return array<string, string> */
    public static function certTypes(bool $activeOnly = false): array
    {
        return DB::table('lab_cert_types')->where('company_id', self::company())->when($activeOnly, fn ($q) => $q->where('active', true))->orderBy('name')
            ->get(['id', 'name', 'issuing_body'])->mapWithKeys(fn ($t) => [$t->id => $t->issuing_body ? "{$t->name} — {$t->issuing_body}" : $t->name])->all();
    }

    /** @return array<string, string> */
    public static function equipment(bool $activeOnly = false): array
    {
        return DB::table('lab_equipment')->where('company_id', self::company())->when($activeOnly, fn ($q) => $q->where('active', true))->orderBy('name')
            ->get(['id', 'code', 'name', 'serial_no'])->mapWithKeys(fn ($e) => [$e->id => trim("{$e->name} ({$e->code})".($e->serial_no ? " · {$e->serial_no}" : ''))])->all();
    }

    /** A certificate as the register picks it: "OEKO-TEX Standard 100 · OTS-2026-118". @return array<string, string> */
    public static function certificates(bool $activeOnly = false): array
    {
        return DB::table('lab_certificates')->join('lab_cert_types', 'lab_cert_types.id', '=', 'lab_certificates.cert_type_id')
            ->where('lab_certificates.company_id', self::company())->when($activeOnly, fn ($q) => $q->where('lab_certificates.active', true))
            ->orderBy('lab_cert_types.name')->orderBy('lab_certificates.document_ref')->get(['lab_certificates.id', 'lab_cert_types.name', 'lab_certificates.document_ref'])
            ->mapWithKeys(fn ($c) => [$c->id => "{$c->name} · {$c->document_ref}"])->all();
    }

    /** "a@x.com, b@y.com" → the addresses, or the first that is not one. @return array{0: list<string>, 1: ?string} */
    public static function emails(?string $list): array
    {
        $all = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) $list) ?: [])));
        foreach ($all as $a) {
            if (! filter_var($a, FILTER_VALIDATE_EMAIL)) {
                return [$all, $a];
            }
        }

        return [$all, null];
    }
}
