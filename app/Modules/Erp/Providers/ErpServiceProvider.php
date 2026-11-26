<?php

declare(strict_types=1);

namespace App\Modules\Erp\Providers;

use App\Modules\DevSample\Models\DsColumn;
use App\Modules\DevSample\Models\DsColumnEvent;
use App\Modules\DevSample\Models\DsGroup;
use App\Modules\Erp\Support\ColumnSetups;
use App\Modules\ExportStatement\Models\EsColumn;
use App\Modules\ExportStatement\Models\EsColumnEvent;
use App\Modules\ExportStatement\Models\EsGroup;
use App\Modules\OrderSummary\Models\OsColumn;
use App\Modules\OrderSummary\Models\OsColumnEvent;
use App\Modules\OrderSummary\Models\OsGroup;
use App\Modules\PreBudget\Models\PbColumn;
use App\Modules\PreBudget\Models\PbColumnEvent;
use App\Modules\PreBudget\Models\PbGroup;
use App\Modules\PreBudget\Models\PbUnitRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class ErpServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Column Setup → its JSON file in the code (user 2026-11-21): any column, group or unit rule
        // saved or removed, and every Column Setup history entry (Unfreeze writes columns in bulk,
        // without model events, but always logs one), saves the company's setup after the request.
        $later = fn (Model $m) => ColumnSetups::saveLater($m->getAttribute('company_id'));
        foreach ([OsColumn::class, OsGroup::class, PbColumn::class, PbGroup::class, PbUnitRule::class, DsColumn::class, DsGroup::class, EsColumn::class, EsGroup::class] as $model) {
            $model::saved($later);
            $model::deleted($later);
        }
        OsColumnEvent::created($later);
        PbColumnEvent::created($later);
        DsColumnEvent::created($later);
        EsColumnEvent::created($later);
    }
}
