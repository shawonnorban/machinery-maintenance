<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers\Web;

use App\Modules\Api\Actions\ManageApiClient;
use App\Modules\Api\Models\ApiClient;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Identity\Models\Permission;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where a person mints a machine's credentials (API 4.2).
 *
 * The screen exists because the alternative is a database seed and a support
 * ticket. It shows the secret exactly once, at creation, and never again —
 * everything after that is the client id, the scopes, and when it was last
 * used, which is what somebody deciding whether to revoke it actually needs.
 */
class ApiClientController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeClients($request);

        return view('api::clients.index', [
            'clients' => ApiClient::query()
                ->with('creator:id,name')
                ->withCount(['tokens as active_tokens' => fn ($q) => $q->whereNull('revoked_at')])
                ->orderBy('name')
                ->get(),
            // Grouped by module so a list of two hundred codes is navigable.
            'permissions' => Permission::query()
                ->orderBy('code')
                ->get(['code', 'name', 'module'])
                ->groupBy('module'),
        ]);
    }

    public function store(Request $request, ManageApiClient $clients): RedirectResponse
    {
        $this->authorizeClients($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', 'max:128'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        ['client' => $client, 'secret' => $secret] = $clients->create(
            $data['name'],
            array_values($data['scopes']),
            $data['expires_at'] ?? null,
            $request->user()->id,
        );

        // Flashed, not stored. It survives exactly one request, which is the
        // one that renders it, and there is no way back to it afterwards.
        return back()
            ->with('status', __('api.client_created', ['name' => $client->name]))
            ->with('new_client_id', $client->client_id)
            ->with('new_client_secret', $secret);
    }

    public function update(Request $request, ApiClient $client, ManageApiClient $clients): RedirectResponse
    {
        $this->authorizeClients($request);

        $data = $request->validate([
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', 'max:128'],
        ]);

        $clients->updateScopes($client, array_values($data['scopes']));

        return back()->with('status', __('api.client_updated', ['name' => $client->name]));
    }

    public function rotate(Request $request, ApiClient $client, ManageApiClient $clients): RedirectResponse
    {
        $this->authorizeClients($request);

        ['secret' => $secret] = $clients->rotateSecret($client);

        return back()
            ->with('status', __('api.secret_rotated', ['name' => $client->name]))
            ->with('new_client_id', $client->client_id)
            ->with('new_client_secret', $secret);
    }

    public function revoke(Request $request, ApiClient $client, ManageApiClient $clients): RedirectResponse
    {
        $this->authorizeClients($request);

        $clients->revoke($client);

        return back()->with('status', __('api.client_revoked', ['name' => $client->name]));
    }

    /**
     * Tokens a person holds, so they can be given up from a screen as well as
     * from an endpoint. Somebody who has lost a tablet cannot call
     * `/auth/logout` from it.
     */
    public function revokeToken(Request $request, ApiToken $token): RedirectResponse
    {
        $this->authorizeClients($request);

        $token->revoke();

        return back()->with('status', __('api.token_revoked'));
    }

    private function authorizeClients(Request $request): void
    {
        if (! $request->user()->can('admin.api_client.manage')) {
            abort(403);
        }
    }
}
