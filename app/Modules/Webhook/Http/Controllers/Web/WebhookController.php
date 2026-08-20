<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Http\Controllers\Web;

use App\Modules\Webhook\Actions\ManageWebhookEndpoint;
use App\Modules\Webhook\Jobs\DeliverWebhook;
use App\Modules\Webhook\Models\WebhookDelivery;
use App\Modules\Webhook\Models\WebhookEndpoint;
use App\Modules\Webhook\Services\WebhookEvents;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Managing outgoing integrations (SRS 43, ERD Section 22).
 *
 * The delivery log is the point of this screen. When an integration is not
 * working, the argument is always about whether the event was sent, and the
 * only way to end it is to show what was sent, when, and what came back.
 */
class WebhookController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeWebhooks($request);

        return view('webhook::webhooks.index', [
            'endpoints' => WebhookEndpoint::withCount('subscriptions')->latest()->get(),
            'events' => WebhookEvents::all(),
            // Shown once, on the request that created it, and never again.
            'newSecret' => session('webhook_secret'),
            'newSecretFor' => session('webhook_secret_for'),
        ]);
    }

    public function store(Request $request, ManageWebhookEndpoint $action): RedirectResponse
    {
        $this->authorizeWebhooks($request);

        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string'],
        ]);

        $result = $action->create($data, $request->user()->id);

        return redirect()
            ->route('app.webhooks.index')
            // Flashed rather than stored: it survives exactly one page render.
            ->with('webhook_secret', $result['secret'])
            ->with('webhook_secret_for', $result['endpoint']->id)
            ->with('status', __('webhook.created'));
    }

    public function show(Request $request, WebhookEndpoint $endpoint): View
    {
        $this->authorizeWebhooks($request);
        $this->assertOwned($endpoint);

        return view('webhook::webhooks.show', [
            'endpoint' => $endpoint->load('subscriptions'),
            'events' => WebhookEvents::all(),
            'deliveries' => WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function update(Request $request, WebhookEndpoint $endpoint, ManageWebhookEndpoint $action): RedirectResponse
    {
        $this->authorizeWebhooks($request);
        $this->assertOwned($endpoint);

        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string'],
        ]);

        $action->update($endpoint, $data, $request->user()->id);

        return redirect()
            ->route('app.webhooks.show', $endpoint)
            ->with('status', __('webhook.updated'));
    }

    public function rotate(Request $request, WebhookEndpoint $endpoint, ManageWebhookEndpoint $action): RedirectResponse
    {
        $this->authorizeWebhooks($request);
        $this->assertOwned($endpoint);

        $result = $action->rotateSecret($endpoint);

        return redirect()
            ->route('app.webhooks.index')
            ->with('webhook_secret', $result['secret'])
            ->with('webhook_secret_for', $endpoint->id)
            ->with('status', __('webhook.rotated'));
    }

    public function enable(Request $request, WebhookEndpoint $endpoint, ManageWebhookEndpoint $action): RedirectResponse
    {
        $this->authorizeWebhooks($request);
        $this->assertOwned($endpoint);

        $action->enable($endpoint);

        return back()->with('status', __('webhook.enabled'));
    }

    public function pause(Request $request, WebhookEndpoint $endpoint, ManageWebhookEndpoint $action): RedirectResponse
    {
        $this->authorizeWebhooks($request);
        $this->assertOwned($endpoint);

        $action->pause($endpoint);

        return back()->with('status', __('webhook.paused'));
    }

    /**
     * Send one delivery again.
     *
     * The same event id goes out, so a receiver that did get the first one can
     * recognise the repeat rather than acting on it twice.
     */
    public function redeliver(Request $request, WebhookDelivery $delivery): RedirectResponse
    {
        $this->authorizeWebhooks($request);

        if ($delivery->company_id !== app(TenantContext::class)->companyId()) {
            abort(404);
        }

        $delivery->forceFill(['status' => 'PENDING', 'next_retry_at' => null])->save();

        DeliverWebhook::dispatch($delivery->id, $delivery->company_id);

        return back()->with('status', __('webhook.redelivering'));
    }

    private function authorizeWebhooks(Request $request): void
    {
        if (! $request->user()->can('webhook.endpoint.manage')) {
            abort(403);
        }
    }

    private function assertOwned(WebhookEndpoint $endpoint): void
    {
        if ($endpoint->company_id !== app(TenantContext::class)->companyId()) {
            abort(404);
        }
    }
}
