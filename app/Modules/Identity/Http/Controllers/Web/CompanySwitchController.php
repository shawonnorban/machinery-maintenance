<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Identity\Actions\SwitchCompany;
use App\Modules\Identity\Http\Requests\SwitchCompanyRequest;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class CompanySwitchController extends Controller
{
    public function store(SwitchCompanyRequest $request, SwitchCompany $switch): RedirectResponse
    {
        $company = $switch->handle($request->user(), $request->string('company_id')->toString());

        // The tenant dashboard this used to send someone back to is the
        // Next.js app now (Phase D/F) — there's no Blade page left to return
        // the flash message to, so it's dropped rather than set somewhere
        // nothing will ever read it.
        return redirect(config('tenancy.frontend_url'));
    }
}
