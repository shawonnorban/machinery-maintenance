<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\UserRole;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * What each role can do (SRS 5, Permissions 2).
 *
 * Read-only for now, and useful as it stands: an administrator handing out
 * roles has to be able to see what they are handing out. Seeded roles are not
 * editable in any case — a tenant clones one to change it — so the screen that
 * matters first is the one that answers "what does Store Manager actually
 * mean".
 */
class RoleController extends Controller
{
    public function index(Request $request): View
    {
        if (! $request->user()->can('admin.role.manage')) {
            abort(403);
        }

        $roles = Role::query()
            ->whereIn('scope', ['COMPANY', 'FACTORY'])
            ->with('permissions:id,code,name')
            ->orderBy('scope')
            ->orderBy('name')
            ->get();

        $assigned = UserRole::query()
            ->selectRaw('role_id, count(*) as holders')
            ->groupBy('role_id')
            ->pluck('holders', 'role_id');

        return view('identity::roles.index', [
            'roles' => $roles,
            'assigned' => $assigned,
        ]);
    }
}
