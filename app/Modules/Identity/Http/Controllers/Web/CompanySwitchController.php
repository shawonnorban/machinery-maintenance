<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Identity\Actions\SwitchCompany;
use App\Modules\Identity\Http\Requests\SwitchCompanyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class CompanySwitchController extends Controller
{
    public function store(SwitchCompanyRequest $request, SwitchCompany $switch): RedirectResponse
    {
        $company = $switch->handle($request->user(), $request->string('company_id')->toString());

        return redirect()
            ->route('app.dashboard')
            ->with('status', __('auth.company_switched', ['company' => $company->name]));
    }
}
