<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * A user agent string, reduced to something a person can recognise.
 *
 * Not parsing: recognising. Somebody deciding whether to sign a device out
 * needs "Chrome on Android", not a hundred characters of version tokens
 * they will skip over. Shared between the account screen and its API
 * equivalent (ADR-003) so the two never describe the same session
 * differently.
 */
class UserAgent
{
    public static function describe(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => __('account.unknown_browser'),
        };

        $platform = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => __('account.unknown_platform'),
        };

        return $browser.' · '.$platform;
    }
}
