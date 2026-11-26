<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Support\RoleBlueprint;
use App\Modules\Identity\Support\RoleProvisioner;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles by department and designation (user, 2026-11-22): gives the company
 * its own editable copy of every role, then gives each person the role their
 * HR department and designation call for (RoleBlueprint::roleFor). Only a
 * person holding nothing but a general role (Viewer, Management) is moved;
 * owners, admins and anyone already given a particular role keep theirs.
 */
class RolesByDepartmentCommand extends Command
{
    protected $signature = 'access:roles-by-department {company : company code} {--dry-run : list the changes only}';

    protected $description = "Give the company its own copy of every role and each person the role of their HR department and designation";

    /** Roles a person may be moved off. */
    private const GENERAL = ['VIEWER', 'FACTORY_MANAGER'];

    public function handle(): int
    {
        $company = Company::query()->where('code', $this->argument('company'))->first();
        if (! $company) {
            $this->error('No such company.');

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');
        if (! $dry) {
            $r = RoleProvisioner::provision($company->id);
            $this->info(sprintf('Roles: %d copied, %d brought up to date, %d references moved to the copies.', $r['created'], $r['synced'], $r['moved']));
        }

        $roles = DB::table('roles')->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $company->id))
            ->orderByRaw('company_id is null')->get(['id', 'name', 'scope'])->unique('name')->keyBy('name');
        $people = DB::table('company_users as cu')->join('users as u', 'u.id', '=', 'cu.user_id')
            ->leftJoin('hr_employees as e', fn ($j) => $j->on('e.employee_code', '=', 'u.employee_code')->where('e.company_id', $company->id))
            ->leftJoin('departments as d', 'd.id', '=', DB::raw('coalesce(e.department_id, u.department_id)'))
            ->leftJoin('designations as g', 'g.id', '=', 'e.designation_id')
            ->where('cu.company_id', $company->id)
            ->get(['u.id', 'u.name', 'd.name as department', 'g.name as designation']);

        $rows = [];
        $moved = 0;
        foreach ($people as $p) {
            $target = RoleBlueprint::roleFor($p->department, $p->designation);
            if ($target === null || ! isset($roles[$target])) {
                continue;
            }
            $held = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.company_id', $company->id)->where('user_roles.user_id', $p->id)
                ->get(['user_roles.id', 'roles.name', 'user_roles.factory_id']);
            if ($held->isEmpty() || $held->contains(fn ($h) => ! in_array($h->name, self::GENERAL, true)) || $held->pluck('name')->all() === [$target]) {
                continue;
            }
            $rows[] = [$p->name, $p->department ?? '—', $p->designation ?? '—', $held->pluck('name')->implode(', '), $target];
            if ($dry) {
                continue;
            }
            $role = $roles[$target];
            $factory = $role->scope === 'FACTORY'
                ? ($held->pluck('factory_id')->filter()->first() ?? DB::table('factories')->where('company_id', $company->id)->orderBy('created_at')->value('id'))
                : null;
            DB::transaction(function () use ($held, $company, $p, $role, $factory): void {
                DB::table('user_roles')->whereIn('id', $held->pluck('id'))->delete();
                DB::table('user_roles')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $company->id, 'user_id' => $p->id, 'role_id' => $role->id, 'factory_id' => $factory, 'created_at' => now(), 'updated_at' => now()]);
            });
            $moved++;
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(\App\Modules\Identity\Services\PermissionResolver::class)->flush();

        $this->table(['Person', 'Department', 'Designation', 'Was', 'Now'], $rows);
        $this->info(($dry ? 'Would move ' : 'Moved ').($dry ? count($rows) : $moved).' people.');

        return self::SUCCESS;
    }
}
