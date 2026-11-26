<?php

declare(strict_types=1);

namespace App\Modules\Erp\Support;

use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * One module's sample master data, seeded through its screens' own rules
 * (ResourceSeeder) into the company the tenant context names.
 *
 * The organisation a master points at — factories, floors, shifts, users,
 * departments — is each company's own setup. The lookups ask by the name the
 * sample data uses and fall back to the company's own (its first factory, the
 * n-th user) so the same seed fits any company; a company without that setup
 * at all gets a reference error, which the seeder reports against the row.
 */
abstract class MasterSeed
{
    abstract public function seed(ResourceSeeder $s): void;

    protected function companyId(): string
    {
        return app(TenantContext::class)->companyId();
    }

    protected function factoryId(string $code): string
    {
        $q = fn () => DB::table('factories')->where('company_id', $this->companyId());

        return $this->pick($q()->where('code', $code), $q()->orderBy('code'), "factory {$code}");
    }

    /** A floor by the start of its building's name ("Building A") and its own name; else any floor of the company. */
    protected function floorId(string $building, string $floor): string
    {
        $q = fn () => DB::table('floors')->join('buildings', 'buildings.id', '=', 'floors.building_id')->where('floors.company_id', $this->companyId());

        return $this->pick($q()->where('buildings.name', 'like', $building.'%')->where('floors.name', $floor), $q()->orderBy('floors.name'), "floor {$building}/{$floor}", 'floors.id');
    }

    protected function shiftId(string $name): string
    {
        $q = fn () => DB::table('hr_shifts')->where('company_id', $this->companyId())->orderBy('created_at');

        return $this->pick($q()->where('name', $name), $q(), "shift {$name}");
    }

    /** A user of this company by name (users.id, which most pickers store); else its n-th user, so different people stay different. */
    protected function userId(string $name, int $nth = 0): string
    {
        return $this->pick($this->users()->where('users.name', $name), $this->users()->orderBy('company_users.created_at')->offset($nth), "user {$name}", 'users.id');
    }

    /** The company-user row of a user by name, which the approval pickers store; else its n-th user. */
    protected function companyUserId(string $name, int $nth = 0): string
    {
        return $this->pick($this->users()->where('users.name', $name), $this->users()->orderBy('company_users.created_at')->offset($nth), "company user {$name}", 'company_users.id');
    }

    private function users(): Builder
    {
        return DB::table('company_users')->join('users', 'users.id', '=', 'company_users.user_id')->where('company_users.company_id', $this->companyId());
    }

    private function pick(Builder $exact, Builder $fallback, string $what, string $column = 'id'): string
    {
        return $exact->value($column) ?? $fallback->value($column) ?? throw new RuntimeException("Seed reference not found: {$what}");
    }
}
