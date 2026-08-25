<?php

declare(strict_types=1);

namespace App\Modules\Platform\Providers;

use App\Modules\Platform\View\PlatformShellComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class PlatformServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The shell, on every platform screen, without a controller having to
        // remember. A controller that forgot would render a sidebar with no
        // notifications and a bell that always said nought.
        View::composer('platform::layout', PlatformShellComposer::class);
    }
}
