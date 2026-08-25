<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * One customer, one factory, and everything that customer would have.
 *
 * The same demo data as DemoTenantSeeder — machines in every status, work
 * orders open and closed, breakdowns, stock with a reservation, costs, a
 * warranty, a service contract, a subscription with invoices and a payment —
 * but for a single company with a single factory.
 *
 * A subclass rather than a copy. The full demo is a thousand lines of
 * carefully staged data, and a second copy of it would be identical on the day
 * it was written and quietly different six months later, which is the failure
 * mode where a demo shows something the product no longer does.
 *
 * The two things this turns off are the two the second company and second
 * factory existed for: the company switcher, and a factory-scoped role that
 * has something to be scoped away from. Neither means anything here.
 *
 * NEVER seeded automatically. Run it deliberately:
 *
 *   php artisan db:seed --class=Database\\Seeders\\DemoCustomerSeeder
 *
 * And on a live installation, know what it is: nine sign-ins sharing one
 * well-known password. Fine for showing somebody the product, not something to
 * leave sitting beside real customers.
 */
class DemoCustomerSeeder extends DemoTenantSeeder
{
    protected function seedsSecondCompany(): bool
    {
        return false;
    }

    protected function seedsSecondFactory(): bool
    {
        return false;
    }
}
