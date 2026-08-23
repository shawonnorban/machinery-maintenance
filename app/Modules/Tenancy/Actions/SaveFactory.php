<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Asset\Models\Asset;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
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
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Factory
    {
        return Factory::create([
            'company_id' => $this->context->companyId(),
            'name' => $data['name'],
            'code' => strtoupper(trim((string) $data['code'])),
            'address' => $data['address'] ?? null,
            'timezone' => $data['timezone'] ?? config('app.display_timezone', 'Asia/Dhaka'),
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Factory $factory, array $data): Factory
    {
        $factory->update([
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'timezone' => $data['timezone'] ?? $factory->timezone,
        ]);

        return $factory->fresh();
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
}
