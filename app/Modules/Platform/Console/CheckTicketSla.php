<?php

declare(strict_types=1);

namespace App\Modules\Platform\Console;

use App\Modules\Platform\Actions\ManageSupportTicket;
use App\Modules\Platform\Models\SupportTicket;
use App\Shared\Scopes\TenantScope;
use Illuminate\Console\Command;

/**
 * Flags a ticket the moment it misses a deadline it was never going to get a
 * second warning for on its own — nobody reads a due-date column that never
 * turns red. Every open ticket across every company in one pass: a support
 * ticket carries no factory/department scope to loop over the way
 * `AlertExpiringCoverage` loops over companies, and `PlatformNotifier` is
 * tenant-context-free by design.
 */
class CheckTicketSla extends Command
{
    protected $signature = 'support:check-sla';

    protected $description = 'Flag support tickets that have missed their first-response or resolution SLA';

    public function handle(ManageSupportTicket $support): int
    {
        $now = now();

        $firstResponseBreaches = SupportTicket::withoutGlobalScope(TenantScope::class)
            ->whereIn('status', SupportTicket::OPEN_STATUSES)
            ->whereNull('first_responded_at')
            ->whereNull('first_response_breach_notified_at')
            ->whereNotNull('first_response_due_at')
            ->where('first_response_due_at', '<', $now)
            ->get();

        foreach ($firstResponseBreaches as $ticket) {
            $support->notifySlaBreach($ticket, 'first_response');
        }

        $resolutionBreaches = SupportTicket::withoutGlobalScope(TenantScope::class)
            ->whereIn('status', SupportTicket::OPEN_STATUSES)
            ->whereNull('resolution_breach_notified_at')
            ->whereNotNull('resolution_due_at')
            ->where('resolution_due_at', '<', $now)
            ->get();

        foreach ($resolutionBreaches as $ticket) {
            $support->notifySlaBreach($ticket, 'resolution');
        }

        $this->info(sprintf(
            'Flagged %d first-response breaches and %d resolution breaches.',
            $firstResponseBreaches->count(),
            $resolutionBreaches->count(),
        ));

        return self::SUCCESS;
    }
}
