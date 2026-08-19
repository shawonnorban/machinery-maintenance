<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Actions\CreateAsset;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Asset\Models\Manufacturer;
use App\Modules\Asset\Services\QrTokenGenerator;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Breakdown\Actions\TransitionBreakdown;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\FailureCode;
use App\Modules\Breakdown\Models\RootCause;
use App\Modules\Calendar\Models\FactoryCalendar;
use App\Modules\Calendar\Models\FactoryHoliday;
use App\Modules\Calendar\Models\Shift;
use App\Modules\Calendar\Models\ShiftBreak;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Maintenance\Models\ChecklistItem;
use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Maintenance\Models\MaintenanceTemplateVersion;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Settings\Actions\SetSetting;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\WorkOrder\Actions\AssignTechnicians;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Actions\RecordChecklistResult;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use App\Modules\WorkOrder\Models\LaborRateGrade;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
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

        $dhaka = $this->factory($delta, 'Dhaka Unit 1', 'DHK');
        $gazipur = $this->factory($delta, 'Gazipur Unit 2', 'GAZ');
        $this->locations($delta, $dhaka);
        $this->calendar($delta, $dhaka);
        $this->calendar($delta, $gazipur);
        $this->settings($delta, $dhaka);

        app(TenantContext::class)->set($omega->id);
        $narayanganj = $this->factory($omega, 'Narayanganj Unit', 'NGJ');
        $this->calendar($omega, $narayanganj);

        app(TenantContext::class)->set($delta->id);

        // One user per role, so every permission set can be compared in the UI.
        $owner = $this->user($delta, 'COMPANY_OWNER', 'owner@delta.test', 'Rahim Uddin');
        $this->user($delta, 'FACTORY_MANAGER', 'manager@delta.test', 'Nasrin Akter');
        $maintenanceManager = $this->user($delta, 'MAINTENANCE_MANAGER', 'maintenance@delta.test', 'Kamrul Hasan');
        $this->user($delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test', 'Sabbir Ahmed');
        $technicianUser = $this->user($delta, 'TECHNICIAN', 'technician@delta.test', 'Karim Mia');
        $this->user($delta, 'STORE_MANAGER', 'store@delta.test', 'Farhana Islam');
        $this->user($delta, 'STOREKEEPER', 'storekeeper@delta.test', 'Jashim Uddin');
        $this->user($delta, 'AUDITOR', 'auditor@delta.test', 'Tanvir Rahman');
        $this->user($delta, 'VIEWER', 'viewer@delta.test', 'Shirin Sultana');

        $this->technicians($delta, $dhaka, $this->laborGrades($delta), $technicianUser);

        // Machines and work in every interesting state, so the screens can be
        // judged against real data rather than against empty tables.
        $assets = $this->assets($delta, $dhaka);
        $this->workOrders($delta, $dhaka, $assets, $maintenanceManager);
        $this->breakdowns($delta, $assets, $maintenanceManager);

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
            ['name' => 'Building A'],
        );

        $sewing = Department::updateOrCreate(
            ['company_id' => $company->id, 'factory_id' => $factory->id, 'code' => 'SEW'],
            ['name' => 'Sewing Department'],
        );

        Department::updateOrCreate(
            ['company_id' => $company->id, 'factory_id' => $factory->id, 'code' => 'CUT'],
            ['name' => 'Cutting Department'],
        );

        foreach ([1, 2, 3, 4, 5, 6] as $n) {
            ProductionLine::updateOrCreate(
                ['company_id' => $company->id, 'department_id' => $sewing->id, 'code' => "L{$n}"],
                ['name' => "Line {$n}"],
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
                'name' => 'Day shift',
                'start_time' => '08:00:00',
                'end_time' => '22:00:00',
                'days_of_week' => [1, 2, 3, 4, 6, 7],
                'status' => 'ACTIVE',
            ],
        );

        ShiftBreak::updateOrCreate(
            ['company_id' => $company->id, 'shift_id' => $shift->id, 'name' => 'Lunch break'],
            ['start_time' => '13:00:00', 'end_time' => '14:00:00', 'counts_as_operating_time' => false],
        );

        foreach ([
            '2026-02-21' => 'Language Martyrs Day',
            '2026-03-26' => 'Independence Day',
            '2026-12-16' => 'Victory Day',
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

    /**
     * Labour rate grades (ADR-065, Seed Catalog 13).
     *
     * Grades, not wages. Two technicians on the same grade cost the same by
     * design: maintenance needs comparable cost per machine, and putting real
     * pay here would make a maintenance tool an HR data store (SRS 3.3).
     */
    private function laborGrades(Company $company): array
    {
        $grades = [];

        foreach ([
            ['Helper', 'G1', '60.0000'],
            ['Technician', 'G2', '110.0000'],
            ['Senior Technician', 'G3', '160.0000'],
            ['Engineer', 'G4', '240.0000'],
        ] as [$name, $code, $rate]) {
            $grades[$code] = LaborRateGrade::updateOrCreate(
                ['company_id' => $company->id, 'code' => $code, 'effective_from' => '2026-01-01'],
                [
                    'name' => $name,
                    'standard_hourly_rate' => $rate,
                    // Twice the ordinary rate is the common Bangladesh Labour
                    // Act treatment.
                    'overtime_multiplier' => '2.0000',
                    'currency' => 'BDT',
                    'active' => true,
                ],
            );
        }

        return $grades;
    }

    /**
     * Technicians. Enough of them, on different grades, that a work order's cost
     * breakdown and the capacity check are both exercisable.
     *
     * @param  array<string, LaborRateGrade>  $grades
     */
    private function technicians(Company $company, Factory $factory, array $grades, ?User $linkedUser): void
    {
        $roster = [
            ['Karim Mia', 'EMP-1001', 'G2', 'Sewing machines', 5],
            ['Jahangir Alam', 'EMP-1002', 'G3', 'Electrical', 5],
            ['Sumon Sheikh', 'EMP-1003', 'G1', 'General', 8],
            ['Ruhul Amin', 'EMP-1004', 'G3', 'Boiler and generator', 4],
            ['Abdul Karim', 'EMP-1005', 'G4', 'Compressor and HVAC', 3],
        ];

        foreach ($roster as $index => [$name, $employeeId, $gradeCode, $specialisation, $limit]) {
            Technician::updateOrCreate(
                ['company_id' => $company->id, 'employee_id' => $employeeId],
                [
                    'factory_id' => $factory->id,
                    // Only the first is linked to a login: a technician may
                    // exist without an account, and a supervisor records their
                    // time for them (ERD Section 16).
                    'user_id' => $index === 0 ? $linkedUser?->id : null,
                    'labor_grade_id' => $grades[$gradeCode]->id,
                    'name' => $name,
                    'phone' => '+8801'.str_pad((string) (700000000 + $index), 9, '0'),
                    'specialization' => $specialisation,
                    'joining_date' => '2024-01-15',
                    'max_concurrent_work_orders' => $limit,
                    'status' => 'ACTIVE',
                ],
            );
        }
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

    /**
     * Commissioned machines on Line 3.
     *
     * Driven through the real status transitions rather than inserted as
     * RUNNING, so the status history is populated and the asset timeline on the
     * detail screen has something in it.
     *
     * @return list<Asset>
     */
    private function assets(Company $company, Factory $factory): array
    {
        $location = AssetLocation::firstOrCreate(
            ['factory_id' => $factory->id, 'code' => $factory->code.'-L3'],
            [
                'name' => 'Line 3',
                'qr_code' => app(QrTokenGenerator::class)->forLocation($company->id),
                'full_path' => $factory->name.' › Building A › Sewing Department › Line 3',
            ],
        );

        $type = AssetType::where('code', 'SEWING')->whereNull('company_id')->firstOrFail();
        $category = AssetCategory::where('code', 'LOCKSTITCH')->whereNull('company_id')->firstOrFail();
        $juki = Manufacturer::where('code', 'JUKI')->whereNull('company_id')->first();

        $create = app(CreateAsset::class);
        $status = app(ChangeAssetStatus::class);
        $assets = [];

        foreach ([
            'Juki DDL-9000C', 'Juki DDL-8700', 'Brother S-7300A',
            'Juki MO-6714S', 'Pegasus M700', 'Siruba F007K',
        ] as $index => $name) {
            $code = sprintf('SEW-%s-%05d', $factory->code, 412 + $index);

            if (Asset::where('asset_code', $code)->exists()) {
                $assets[] = Asset::where('asset_code', $code)->firstOrFail();

                continue;
            }

            $asset = $create->handle([
                'asset_type_id' => $type->id,
                'asset_category_id' => $category->id,
                'manufacturer_id' => $juki?->id,
                'asset_code' => $code,
                'name' => $name,
                // A mix, so the criticality filter and the
                // verification-required rule both have something to act on.
                'criticality' => $index % 3 === 0 ? 'HIGH' : 'MEDIUM',
                'current_factory_id' => $factory->id,
                'asset_location_id' => $location->id,
                'acquisition_cost' => '285000',
                'currency' => 'BDT',
            ]);

            foreach (['PURCHASED', 'INSTALLED', 'COMMISSIONED', 'RUNNING'] as $step) {
                $asset = $status->handle($asset, $step);
            }

            $assets[] = $asset;
        }

        return $assets;
    }

    /**
     * One work order in each interesting state.
     *
     * Every screen in the module is only judgeable against real data: an empty
     * queue tells you nothing about whether the hold banner, the checklist
     * progress counter or the disabled Complete button work.
     *
     * Driven through the actual transitions for the same reason as the assets —
     * inserting a row with status IN_PROGRESS would leave no history, no
     * assignment and no actual_start, which is not what an in-progress work
     * order looks like.
     *
     * @param  list<Asset>  $assets
     */
    private function workOrders(Company $company, Factory $factory, array $assets, User $raisedBy): void
    {
        if ($assets === [] || WorkOrder::where('factory_id', $factory->id)->exists()) {
            return;
        }

        $template = MaintenanceTemplate::whereNull('company_id')
            ->get()
            ->first(fn (MaintenanceTemplate $t) => $t->currentVersion() !== null);

        $version = $template?->currentVersion();
        $preventive = MaintenanceType::whereNull('company_id')->where('code', 'PREVENTIVE')->firstOrFail();

        // The technician who has a login, so "My work" shows something when you
        // sign in as technician@delta.test.
        $linked = Technician::whereNotNull('user_id')->orderBy('employee_id')->first();
        $others = Technician::whereNull('user_id')->orderBy('employee_id')->get();

        $create = app(CreateWorkOrder::class);
        $transition = app(TransitionWorkOrder::class);
        $assign = app(AssignTechnicians::class);
        $record = app(RecordChecklistResult::class);

        foreach ([
            ['DRAFT', 'HIGH', -2],
            ['SCHEDULED', 'MEDIUM', -1],
            ['ASSIGNED', 'CRITICAL', 0],
            ['IN_PROGRESS', 'HIGH', 0],
            ['ON_HOLD', 'MEDIUM', 1],
            ['COMPLETED', 'LOW', 2],
        ] as $index => [$target, $priority, $dayOffset]) {
            $workOrder = $create->handle([
                'asset_id' => $assets[$index]->id,
                'maintenance_type_id' => $preventive->id,
                'template_version_id' => $version?->id,
                'title' => $template?->name ?? 'Preventive service',
                'priority' => $priority,
                'scheduled_start' => CarbonImmutable::now()->addDays($dayOffset)->setTime(9, 0),
            ], $raisedBy->id);

            if ($target === 'DRAFT') {
                continue;
            }

            $workOrder = $transition->schedule($workOrder, $raisedBy->id);

            if ($target === 'SCHEDULED') {
                continue;
            }

            // The linked technician gets the three states worth looking at on
            // the mobile screen; the rest are spread across the roster.
            $technician = in_array($target, ['ASSIGNED', 'IN_PROGRESS', 'ON_HOLD'], true)
                ? $linked
                : ($others[$index % max($others->count(), 1)] ?? $linked);

            if ($technician === null) {
                continue;
            }

            $assign->handle($workOrder->fresh(), [$technician->id], $raisedBy->id);

            if ($target === 'ASSIGNED') {
                continue;
            }

            $workOrder = $transition->start($workOrder->fresh(), $raisedBy->id);

            if ($target === 'IN_PROGRESS') {
                continue;
            }

            if ($target === 'ON_HOLD') {
                // The reason that matters: it makes a spare-part shortage
                // visible as its own cause rather than as slow repair work.
                $transition->hold($workOrder, 'AWAITING_PARTS', $raisedBy->id, 'Bobbin case on order from vendor');

                continue;
            }

            $this->answerChecklist($record, $workOrder, $version, $raisedBy);
            $transition->complete($workOrder->fresh(), $raisedBy->id);
        }
    }

    /**
     * Answers every required item so the work order can legitimately complete.
     * Completion is gated on this, and skipping the gate in a seeder would mean
     * the demo shows a state the application cannot actually produce.
     */
    private function answerChecklist(
        RecordChecklistResult $record,
        WorkOrder $workOrder,
        ?MaintenanceTemplateVersion $version,
        User $user,
    ): void {
        if ($version === null) {
            return;
        }

        foreach ($version->items()->where('required', true)->orderBy('sequence')->get() as $item) {
            $record->handle($workOrder->fresh(), [
                'checklist_item_id' => $item->id,
                'result' => 'PASS',
                // A mid-tolerance reading, so nothing is forced to FAIL by the
                // tolerance rule.
                'numeric_value' => $item->isNumeric() ? $this->midTolerance($item) : null,
                'text_value' => match ($item->input_type) {
                    'TEXT' => 'Checked, no issue found',
                    'CHOICE' => $item->options_json[0] ?? null,
                    default => null,
                },
            ], $user->id);
        }
    }

    /**
     * Breakdowns in a spread of states, plus a machine with a history of
     * repeated failures.
     *
     * The repeat offender matters most: a list of individual breakdowns hides
     * the machine that has failed four times this month, and that machine is a
     * replacement decision nobody has made yet (SRS 16).
     *
     * @param  list<Asset>  $assets
     */
    private function breakdowns(Company $company, array $assets, User $reporter): void
    {
        if ($assets === [] || Breakdown::where('company_id', $company->id)->exists()) {
            return;
        }

        $report = app(ReportBreakdown::class);
        $transition = app(TransitionBreakdown::class);
        $factoryTimezone = Factory::find($assets[0]->current_factory_id)?->timezone ?? 'Asia/Dhaka';
        $wear = RootCause::whereNull('company_id')->where('code', 'NORMAL_WEAR')->first();

        // One machine, four closed failures over a month, all the same cause.
        // This is what the repeat-offender report exists to surface.
        $repeat = $assets[0];
        $bearing = FailureCode::whereNull('company_id')->where('code', 'BEARING_FAILURE')->first();

        foreach ([28, 21, 14, 7] as $daysAgo) {
            // Expressed on the factory clock, then stored as the instant it
            // names. 10:15 in Dhaka is mid-shift; 10:15 UTC would be a quarter
            // past four in the afternoon there.
            $at = CarbonImmutable::now($factoryTimezone)
                ->subDays($daysAgo)
                ->setTime(10, 15)
                ->setTimezone('UTC');

            $breakdown = $report->handle([
                'asset_id' => $repeat->id,
                'problem_description' => 'Loud grinding from the head, machine seizes under load',
                'failure_at' => $at,
                'reported_at' => $at->addMinutes(4),
            ], $reporter->id);

            $breakdown = $transition->acknowledge($breakdown, $reporter->id, $at->addMinutes(12));
            $breakdown = $transition->startRepair($breakdown, $reporter->id, $at->addMinutes(25));
            $breakdown = $transition->completeRepair($breakdown, $reporter->id, $at->addMinutes(95));
            $breakdown = $transition->resumeProduction($breakdown, $reporter->id, $at->addMinutes(105));

            $transition->close($breakdown, [
                'failure_code_id' => $bearing?->id,
                'root_cause_id' => $wear?->id,
                'corrective_action' => 'Head bearing replaced, shaft realigned',
                'preventive_action' => 'Add weekly lubrication check to the PM checklist',
            ], $reporter->id);

            // Back in service, so the next failure counts as its own event
            // rather than being linked as a recurrence.
            $machine = Asset::find($repeat->id);

            if ($machine !== null && $machine->status !== 'RUNNING' && $machine->canTransitionTo('RUNNING')) {
                app(ChangeAssetStatus::class)->handle($machine, 'RUNNING', $reporter->id, 'Back in service', 'BREAKDOWN');
            }
        }

        // Two open breakdowns at different points in the chain, so the queue and
        // the action bar both have something real to show.
        $open = $report->handle([
            'asset_id' => $assets[1]->id,
            'problem_description' => 'Needle bar jammed, thread shredding',
            'failure_at' => CarbonImmutable::now()->subHours(3),
            'reported_at' => CarbonImmutable::now()->subHours(3)->addMinutes(6),
        ], $reporter->id);

        $transition->acknowledge($open, $reporter->id, CarbonImmutable::now()->subHours(2));

        $inRepair = $report->handle([
            'asset_id' => $assets[2]->id,
            'problem_description' => 'Motor overheating and cutting out after ten minutes',
            'failure_at' => CarbonImmutable::now()->subHours(6),
            'reported_at' => CarbonImmutable::now()->subHours(6)->addMinutes(3),
        ], $reporter->id);

        $inRepair = $transition->acknowledge($inRepair, $reporter->id, CarbonImmutable::now()->subHours(5));
        $transition->startRepair($inRepair, $reporter->id, CarbonImmutable::now()->subHours(4));
    }

    private function midTolerance(ChecklistItem $item): string
    {
        $min = $item->tolerance_min !== null ? (float) $item->tolerance_min : null;
        $max = $item->tolerance_max !== null ? (float) $item->tolerance_max : null;

        $value = match (true) {
            $min !== null && $max !== null => ($min + $max) / 2,
            $min !== null => $min + 1,
            $max !== null => $max - 1,
            default => 1.0,
        };

        return number_format($value, 4, '.', '');
    }
}
