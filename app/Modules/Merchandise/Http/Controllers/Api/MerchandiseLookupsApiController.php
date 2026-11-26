<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\DyeHouse\Models\DyeColourGroup;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Tenancy\Models\Section;
use App\Modules\TradeExport\Models\TxBank;
use App\Modules\TradeExport\Support\TradeExportDefaults;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * What Merchandise Desk's master forms pick from, as Brickland offers it
 * (AnnotechERP 06/01): countries as "Name (code)", the shared bank register,
 * Material Procurement's suppliers as "NAME (code)", the item categories, the
 * colour groups and the company's people as "Name (login)".
 */
class MerchandiseLookupsApiController extends ApiController
{
    public function index(): JsonResponse
    {
        $this->allow('merchandise.master.view');
        $company = $this->caller()->companyId;
        TradeExportDefaults::banks($company);
        $members = CompanyUser::query()->where('company_id', $company)->where('status', 'ACTIVE')->pluck('user_id');

        return ApiResponse::ok([
            'countries' => OriginCountry::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($c) => ['value' => $c->id, 'label' => "{$c->name} ({$c->code})"])->all(),
            'banks' => TxBank::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($b) => ['value' => $b->id, 'label' => $b->name])->all(),
            'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->all(),
            'item_categories' => DB::table('item_categories')->where('company_id', $company)->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            'colour_groups' => DyeColourGroup::query()->orderBy('name')->get(['id', 'name'])->map(fn ($g) => ['value' => $g->id, 'label' => $g->name])->all(),
            'fabric_types' => FabricType::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($t) => ['value' => $t->id, 'label' => "{$t->name} ({$t->code})"])->all(),
            'offices' => BuyerOffice::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($o) => ['value' => $o->id, 'label' => $o->name])->all(),
            // Internal sections a lab dip request can go to (Lab Dip Programme's Request To).
            'sections' => Section::query()->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->all(),
            'users' => User::query()->whereIn('id', $members)->orderBy('name')->get(['id', 'name', 'username', 'email'])->map(fn ($u) => ['value' => $u->id, 'label' => $u->name.' ('.($u->username ?: $u->email).')'])->all(),
        ]);
    }
}
