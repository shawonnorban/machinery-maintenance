<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Http\Controllers\Api;

use App\Modules\GeneralStore\Models\GsAuditEntry;
use App\Modules\GeneralStore\Models\GsSetting;
use App\Modules\GeneralStore\Support\GsBook;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * General Store → Administration (AnnotechERP 10/07). Audit Log: the latest
 * 1,000 entries of who created items, received, issued, adjusted, edited,
 * cancelled or printed what, and when — searched, or taken as Excel.
 * Settings: whether a requisition needs the Store Manager's approval,
 * whether the store may issue without one, whether an issue must name who
 * took it, the name and footer on vouchers — and who holds the store roles.
 */
class GsAdminApiController extends ApiController
{
    /** The roles that run the store, in the order the Settings screen lists them. */
    public const ROLES = ['STORE_MANAGER', 'STOREKEEPER', 'FACTORY_ADMIN'];

    public function audit(Request $request): JsonResponse
    {
        $this->allow('store.audit.view');

        return ApiResponse::ok(['rows' => $this->entries((string) $request->query('q', ''))]);
    }

    public function auditExcel(Request $request): StreamedResponse
    {
        $this->allow('store.audit.view');
        $rows = $this->entries((string) $request->query('q', ''));
        $columns = ['when', 'user', 'action', 'screen', 'summary'];

        return response()->streamDownload(function () use ($rows, $columns): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_map(fn ($c) => __("store.admin.col.{$c}"), $columns)));
            foreach ($rows as $r) {
                $writer->addRow(Row::fromValues(array_map(fn ($c) => $r[$c], $columns)));
            }
            $writer->close();
        }, 'store-audit-log-'.now()->format('Ymd-His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function settings(): JsonResponse
    {
        $this->allow('store.settings.manage');

        return ApiResponse::ok($this->settingsPayload());
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $this->allow('store.settings.manage');
        $data = $request->validate([
            'requisition_approval' => ['required', 'boolean'], 'direct_issue' => ['required', 'boolean'], 'receiver_required' => ['required', 'boolean'],
            'voucher_name' => ['nullable', 'string', 'max:160'], 'voucher_footer' => ['nullable', 'string', 'max:300'],
        ]);
        $setting = GsSetting::query()->first() ?? new GsSetting;
        $before = $setting->exists ? $setting->only(array_keys($data)) : [];
        $setting->fill($data + ['updated_by' => $this->caller()->auditUserId()])->save();
        $changed = collect($data)->filter(fn ($v, $k) => ($before[$k] ?? null) !== $setting->{$k})->keys()->implode(', ');
        GsBook::audit('UPDATE', 'gs-settings', null, __('store.admin.audit.settings', ['fields' => $changed !== '' ? $changed : '—'], 'en'));

        return ApiResponse::ok($this->settingsPayload());
    }

    /** @return array<string, mixed> */
    private function settingsPayload(): array
    {
        $s = GsBook::settings();
        $company = app(TenantContext::class)->companyId();
        $holders = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')->join('users', 'users.id', '=', 'user_roles.user_id')
            ->where('user_roles.company_id', $company)->whereIn('roles.name', self::ROLES)
            ->orderBy('users.name')->get(['roles.name as role', 'users.name', 'users.email']);

        return [
            'requisition_approval' => $s->requisition_approval, 'direct_issue' => $s->direct_issue, 'receiver_required' => $s->receiver_required,
            'voucher_name' => $s->voucher_name, 'voucher_footer' => $s->voucher_footer,
            'company_name' => Company::query()->whereKey($company)->value('name'),
            'updated_at' => $s->updated_at?->toIso8601String(), 'updated_by' => $s->updated_by ? DB::table('users')->where('id', $s->updated_by)->value('name') : null,
            'roles' => collect(self::ROLES)->map(fn ($role) => [
                'role' => $role, 'people' => $holders->where('role', $role)->map(fn ($h) => ['name' => $h->name, 'email' => $h->email])->unique('email')->values()->all(),
            ])->all(),
        ];
    }

    /** @return list<array{when: string, user: ?string, action: string, screen: string, summary: string}> */
    private function entries(string $q): array
    {
        $q = mb_strtolower(trim($q));
        $rows = GsAuditEntry::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->whereRaw('lower(summary) like ?', ["%{$q}%"])->orWhereRaw('lower(action) like ?', ["%{$q}%"])
                ->orWhereRaw('lower(screen) like ?', ["%{$q}%"])->orWhereIn('user_id', DB::table('users')->whereRaw('lower(name) like ?', ["%{$q}%"])->select('id'))))
            ->orderByDesc('created_at')->limit(1000)->get();
        $names = DB::table('users')->whereIn('id', $rows->pluck('user_id')->filter()->unique())->pluck('name', 'id');

        return $rows->map(fn (GsAuditEntry $e) => [
            'when' => $e->created_at->toIso8601String(), 'user' => $names[$e->user_id] ?? null, 'action' => $e->action, 'screen' => $e->screen, 'summary' => $e->summary,
        ])->all();
    }
}
