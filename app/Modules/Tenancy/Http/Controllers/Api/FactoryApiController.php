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

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'timezone' => ['required', 'string', Rule::in(self::TIMEZONES)],
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
            'asset_count' => $factory->asset_count ?? null,
            'created_at' => $factory->created_at?->toIso8601String(),
        ];
    }
}
