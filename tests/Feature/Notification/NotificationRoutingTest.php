<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Modules\Identity\Models\Role;
use App\Modules\Notification\Models\Notification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * A notification that goes nowhere looks exactly like one that was never
 * due.
 *
 * `MaintenanceNotifier::holdersOf()` takes a role code and returns an
 * empty collection when no such role exists — which is the right thing
 * for a tenant who has not staffed that role, and a silent hole when the
 * code is simply misspelt or renamed. Nothing throws, no row is written,
 * and the storekeeper is left waiting for a warning the system believes
 * it sent.
 *
 * The same is true of the event type and its wording: an event outside
 * the allowlist is refused at the gate, and one without a translation
 * arrives as a raw key in somebody's bell menu.
 */
class NotificationRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_every_role_a_notifier_addresses_is_a_role_that_exists(): void
    {
        $addressed = $this->matchesInSource("/holdersOf\('([A-Z_]+)'/");

        $this->assertNotEmpty($addressed, 'No notifier addresses any role — this test found nothing to check.');

        $seeded = Role::whereNull('company_id')->pluck('name')->all();
        $unknown = array_values(array_diff($addressed, $seeded));

        $this->assertSame(
            [],
            $unknown,
            'These role codes are notified but seeded to nobody, so those notifications go silently nowhere: '
            .implode(', ', $unknown),
        );
    }

    public function test_every_event_a_notifier_raises_is_on_the_allowlist(): void
    {
        $raised = $this->matchesInSource("/eventType: '([A-Z_]+)'/");

        $this->assertNotEmpty($raised, 'No notifier raises any event — this test found nothing to check.');

        $allowed = array_merge(Notification::EVENT_TYPES, Notification::PLATFORM_EVENT_TYPES);
        $unknown = array_values(array_diff($raised, $allowed));

        $this->assertSame(
            [],
            $unknown,
            "These events are raised but are not in Notification::EVENT_TYPES, so they are refused at the gate: "
            .implode(', ', $unknown),
        );
    }

    /**
     * Tenant events only. A platform notice is worded at the call site
     * from each locale's `platform.php` and handed to the notifier
     * already written, because it is addressed to colleagues rather than
     * to a customer and carries no per-event template.
     */
    public function test_every_event_has_a_title_and_body_in_both_languages(): void
    {
        $missing = [];

        $tenantEvents = array_diff(Notification::EVENT_TYPES, Notification::PLATFORM_EVENT_TYPES);

        foreach ($tenantEvents as $event) {
            foreach (['en', 'bn'] as $locale) {
                foreach (['title', 'body'] as $part) {
                    $key = "notification.event.{$event}.{$part}";

                    if (! is_string(__($key, [], $locale)) || __($key, [], $locale) === $key) {
                        $missing[] = "{$locale}: {$key}";
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            "These notifications would arrive as a raw key rather than a sentence:\n".implode("\n", $missing),
        );
    }

    /**
     * Every distinct capture of `$pattern` across the application source.
     *
     * Read from the files rather than by calling the notifiers, because
     * the point is to cover paths no test happens to exercise — a
     * quarterly sweep that fires once a year is exactly where a renamed
     * role hides.
     *
     * @return list<string>
     */
    private function matchesInSource(string $pattern): array
    {
        $found = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            if ($source === false || preg_match_all($pattern, $source, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $match) {
                $found[$match] = true;
            }
        }

        $keys = array_keys($found);
        sort($keys);

        return $keys;
    }
}
