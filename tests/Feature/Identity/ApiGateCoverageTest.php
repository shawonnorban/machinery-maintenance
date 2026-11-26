<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * No `api/v1` endpoint may be reachable without a permission check.
 *
 * An endpoint that forgets its gate is not something a normal test finds:
 * every test calls it as somebody who is allowed, so it answers correctly
 * and nobody notices that it would also have answered a storekeeper asking
 * for another factory's costs. The failure only shows up as a breach.
 *
 * So this reads the routing table instead of making requests, and asks of
 * every controller action whether a gate is reached from it — directly, or
 * through one of the controller's own helpers (`step()`, `resolve()`,
 * `authorizeView()` all gate on behalf of the actions that call them).
 */
class ApiGateCoverageTest extends TestCase
{
    /**
     * Ways a controller checks permission. `allow()` is the house style;
     * the others are what predates it or sits outside the tenant guard.
     *
     * @var list<string>
     */
    private const GATE_EXPRESSIONS = [
        'allow(',
        'authorize(',
        '->can(',
        'Gate::',
        // A catalogue endpoint asks its registry which entries this
        // person may run, and answers with those. The check is real; it
        // just belongs to the registry rather than the controller,
        // because the answer is a filtered list, not a yes or no.
        'availableTo(',
        '->allows(',
        // Somebody's own export or import job. Another person's is a 404
        // from the ownership check, which is a stronger refusal than a
        // permission would give: the row's existence is itself private.
        'user_id !==',
        'user_id !=',
        'requested_by !==',
        "where('user_id'",
    ];

    /**
     * Actions whose gate is one call further in, in a collaborator this
     * test cannot follow.
     *
     * The excuse is checked rather than taken on trust: each entry names
     * the class and method that does the refusing and a fragment that
     * must still be in it, so the day somebody deletes that check, this
     * test fails instead of quietly widening.
     *
     * @var array<string, array{class-string, string, string}>
     */
    private const GATED_BY_COLLABORATOR = [
        'ExportApiController@store' => [
            \App\Modules\Reporting\Services\DataExporter::class,
            'handle',
            "can('export.job.create')",
        ],
        'ReportJobApiController@store' => [
            \App\Modules\Reporting\Actions\RequestReport::class,
            'handle',
            '$this->authorize(',
        ],
    ];

    /**
     * Endpoints that answer about the caller themselves, and so have
     * nothing to check a permission against.
     *
     * Each entry is a reason, not an exemption: `auth/*` is how a caller
     * finds out who they are and what they may do — gating it on a
     * permission would mean needing a permission to discover you hold
     * none. Notifications are already filtered to the recipient, so the
     * row is the caller's own by construction. Health answers before
     * there is a caller at all, and `broadcasting/auth` is Laravel's own
     * channel handshake, authorised per-channel rather than per-route.
     *
     * Platform endpoints run behind the separate platform guard
     * (`PlatformAuthApiController`), which is not the tenant permission
     * system this test is about.
     *
     * @var list<string>
     */
    private const SELF_SCOPED = [
        'api/v1/auth/',
        'api/v1/health',
        'api/v1/notifications',
        'api/v1/notification-preferences',
        'api/v1/broadcasting/auth',
        'api/v1/platform/',
        // A tenant's own subscription and its support tickets: the
        // account holder's own record, scoped by tenancy, and reaching
        // somebody else's is a 404 from the scope rather than a 403.
        'api/v1/subscription',
        'api/v1/support/',
        // Chat (Norban's chat module): a company's people talking — reached by
        // being in the chat (a 404 otherwise), not by a permission, as one's
        // own notifications are.
        'api/v1/chat/',
        // The caller's own sidebar (AnnotechERP 02/01): the menu tree
        // already filtered to their own View permissions — the menu-shaped
        // twin of auth/permissions. Editing menus and grants stays gated.
        'api/v1/access/navigation',
        'api/v1/access/restricted-screens',
        // Which modules the platform has not given the caller's own company
        // (LicensedModules) — its own switches, used to show "not enabled".
        'api/v1/access/locked-modules',
        // A factory TV (Production Tracking): no login at all — the unguessable
        // link token issued from the TV screen setup is the key, and the
        // screen only reads that link's own board.
        'api/v1/public/production-tracking/tv/',
        // The QC's phone (AnnotechERP 37): `mobile/` is the PIN sign-in and that
        // PIN session's own person, PIN and end (pinSession() refuses any other
        // token); `phone/` is the approved phone itself, by its id and secret
        // (AuthenticatePhone), and each tap is checked against its QC's permission.
        'api/v1/quality-tally/mobile/',
        'api/v1/quality-tally/phone/',
    ];

    public function test_every_api_endpoint_reaches_a_permission_check(): void
    {
        $ungated = [];
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            if (! $this->isTenantApiRoute($route)) {
                continue;
            }

            $action = $route->getActionName();

            if (! str_contains($action, '@')) {
                continue;
            }

            [$class, $method] = explode('@', $action);

            if (! class_exists($class) || ! method_exists($class, $method)) {
                continue;
            }

            $checked++;

            if ($this->reachesGate($class, $method)) {
                continue;
            }

            if (isset(self::GATED_BY_COLLABORATOR[class_basename($class).'@'.$method])) {
                continue;
            }

            $ungated[] = $route->methods()[0].' '.$route->uri().'  ('.class_basename($class).'@'.$method.')';
        }

