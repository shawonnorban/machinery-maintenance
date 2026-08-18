<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Database\Seeders\PermissionSeeder;
use App\Modules\Identity\Database\Seeders\RoleSeeder;
use Illuminate\Database\Seeder;

/**
 * Platform seed only: permissions, roles, and other rows that are identical
 * for every tenant (Seed Catalog 1).
 *
 * The garment industry seed and the demo tenant are separate seeders so a
 * production install never receives demo data.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }
}
