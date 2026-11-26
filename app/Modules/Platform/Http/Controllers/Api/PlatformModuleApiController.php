<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Api;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Platform\Support\LicensedModules;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Platform → Customer → Modules: every module of the company app, by sidebar
 * section, and whether this customer has been given each (LicensedModules).
 * Core modules are always on. Switching one off hides it from the company
 * and keeps its data; switching it on brings it back.
 */
class PlatformModuleApiController extends ApiController
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(Company $company): JsonResponse
    {
        return ApiResponse::ok($this->rows($company));
    }

    /** One module (`{code}` in the address) or many (`codes`), on or off. */
    public function update(Request $request, Company $company, ?string $code = null): JsonResponse
    {
        $sellable = array_keys(array_filter(LicensedModules::catalog(), fn (array $m): bool => ! $m['core']));
        if ($code !== null) {
            abort_unless(in_array($code, $sellable, true), 404);
            $request->merge(['codes' => [$code]]);
        }
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'codes' => ['required', 'array', 'min:1'],
            'codes.*' => ['string', Rule::in($sellable)],
        ]);
        $on = (bool) $data['enabled'];
        $changed = [];

        DB::transaction(function () use ($company, $data, $on, $request, &$changed) {
            foreach (array_unique($data['codes']) as $c) {
                if (LicensedModules::isEnabled($company->id, $c) === $on) {
                    continue;
                }
                DB::table('company_modules')->updateOrInsert(
                    ['company_id' => $company->id, 'module_code' => $c],
                    ['id' => DB::table('company_modules')->where('company_id', $company->id)->where('module_code', $c)->value('id') ?? (string) Str::ulid(),
                        'enabled' => $on, 'enabled_by' => $request->user()?->id, 'updated_at' => now(), 'created_at' => now()],
                );
                $changed[] = $c;
            }
        });
        LicensedModules::forget($company->id);

        if ($changed !== []) {
            $this->audit->event(
                'SUBSCRIPTION_CHANGED',
                ['reason' => $on ? 'MODULE_ENABLED' : 'MODULE_DISABLED', 'company_id' => $company->id, 'modules' => $changed],
                userId: $request->user()?->id,
                label: implode(', ', $changed),
            );
        }

        return ApiResponse::ok($this->rows($company));
    }

    /** @return list<array<string, mixed>> */
    private function rows(Company $company): array
    {
        $rows = DB::table('company_modules as m')->leftJoin('users as u', 'u.id', '=', 'm.enabled_by')
            ->where('m.company_id', $company->id)->get(['m.module_code', 'm.updated_at', 'u.name'])->keyBy('module_code');
        $tz = (string) config('app.timezone');

        return collect(LicensedModules::catalog())->map(fn (array $m, string $code): array => [
            'code' => $code, 'section' => $m['section'], 'name' => $m['name'], 'name_bn' => $m['name_bn'], 'description' => $m['description'],
            'core' => $m['core'], 'enabled' => LicensedModules::isEnabled($company->id, $code),
            // With its zone: a bare "Y-m-d H:i:s" reads as UTC on the server and local time in the browser.
            'changed_at' => isset($rows[$code]) && $rows[$code]->updated_at ? Carbon::parse($rows[$code]->updated_at, $tz)->toIso8601String() : null,
            'changed_by' => $rows[$code]->name ?? null,
        ])->values()->all();
    }
}