        // A guard that checks nothing would pass silently, so say what it saw.
        $this->assertGreaterThan(300, $checked, 'The routing table looks empty — this test checked almost nothing.');

        sort($ungated);

        $this->assertSame(
            [],
            $ungated,
            "These endpoints reach no permission check, so anyone with a valid token can call them:\n"
            .implode("\n", $ungated),
        );
    }

    private function isTenantApiRoute(RoutingRoute $route): bool
    {
        $uri = $route->uri();

        if (! str_starts_with($uri, 'api/v1/')) {
            return false;
        }

        foreach (self::SELF_SCOPED as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                return false;
            }
        }

        // A signed URL is the credential: it was minted for one file, for
        // a few minutes, by an endpoint that did check a permission. That
        // is the whole point of handing one to a browser that cannot send
        // an Authorization header.
        return ! in_array('signed', $route->gatherMiddleware(), true);
    }

    /**
     * Does a gate get reached from this action?
     *
     * Follows `$this->helper(...)` calls within the same controller until
     * nothing new is found, because several controllers put the check in a
     * shared step rather than repeating it in fifteen transition actions.
     *
     * @param  class-string  $class
     */
    private function reachesGate(string $class, string $method): bool
    {
        $seen = [];
        $pending = [$method];

        while ($pending !== []) {
            $current = array_pop($pending);

            if (isset($seen[$current]) || ! method_exists($class, $current)) {
                continue;
            }

            $seen[$current] = true;
            $body = $this->bodyOf($class, $current);

            foreach (self::GATE_EXPRESSIONS as $expression) {
                if (str_contains($body, $expression)) {
                    return true;
                }
            }

            // A FormRequest authorises before the action runs, so a
            // controller that type-hints one has already been gated by
            // the time its body is reached.
            if ($this->formRequestAuthorises($class, $current)) {
                return true;
            }

            // Anything else this controller calls on itself.
            if (preg_match_all('/\$this->(\w+)\(/', $body, $matches) > 0) {
                foreach ($matches[1] as $called) {
                    $pending[] = $called;
                }
            }
        }

        return false;
    }

    /**
     * Does any FormRequest this method type-hints carry a real
     * `authorize()`? Laravel's base one returns true, which authorises
     * nothing, so the override has to be the class's own.
     *
     * @param  class-string  $class
     */
    private function formRequestAuthorises(string $class, string $method): bool
    {
        foreach ((new ReflectionMethod($class, $method))->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $name = $type->getName();

            if (! is_subclass_of($name, \Illuminate\Foundation\Http\FormRequest::class)) {
                continue;
            }

            if (! method_exists($name, 'authorize')) {
                continue;
            }

            if ((new ReflectionMethod($name, 'authorize'))->getDeclaringClass()->getName() !== $name) {
                continue;
            }

            foreach (self::GATE_EXPRESSIONS as $expression) {
                if (str_contains($this->bodyOf($name, 'authorize'), $expression)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param  class-string  $class */
    private function bodyOf(string $class, string $method): string
    {
        $reflection = new ReflectionMethod($class, $method);
        $file = $reflection->getFileName();

        if ($file === false) {
            return '';
        }

        // An inherited helper lives in the parent's file, which is where
        // `ApiController::allow()` itself is.
        $lines = file($file);

        if ($lines === false) {
            return '';
        }

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ));
    }

    /**
     * An action excused above is only excused while the collaborator it
     * points at still refuses.
     */
    public function test_every_delegated_gate_is_still_in_place(): void
    {
        foreach (self::GATED_BY_COLLABORATOR as $action => [$class, $method, $fragment]) {
            $this->assertTrue(
                method_exists($class, $method),
                "{$action} is excused because {$class}::{$method}() gates it, and that method is gone.",
            );

            $this->assertStringContainsString(
                $fragment,
                $this->bodyOf($class, $method),
                "{$action} is excused because {$class}::{$method}() gates it, and that check is gone.",
            );
        }
    }

    /**
     * The allowlist must not outlive what it excuses: a prefix that no
     * longer matches any route is a note about a decision nobody can
     * check any more.
     */
    public function test_the_self_scoped_allowlist_holds_no_stale_entries(): void
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            $uris[] = $route->uri();
        }

        foreach (self::SELF_SCOPED as $prefix) {
            $matched = false;

            foreach ($uris as $uri) {
                if (str_starts_with($uri, $prefix)) {
                    $matched = true;
                    break;
                }
            }

            $this->assertTrue($matched, "No route starts with '{$prefix}' any more — drop it from the allowlist.");
        }
    }

    /**
     * `ApiController::allow()` is what every gate above ultimately calls,
     * so if it ever stopped refusing, this whole test would keep passing
     * while the API let everyone through.
     */
    public function test_the_gate_helper_actually_refuses(): void
    {
        $reflection = new ReflectionClass(\App\Shared\Http\Api\ApiController::class);
        $body = $this->bodyOf($reflection->getName(), 'allow');

        $this->assertStringContainsString('ErrorCode::FORBIDDEN', $body);
        $this->assertStringContainsString('! app(', $body);
    }
}
