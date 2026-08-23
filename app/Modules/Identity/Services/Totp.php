<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

/**
 * Time-based one-time passwords, RFC 6238 (SRS 50.3).
 *
 * Written out rather than pulled in. The algorithm is thirty lines — an HMAC,
 * a dynamic truncation, a modulo — and every authenticator app on a phone
 * implements the same standard. A dependency here would be thirty lines of
 * code wrapped in a supply chain, for a security-critical path.
 *
 * The parameters are the ones every app assumes when they are absent from the
 * enrolment URI: SHA-1, six digits, thirty seconds. They are not choices so
 * much as the definition of what "scan this with Google Authenticator" means.
 */
class Totp
{
    private const DIGITS = 6;

    private const PERIOD = 30;

    private const ALGORITHM = 'sha1';

    /**
     * How far either side of now a code is accepted.
     *
     * One step, which is thirty seconds each way. Phones drift, and somebody
     * typing a six-digit code from a screen takes a few seconds; a window of
     * zero rejects honest people. A wider window would multiply the number of
     * codes valid at any instant, which is the only thing that makes brute
     * force easier.
     */
    private const WINDOW = 1;

    /** RFC 4648 base32, which is what authenticator apps read. */
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * A fresh secret, base32-encoded.
     *
     * 160 bits, matching the SHA-1 block the HMAC uses. Shorter would be
     * weaker for no gain; longer is discarded by the HMAC anyway.
     */
    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /**
     * The code for a given moment, normally now.
     */
    public function codeAt(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), self::PERIOD);

        return $this->codeForCounter($secret, $counter);
    }

    /**
     * Is this the code on the person's phone?
     *
     * Compared with `hash_equals`, not `===`. A timing-variable comparison of
     * a six-digit code is a real oracle when an attacker can try repeatedly,
     * and the cost of doing it properly is nothing.
     */
    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $counter = intdiv($timestamp ?? time(), self::PERIOD);

        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            if (hash_equals($this->codeForCounter($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The enrolment URI an authenticator app scans.
     *
     * The issuer appears twice — once in the label and once as a parameter —
     * because different apps read different halves, and an entry that shows up
     * as a bare email address among forty others is one nobody can identify
     * when they need it.
     */
    public function provisioningUri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper(self::ALGORITHM),
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);
    }

    private function codeForCounter(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);

        // The counter as eight bytes, big-endian. pack("J") would do it on
        // 64-bit builds only; this is explicit and portable.
        $binaryCounter = str_pad(pack('N*', 0, $counter), 8, "\0", STR_PAD_LEFT);
        $binaryCounter = substr($binaryCounter, -8);

        $hash = hash_hmac(self::ALGORITHM, $binaryCounter, $key, true);

        // Dynamic truncation: the low nibble of the last byte picks where in
        // the hash to read from, so no fixed part of the HMAC is ever exposed.
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::BASE32[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }

    private function base32Decode(string $secret): string
    {
        // Padding and case are both things a person retyping a secret by hand
        // gets wrong, and neither carries meaning.
        $secret = strtoupper(str_replace('=', '', trim($secret)));

        $bits = '';

        foreach (str_split($secret) as $character) {
            $index = strpos(self::BASE32, $character);

            if ($index === false) {
                continue;
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
