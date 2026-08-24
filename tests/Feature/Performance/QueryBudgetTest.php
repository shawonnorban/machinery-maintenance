<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The one performance property a test can actually hold down (SRS 45, 51).
 *
 * Latency is a property of a machine under load and belongs in a load test.
 * What belongs here is the thing that turns a fast screen into a slow one
 * without anybody noticing: a query issued once per row.
 *
 * The method is comparative rather than absolute. A fixed budget — "the asset
 * list must issue at most 14 queries" — breaks every time somebody adds a
 * legitimate lookup, and gets raised until it means nothing. Instead the same
 * screen is rendered with a few rows and with many, and the difference is what
 * is asserted: the query count must not grow with the data. A screen that
 * issues forty queries for five rows and forty-one for fifty is fine. One that
 * issues eleven and then a hundred and one is the outage.
 *
 * `Model::preventLazyLoading` already turns the commonest cause into a failure
 * everywhere. This catches the other one: an explicit loop that queries.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * How much a count may grow between the small and large fixtures before it
     * counts as growing with the data.
     *
     * Not zero. Pagination itself changes shape — a `count` for the paginator,
     * an `in` clause that eager-loads more distinct parents — and a couple of
     * extra queries for ten times the rows is not an N+1.
     */
    private const TOLERANCE = 3;

    private Company $delta;

    private Factory $dhaka;

    private User $owner;

    private int $assetsMade = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);
    }

    public function test_the_asset_list_does_not_query_once_per_machine(): void
    {
        $this->assertDoesNotGrowWithData('/app/assets', fn (int $n) => $this->makeAssets($n));
    }

    public function test_the_asset_api_does_not_query_once_per_machine(): void
    {
        $token = $this->apiToken();

        $this->assertDoesNotGrowWithData(
            '/api/v1/assets?per_page=100',
            fn (int $n) => $this->makeAssets($n),
            $token,
        );
    }

    public function test_the_breakdown_list_does_not_query_once_per_breakdown(): void
    {
        $this->assertDoesNotGrowWithData('/app/breakdowns', function (int $n): void {
            $assets = $this->makeAssets($n);

            foreach ($assets as $asset) {
                app(ReportBreakdown::class)->handle([
                    'asset_id' => $asset->id,
                    'problem_description' => 'Stopped',
                ], $this->owner->id);
            }
        });
    }

    /**
     * Render the same screen twice, with a few rows and with many, and assert
     * the query count did not follow the rows.
     *
     * @param  callable(int): mixed  $seed
     */
    private function assertDoesNotGrowWithData(string $url, callable $seed, ?string $token = null): void
    {
        $seed(3);
        $small = $this->countQueries($url, $token);

        $seed(30);
        $large = $this->countQueries($url, $token);

        $this->assertLessThanOrEqual(
            $small + self::TOLERANCE,
            $large,
            sprintf(
                '%s issued %d queries for a few rows and %d for ten times as many. '
                .'That is a query per row, which is a fast screen today and an outage at 20,000 assets.',
                $url,
                $small,
                $large,
            ),
        );
    }

    private function countQueries(string $url, ?string $token): int
    {
        // The query log rather than `DB::listen`: a listener registered here
        // would still be attached for the second measurement and count every
        // query twice, and the obvious way to drop it — purging the connection
        // — would roll back the transaction this test is running inside.
        DB::enableQueryLog();
        DB::flushQueryLog();

        $request = $token === null
            ? $this->actingAs($this->owner)->get($url)
            : $this->withToken($token)->getJson($url);

        $request->assertOk();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }

    /**
     * @return list<Asset>
     */
    private function makeAssets(int $count): array
    {
        $made = [];

        for ($i = 0; $i < $count; $i++) {
            // A running counter across calls, because this is called twice in
            // one test and an asset code is unique per company.
            $this->assetsMade++;

            $made[] = WorkOrderFixture::runningAsset(
                $this->delta,
                $this->dhaka,
                sprintf('SEW-DHK-%05d', $this->assetsMade),
            );
        }

        return $made;
    }

    private function apiToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@delta.test',
            'password' => 'correct-horse-battery',
        ])->json('data.access_token');
    }
}
