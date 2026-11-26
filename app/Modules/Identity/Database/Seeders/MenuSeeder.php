<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Actions\SyncMenus;
use Illuminate\Database\Seeder;

/** The menu tree (AnnotechERP 02/01) — after permissions, which its flags name. */
class MenuSeeder extends Seeder
{
    public function run(SyncMenus $sync): void
    {
        $sync->handle();
    }
}
