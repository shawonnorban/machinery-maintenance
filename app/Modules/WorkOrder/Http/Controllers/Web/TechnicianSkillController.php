<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Controllers\Web;

use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\TechnicianSkill;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * What a technician is actually trained on (SRS 25).
 *
 * Separate from the area they cover: a dyeing technician may hold an
 * electrical certificate, and the person to send to a tripped panel at 2am is
 * the one who has it rather than whoever happens to be nearest.
 *
 * A skill is a free-text name on purpose. Every factory has its own words for
 * what its people can do, and a fixed list would either be wrong everywhere or
 * so long that nobody reads it.
 */
class TechnicianSkillController extends Controller
{
    /** What "trained on" actually means, weakest first. */
    public const PROFICIENCIES = ['BASIC', 'INTERMEDIATE', 'EXPERT', 'CERTIFIED'];

    public function __construct(private readonly TenantContext $context) {}

    public function store(Request $request, Technician $technician): RedirectResponse
    {
        $this->authorizeSkills($request);
        $this->assertReachable($technician);

        $data = $request->validate([
            'skill_name' => ['required', 'string', 'max:255'],
            'proficiency' => ['required', Rule::in(self::PROFICIENCIES)],
        ]);

        $exists = TechnicianSkill::where('technician_id', $technician->id)
            ->where('skill_name', $data['skill_name'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'skill_name' => __('technician.skill_already_listed'),
            ])->status(422);
        }

        TechnicianSkill::create($data + [
            'company_id' => $this->context->companyId(),
            'technician_id' => $technician->id,
        ]);

        return back()->with('status', __('technician.skill_added'));
    }

    public function destroy(Request $request, Technician $technician, TechnicianSkill $skill): RedirectResponse
    {
        $this->authorizeSkills($request);
        $this->assertReachable($technician);

        if ($skill->technician_id !== $technician->id) {
            abort(404);
        }

        $skill->delete();

        return back()->with('status', __('technician.skill_removed'));
    }

    private function assertReachable(Technician $technician): void
    {
        if (! $this->context->canAccessFactory((string) $technician->factory_id)) {
            abort(404);
        }
    }

    private function authorizeSkills(Request $request): void
    {
        if (! $request->user()->can('technician.technician.manage')) {
            abort(403);
        }
    }
}
