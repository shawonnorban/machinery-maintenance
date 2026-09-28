<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Providers;

use App\Modules\Vendor\Models\ServiceContract;
use App\Modules\Vendor\Models\Vendor;
use App\Modules\Vendor\Models\Warranty;
use App\Modules\Vendor\Models\WarrantyClaim;
use App\Modules\Vendor\Policies\ServiceContractPolicy;
use App\Modules\Vendor\Policies\VendorPolicy;
use App\Modules\Vendor\Policies\WarrantyPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class VendorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(Warranty::class, WarrantyPolicy::class);
        Gate::policy(WarrantyClaim::class, WarrantyPolicy::class);
        Gate::policy(ServiceContract::class, ServiceContractPolicy::class);
    }
}
