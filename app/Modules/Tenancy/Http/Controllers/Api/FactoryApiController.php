<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers\Api;

use App\Modules\Tenancy\Actions\SaveFactory;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Factories, over the wire (API 5; mirrors the web `FactoryController`,
 * which this delegates every write to unchanged per ADR-003).
 *
 * One permission for the whole area, same as the web screen: a factory is
 * the unit everything else is scoped to, and only whoever administers the
 * estate reaches this, not every role that merely works within a factory.
 */
class FactoryApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('settings.factory.manage');

        $query = Factory::query()->withCount(['assets as asset_count'])->orderBy('name');

        if (is_string($status = $request->query('status')) && $status !== '') {
            $query->where('status', $status);
        }

        if (is_string($search = $request->query('search')) && $search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('code', 'like', $term));
        }

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (Factory $factory): array => $this->summary($factory),
        );
    }

    public function show(Factory $factory): JsonResponse
    {
        $this->allow('settings.factory.manage');

        return ApiResponse::ok($this->summary($factory));
    }

    public function store(Request $request, SaveFactory $action): JsonResponse
    {
        $this->allow('settings.factory.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'timezone' => ['required', 'string', Rule::in(self::TIMEZONES)],
            // Company Management's Factory tab (AnnotechERP 01/02).
            'contact' => ['nullable', 'string', 'max:64'],
            'established_date' => ['nullable', 'date'],
            'area_sqft' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'code' => [
                'required', 'string', 'max:5', 'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('factories')->where('company_id', $this->context->companyId()),
            ],
        ]);

        return ApiResponse::created($this->summary($action->create($data)));
    }

    public function update(Request $request, Factory $factory, SaveFactory $action): JsonResponse
    {
        $this->allow('settings.factory.manage');
        if ($request->has('code')) {
            // Codes are kept in capitals, so "nfl" and "NFL" are the same code.
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'timezone' => ['required', 'string', Rule::in(self::TIMEZONES)],
            // Company Management's Factory tab (AnnotechERP 01/02).
            'contact' => ['nullable', 'string', 'max:64'],
            'established_date' => ['nullable', 'date'],
            'area_sqft' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            // The code may change too; what keeps it as text follows (SaveFactory::renameCodeCopies).
            'code' => [
                'sometimes', 'required', 'string', 'max:5', 'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('factories')->where('company_id', $this->context->companyId())->ignore($factory->id),
            ],
        ]);

        return ApiResponse::ok($this->summary($action->update($factory, $data)));
    }

    /**
     * A factory is closed rather than deleted whenever it has ever run
     * anything: it still owns the work orders, costs and breakdowns filed
     * against it.
     */
    public function setActive(Request $request, Factory $factory, SaveFactory $action): JsonResponse
    {
        $this->allow('settings.factory.manage');

        $data = $request->validate(['active' => ['required', 'boolean']]);

        return ApiResponse::ok($this->summary($action->setStatus($factory, $data['active'] ? 'ACTIVE' : 'INACTIVE')));
    }

    public function destroy(Factory $factory, SaveFactory $action): JsonResponse
    {
        $this->allow('settings.factory.manage');

        $action->delete($factory);

        return ApiResponse::noContent();
    }

    /**
     * A short list rather than all ~400 IANA zones: every one of these is a
     * country a Bangladeshi group actually operates or sources in, same list
     * the web form offers.
     */
    private const TIMEZONES = [
        'Asia/Dhaka', 'Asia/Kolkata', 'Asia/Karachi', 'Asia/Colombo',
        'Asia/Yangon', 'Asia/Bangkok', 'Asia/Ho_Chi_Minh', 'Asia/Jakarta',
        'Asia/Shanghai', 'Asia/Hong_Kong', 'Asia/Singapore', 'Asia/Dubai',
        'Europe/Istanbul', 'Europe/London', 'UTC',
    ];

    /**
     * @return array<string, mixed>
     */
    private function summary(Factory $factory): array
    {
        return [
            'id' => $factory->id,
            'name' => $factory->name,
            'code' => $factory->code,
            'address' => $factory->address,
            'timezone' => $factory->timezone,
            'status' => $factory->status,
            'contact' => $factory->contact,
            'established_date' => $factory->established_date?->format('Y-m-d'),
            'area_sqft' => $factory->area_sqft,
            'remarks' => $factory->remarks,
            'asset_count' => $factory->asset_count ?? null,
            'created_at' => $factory->created_at?->toIso8601String(),
        ];
    }
}
