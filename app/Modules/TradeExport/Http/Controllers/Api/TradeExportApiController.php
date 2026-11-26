<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Settings\Models\Currency;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Trade & Export → Master Data (AnnotechERP 22/01): what its forms pick from.
 */
class TradeExportApiController extends ApiController
{
    public function lookups(): JsonResponse
    {
        $this->allow('tradeexport.master.view');
        $company = $this->caller()->companyId;

        return ApiResponse::ok([
            'company' => ['value' => $company, 'label' => Company::query()->whereKey($company)->value('name')],
            'buyers' => Buyer::query()->where('active', true)->orderBy('short_name')->get(['id', 'short_name', 'name'])->map(fn ($b) => ['value' => $b->id, 'label' => $b->short_name])->all(),
            'countries' => OriginCountry::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            'currencies' => Currency::query()->where('active', true)->orderBy('code')->get(['code', 'name', 'exchange_rate'])
                ->map(fn ($c) => ['value' => $c->code, 'label' => "{$c->code} — {$c->name}", 'rate' => $c->exchange_rate === null ? null : rtrim(rtrim(number_format((float) $c->exchange_rate, 6, '.', ''), '0'), '.')])->all(),
        ]);
    }
}
