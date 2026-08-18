<?php

declare(strict_types=1);

namespace App\Shared\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Base for every module controller.
 *
 * Laravel 11+ no longer wires AuthorizesRequests into the skeleton controller,
 * so $this->authorize() is unavailable by default. Every controller in this
 * application authorizes, so it belongs here rather than being remembered
 * per class (API 34).
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;
}
