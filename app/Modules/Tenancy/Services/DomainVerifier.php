<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Models\CompanyDomain;

/**
 * Proof that a customer owns the address they have claimed.
 *
 * The check is a DNS TXT record, because publishing one requires access to the
 * domain's DNS — which is the thing being proved. A file served at a URL would
 * prove only that somebody can already point that name at us, which is the
 * question, not the answer.
 *
 * A class rather than a call to dns_get_record() inside the controller so the
 * tests can answer without a network, and so a deployment behind a resolver
 * that lies has one place to change.
 */
class DomainVerifier
{
    public function matches(CompanyDomain $domain): bool
    {
        foreach ($this->txtRecords($domain->verificationRecordName()) as $value) {
            // Trimmed and compared exactly. Several DNS panels wrap the value
            // in quotes and some append a dot, and a customer who has done
            // everything right should not be told they have not.
            if (trim($value, " \t\"") === $domain->verification_token) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function txtRecords(string $name): array
    {
        // The @ is deliberate: a name that does not resolve yet is the normal
        // case here, not an error worth a warning in the log. It is what the
        // customer sees before they have added the record.
        $records = @dns_get_record($name, DNS_TXT);

        if ($records === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (array $record): string => (string) ($record['txt'] ?? ''),
            $records,
        )));
    }
}
