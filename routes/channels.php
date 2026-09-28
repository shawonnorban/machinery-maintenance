<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PermissionResolver;
use Illuminate\Support\Facades\Broadcast;

/*
 * WebSocket channel authorization (SRS 29, ADR-018).
 *
 * This file is the only thing standing between a private channel and another
 * company's data. A websocket subscription bypasses every controller, policy
 * and global scope in the application: once a client is on a channel, it
 * receives whatever is broadcast there for as long as it stays connected.
 *
 * So each callback re-derives membership from the database rather than trusting
 * anything the client sent, and returns a boolean rather than a payload —
 * whatever is returned from a channel callback is handed to the client, and a
 * user object here would leak fields nobody meant to publish.
 */

/**
 * Everything happening inside one company.
 *
 * Membership must be active. A user removed from a company keeps their session
 * cookie until it expires, and without the status check they would keep
 * receiving that company's events after being taken off it.
 */
Broadcast::channel('company.{companyId}', function (User $user, string $companyId): bool {
    return $user->memberships()
        ->where('company_id', $companyId)
        ->where('status', 'ACTIVE')
        ->exists();
});

/**
 * One factory's floor: breakdowns, work orders, asset status.
 *
 * Factory reach, not just company membership. A manager scoped to Dhaka has no
 * business receiving Gazipur's breakdown alerts, and the same rule that hides
 * them on screen has to hide them on the socket (ADR-042).
 */
Broadcast::channel('factory.{factoryId}', function (User $user, string $factoryId): bool {
    $resolver = app(PermissionResolver::class);

    foreach ($user->memberships()->where('status', 'ACTIVE')->pluck('company_id') as $companyId) {
        if (in_array($factoryId, $resolver->accessibleFactoryIds($user, $companyId), true)) {
            return true;
        }
    }

    return false;
});

/**
 * One person's own notifications.
 *
 * Compared as strings, not loosely. ULIDs are strings, and a loose comparison
 * of two of them is a comparison of two zeros.
 */
Broadcast::channel('user.{userId}', function (User $user, string $userId): bool {
    return $user->id === $userId;
});
