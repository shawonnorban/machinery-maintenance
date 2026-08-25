<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Let a notification belong to nobody's company.
 *
 * Every notification so far has been to somebody inside a tenant, so company_id
 * was NOT NULL and that was right. Platform staff belong to no company by
 * design (SRS §5), which meant the one group of people who most need telling
 * when a colleague opens support access to a customer were the one group the
 * notification system could not address.
 *
 * Null here means "the platform", not "unknown". The tenant scope excludes
 * these rows from every customer's view exactly as before, because a scope
 * matching on company_id never matches null.
 *
 * Raw SQL rather than ->change(): the column carries a foreign key, and
 * Laravel's column change drops and rebuilds the definition it was given
 * without it. MODIFY leaves the constraint alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE notifications MODIFY company_id CHAR(26) NULL');
    }

    public function down(): void
    {
        // Platform notifications have no company to move to, so they go. Coming
        // back down this migration with them in place would fail on the NOT
        // NULL constraint, which is a worse outcome than losing read messages.
        DB::table('notifications')->whereNull('company_id')->delete();

        DB::statement('ALTER TABLE notifications MODIFY company_id CHAR(26) NOT NULL');
    }
};
