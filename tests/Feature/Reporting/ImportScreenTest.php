<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Models\ImportJob;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The import screens over HTTP (SRS 33).
 */
class ImportScreenTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        AssetLocation::create([
            'factory_id' => $this->dhaka->id,
            'name' => 'Line 3',
            'code' => 'DHK-L3',
            'status' => 'ACTIVE',
        ]);
    }

    private function user(string $role, string $email): User
    {
        $user = TenantFixture::user($this->delta, $role, $email);
        TenantFixture::actingAsTenant($this->delta);

        return $user;
    }

    private function csv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('assets.csv', <<<'CSV'
        asset_code,name,asset_type_code,asset_category_code,factory_code,location_code
        IMP-001,Imported lockstitch,SEWING,LOCKSTITCH,DHK,DHK-L3
        CSV);
    }

    public function test_the_index_lists_what_the_user_may_write(): void
    {
        $technician = $this->user('TECHNICIAN', 'tech@delta.test');

        $this->actingAs($technician)
            ->get('/app/imports')
            ->assertOk()
            // A technician creates neither machines nor locations, so importing
            // is gated on the same permission as creating one by hand.
            ->assertSee(__('import.no_importers'));
    }

    public function test_an_import_the_user_cannot_write_is_not_found(): void
    {
        $technician = $this->user('TECHNICIAN', 'tech2@delta.test');

        $this->actingAs($technician)->get('/app/imports/assets')->assertNotFound();
        $this->actingAs($technician)->get('/app/imports/assets/template')->assertNotFound();
    }

    public function test_the_template_carries_the_headers_and_an_example(): void
    {
        $owner = $this->user('COMPANY_OWNER', 'owner@delta.test');

        $response = $this->actingAs($owner)->get('/app/imports/assets/template')->assertOk();

        $body = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('asset_code', $body);
        // One filled row, because a header line alone does not say which date
        // format or whether the type is the name or the code.
        $this->assertStringContainsString('SEW-DHK-00412', $body);
    }

    public function test_uploading_lands_on_the_review_screen_without_writing(): void
    {
        $owner = $this->user('COMPANY_OWNER', 'owner2@delta.test');

        $response = $this->actingAs($owner)
            ->post('/app/imports/assets', ['file' => $this->csv()]);

        $job = ImportJob::firstOrFail();

        $response->assertRedirect(route('app.imports.review', $job));

        $this->assertSame('VALIDATED', $job->status);
        $this->assertSame(0, Asset::where('asset_code', 'IMP-001')->count());
    }

    public function test_the_review_screen_shows_the_counts_and_confirm_writes(): void
    {
        $owner = $this->user('COMPANY_OWNER', 'owner3@delta.test');

        $this->actingAs($owner)->post('/app/imports/assets', ['file' => $this->csv()]);

        $job = ImportJob::firstOrFail();

        $this->actingAs($owner)
            ->get(route('app.imports.review', $job))
            ->assertOk()
            ->assertSee(__('import.stats.valid'))
            ->assertSee('IMP-001');

        $this->actingAs($owner)
            ->post(route('app.imports.confirm', $job))
            ->assertRedirect(route('app.imports.review', $job))
            ->assertSessionHas('status', __('import.completed'));

        $this->assertSame(1, Asset::where('asset_code', 'IMP-001')->count());
    }

    public function test_confirming_twice_does_not_import_twice(): void
    {
        $owner = $this->user('COMPANY_OWNER', 'owner4@delta.test');

        $this->actingAs($owner)->post('/app/imports/assets', ['file' => $this->csv()]);

        $job = ImportJob::firstOrFail();

        $this->actingAs($owner)->post(route('app.imports.confirm', $job));
        $this->actingAs($owner)
            ->from(route('app.imports.review', $job))
            ->post(route('app.imports.confirm', $job))
            ->assertSessionHas('error', __('import.not_confirmable'));

        // The state guard is the protection: an import is not idempotent from
        // the person's point of view, and a double-clicked button must not
        // write the file twice.
        $this->assertSame(1, Asset::where('asset_code', 'IMP-001')->count());
    }

    public function test_the_error_report_downloads_as_a_file(): void
    {
        $owner = $this->user('COMPANY_OWNER', 'owner5@delta.test');

        $bad = UploadedFile::fake()->createWithContent('assets.csv', <<<'CSV'
        asset_code,name,asset_type_code,asset_category_code,factory_code,location_code
        IMP-001,Unknown type,NOSUCHTYPE,LOCKSTITCH,DHK,DHK-L3
        CSV);

        $this->actingAs($owner)->post('/app/imports/assets', ['file' => $bad]);

        $job = ImportJob::firstOrFail();

        $response = $this->actingAs($owner)
            ->get(route('app.imports.errors', $job))
            ->assertOk();

        $body = $response->streamedContent();

        $this->assertStringContainsString('NOSUCHTYPE', $body);
        $this->assertStringContainsString(__('import.errors.unknown_reference'), $body);
    }

    public function test_a_person_cannot_open_someone_elses_import(): void
    {
        $owner = $this->user('COMPANY_OWNER', 'owner6@delta.test');
        $other = $this->user('FACTORY_ADMIN', 'admin@delta.test');

        $this->actingAs($owner)->post('/app/imports/assets', ['file' => $this->csv()]);

        $job = ImportJob::firstOrFail();

        // The error report repeats the uploaded file back to whoever opens it.
        $this->actingAs($other)->get(route('app.imports.review', $job))->assertNotFound();
        $this->actingAs($other)->get(route('app.imports.errors', $job))->assertNotFound();
    }

    public function test_an_import_can_be_cancelled_before_it_is_confirmed(): void
    {
        $owner = $this->user('COMPANY_OWNER', 'owner7@delta.test');

        $this->actingAs($owner)->post('/app/imports/assets', ['file' => $this->csv()]);

        $job = ImportJob::firstOrFail();

        $this->actingAs($owner)
            ->post(route('app.imports.cancel', $job))
            ->assertRedirect(route('app.imports.index'));

        $this->assertSame('CANCELLED', $job->fresh()->status);
        $this->assertSame(0, Asset::where('asset_code', 'IMP-001')->count());
    }

    public function test_the_import_screens_render_in_bengali(): void
    {
        $owner = $this->user('COMPANY_OWNER', 'owner8@delta.test');
        $owner->update(['locale' => 'bn']);

        $this->actingAs($owner)
            ->get('/app/imports')
            ->assertOk()
            ->assertSee(__('import.imports', locale: 'bn'), false);
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/app/imports')->assertRedirect('/login');
    }
}
