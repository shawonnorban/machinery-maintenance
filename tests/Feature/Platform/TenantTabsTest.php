<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * A customer's page, split into tabs (SRS 3.1, 5, 40).
 *
 * Company management, billing, tickets and the rest used to be panels
 * stacked on one page, and the page grew a panel every time this customer
 * gained a capability until it was longer than anybody read. Each tab is now
 * its own URL — this is the test that every one of them still renders, and
 * that an unlisted tab name is refused rather than silently shown as a blank
 * page.
 */
class TenantTabsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        $this->staff = User::create([
            'name' => 'Platform Support', 'email' => 'support@platform.test',
            'password' => 'correct-horse-battery', 'status' => 'ACTIVE', 'locale' => 'en',
            'is_platform_admin' => true,
        ]);

        Storage::fake('public');
    }

    /**
     * @return iterable<string, array{0: ?string, 1: string}>
     */
    public static function tabProvider(): iterable
    {
        yield 'default (company)' => [null, 'platform.company_management'];
        yield 'billing' => ['billing', 'platform.bill_management'];
        yield 'domains' => ['domains', 'platform.domains'];
        yield 'support' => ['support', 'platform.support_access'];
        yield 'tickets' => ['tickets', 'platform.support_ticket'];
        yield 'analytics' => ['analytics', 'platform.analytics'];
        yield 'danger' => ['danger', 'platform.danger_zone'];
    }

    #[DataProvider('tabProvider')]
    public function test_every_tab_renders(?string $tab, string $activeLabelKey): void
    {
        $url = '/platform/tenants/'.$this->delta->id.($tab === null ? '' : '/'.$tab);

        $this->actingAs($this->staff)
            ->get($url)
            ->assertOk()
            ->assertSee(__($activeLabelKey));
    }

    public function test_an_unlisted_tab_is_refused_rather_than_shown_blank(): void
    {
        // The route's own {tab} constraint, not a runtime check: a name
        // nobody defined should never reach the controller as "just another
        // tab that happens to render nothing".
        $this->actingAs($this->staff)
            ->get('/platform/tenants/'.$this->delta->id.'/not-a-real-tab')
            ->assertNotFound();
    }

    public function test_company_details_can_be_extended_with_contact_fields(): void
    {
        $this->actingAs($this->staff)
            ->patch('/platform/tenants/'.$this->delta->id.'/details', [
                'name' => 'Delta Apparels Ltd',
                'legal_name' => 'Delta Apparels Limited',
                'email' => 'accounts@deltaapparels.test',
                'phone' => '+880 1711 000000',
                'country' => 'Bangladesh',
                'address' => 'Plot 14, Dhaka EPZ',
                'base_currency' => 'BDT',
                'timezone' => 'Asia/Dhaka',
                'default_locale' => 'en',
            ])
            ->assertRedirect();

        $company = $this->delta->fresh();

        $this->assertSame('accounts@deltaapparels.test', $company->email);
        $this->assertSame('+880 1711 000000', $company->phone);
        $this->assertSame('Bangladesh', $company->country);
        $this->assertSame('Plot 14, Dhaka EPZ', $company->address);
    }

    public function test_a_logo_can_be_uploaded_and_replaced(): void
    {
        $first = UploadedFile::fake()->image('logo.png', 200, 200)->size(50);

        $this->actingAs($this->staff)
            ->post('/platform/tenants/'.$this->delta->id.'/logo', ['logo' => $first])
            ->assertRedirect();

        $company = $this->delta->fresh();
        $firstPath = $company->logo_path;

        $this->assertNotNull($firstPath);
        Storage::disk('public')->assertExists($firstPath);
        $this->assertNotNull($company->logoUrl());

        // Replaced, not accumulated: the old file is removed rather than left
        // behind as an orphan nobody points at any more.
        $second = UploadedFile::fake()->image('new-logo.png', 200, 200)->size(50);

        $this->actingAs($this->staff)
            ->post('/platform/tenants/'.$this->delta->id.'/logo', ['logo' => $second])
            ->assertRedirect();

        Storage::disk('public')->assertMissing($firstPath);
        $this->assertNotSame($firstPath, $company->fresh()->logo_path);
    }

    public function test_a_logo_upload_is_refused_when_too_large_or_wrong_type(): void
    {
        $tooLarge = UploadedFile::fake()->image('logo.png', 200, 200)->size(1024);

        $this->actingAs($this->staff)
            ->from('/platform/tenants/'.$this->delta->id)
            ->post('/platform/tenants/'.$this->delta->id.'/logo', ['logo' => $tooLarge])
            ->assertSessionHasErrors('logo');

        $notAnImage = UploadedFile::fake()->create('logo.pdf', 50, 'application/pdf');

        $this->actingAs($this->staff)
            ->from('/platform/tenants/'.$this->delta->id)
            ->post('/platform/tenants/'.$this->delta->id.'/logo', ['logo' => $notAnImage])
            ->assertSessionHasErrors('logo');

        $this->assertNull($this->delta->fresh()->logo_path);
    }

    public function test_the_tab_bar_carries_the_open_ticket_badge(): void
    {
        $this->actingAs($this->owner)->post('/app/support/tickets', [
            'subject' => 'Meter readings not syncing',
            'body' => 'Meter readings stopped appearing for the compressor.',
        ]);

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->staff)
            ->get('/platform/tenants/'.$this->delta->id)
            ->assertOk()
            ->assertSee('badge', false);
    }

    public function test_the_company_tab_reads_rather_than_edits(): void
    {
        $this->delta->forceFill([
            'phone' => '+880 1711 000000',
            'country' => 'Bangladesh',
        ])->save();

        $response = $this->actingAs($this->staff)
            ->get('/platform/tenants/'.$this->delta->id)
            ->assertOk();

        // The facts are readable as text, not sitting in input boxes that
        // make every one of them look half-changed.
        $response->assertSee('+880 1711 000000')
            ->assertSee('Bangladesh')
            ->assertDontSee('name="phone"', false)
            ->assertDontSee('name="country"', false);

        // And editing is one click away rather than gone.
        $response->assertSee(route('platform.tenants.edit', $this->delta), false);
    }

    public function test_the_edit_page_carries_the_form(): void
    {
        $this->actingAs($this->staff)
            ->get('/platform/tenants/'.$this->delta->id.'/company/edit')
            ->assertOk()
            ->assertSee('name="phone"', false)
            ->assertSee('name="country"', false)
            ->assertSee('name="logo"', false);
    }

    public function test_the_edit_url_is_not_swallowed_by_the_tab_route(): void
    {
        // /tenants/{id}/company/edit is two segments where a tab is one, and
        // the route is declared first regardless — but "company" is also a
        // real tab name, so this is exactly the collision worth pinning down.
        $this->actingAs($this->staff)
            ->get('/platform/tenants/'.$this->delta->id.'/company/edit')
            ->assertOk()
            ->assertSee(__('platform.edit_company', ['name' => $this->delta->name]));
    }

    public function test_a_logo_shows_on_the_customer_card_and_the_page_head(): void
    {
        $this->actingAs($this->staff)->post('/platform/tenants/'.$this->delta->id.'/logo', [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200)->size(50),
        ]);

        $url = $this->delta->fresh()->logoUrl();

        // The card on the customer list showed the monogram whatever the
        // customer had uploaded, because the choice was made at each call
        // site and this one had never been told about logos.
        $this->actingAs($this->staff)->get('/platform')->assertOk()->assertSee($url, false);

        $this->actingAs($this->staff)
            ->get('/platform/tenants/'.$this->delta->id)
            ->assertOk()
            ->assertSee($url, false);
    }

    public function test_a_customer_without_a_logo_still_gets_a_monogram(): void
    {
        // The fallback is the whole reason the choice moved into one partial:
        // a customer mid-onboarding has no logo and must not render an empty
        // box where every other card has a mark.
        $this->actingAs($this->staff)
            ->get('/platform')
            ->assertOk()
            ->assertSee('tenant-mark', false)
            ->assertSee('>DA<', false);
    }
}
