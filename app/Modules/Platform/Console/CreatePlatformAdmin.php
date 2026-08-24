<?php

declare(strict_types=1);

namespace App\Modules\Platform\Console;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * The first way in (SRS 5).
 *
 * A command rather than a screen, and necessarily so: a screen for creating
 * platform administrators would have to be behind the platform area, which
 * needs a platform administrator to reach. Somebody has to be able to make the
 * first one from a shell on the server, which is the only place where being
 * able to do it is already proof of authority.
 *
 * It also promotes an existing account, which is the commoner case after the
 * first: support staff who already have a login.
 *
 *   php artisan platform:admin support@example.com --name="Rahim Uddin"
 *
 * Deliberately not seeded. A seeded platform account would exist with a known
 * address in every deployment of this codebase, including the customer's.
 */
class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:admin
                            {email : The account to create or promote}
                            {--name= : Required when the account does not exist yet}
                            {--revoke : Take platform access away instead of granting it}';

    protected $description = 'Grant or revoke platform administrator access for one account';

    public function handle(AuditRecorder $audit): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($this->option('revoke')) {
            return $this->revoke($user, $email, $audit);
        }

        $password = null;

        if ($user === null) {
            $name = (string) $this->option('name');

            if (trim($name) === '') {
                $this->error('No account with that address. Pass --name to create one.');

                return self::FAILURE;
            }

            // Shown once, on this terminal, for whoever is running it to pass
            // on. Not emailed: the address has not been verified, and a first
            // password sent to a mistyped one is a credential handed away.
            $password = Str::password(20, symbols: false);

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'status' => 'ACTIVE',
                'locale' => 'en',
            ]);
        }

        $user->forceFill(['is_platform_admin' => true])->save();

        $audit->event(
            'SECURITY_EVENT',
            ['reason' => 'PLATFORM_ADMIN_GRANTED', 'email' => $email],
            userId: $user->id,
            label: 'PLATFORM_ADMIN_GRANTED',
        );

        $this->info("Platform access granted to {$email}.");

        if ($password !== null) {
            $this->newLine();
            $this->line('  Password: '.$password);
            $this->comment('  Shown once. There is no way back to it.');
        }

        if (! $user->hasMfa()) {
            // Not enrolled from here: a second factor has to be set up on the
            // phone that will hold it, by the person who will use it. Saying so
            // beats letting them discover the redirect and think it is a fault
            // (SRS 50.3).
            $this->newLine();
            $this->comment('  Two-step sign-in is compulsory for platform accounts.');
            $this->comment('  First sign-in lands on the account screen to set it up.');
        }

        $this->newLine();
        $this->line('  Sign in, then go to /platform');

        return self::SUCCESS;
    }

    private function revoke(?User $user, string $email, AuditRecorder $audit): int
    {
        if ($user === null) {
            $this->error("No account with the address {$email}.");

            return self::FAILURE;
        }

        // The account itself is left alone. Platform access is a capability,
        // not an identity, and somebody who has stopped doing support may
        // still be a member of a company.
        $user->forceFill(['is_platform_admin' => false])->save();

        $audit->event(
            'SECURITY_EVENT',
            ['reason' => 'PLATFORM_ADMIN_REVOKED', 'email' => $email],
            userId: $user->id,
            label: 'PLATFORM_ADMIN_REVOKED',
        );

        $this->info("Platform access revoked for {$email}.");

        return self::SUCCESS;
    }
}
