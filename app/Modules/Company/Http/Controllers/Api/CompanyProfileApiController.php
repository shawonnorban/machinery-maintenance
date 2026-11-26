<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Controllers\Api;

use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Company Management → Company Profile (AnnotechERP 01/01): the acting
 * company's own registration details. One record — there is no "add", only
 * edit. The code is fixed: it is in every document number already issued.
 */
class CompanyProfileApiController extends ApiController
{
    public const PERMISSION = 'settings.company.manage';

    private const EDITABLE = [
        'name', 'legal_name', 'address', 'phone', 'email', 'website',
        'trade_license_no', 'tin', 'bin', 'registration_no', 'registration_date',
    ];

    public function __construct(private readonly TenantContext $context) {}

    public function show(): JsonResponse
    {
        $this->allow(self::PERMISSION);

        return ApiResponse::ok($this->summary($this->company()));
    }

    public function update(Request $request): JsonResponse
    {
        $this->allow(self::PERMISSION);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'trade_license_no' => ['nullable', 'string', 'max:64'],
            'tin' => ['nullable', 'string', 'max:32'],
            'bin' => ['nullable', 'string', 'max:32'],
            'registration_no' => ['nullable', 'string', 'max:64'],
            'registration_date' => ['nullable', 'date'],
        ]);

        $company = $this->company();
        $company->fill(array_intersect_key($data, array_flip(self::EDITABLE)))->save();

        return ApiResponse::ok($this->summary($company->fresh()));
    }

    private function company(): Company
    {
        return Company::query()->findOrFail($this->context->companyId());
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Company $company): array
    {
        return [
            'id' => $company->id,
            'code' => $company->code,
            'name' => $company->name,
            'legal_name' => $company->legal_name,
            'address' => $company->address,
            'phone' => $company->phone,
            'email' => $company->email,
            'website' => $company->website,
            'logo_url' => $company->logoUrl(),
            'trade_license_no' => $company->trade_license_no,
            'tin' => $company->tin,
            'bin' => $company->bin,
            'registration_no' => $company->registration_no,
            'registration_date' => $company->registration_date?->format('Y-m-d'),
            'status' => $company->status,
        ];
    }
}
