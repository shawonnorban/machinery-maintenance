<?php

declare(strict_types=1);

namespace App\Modules\Platform\Database\Seeders;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;

/**
 * This project's own fixed platform administrator (on direct request) —
 * unlike `platform:admin` (see that command's own docblock on why a
 * generic starter template should never seed one), this codebase is a
 * single, known deployment, and the account is meant to survive every
 * `migrate:fresh` unchanged rather than be re-minted with a fresh
 * one-time password each time.
 *
 * `updateOrCreate` keyed on email: re-running this after the account
 * already exists resets its password and admin flag back to these values
 * rather than leaving a diverged row in place.
 */
class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@noirban.com'],
            [
                'name' => 'Platform Admin',
                'password' => 'Norban@2026',
                'status' => 'ACTIVE',
                'locale' => 'en',
                'is_platform_admin' => true,
            ],
        );
    }
}
