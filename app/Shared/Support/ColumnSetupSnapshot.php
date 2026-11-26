<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Shared\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

/**
 * A company's Column Setup (groups, columns and, for the BOM worksheet, its
 * unit rules) saved to a JSON file in the code, so a fresh database starts
 * every new company's sheet as the factory set it up rather than from the
 * hard-coded catalog (user 2026-11-21: "fresh seed must keep what is set").
 *
 * The file holds no ids: a column points at its group by the group's code,
 * formulas are already stored by column key, and role grants by role name.
 * `erp:save-column-setup <company>` writes the files again after more setup.
 */
final class ColumnSetupSnapshot
{
    // Row fields not carried: they belong to one database (ids, owner, times) or are set on restore.
    private const SKIP = ['id', 'company_id', 'group_id', 'sheet', 'updated_by', 'created_at', 'updated_at'];

    /**
     * The saved setup, or null when there is none (or snapshots are off, as in the test suite).
     *
     * @return array{groups: list<array<string, mixed>>, columns: list<array<string, mixed>>, unit_rules?: list<array<string, mixed>>}|null
     */
    public static function read(string $path): ?array
    {
        if (! config('app.column_setup_snapshots', true) || ! File::exists($path)) {
            return null;
        }
        $data = json_decode((string) File::get($path), true);

        return is_array($data) && ! empty($data['groups']) && ! empty($data['columns']) ? $data : null;
    }

    /**
     * One company's setup as it stands.
     *
     * @param  class-string<Model>  $groupModel
     * @param  class-string<Model>  $columnModel
     * @param  class-string<Model>|null  $ruleModel
     * @return array<string, mixed>
     */
    public static function export(string $companyId, string $groupModel, string $columnModel, ?string $sheet = null, ?string $ruleModel = null): array
    {
        $scoped = fn (string $model) => $model::query()->withoutGlobalScope(TenantScope::class)->where('company_id', $companyId)
            ->when($sheet !== null && $model !== $ruleModel, fn ($q) => $q->where('sheet', $sheet));
        $groups = $scoped($groupModel)->orderBy('sort_order')->get();
        $codes = $groups->pluck('code', 'id');
        $rank = $groups->pluck('id')->flip();
        $out = [
            'groups' => $groups->map(fn (Model $g) => self::fields($g))->values()->all(),
            // A column's sort_order counts within its group: group order first, then its place, then key.
            'columns' => $scoped($columnModel)->get()
                ->sortBy([fn ($a, $b) => ($rank[$a->group_id] ?? PHP_INT_MAX) <=> ($rank[$b->group_id] ?? PHP_INT_MAX), ['sort_order', 'asc'], ['key', 'asc']])
                ->map(fn (Model $c) => ['group' => $codes[$c->group_id] ?? null] + self::fields($c))->values()->all(),
        ];
        if ($ruleModel !== null) {
            $out['unit_rules'] = $scoped($ruleModel)->orderBy('uom')->get()->map(fn (Model $r) => self::fields($r))->values()->all();
        }

        return $out;
    }

    /**
     * Writes a setup as readable JSON (one file per sheet, kept in the module): a new file renamed into
     * place, so a reader never sees half a file and the web server needs only the folder writable.
     */
    public static function write(string $path, array $data): void
    {
        File::ensureDirectoryExists(dirname($path));
        $tmp = $path.'.'.getmypid().'.tmp';
        File::put($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        @chmod($tmp, 0664);
        if (! @rename($tmp, $path)) {
            File::delete($tmp);
            throw new \RuntimeException("Column Setup could not be saved to {$path}.");
        }
    }

    /**
     * Makes a company's sheet from a saved setup (inside the caller's transaction).
     *
     * @param  array<string, mixed>  $data
     * @param  class-string<Model>  $groupModel
     * @param  class-string<Model>  $columnModel
     * @param  class-string<Model>|null  $ruleModel
     */
    public static function restore(string $companyId, array $data, string $groupModel, string $columnModel, ?string $sheet = null, ?string $ruleModel = null): void
    {
        $extra = ['company_id' => $companyId] + ($sheet !== null ? ['sheet' => $sheet] : []);
        $groups = [];
        foreach ($data['groups'] as $g) {
            $groups[$g['code']] = $groupModel::query()->create($extra + $g)->id;
        }
        foreach ($data['columns'] as $c) {
            $code = $c['group'] ?? null;
            unset($c['group']);
            if (! isset($groups[$code])) {
                continue;
            }
            $columnModel::query()->create($extra + ['group_id' => $groups[$code]] + $c);
        }
        if ($ruleModel !== null && ! $ruleModel::query()->withoutGlobalScope(TenantScope::class)->where('company_id', $companyId)->exists()) {
            foreach ($data['unit_rules'] ?? [] as $r) {
                $ruleModel::query()->create(['company_id' => $companyId] + $r);
            }
        }
    }

    /** A row's own fields, with its casts applied (lists and flags as JSON values, not strings). */
    private static function fields(Model $m): array
    {
        return collect($m->attributesToArray())->only($m->getFillable())->except(self::SKIP)->all();
    }
}
