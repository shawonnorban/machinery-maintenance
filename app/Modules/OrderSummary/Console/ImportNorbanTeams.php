<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Console;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerUserPermission;
use App\Modules\OrderSummary\Models\OsTeam;
use App\Modules\OrderSummary\Models\OsTeamMember;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Norban's teams (Manage Team) into Order Summary → Teams (user, 2026-11-20):
 * each team with its department; Norban's leader becomes the team head and
 * stays a member; every member keeps the buyers Norban gave them (Buyer
 * Account Permission, kind Merchandising — what Order Summary and Pre-Budget
 * scope a merchandiser by), and a team buyer no member holds goes to the head,
 * so the head still sees it. People and buyers are matched by e-mail and by
 * name ("CnA" is "C&A"); whoever is not found is reported. Run again, it
 * brings the teams to Norban's (members replaced, buyer permissions added).
 *
 *   php artisan ordersummary:import-norban-teams DAL storage/app/private/norban_teams.json
 */
class ImportNorbanTeams extends Command
{
    protected $signature = 'ordersummary:import-norban-teams {company : Company code} {file : Norban teams JSON (exported from Norban)}';

    protected $description = 'Bring Norban\'s teams in as Order Summary teams with their buyers';

    /** Norban department => this ERP's department name. */
    private const DEPARTMENTS = ['Merchandising' => 'Merchandising Department', 'Commercial' => 'Commercial Department', 'Procurement' => 'Procurement Department'];

    public function handle(TenantContext $context): int
    {
        $company = Company::query()->where('code', $this->argument('company'))->first();
        $data = json_decode((string) @file_get_contents(base_path((string) $this->argument('file'))), true);
        if (! $company || ! is_array($data['teams'] ?? null)) {
            $this->error('No such company, or the file holds no teams.');

            return self::FAILURE;
        }
        $c = $company->id;
        $context->forget();
        $context->set($c, DB::table('factories')->where('company_id', $c)->pluck('id')->all());

        $same = fn (string $s) => preg_replace('/[^a-z0-9]/', '', str_replace('&', 'n', mb_strtolower($s)));
        $buyers = [];
        foreach (Buyer::query()->get(['id', 'name', 'short_name']) as $b) {
            $buyers[$same((string) $b->short_name)] ??= $b->id;
            $buyers[$same((string) $b->name)] ??= $b->id;
        }
        $members = DB::table('company_users as cu')->join('users as u', 'u.id', '=', 'cu.user_id')->where('cu.company_id', $c)->where('cu.status', 'ACTIVE')
            ->get(['u.id', 'u.email'])->mapWithKeys(fn ($u) => [mb_strtolower($u->email) => $u->id])->all();
        $notes = [];

        foreach ($data['teams'] as $t) {
            DB::transaction(function () use ($t, $c, $same, $buyers, $members, &$notes): void {
                $deptName = self::DEPARTMENTS[$t['department'] ?? ''] ?? (string) $t['department'];
                // The merchandisers sit in the Norban factory (NFL): its department first, else any of that name.
                $dept = DB::table('departments')->where('company_id', $c)->whereRaw('lower(name) = lower(?)', [$deptName])
                    ->orderByRaw('factory_id = (select id from factories where company_id = ? and upper(name) = ? limit 1) desc', [$c, 'NFL'])->value('id');
                $people = [];
                $head = null;
                foreach ($t['members'] as $m) {
                    $id = $members[mb_strtolower((string) $m['email'])] ?? null;
                    if (! $id) {
                        $notes[] = "{$t['name']}: {$m['name']} ({$m['email']}) has no login here";

                        continue;
                    }
                    $people[$id] = $this->buyerIds($m['buyers'] ?? [], $same, $buyers, $t['name'], $notes);
                    if (($m['role'] ?? '') === 'leader') {
                        $head = $id;
                    }
                }
                // A team buyer no member holds: the head's, so the head still sees it.
                $held = array_merge([], ...array_values($people));
                $loose = array_diff($this->buyerIds($t['buyers'] ?? [], $same, $buyers, $t['name'], $notes), $held);
                if ($head && $loose) {
                    $people[$head] = array_values(array_unique([...$people[$head], ...$loose]));
                }

                $team = OsTeam::query()->where('company_id', $c)->where('name', $t['name'])->first() ?? new OsTeam(['company_id' => $c, 'name' => $t['name']]);
                $team->fill(['department_id' => $dept, 'team_head_id' => $head, 'active' => true])->save();
                OsTeamMember::query()->where('team_id', $team->id)->delete();
                foreach ($people as $user => $ids) {
                    OsTeamMember::query()->create(['company_id' => $c, 'team_id' => $team->id, 'user_id' => $user]);
                    foreach ($ids as $b) {
                        BuyerUserPermission::query()->firstOrCreate(['company_id' => $c, 'kind' => 'MERCHANDISING', 'user_id' => $user, 'buyer_id' => $b]);
                    }
                }
                $this->line("{$t['name']}: ".count($people).' member(s), head '.($head ? 'set' : 'none'));
            });
        }
        foreach (array_unique($notes) as $n) {
            $this->warn("  {$n}");
        }

        return self::SUCCESS;
    }

    /** @return list<string> the buyer ids of Norban's buyer names (one not found is reported) */
    private function buyerIds(array $names, \Closure $same, array $buyers, string $team, array &$notes): array
    {
        $out = [];
        foreach ($names as $n) {
            $id = $buyers[$same((string) $n)] ?? null;
            $id ? $out[] = $id : $notes[] = "{$team}: buyer {$n} is not in the Buyer master";
        }

        return array_values(array_unique($out));
    }
}
