<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Actions\SyncMenus;
use Illuminate\Console\Command;

/** Writes MenuRegistry to the menu tables (AnnotechERP 02/01). Run on every deploy. */
class SyncMenusCommand extends Command
{
    protected $signature = 'access:sync-menus';

    protected $description = 'Sync the menu tree and its flag → permission map from MenuRegistry';

    public function handle(SyncMenus $sync): int
    {
        $result = $sync->handle();
        $this->info(sprintf('Menus synced: %d modules, %d menus, %d flag permissions.', $result['modules'], $result['menus'], $result['permissions']));

        return self::SUCCESS;
    }
}
