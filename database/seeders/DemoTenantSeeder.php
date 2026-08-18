<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Calendar\Models\FactoryCalendar;
use App\Modules\Calendar\Models\FactoryHoliday;
use App\Modules\Calendar\Models\Shift;
use App\Modules\Calendar\Models\ShiftBreak;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Settings\Actions\SetSetting;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * A realistic tenant for demonstrations, UAT and manual testing
 * (Seed Catalog 14).
 *
 * NEVER installed in production: run explicitly with
 *   php artisan db:seed --class=Database\\Seeders\\DemoTenantSeeder
 *
 * Two companies are created on purpose. Cross-tenant behaviour is invisible
 * with only one, and the company switcher cannot be exercised.
 */
class DemoTenantSeeder extends Seeder
{
    public const PASSWORD = 'password123';

    public function run(): void
    {
        $delta = $this->company('Delta Apparels Ltd', 'ডেল্টা অ্যাপারেলস লিমিটেড', 'DAL', 'en');
        $omega = $this->company('Omega Textiles Ltd', 'ওমেগা টেক্সটাইলস লিমিটেড', 'OTL', 'en');

        app(TenantContext::class)->set($delta->id);

        $dhaka = $this->factory($delta, 'ঢাকা ইউনিট ১', 'DHK');
        $gazipur = $this->factory($delta, 'গাজীপুর ইউনিট ২', 'GAZ');
        $this->locations($delta, $dhaka);
        $this->calendar($delta, $dhaka);
        $this->calendar($delta, $gazipur);
        $this->settings($delta, $dhaka);

        app(TenantContext::class)->set($omega->id);
        $narayanganj = $this->factory($omega, 'নারায়ণগঞ্জ ইউনিট', 'NGJ');
        $this->calendar($omega, $narayanganj);

        app(TenantContext::class)->set($delta->id);

        // One user per role, so every permission set can be compared in the UI.
        $owner = $this->user($delta, 'COMPANY_OWNER', 'owner@delta.test', 'Rahim Uddin');
        $this->user($delta, 'FACTORY_MANAGER', 'manager@delta.test', 'Nasrin Akter');
        $this->user($delta, 'MAINTENANCE_MANAGER', 'maintenance@delta.test', 'Kamrul Hasan');
        $this->user($delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test', 'Sabbir Ahmed');
        $this->user($delta, 'TECHNICIAN', 'technician@delta.test', 'Karim Mia');
        $this->user($delta, 'STORE_MANAGER', 'store@delta.test', 'Farhana Islam');
        $this->user($delta, 'STOREKEEPER', 'storekeeper@delta.test', 'Jashim Uddin');
        $this->user($delta, 'AUDITOR', 'auditor@delta.test', 'Tanvir Rahman');
        $this->user($delta, 'VIEWER', 'viewer@delta.test', 'Shirin Sultana');

        // A factory-scoped role: this manager reaches Dhaka only, which makes
        // factory scoping visible in the UI.
        $this->user($delta, 'MAINTENANCE_MANAGER', 'dhaka-only@delta.test', 'Mizanur Rahman', $dhaka->id);

        // The owner also belongs to Omega, so the company switcher appears.
        $this->addMembership($owner, $omega, 'COMPANY_ADMIN');

        $this->command?->newLine();
        $this->command?->info('Demo tenant ready. Password for every account: '.self::PASSWORD);
        $this->command?->table(
            ['Email', 'Role', 'Scope'],
            [
                ['owner@delta.test', 'Company Owner', 'Delta + Omega (switcher)'],
                ['manager@delta.test', 'Factory Manager', 'Delta, all factories'],
                ['maintenance@delta.test', 'Maintenance Manager', 'Delta, all factories'],
                ['engineer@delta.test', 'Maintenance Engineer', 'Delta, all factories'],
                ['technician@delta.test', 'Technician', 'Delta, all factories'],
                ['store@delta.test', 'Store Manager', 'Delta, all factories'],
                ['storekeeper@delta.test', 'Storekeeper', 'Delta, all factories'],
                ['auditor@delta.test', 'Auditor', 'Delta, read-only'],
                ['viewer@delta.test', 'Viewer', 'Delta, read-only'],
                ['dhaka-only@delta.test', 'Maintenance Manager', 'Delta, Dhaka factory only'],
            ],
        );
    }

    private function company(string $name, string $nameBn, string $code, string $locale): Company
    {
        return Company::updateOrCreate(
            ['code' => $code],
            [
                'name' => $locale === 'bn' ? $nameBn : $name,
                'legal_name' => $name,
                'base_currency' => 'BDT',
                'timezone' => 'Asia/Dhaka',
                'default_locale' => $locale,
            ],
        );
    }

    private function factory(Company $company, string $name, string $code): Factory
    {
        return Factory::withoutGlobalScope(TenantScope::class)->updateOrCreate(
            ['company_id' => $company->id, 'code' => $code],
            ['name' => $name, 'timezone' => 'Asia/Dhaka', 'status' => 'ACTIVE'],
        );
    }

    private function locations(Company $company, Factory $factory): void
    {
        $building = Building::updateOrCreate(
            ['company_id' => $company->id, 'factory_id' => $factory->id, 'code' => 'A'],
            ['name' => 'ভবন এ'],
        );

        $sewing = Department::updateOrCreate(
            ['company_id' => $company->id, 'factory_id' => $factory->id, 'code' => 'SEW'],
            ['name' => 'সেলাই বিভাগ'],
        );

        Department::updateOrCreate(
            ['company_id' => $company->id, 'factory_id' => $factory->id, 'code' => 'CUT'],
            ['name' => 'কাটিং বিভাগ'],
        );

        foreach ([1, 2, 3, 4, 5, 6] as $n) {
            ProductionLine::updateOrCreate(
                ['company_id' => $company->id, 'department_id' => $sewing->id, 'code' => "L{$n}"],
                ['name' => "লাইন {$n}"],
            );
        }

        unset($building);
    }

    /**
     * One 08:00-22:00 shift, Saturday to Thursday, Friday off, with an unpaid
     * lunch hour. This is the pattern the ADR-048 overnight case assumes.
     */
    private function calendar(Company $company, Factory $factory): void
    {
        FactoryCalendar::updateOrCreate(
            ['company_id' => $company->id, 'factory_id' => $factory->id, 'effective_from' => '2026-01-01'],
            ['operating_mode' => 'SHIFT_BASED', 'weekly_off_days' => [5]],
        );

        $shift = Shift::updateOrCreate(
            ['company_id' => $company->id, 'factory_id' => $factory->id, 'code' => 'DAY', 'effective_from' => '2026-01-01'],
            [
                'name' => 'দিনের শিফট',
                'start_time' => '08:00:00',
                'end_time' => '22:00:00',
                'days_of_week' => [1, 2, 3, 4, 6, 7],
                'status' => 'ACTIVE',
            ],
        );

        ShiftBreak::updateOrCreate(
            ['company_id' => $company->id, 'shift_id' => $shift->id, 'name' => 'দুপুরের বিরতি'],
            ['start_time' => '13:00:00', 'end_time' => '14:00:00', 'counts_as_operating_time' => false],
        );

        foreach ([
            '2026-02-21' => 'শহীদ দিবস',
            '2026-03-26' => 'স্বাধীনতা দিবস',
            '2026-12-16' => 'বিজয় দিবস',
        ] as $date => $name) {
            FactoryHoliday::updateOrCreate(
                ['company_id' => $company->id, 'factory_id' => $factory->id, 'date' => $date],
                ['name' => $name, 'is_working_day' => false],
            );
        }
    }

    private function settings(Company $company, Factory $dhaka): void
    {
        $set = app(SetSetting::class);

        $set->handle('locale.default', 'en');
        $set->handle('metrics.downtime_uses_shift_calendar', true);
        $set->handle('work_order.approval_cost_threshold', '50000');
        // Gazipur runs continuously; Dhaka keeps the shift calendar. A factory
        // override makes the resolution order visible in the settings screen.
        $set->handle('factory.operating_mode', 'SHIFT_BASED', factoryId: $dhaka->id);
    }

    private function user(
        Company $company,
        string $roleCode,
        string $email,
        string $name,
        ?string $factoryId = null,
    ): User {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => self::PASSWORD,
                'status' => 'ACTIVE',
                'timezone' => 'Asia/Dhaka',
                'locale' => $company->default_locale,
            ],
        );

        CompanyUser::updateOrCreate(
            ['company_id' => $company->id, 'user_id' => $user->id],
            ['status' => 'ACTIVE', 'is_default' => true],
        );

        $role = Role::whereNull('company_id')->where('code', $roleCode)->firstOrFail();

        UserRole::withoutGlobalScope(TenantScope::class)->updateOrCreate(
            [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role_id' => $role->id,
                'factory_id' => $factoryId,
            ],
            [],
        );

        return $user;
    }

    private function addMembership(User $user, Company $company, string $roleCode): void
    {
        CompanyUser::updateOrCreate(
            ['company_id' => $company->id, 'user_id' => $user->id],
            ['status' => 'ACTIVE', 'is_default' => false],
        );

        $role = Role::whereNull('company_id')->where('code', $roleCode)->firstOrFail();

        UserRole::withoutGlobalScope(TenantScope::class)->updateOrCreate(
            ['company_id' => $company->id, 'user_id' => $user->id, 'role_id' => $role->id, 'factory_id' => null],
            [],
        );
    }
}
