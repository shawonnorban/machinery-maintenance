<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Material Procurement is its own module, not the old project's Procurement:
 * a database that created its masters under the old names gets the module's
 * names. A fresh database already creates mp_items / mp_suppliers.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['procurement_items' => 'mp_items', 'procurement_suppliers' => 'mp_suppliers'] as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
                DB::statement("ALTER INDEX IF EXISTS {$old}_code_unique RENAME TO {$new}_code_unique");
            }
        }
    }

    public function down(): void
    {
        // The old names belonged to a module the ERP no longer uses; nothing to restore.
    }
};
