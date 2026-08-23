<?php

declare(strict_types=1);

namespace App\Modules\Asset\Actions;

use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Services\QrTokenGenerator;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\Tenancy\Models\Section;
use App\Modules\Tenancy\Models\Workstation;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Where machines live (ADR-052, SRS 8).
 *
 * One addressable entity rather than a polymorphic pointer at a building or a
 * workstation: everything above the factory is optional, so a factory that
 * tracks its floor as a flat list of line codes is not forced to model a
 * hierarchy it does not use, and one that models all of it still has a single
 * row an asset can point at.
 *
 * Two things happen here that nothing else does. A location gets its QR token,
 * without which the label printed for it scans to nothing. And full_path is
 * rebuilt — the denormalised line every screen shows instead of a bare name,
 * which was read in six places and written in none.
 */
class SaveAssetLocation
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly QrTokenGenerator $qr,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AssetLocation
    {
        $values = $this->values($data);

        return DB::transaction(function () use ($values): AssetLocation {
            $location = new AssetLocation;

            $location->fill($values);
            $location->qr_code = $this->qr->forLocation($this->context->companyId());
            $location->save();

            return $this->refreshPath($location);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AssetLocation $location, array $data): AssetLocation
    {
        return DB::transaction(function () use ($location, $data): AssetLocation {
            $location->fill($this->values($data));
            $location->save();

            return $this->refreshPath($location);
        });
    }

    public function setStatus(AssetLocation $location, string $status): AssetLocation
    {
        $location->forceFill(['status' => $status])->save();

        return $location->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function values(array $data): array
    {
        $factory = $this->assertFactoryReachable($data['factory_id'] ?? null);

        $values = [
            'factory_id' => $factory->id,
            'name' => $data['name'],
            'code' => strtoupper(trim((string) $data['code'])),
            'status' => $data['status'] ?? 'ACTIVE',
        ];

        // Each optional level is checked against the factory it claims to sit
        // in. A line from another factory would put the machine on a floor it
        // is not standing on.
        foreach ([
            'building_id' => Building::class,
            'floor_id' => Floor::class,
            'department_id' => Department::class,
            'section_id' => Section::class,
            'production_line_id' => ProductionLine::class,
            'workstation_id' => Workstation::class,
        ] as $field => $model) {
            $values[$field] = $this->optionalReference($model, $data[$field] ?? null, $field);
        }

        return $values;
    }

    private function assertFactoryReachable(?string $factoryId): Factory
    {
        $factory = $factoryId === null ? null : Factory::find($factoryId);

        if ($factory === null || ! $this->context->canAccessFactory($factory->id)) {
            throw ValidationException::withMessages([
                'factory_id' => __('asset.location_factory_unavailable'),
            ]);
        }

        return $factory;
    }

    /**
     * @param  class-string  $model
     */
    private function optionalReference(string $model, mixed $id, string $field): ?string
    {
        if (! filled($id)) {
            return null;
        }

        // Tenant-scoped models, so a row from another company is simply not
        // found and the reference is refused rather than silently stored.
        if (! $model::query()->whereKey($id)->exists()) {
            throw ValidationException::withMessages([
                $field => __('asset.location_reference_unavailable'),
            ]);
        }

        return (string) $id;
    }

    /**
     * The display line: factory, then whichever levels were filled in.
     *
     * Denormalised on purpose — it is shown on every scan, every asset screen
     * and every transfer, and joining six tables to render one line of text on
     * a list of two hundred assets is not worth the correctness it buys.
     */
    private function refreshPath(AssetLocation $location): AssetLocation
    {
        $location->load([
            'factory:id,name', 'building:id,name', 'floor:id,name',
            'department:id,name', 'section:id,name',
            'productionLine:id,name', 'workstation:id,name',
        ]);

        $location->forceFill([
            'full_path' => mb_substr($location->buildFullPath(), 0, 512),
        ])->save();

        return $location->fresh();
    }
}
