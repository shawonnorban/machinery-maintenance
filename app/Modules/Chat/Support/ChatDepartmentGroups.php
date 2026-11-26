<?php

declare(strict_types=1);

namespace App\Modules\Chat\Support;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMember;
use App\Shared\Scopes\TenantScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * One group chat per HR department (user, 2026-11-23), its people kept in
 * step with HR & Payroll: active employees of the department who have an
 * active user in the company. A department of the same name in two
 * factories is one group. Someone moved to another department leaves the
 * old group (and joins the new one); someone who left a group on their own
 * stays out. The groups are rows like any chat, so they outlive a refresh,
 * and a fresh database gets them again the first time anyone opens chat.
 */
final class ChatDepartmentGroups
{
    public const PREFIX = 'dept:';

    /** At most once every ten minutes per company (each chat list asks). */
    public static function syncSoon(string $companyId): void
    {
        if (Cache::add("chat:dept-sync:{$companyId}", 1, now()->addMinutes(10))) {
            self::sync($companyId);
        }
    }

    /** @return array{groups: int, joined: int, left: int} */
    public static function sync(string $companyId): array
    {
        $rows = DB::table('hr_employees as e')
            ->join('departments as d', 'd.id', '=', 'e.department_id')
            ->join('users as u', 'u.employee_code', '=', 'e.employee_code')
            ->join('company_users as cu', fn ($j) => $j->on('cu.user_id', '=', 'u.id')->where('cu.company_id', '=', $companyId)->where('cu.status', '=', 'ACTIVE'))
            ->where('e.company_id', $companyId)->where('e.status', 'ACTIVE')
            ->whereNotNull('d.name')
            ->get(['d.name', 'u.id as user_id']);

        $byKey = [];
        $names = [];
        foreach ($rows as $r) {
            $name = trim((string) $r->name);
            if ($name === '') {
                continue;
            }
            $key = self::PREFIX.mb_strtolower($name);
            $names[$key] ??= $name;
            $byKey[$key][$r->user_id] = true;
        }

        // The owner and the admins sit in every department group, as its admins (user, 2026-11-23).
        $admins = self::admins($companyId);

        $joined = $left = 0;
        foreach ($byKey as $key => $people) {
            $people += array_fill_keys($admins, true);
            [$chat, $made] = self::group($companyId, $key, $names[$key]);
            $members = ChatMember::withoutGlobalScope(TenantScope::class)->where('chat_id', $chat->id)->get()->keyBy('user_id');
            $touched = [];
            foreach (array_keys($people) as $userId) {
                $m = $members->get($userId);
                $role = in_array($userId, $admins, true) ? ChatMember::ADMIN : ChatMember::MEMBER;
                if ($m === null) {
                    ChatMember::withoutGlobalScope(TenantScope::class)->create(['company_id' => $companyId, 'chat_id' => $chat->id, 'user_id' => $userId, 'role' => $role, 'joined_at' => now()]);
                    $touched[] = $userId;
                    $joined++;
                } elseif ($m->left_at && $m->synced_out) {
                    $m->forceFill(['left_at' => null, 'synced_out' => false, 'joined_at' => now(), 'role' => $role])->save();
                    $touched[] = $userId;
                    $joined++;
                } elseif (! $m->left_at && $role === ChatMember::ADMIN && $m->role === ChatMember::MEMBER) {
                    $m->forceFill(['role' => ChatMember::ADMIN])->save();
                    $touched[] = $userId;
                }
            }
            foreach ($members as $userId => $m) {
                if (! $m->left_at && ! isset($people[$userId])) {
                    $m->forceFill(['left_at' => now(), 'synced_out' => true])->save();
                    $touched[] = $userId;
                    $left++;
                }
            }
            if ($touched !== [] || $made) {
                ChatPush::send($made ? array_keys($people) : $touched, 'chat', $chat->id);
            }
        }

        // A department no one is in any more keeps its group (and its history); its people leave it.
        $gone = Chat::withoutGlobalScope(TenantScope::class)->where('company_id', $companyId)->where('system_key', 'like', self::PREFIX.'%')
            ->whereNotIn('system_key', array_keys($byKey) ?: [''])->pluck('id');
        if ($gone->isNotEmpty()) {
            $left += ChatMember::withoutGlobalScope(TenantScope::class)->whereIn('chat_id', $gone)->whereNull('left_at')->update(['left_at' => now(), 'synced_out' => true]);
        }

        return ['groups' => count($byKey), 'joined' => $joined, 'left' => $left];
    }

    /**
     * The company's owner and admins with an active membership: a role that is
     * an admin role, or a company copy of one (RoleProvisioner's copies).
     *
     * @return list<string>
     */
    private static function admins(string $companyId): array
    {
        $codes = \App\Modules\Identity\Support\RoleBlueprint::ADMIN_ROLES;

        return DB::table('user_roles as ur')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->join('company_users as cu', fn ($j) => $j->on('cu.user_id', '=', 'ur.user_id')->where('cu.company_id', '=', $companyId)->where('cu.status', '=', 'ACTIVE'))
            ->where('ur.company_id', $companyId)
            ->where(fn ($q) => $q->where('r.is_admin', true)->orWhereIn('r.name', $codes)->orWhereIn('r.template', $codes))
            ->distinct()->pluck('ur.user_id')->all();
    }

    /** @return array{0: Chat, 1: bool} the department's group, and whether it was made now */
    private static function group(string $companyId, string $key, string $name): array
    {
        $chat = Chat::withoutGlobalScope(TenantScope::class)->where('company_id', $companyId)->where('system_key', $key)->first();
        if ($chat) {
            return [$chat, false];
        }

        return [Chat::withoutGlobalScope(TenantScope::class)->create([
            'company_id' => $companyId, 'is_group' => true, 'name' => $name, 'system_key' => $key,
            'last_message_at' => now(), // described by the info drawer's own badge, in each reader's language
        ]), true];
    }
}
