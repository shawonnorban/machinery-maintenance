<?php

declare(strict_types=1);

namespace App\Modules\Chat\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The people of a company one may chat with: every active account of it,
 * with what WhatsApp shows of a contact — a name, the HR photo when the
 * person's employee record has one (else initials on a colour of their
 * own), the designation as their "about", and online / last seen.
 */
final class ChatPeople
{
    /** Seen within this many seconds counts as online (the app pings each minute). */
    public const ONLINE_SECONDS = 90;

    /** @var array<string, Collection<string, array<string, mixed>>> */
    private static array $cache = [];

    /** @return Collection<string, array<string, mixed>> user id => card */
    public static function all(string $companyId): Collection
    {
        return self::$cache[$companyId] ??= self::load($companyId);
    }

    public static function forget(): void
    {
        self::$cache = [];
    }

    /** @return array<string, mixed>|null */
    public static function card(string $companyId, ?string $userId): ?array
    {
        return $userId ? (self::all($companyId)->get($userId) ?? self::unknown($userId)) : null;
    }

    /** @return Collection<string, array<string, mixed>> */
    private static function load(string $companyId): Collection
    {
        $rows = DB::table('company_users as cu')
            ->join('users as u', 'u.id', '=', 'cu.user_id')
            ->leftJoin('hr_employees as e', fn ($j) => $j->on('e.employee_code', '=', 'u.employee_code')->where('e.company_id', '=', $companyId))
            ->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')
            ->leftJoin('chat_presence as p', fn ($j) => $j->on('p.user_id', '=', 'u.id')->where('p.company_id', '=', $companyId))
            ->where('cu.company_id', $companyId)->where('cu.status', 'ACTIVE')->whereNull('u.deleted_at')->where('u.status', 'ACTIVE')
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.email', 'e.photo_file_id', 'd.name as designation', 'p.last_seen_at']);
        $online = now()->subSeconds(self::ONLINE_SECONDS);

        return $rows->mapWithKeys(fn ($r) => [$r->id => [
            'id' => $r->id, 'name' => $r->name, 'email' => $r->email, 'initials' => self::initials($r->name), 'tone' => self::tone($r->id),
            'photo' => $r->photo_file_id !== null, 'about' => $r->designation,
            'online' => $r->last_seen_at !== null && Carbon::parse($r->last_seen_at)->gte($online),
            'last_seen_at' => $r->last_seen_at ? Carbon::parse($r->last_seen_at)->toIso8601String() : null,
        ]]);
    }

    /** A person no longer in the company (their old messages still show a name). */
    private static function unknown(string $userId): array
    {
        $name = (string) (DB::table('users')->where('id', $userId)->value('name') ?? '—');

        return ['id' => $userId, 'name' => $name, 'email' => null, 'initials' => self::initials($name), 'tone' => self::tone($userId), 'photo' => false, 'about' => null, 'online' => false, 'last_seen_at' => null, 'gone' => true];
    }

    public static function initials(string $name): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/u', trim($name)) ?: []));
        $first = mb_substr($parts[0] ?? '?', 0, 1);
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    /** One of twelve avatar colours, always the same for a person. */
    public static function tone(string $id): int
    {
        return (int) (hexdec(substr(md5($id), 0, 6)) % 12);
    }

    public static function touch(string $companyId, string $userId): void
    {
        DB::table('chat_presence')->upsert(
            [['id' => strtolower((string) \Illuminate\Support\Str::ulid()), 'company_id' => $companyId, 'user_id' => $userId, 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]],
            ['company_id', 'user_id'],
            ['last_seen_at', 'updated_at'],
        );
    }
}
