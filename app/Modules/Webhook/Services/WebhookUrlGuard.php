<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Services;

use Illuminate\Validation\ValidationException;

/**
 * Decides whether the platform is willing to call a URL a customer typed in.
 *
 * A webhook endpoint is a request this server makes to an address supplied by a
 * user, which is the definition of server-side request forgery. Left
 * unguarded, a tenant could point one at the cloud metadata service, at an
 * internal admin panel, or at a database on the private network, and the
 * platform would fetch it for them from inside the perimeter.
 *
 * So the rules are deliberately blunt: HTTPS only, public addresses only, no
 * credentials in the URL, and the host is resolved before it is accepted — a
 * name that resolves into a private range is a private address regardless of
 * how it is spelled.
 */
class WebhookUrlGuard
{
    /**
     * Ranges that are never callable, whatever the DNS says.
     *
     * 169.254.169.254 is the cloud metadata endpoint and is the single most
     * valuable target on this list; the rest is the private network the server
     * happens to sit inside.
     */
    private const BLOCKED_RANGES = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
        '172.16.0.0/12', '192.0.0.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '224.0.0.0/4', '240.0.0.0/4',
    ];

    public function assertCallable(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw ValidationException::withMessages(['url' => __('webhook.url_invalid')]);
        }

        if (strtolower($parts['scheme']) !== 'https' && ! $this->allowsInsecure()) {
            // A signed payload sent over plain HTTP is a signed payload anybody
            // on the path can read.
            throw ValidationException::withMessages(['url' => __('webhook.url_https_required')]);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            // Credentials in a URL end up in logs, in delivery records, and in
            // screenshots attached to support tickets.
            throw ValidationException::withMessages(['url' => __('webhook.url_no_credentials')]);
        }

        foreach ($this->addressesFor($parts['host']) as $address) {
            if ($this->isBlocked($address)) {
                throw ValidationException::withMessages(['url' => __('webhook.url_private')]);
            }
        }
    }

    public function isCallable(string $url): bool
    {
        try {
            $this->assertCallable($url);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function addressesFor(string $host): array
    {
        // A literal address needs no lookup; a name does, because the check is
        // about where the request lands rather than how it is written.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];

        $addresses = [];

        foreach ($records as $record) {
            $addresses[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        $addresses = array_values(array_filter($addresses));

        if ($addresses === []) {
            // In production a host that resolves to nothing is refused rather
            // than allowed: an unresolvable name today can resolve internally
            // tomorrow. Locally and in tests it is accepted, because the whole
            // point of a test host is that it does not exist — and a literal
            // private address is still refused either way, which is the part
            // that actually protects the network.
            if (app()->environment('local', 'testing')) {
                return [];
            }

            throw ValidationException::withMessages(['url' => __('webhook.url_unresolvable')]);
        }

        return $addresses;
    }

    private function isBlocked(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            // Loopback, link-local and unique-local v6, plus v4-mapped
            // addresses, which are a private v4 address wearing a v6 hat.
            $lower = strtolower($address);

            return $lower === '::1'
                || str_starts_with($lower, 'fe80:')
                || str_starts_with($lower, 'fc')
                || str_starts_with($lower, 'fd')
                || str_starts_with($lower, '::ffff:');
        }

        foreach (self::BLOCKED_RANGES as $range) {
            [$subnet, $bits] = explode('/', $range);

            $mask = -1 << (32 - (int) $bits);

            if ((ip2long($address) & $mask) === (ip2long($subnet) & $mask)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Local development points webhooks at a machine on the same network, and
     * refusing that would make the feature untestable without a public host.
     * Never true outside local.
     */
    private function allowsInsecure(): bool
    {
        return app()->environment('local', 'testing');
    }
}
