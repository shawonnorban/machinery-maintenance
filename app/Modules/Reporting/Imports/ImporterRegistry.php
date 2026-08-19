<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Imports;

use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Imports\Types\AssetImporter;
use App\Modules\Reporting\Imports\Types\LocationImporter;
use App\Modules\Reporting\Imports\Types\MaintenanceHistoryImporter;
use App\Modules\Reporting\Imports\Types\SparePartImporter;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * What can be imported (SRS 33).
 *
 * Ordered as a factory should work through them: locations before assets,
 * because an asset row names its location by code, and spare parts before
 * history for the same reason. The screens say so explicitly — nothing here can
 * enforce an order across separate uploads, and a file that fails every row for
 * one missing prerequisite is the fastest way to lose somebody's confidence in
 * the feature.
 *
 * Vendor import from SRS 33 is absent: the Vendor module does not exist yet
 * (step 28), and offering an import that writes nowhere is worse than not
 * offering it.
 */
class ImporterRegistry
{
    /** @var list<class-string<Importer>> */
    private const IMPORTERS = [
        LocationImporter::class,
        AssetImporter::class,
        SparePartImporter::class,
        MaintenanceHistoryImporter::class,
    ];

    /** @var array<string, Importer>|null */
    private ?array $resolved = null;

    /**
     * @return array<string, Importer>
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $importers = [];

        foreach (self::IMPORTERS as $class) {
            $importer = app($class);
            $importers[$importer->type()] = $importer;
        }

        return $this->resolved = $importers;
    }

    public function find(string $type): Importer
    {
        return $this->all()[$type] ?? throw new InvalidArgumentException("Unknown import type [{$type}].");
    }

    public function has(string $type): bool
    {
        return isset($this->all()[$type]);
    }

    /**
     * @return Collection<string, Importer>
     */
    public function availableTo(User $user): Collection
    {
        return collect($this->all())->filter(fn (Importer $importer) => $importer->allows($user));
    }
}
