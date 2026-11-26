<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Console;

use App\Modules\HrPayroll\Models\HrEmployee;
use App\Modules\HrPayroll\Models\HrEmployeeAssignment;
use App\Modules\HrPayroll\Support\SalaryStructures;
use App\Modules\Identity\Actions\ManageCompanyUser;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Norban's users into a company as employees with a login (user, 2026-11-20):
 * each becomes an HR employee (card no = Norban's employee id) posted to the
 * Norban factory, its department and designation (made where the company has
 * none of that name), and gets a sign-in — Norban's username and e-mail, the
 * given password — with a role matched to Norban's: Super Admin / Admin →
 * Company Admin, Md / Manager → Factory Manager, everyone else → Viewer.
 *
 * Norban keeps no salary and many NIDs are missing or malformed, so the
 * employee is filed on a Staff salary structure with Gross 0 and no NID where Norban's is not 10/13/17
 * digits (user: bring everyone, leave those blank) — HR fills them on edit.
 * Run again, it adds only who is not there yet (by card no / e-mail).
 *
 *   php artisan hr:import-norban-users DAL storage/app/private/norban_users.json
 */
class ImportNorbanUsers extends Command
{
    protected $signature = 'hr:import-norban-users {company : Company code} {file : Norban users JSON (exported from Norban)} {--password=Norban123 : The sign-in password every new login gets}';

    protected $description = 'Bring Norban\'s users in as HR employees with a login';

    /** Norban department => this ERP's department name. */
    private const DEPARTMENTS = [
        'Admin & HR' => 'HR & Administration', 'Admin (Assistant)' => 'HR & Administration', 'Admin (Driver)' => 'HR & Administration',
        'IT' => 'IT', 'Accounts & Finance' => 'Accounts & Finance', 'Audit' => 'Audit', 'Commercial' => 'Commercial Department',
        'Procurement' => 'Procurement Department', 'Merchandising' => 'Merchandising Department', 'Design' => 'Design & Product Development',
        'CAD' => 'CAD & Pattern Department', 'MIS' => 'MIS', 'Production' => 'Sewing / Production Department',
    ];

    /** Norban role => this ERP's role code, highest first. */
    private const ROLES = ['Super Admin' => 'COMPANY_ADMIN', 'Admin' => 'COMPANY_ADMIN', 'Md' => 'FACTORY_MANAGER', 'Manager' => 'FACTORY_MANAGER'];

    public function handle(TenantContext $context): int
    {
        $company = Company::query()->where('code', $this->argument('company'))->first();
        $data = json_decode((string) @file_get_contents(base_path((string) $this->argument('file'))), true);
        if (! $company || ! is_array($data['users'] ?? null)) {
            $this->error('No such company, or the file holds no users.');

            return self::FAILURE;
        }
        $c = $company->id;
        $context->forget();
        // Every factory of the company: a factory role (Viewer, Factory Manager) is given at the Norban factory.
        $context->set($c, DB::table('factories')->where('company_id', $c)->pluck('id')->all());
        $owner = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')->where('user_roles.company_id', $c)->where('roles.name', 'COMPANY_OWNER')->value('user_roles.user_id');
        // A salary structure Quick Registration offers (gazette + employment type): a Staff one, else the first.
        $structures = SalaryStructures::all();
        $staff = DB::table('hr_employment_types')->where('company_id', $c)->where('name', 'Staff')->value('id');
        $pick = collect($structures)->first(fn ($s) => str_ends_with($s['value'], ":{$staff}")) ?? ($structures[0] ?? null);
        [$gazette, $type] = $pick ? explode(':', $pick['value']) : [null, null];
        $roles = collect(['COMPANY_ADMIN', 'FACTORY_MANAGER', 'VIEWER'])->mapWithKeys(fn ($n) => [$n => \App\Modules\Identity\Support\RoleProvisioner::resolve($n, $c)?->id])->filter();
        if (! $gazette || ! $type || $roles->count() < 3) {
            $this->error('The company needs a salary structure (HR → Gazette) and the Company Admin / Factory Manager / Viewer roles.');

            return self::FAILURE;
        }
        $manage = app(ManageCompanyUser::class);
        $password = (string) $this->option('password');
        $made = ['employees' => 0, 'logins' => 0];
        $notes = [];

        foreach ($data['users'] as $u) {
            $card = trim((string) $u['emp_id']) ?: null;
            $email = mb_strtolower(trim((string) $u['email']));
            try {
                DB::transaction(function () use ($u, $c, $card, $email, $owner, $gazette, $type, $roles, $manage, $password, &$made, &$notes): void {
                    $factory = $this->factory($c, (string) $u['factory']);
                    $employee = $card ? HrEmployee::query()->where('card_no', $card)->first() : null;
                    if (! $employee) {
                        // Counted afresh each time: a user turned away (rolled back) leaves no gap in the numbers.
                        $last = (int) HrEmployee::query()->where('employee_code', 'like', 'EMP-%')->pluck('employee_code')->map(fn ($x) => (int) substr($x, 4))->max();
                        $code = 'EMP-'.str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT);
                        $nid = preg_replace('/\s+/', '', (string) $u['nid_number']);
                        $mobile = preg_replace('/^\+?880/', '0', preg_replace('/[\s-]/', '', (string) $u['phone']));
                        $placement = [
                            'factory_id' => $factory,
                            'department_id' => $this->department($c, $factory, self::DEPARTMENTS[$u['department'] ?? ''] ?? ($u['department'] ?: 'HR & Administration')),
                            'designation_id' => $this->designation($c, trim((string) $u['designation']) ?: 'Staff'),
                        ];
                        $employee = HrEmployee::query()->create([
                            'company_id' => $c, 'employee_code' => $code, 'card_no' => $card ?? $code, 'name' => $u['full_name'],
                            'nid' => preg_match('/^(\d{10}|\d{13}|\d{17})$/', $nid) ? $nid : null,
                            'mobile' => preg_match('/^01\d{9}$/', $mobile) ? $mobile : null, 'email' => $email ?: null,
                            'joining_date' => $u['join_date'] ?: null, 'status' => $u['is_active'] ? 'ACTIVE' : 'INACTIVE',
                            'father_name' => $u['father_name'] ?: null, 'mother_name' => $u['mother_name'] ?: null,
                            'present_address' => $u['address'] ?: null, 'permanent_address' => $u['permanent_address'] ?: null,
                            'gazette_id' => $gazette, 'employment_type_id' => $type, 'gross_a' => 0, 'gross_b' => 0, 'created_by' => $owner,
                        ] + $placement);
                        HrEmployeeAssignment::query()->create(['company_id' => $c, 'employee_id' => $employee->id, 'effective_from' => $u['join_date'] ?: now()->toDateString(), 'created_by' => $owner] + $placement);
                        $made['employees']++;
                    }

                    if ($email === '') {
                        return;
                    }
                    $user = User::query()->where('email', $email)->first();
                    if ($user && DB::table('company_users')->where('company_id', $c)->where('user_id', $user->id)->exists()) {
                        return;
                    }
                    $username = mb_strtolower(trim((string) $u['username'])) ?: null;
                    if ($username && User::query()->where('username', $username)->exists()) {
                        $notes[] = "{$u['full_name']}: username {$username} is taken, signs in by e-mail";
                        $username = null;
                    }
                    $role = 'VIEWER';
                    foreach (self::ROLES as $norban => $ours) {
                        if (in_array($norban, $u['roles'] ?? [], true)) {
                            $role = $ours;
                            break;
                        }
                    }
                    $manage->invite(array_filter([
                        'name' => $u['full_name'], 'email' => $email, 'phone' => $u['phone'] ?: null, 'password' => $password,
                        'username' => $username, 'user_type' => 'INTERNAL', 'employee_code' => $employee->employee_code,
                        'must_change_password' => false, 'locale' => 'en', 'changed_by' => $owner,
                    ], fn ($v) => $v !== null), [$roles[$role]], $factory);
                    if ($user) {
                        $notes[] = "{$u['full_name']}: {$email} already had an account (kept its own password)";
                    }
                    $made['logins']++;
                });
            } catch (ValidationException $e) {
                $notes[] = "{$u['full_name']}: ".collect($e->errors())->flatten()->implode(' ');
            } catch (Throwable $e) {
                $notes[] = "{$u['full_name']}: ".Str::limit($e->getMessage(), 200);
            }
        }

        $this->info("{$company->code}: {$made['employees']} employee(s), {$made['logins']} login(s)");
        foreach ($notes as $n) {
            $this->warn("  {$n}");
        }

        return self::SUCCESS;
    }

    /** The company's factory of Norban's factory name (NFL), else its first. */
    private function factory(string $c, string $name): string
    {
        return DB::table('factories')->where('company_id', $c)->whereRaw('upper(name) = upper(?)', [$name])->value('id')
            ?? DB::table('factories')->where('company_id', $c)->orderBy('code')->value('id');
    }

    /** A department of that name in the factory, made there when missing (code borrowed from a namesake elsewhere). */
    private function department(string $c, string $factory, string $name): string
    {
        $q = fn () => DB::table('departments')->where('company_id', $c);
        if ($id = $q()->where('factory_id', $factory)->whereRaw('lower(name) = lower(?)', [$name])->value('id')) {
            return $id;
        }
        $base = $q()->whereRaw('lower(name) = lower(?)', [$name])->value('code')
            ?? (Str::upper(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($name))) ?: 'DEPT');
        $base = Str::limit($base, 12, '');
        $code = $base;
        for ($n = 2; $q()->where('factory_id', $factory)->where('code', $code)->exists(); $n++) {
            $code = $base.$n;
        }
        $id = strtolower((string) Str::ulid());
        DB::table('departments')->insert(['id' => $id, 'company_id' => $c, 'factory_id' => $factory, 'name' => $name, 'code' => $code, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    /** A designation of that name ("Asst.Manager" finds "Asst. Manager"), made when missing. */
    private function designation(string $c, string $name): string
    {
        $key = fn (string $s) => preg_replace('/[^a-z0-9]/', '', mb_strtolower($s));
        foreach (DB::table('designations')->where('company_id', $c)->get(['id', 'name']) as $d) {
            if ($key($d->name) === $key($name)) {
                return $d->id;
            }
        }
        $base = Str::limit(Str::upper(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($name))) ?: 'DESG', 10, '');
        $code = $base;
        for ($n = 2; DB::table('designations')->where('company_id', $c)->where('code', $code)->exists(); $n++) {
            $code = $base.$n;
        }
        $id = strtolower((string) Str::ulid());
        DB::table('designations')->insert(['id' => $id, 'company_id' => $c, 'name' => $name, 'code' => $code, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }
}
