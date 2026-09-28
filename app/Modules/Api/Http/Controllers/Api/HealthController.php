<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers\Api;

use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Is this thing running, and is it ready to be sent traffic (API 19.3)?
 *
 * Two questions, not one, and a load balancer needs them kept apart. "Alive"
 * asks whether the process should be restarted; "ready" asks whether it should
 * be sent requests. A deploy where the database is still migrating is alive
 * and not ready, and answering one question for both either restarts a healthy
 * process or sends traffic into a broken one.
 *
 * Neither endpoint authenticates. Both are deliberately silent about what
 * failed — an unauthenticated endpoint that names the database host is a gift.
 */
class HealthController extends Controller
{
    public function alive(): JsonResponse
    {
        return ApiResponse::ok(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::connection()->select('select 1')),
            'cache' => $this->check(function (): void {
                Cache::put('health:ready', '1', 5);
                Cache::get('health:ready');
            }),
        ];

        $ready = ! in_array(false, $checks, true);

        return ApiResponse::ok(
            ['status' => $ready ? 'ready' : 'not_ready', 'checks' => $checks],
            status: $ready ? 200 : 503,
        );
    }

    private function check(callable $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (Throwable) {
            // Swallowed on purpose. What failed belongs in the log, not in an
            // unauthenticated response body.
            return false;
        }
    }
}
