<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Modules\Identity\Support\MenuRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which modules of the company app a company has been given (Platform →
 * Customer → Modules; user, 2026-11-24). Every MenuRegistry module comes
 * under it except the core ones, which are always on.
 *
 *  - A `company_modules` row says on or off; no row = the module's default:
 *    on for the modules companies have always had, off for OPT_IN (sold
 *    separately). A company onboarded from the platform gets "off" rows for
 *    every non-core module (offRowsForNewCompany) — it starts with the core.
 *  - While a module is off it leaves the company's sidebar, its screens show
 *    "not enabled" (lockedUrls → SetupGuard), and every permission that ONLY
 *    off modules' screens use is refused (permits, in the Gate and
 *    ApiCaller). A permission an on module's screen also uses stays — Production
 *    Tracking must not lose a lookup because Merchandise is off.
 */
final class LicensedModules
{
    /** Always on; the platform cannot switch them off (user, 2026-11-24). */
    public const CORE = ['ACCESS', 'APPROVAL', 'SETTINGS', 'COMPANY', 'CHAT', 'HR_PAYROLL'];

    /** Off until the platform switches them on, for existing companies too. */
    public const OPT_IN = ['QUALITY_TALLY'];

    /** @var array<string, string> */
    public const DESCRIPTIONS = [
        'QUALITY_TALLY' => 'Piece-by-piece QC tally for cutting, sewing and quality lines: RFT, defect, rectify and reject taps, DHU, line targets and the hourly tally sheet.',
    ];

    /** @var array<string, array{0: float, 1: array<string, bool>}> */
    private static array $memo = [];

    /** @var array<string, list<string>>|null permission (exact) => modules whose screens use it */
    private static ?array $exact = null;

    /** @var array<string, list<string>>|null permission family prefix => modules */
    private static ?array $families = null;

    /** @var array<string, list<string>> */
    private static array $owners = [];

    /**
     * Every module as the platform lists it: code => section, names, whether
     * core, and its default.
     *
     * @return array<string, array{section: string, name: string, name_bn: string, description: ?string, core: bool, default: bool, order: int}>
     */
    public static function catalog(): array
    {
        $out = [];
        foreach (MenuRegistry::modules() as $code => [$label, , $order, $section]) {
            $out[$code] = [
                'section' => $section, 'name' => self::label($label, 'en'), 'name_bn' => self::label($label, 'bn'),
                'description' => self::DESCRIPTIONS[$code] ?? null, 'core' => in_array($code, self::CORE, true),
                'default' => ! in_array($code, self::OPT_IN, true), 'order' => (int) $order,
            ];
        }
        $sections = array_flip(MenuRegistry::SECTIONS);
        uasort($out, fn (array $a, array $b): int => [$sections[$a['section']] ?? 99, $a['order'], $a['name']] <=> [$sections[$b['section']] ?? 99, $b['order'], $b['name']]);

        return $out;
    }

    /**
     * The company's explicit switches, remembered for a few seconds: the Gate
     * asks once per permission and a sidebar asks hundreds.
     *
     * @return array<string, bool>
     */
    public static function switches(string $companyId): array
    {
        $hit = self::$memo[$companyId] ?? null;
        if ($hit !== null && $hit[0] > microtime(true)) {
            return $hit[1];
        }
        $rows = Schema::hasTable('company_modules')
            ? DB::table('company_modules')->where('company_id', $companyId)->get(['module_code', 'enabled'])
                ->mapWithKeys(fn ($r): array => [(string) $r->module_code => (bool) $r->enabled])->all()
            : [];
        self::$memo[$companyId] = [microtime(true) + 10, $rows];

        return $rows;
    }

    public static function isEnabled(string $companyId, string $code): bool
    {
        if (in_array($code, self::CORE, true)) {
            return true;
        }

        return self::switches($companyId)[$code] ?? ! in_array($code, self::OPT_IN, true);
    }

    /** @return list<string> the module codes switched off for this company */
    public static function disabled(string $companyId): array
    {
        return array_values(array_filter(array_keys(MenuRegistry::modules()), fn (string $code): bool => ! self::isEnabled($companyId, $code)));
    }

    public static function forget(?string $companyId = null): void
    {
        if ($companyId === null) {
            self::$memo = [];
        } else {
            unset(self::$memo[$companyId]);
        }
    }

    /** False when only screens of modules switched off for this company use the permission. */
    public static function permits(string $companyId, string $permission): bool
    {
        $owners = self::owners($permission);
        if ($owners === []) {
            return true;
        }
        foreach ($owners as $code) {
            if (self::isEnabled($companyId, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The screens of the modules switched off for this company (an address
     * an on module's screen also has stays open).
     *
     * @return list<string>
     */
    public static function lockedUrls(string $companyId): array
    {
        $off = array_flip(self::disabled($companyId));
        if ($off === []) {
            return [];
        }
        $closed = [];
        $open = [];
        foreach (MenuRegistry::menus() as [, $module, , , $url]) {
            if (! $url) {
                continue;
            }
            isset($off[$module]) ? $closed[$url] = true : $open[$url] = true;
        }

        return array_values(array_keys(array_diff_key($closed, $open)));
    }

    /**
     * The rows a company onboarded from the platform starts with: every
     * non-core module off (OPT_IN ones are off without a row).
     *
     * @return list<string>
     */
    public static function offForNewCompany(): array
    {
        return array_values(array_filter(array_keys(MenuRegistry::modules()), fn (string $code): bool => ! in_array($code, self::CORE, true) && ! in_array($code, self::OPT_IN, true)));
    }

    /** @return list<string> the modules whose screens use this permission */
    private static function owners(string $permission): array
    {
        if (array_key_exists($permission, self::$owners)) {
            return self::$owners[$permission];
        }
        if (self::$exact === null) {
            self::$exact = [];
            self::$families = [];
            foreach (MenuRegistry::menus() as [, $module, , , , , $view, $prefixes]) {
                foreach (array_filter((array) $view) as $p) {
                    self::$exact[$p][$module] = true;
                }
                foreach ((array) $prefixes as $f) {
                    self::$families[$f][$module] = true;
                }
            }
        }
        $found = self::$exact[$permission] ?? [];
        foreach (self::$families as $family => $modules) {
            if ($permission === $family || str_starts_with($permission, $family.'.')) {
                $found += $modules;
            }
        }

        return self::$owners[$permission] = array_keys($found);
    }

    private static function label(string $key, string $locale): string
    {
        $text = __($key, [], $locale);

        return is_string($text) && $text !== $key ? $text : $key;
    }
}
