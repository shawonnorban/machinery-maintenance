<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Settings\Actions\SetSetting;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Models\SettingDefinition;
use App\Modules\Settings\Services\SettingsResolver;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * How this company wants the product to behave, over the wire (API 19.2;
 * mirrors the web `CompanySettingsController`, which this delegates every
 * write to unchanged per ADR-003).
 *
 * A setting can be answered once for the company and again for a single
 * factory. `GET /settings` says, for every key, which level actually
 * answered it — an integration reading a setting needs the same "why is it
 * behaving this way" the settings screen shows a person.
 */
class SettingsApiController extends ApiController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SettingsResolver $resolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('settings.company.manage');

        $factoryId = $this->factoryId($request);

        $resolved = $this->resolver->all($factoryId);
        // When and by whom each answering row last changed (System Parameter
        // Update's "Updated" column, AnnotechERP 02/01).
        $changed = Setting::query()
            ->whereIn('id', array_filter(array_column($resolved, 'setting_id')))
            ->get(['id', 'updated_at', 'updated_by'])
            ->keyBy('id');
        $editors = User::query()->whereIn('id', $changed->pluck('updated_by')->filter()->unique())->pluck('name', 'id');

        $rows = collect($resolved)
            ->map(function (array $resolved, string $key) use ($factoryId, $changed, $editors): array {
                $definition = $this->resolver->definition($key);
                $row = $resolved['setting_id'] !== null ? $changed->get($resolved['setting_id']) : null;

                return [
                    'key' => $key,
                    'name' => $definition->name,
                    'value' => $resolved['value'],
                    'level' => $resolved['level'],
                    'editable_here' => $factoryId === null
                        ? $definition->allowsLevel('COMPANY')
                        : $definition->allowsLevel('FACTORY'),
                    'updated_at' => $row?->updated_at?->toIso8601String(),
                    'updated_by' => $row?->updated_by !== null ? $editors->get($row->updated_by) : null,
                ];
            })
            ->values();

        return ApiResponse::ok($rows->all());
    }

    /**
     * The catalog of settable keys with types and defaults.
     */
    public function definitions(): JsonResponse
    {
        $this->allow('settings.company.manage');

        $definitions = SettingDefinition::orderBy('key')->get()
            ->map(fn (SettingDefinition $d): array => [
                'key' => $d->key,
                'name' => $d->name,
                'description' => $d->description,
                'value_type' => $d->value_type,
                'allowed_values' => $d->allowed_values,
                'default_value' => $d->default_value,
                'scope_levels' => $d->scope_levels,
                'is_sensitive' => $d->is_sensitive,
            ]);

        return ApiResponse::ok($definitions->all());
    }

    /**
     * Body carries `value` and an optional `factory_id` to set an override.
     */
    public function update(Request $request, string $key, SetSetting $action): JsonResponse
    {
        $this->allow('settings.company.manage');

        $definition = $this->resolver->definition($key);

        $data = $request->validate([
            'value' => ['nullable'],
            'factory_id' => ['nullable', 'string', 'size:26'],
        ]);

        $factoryId = $this->requestedFactoryId($data['factory_id'] ?? null);

        $value = match ($definition->value_type) {
            'BOOL' => $request->boolean('value'),
            // A cleared text field arrives as null; for a text setting that means empty.
            'STRING' => (string) ($data['value'] ?? ''),
            default => $data['value'] ?? null,
        };

        $setting = $action->handle($key, $value, $factoryId, null, $this->caller()->auditUserId());

        return ApiResponse::ok([
            'key' => $setting->key,
            'value' => $this->resolver->get($key, $factoryId),
            'level' => $factoryId === null ? 'COMPANY' : 'FACTORY',
        ]);
    }

    /**
     * Removes an override so the value falls back to the next level up.
     */
    public function destroy(Request $request, string $key): JsonResponse
    {
        $this->allow('settings.company.manage');

        // A key with no override at any level the caller could set is a
        // no-op that still has to name a real setting, not a typo.
        $this->resolver->definition($key);

        $data = $request->validate(['factory_id' => ['nullable', 'string', 'size:26']]);

        $factoryId = $this->requestedFactoryId($data['factory_id'] ?? null);

        Setting::where('company_id', $this->context->companyId())
            ->where('factory_id', $factoryId)
            ->whereNull('production_line_id')
            ->where('key', $key)
            ->delete();

        $this->resolver->flush();

        return ApiResponse::noContent();
    }

    /**
     * The company's own logo, shown in the sidebar mark and wherever else a
     * tenant's branding appears. Mirrors the shape of the platform's own
     * `PlatformTenantAccountApiController::updateLogo` (support staff
     * replacing a customer's logo on their behalf) but scoped to the
     * calling company itself rather than a route-bound `{company}` — this
     * is the tenant doing it themselves, gated the same as every other
     * company-profile write.
     */
    public function updateLogo(Request $request): JsonResponse
    {
        $this->allow('settings.company.manage');

        $data = $request->validate([
            // 2MB, not the platform-support endpoint's 512KB: this is a
            // tenant uploading their own real-world logo export, not a
            // support agent replacing one they already know is small.
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $company = Company::findOrFail($this->context->companyId());

        if ($company->logo_path !== null) {
            Storage::disk('public')->delete($company->logo_path);
        }

        $company->forceFill([
            'logo_path' => $data['logo']->store('company-logos', 'public'),
        ])->save();

        return ApiResponse::ok(['logo_url' => $company->fresh()->logoUrl()]);
    }

    /** Removes the company's logo, reverting the sidebar mark to its default. */
    public function destroyLogo(): JsonResponse
    {
        $this->allow('settings.company.manage');

        $company = Company::findOrFail($this->context->companyId());

        if ($company->logo_path !== null) {
            Storage::disk('public')->delete($company->logo_path);
            $company->forceFill(['logo_path' => null])->save();
        }

        return ApiResponse::noContent();
    }

    private function factoryId(Request $request): ?string
    {
        $requested = $request->query('factory_id');

        return is_string($requested) && $requested !== '' ? $this->requestedFactoryId($requested) : null;
    }

    private function requestedFactoryId(?string $factoryId): ?string
    {
        if ($factoryId === null) {
            return null;
        }

        if (! $this->context->canAccessFactory($factoryId)) {
            abort(403);
        }

        return $factoryId;
    }
}
