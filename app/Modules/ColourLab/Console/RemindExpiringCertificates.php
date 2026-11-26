<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Console;

use App\Modules\ColourLab\Models\LabCertificate;
use App\Modules\ColourLab\Support\ColourLab;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Test Certificate Directory's reminder (AnnotechERP 16/01): Notify BF Days
 * before a certificate's Valid Date, Mail (To) and Mail (Cc) hear that it is
 * running out — once for that Valid Date, so a renewal (a new Valid Date)
 * earns a fresh one and a missed morning still sends on the next.
 */
class RemindExpiringCertificates extends Command
{
    protected $signature = 'colour-lab:certificate-reminders';

    protected $description = 'Mail the lab when a certificate nears its Valid Date';

    public function handle(TenantContext $context): int
    {
        $today = CarbonImmutable::now('Asia/Dhaka')->startOfDay();
        $sent = 0;
        foreach (Company::withoutGlobalScope(TenantScope::class)->get() as $company) {
            $context->forget();
            $context->set($company->id);
            $types = ColourLab::certTypes();
            $due = LabCertificate::query()->where('active', true)->whereNotNull('mail_to')->whereDate('valid_date', '>=', $today)->get()
                ->filter(fn (LabCertificate $c) => $today->greaterThanOrEqualTo($c->valid_date->copy()->subDays($c->notify_days))
                    && $c->notified_for?->toDateString() !== $c->valid_date->toDateString());
            foreach ($due as $c) {
                [$to] = ColourLab::emails($c->mail_to);
                [$cc] = ColourLab::emails($c->mail_cc);
                $name = $types[$c->cert_type_id] ?? $c->document_ref;
                $days = (int) $today->diffInDays($c->valid_date, false);
                Mail::raw(__('colourlab.reminder.body', ['name' => $name, 'ref' => $c->document_ref, 'date' => $c->valid_date->format('d M Y'), 'days' => $days, 'company' => $company->name]),
                    fn ($m) => $m->to($to)->cc($cc)->subject(__('colourlab.reminder.subject', ['name' => $name, 'date' => $c->valid_date->format('d M Y')])));
                $c->update(['notified_for' => $c->valid_date->toDateString()]);
                $sent++;
            }
        }
        $context->forget();
        $this->info("{$sent} certificate reminder(s) sent.");

        return self::SUCCESS;
    }
}
