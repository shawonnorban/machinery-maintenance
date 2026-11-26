<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Asset\Models\Asset;
use App\Modules\Billing\Services\EntitlementGuard;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A factory: the unit everything else in the product is scoped to (SRS 4).
 *
 * Its code is short and appears on every asset label and work order number, so
 * it is uppercased and fixed at creation. Renaming a factory is fine; renumbering
 * one would leave a shelf of printed labels pointing at a code that no longer
 * exists.
 *
 * The timezone belongs here rather than on the company. A group with a mill in
 * Dhaka and an office in Singapore has two clocks, and downtime measured on the
 * wrong one is downtime nobody recognises (ADR-046).
 */
class SaveFactory
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly EntitlementGuard $entitlements,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Factory
    {
        // In the action rather than the controller, so the API cannot walk
        // around it (ADR-066). Only a contract set to BLOCK stops anything.
        $this->entitlements->assertCanAdd('ACTIVE_FACTORIES');

        return Factory::create([
            'company_id' => $this->context->companyId(),
            'name' => $data['name'],
            'code' => strtoupper(trim((string) $data['code'])),
            'address' => $data['address'] ?? null,
            'timezone' => $data['timezone'] ?? config('app.display_timezone', 'Asia/Dhaka'),
            'status' => 'ACTIVE',
        ] + $this->profile($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Factory $factory, array $data): Factory
    {
        DB::transaction(function () use ($factory, $data): void {
            $old = (string) $factory->code;
            $new = isset($data['code']) ? strtoupper(trim((string) $data['code'])) : $old;
            $factory->update([
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'timezone' => $data['timezone'] ?? $factory->timezone,
                'code' => $new,
            ] + $this->profile($data, $factory));
            if ($new !== $old) {
                $this->renameCodeCopies($factory, $old, $new);
            }
        });

        return $factory->fresh();
    }

    /**
     * A factory's code may change (user, 2026-11-20); the places that keep it
     * as text follow it: the Factory cells of Order Summary orders and of
     * Pre-Budget requisitions and drafts (their columns read from the
     * factories list), and the factory's In Transit bin (`<code>-TRANSIT`).
     * Documents already numbered keep the number they were issued.
     */
    private function renameCodeCopies(Factory $factory, string $old, string $new): void
    {
        $company = $factory->company_id;
        $sheets = [['os_columns', 'os_orders', null], ['pb_columns', 'pb_requisitions', 'REQ']];
        $keysOf = fn (string $columns, ?string $sheet) => DB::table($columns)->where('company_id', $company)->where('source', 'factories')
            ->when($sheet, fn ($q) => $q->where('sheet', $sheet))->pluck('key')->all();
        foreach ($sheets as [$columns, $rows, $sheet]) {
            foreach ($keysOf($columns, $sheet) as $key) {
                DB::table($rows)->where('company_id', $company)->whereRaw('"values"->>? = ?', [$key, $old])
                    ->update(['values' => DB::raw("jsonb_set(\"values\", ARRAY['".str_replace("'", "''", $key)."'], to_jsonb('".str_replace("'", "''", $new)."'::text))")]);
            }
        }
        $draftKeys = $keysOf('pb_columns', 'REQ');
        foreach (DB::table('pb_requisition_drafts')->where('company_id', $company)->whereRaw('rows::text like ?', ['%"'.$old.'"%'])->get(['id', 'rows']) as $draft) {
            $list = json_decode((string) $draft->rows, true) ?: [];
            foreach ($list as &$row) {
                foreach ($draftKeys as $key) {
                    if (($row['values'][$key] ?? null) === $old) {
                        $row['values'][$key] = $new;
                    }
                }
            }
            unset($row);
            DB::table('pb_requisition_drafts')->where('id', $draft->id)->update(['rows' => json_encode($list)]);
        }
        DB::table('bins')->where('code', "{$old}-TRANSIT")
            ->whereIn('store_id', DB::table('stores')->whereIn('warehouse_id', DB::table('warehouses')->where('factory_id', $factory->id)->select('id'))->select('id'))
            ->update(['code' => "{$new}-TRANSIT"]);
    }

    /**
     * Close a factory, or reopen it.
     *
     * Never a delete: a closed factory still owns the work orders, costs and
     * breakdowns of everything that ever ran in it.
     */
    public function setStatus(Factory $factory, string $status): Factory
    {
        $factory->forceFill(['status' => $status])->save();

        return $factory->fresh();
    }

    /**
     * Removing a factory registered by mistake, and nothing else.
     */
    public function delete(Factory $factory): void
    {
        $assets = Asset::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('current_factory_id', $factory->id)
            ->count();

        if ($assets > 0) {
            throw ValidationException::withMessages([
                'code' => __('settings.factory_in_use', ['count' => $assets]),
            ])->status(409);
        }

        $factory->delete();
    }

    /**
     * Company Management's Factory columns (AnnotechERP 01/02). On update a key
     * the caller did not send keeps its value — an older client that only knows
     * name/address/timezone must not wipe them.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function profile(array $data, ?Factory $factory = null): array
    {
        $out = [];

        foreach (['contact', 'established_date', 'area_sqft', 'remarks'] as $key) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $data[$key];
            } elseif ($factory === null) {
                $out[$key] = null;
            }
        }

        return $out;
    }
}
