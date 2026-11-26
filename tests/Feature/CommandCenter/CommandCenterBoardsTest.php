<?php

declare(strict_types=1);

namespace Tests\Feature\CommandCenter;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Command Center → Command Center (AnnotechERP 27/01): departmental boards
 * (one per department, the department and owner from the masters, never
 * published without a tile), drill paths (a metric once, three levels from
 * their own lists, rows in thousands) and dashboard compositions (a title
 * once, never released empty — nor emptied once released); and who may.
 */
class CommandCenterBoardsTest extends TestCase
{
    use RefreshDatabase;

    private const E = '/api/v1/erp';

    private Company $delta;

    private User $owner;

    private string $sewing;

    private string $cutting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $factory = TenantFixture::factory($this->delta, 'Delta Unit 1', 'DU1');
        $this->sewing = Department::create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Sewing', 'code' => 'SEW'])->id;
        $this->cutting = Department::create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Cutting', 'code' => 'CUT'])->id;
    }

    public function test_departmental_boards(): void
    {
        $o = $this->as()->getJson('/api/v1/command-center/options')->assertOk()->json('data');
        $this->assertSame(['Cutting', 'Sewing'], array_column($o['departments'], 'label'));
        $this->assertContains($this->owner->name, array_column($o['staff'], 'label'));

        $this->as()->postJson(self::E.'/cc-boards', [])->assertUnprocessable()->assertJsonPath('errors.name.0', __('commandcenter.v.board'));
        $b = $this->as()->postJson(self::E.'/cc-boards', ['name' => ' Sewing Floor Live ', 'department_id' => $this->sewing, 'owner_id' => $this->owner->id, 'tiles' => 12, 'refresh' => 'LIVE', 'audience' => 'ALL_MANAGERS', 'data_health' => 94, 'published' => true])->assertCreated()->json('data');
        $this->assertEquals(['Sewing Floor Live', 'Sewing', $this->owner->name, 12, true, 94], [$b['name'], $b['department'], $b['owner'], $b['tiles'], $b['published'], $b['data_health']]);
        // One board per department.
        $this->as()->postJson(self::E.'/cc-boards', ['name' => 'Sewing two', 'department_id' => $this->sewing])->assertUnprocessable()->assertJsonPath('errors.department_id.0', __('commandcenter.v.board_taken', ['department' => 'Sewing']));
        // A new board is a draft, daily, for department heads, fully healthy.
        $draft = $this->as()->postJson(self::E.'/cc-boards', ['name' => 'Cutting Lay Efficiency', 'department_id' => $this->cutting])->assertCreated()->json('data');
        $this->assertEquals([false, 'DAILY', 'DEPT_HEADS', 100, 0], [$draft['published'], $draft['refresh'], $draft['audience'], $draft['data_health'], $draft['tiles']]);
        // Never published without a tile — nor emptied once published.
        $this->as()->patchJson(self::E."/cc-boards/{$draft['id']}", ['published' => true])->assertUnprocessable()->assertJsonPath('errors.published.0', __('commandcenter.v.publish_empty'));
        $this->as()->patchJson(self::E."/cc-boards/{$b['id']}", ['tiles' => 0])->assertUnprocessable()->assertJsonPath('errors.tiles.0', __('commandcenter.v.publish_empty'));
        $this->as()->patchJson(self::E."/cc-boards/{$draft['id']}", ['tiles' => 7, 'published' => true])->assertOk()->assertJsonPath('data.published', true);
        $this->as()->patchJson(self::E."/cc-boards/{$b['id']}", ['published' => false])->assertOk()->assertJsonPath('data.published', false);
        $this->as()->postJson(self::E.'/cc-boards', ['name' => 'X', 'refresh' => 'MONTHLY'])->assertUnprocessable()->assertJsonValidationErrors(['refresh']);
        $this->as()->postJson(self::E.'/cc-boards', ['name' => 'X', 'data_health' => 120])->assertUnprocessable()->assertJsonValidationErrors(['data_health']);
        $this->assertSame([$b['id'], $draft['id']], array_column($this->as()->getJson(self::E.'/cc-boards')->json('data'), 'id'));
    }

    public function test_drill_paths_and_compositions(): void
    {
        $this->as()->postJson(self::E.'/cc-drill-paths', [])->assertUnprocessable()->assertJsonPath('errors.metric.0', __('commandcenter.v.metric'));
        $p = $this->as()->postJson(self::E.'/cc-drill-paths', ['metric' => 'Shipment value', 'domain' => 'COMMERCIAL', 'level_1' => 'DIVISION', 'level_2' => 'STYLE', 'level_3' => 'COLOUR', 'source' => 'MERCHANDISING', 'grain' => 'DAILY', 'rows_k' => 480])->assertCreated()->json('data');
        $this->assertEquals(['DIVISION', 'STYLE', 'COLOUR', 480], [$p['level_1'], $p['level_2'], $p['level_3'], $p['rows_k']]);
        $this->as()->postJson(self::E.'/cc-drill-paths', ['metric' => 'shipment VALUE'])->assertUnprocessable()->assertJsonPath('errors.metric.0', __('commandcenter.v.metric_taken'));
        // Each level from its own list: a style is never the first level.
        $this->as()->postJson(self::E.'/cc-drill-paths', ['metric' => 'X', 'level_1' => 'STYLE'])->assertUnprocessable()->assertJsonValidationErrors(['level_1']);
        $this->as()->postJson(self::E.'/cc-drill-paths', ['metric' => 'X', 'source' => 'GATE_PASS'])->assertUnprocessable()->assertJsonValidationErrors(['source']);

        $this->as()->postJson(self::E.'/cc-compositions', [])->assertUnprocessable()->assertJsonPath('errors.title.0', __('commandcenter.v.composition'));
        $c = $this->as()->postJson(self::E.'/cc-compositions', ['title' => 'Winter 26 readiness', 'layout' => 'THREE', 'author_id' => $this->owner->id, 'tiles' => 11, 'charts' => 4, 'shared_with' => 'DIVISION_HEADS', 'state' => 'RELEASED'])->assertCreated()->json('data');
        $this->assertEquals(['RELEASED', $this->owner->name], [$c['state'], $c['author']]);
        $this->as()->postJson(self::E.'/cc-compositions', ['title' => 'winter 26 readiness'])->assertUnprocessable()->assertJsonPath('errors.title.0', __('commandcenter.v.composition_taken'));
        $this->as()->postJson(self::E.'/cc-compositions', ['title' => 'Empty', 'state' => 'RELEASED'])->assertUnprocessable()->assertJsonPath('errors.state.0', __('commandcenter.v.release_empty'));
        $this->as()->patchJson(self::E."/cc-compositions/{$c['id']}", ['tiles' => 0, 'charts' => 0])->assertUnprocessable()->assertJsonPath('errors.tiles.0', __('commandcenter.v.release_empty'));
        $this->as()->patchJson(self::E."/cc-compositions/{$c['id']}", ['charts' => 0])->assertOk();
        $this->as()->patchJson(self::E."/cc-compositions/{$c['id']}", ['state' => 'ARCHIVED', 'tiles' => 0])->assertOk()->assertJsonPath('data.state', 'ARCHIVED');
        $draft = $this->as()->postJson(self::E.'/cc-compositions', ['title' => 'Attrition & overtime'])->assertCreated()->json('data');
        $this->assertEquals(['DRAFT', 'BOARD', 0, 0], [$draft['state'], $draft['shared_with'], $draft['tiles'], $draft['charts']]);
        $this->as()->postJson(self::E.'/cc-compositions', ['title' => 'X', 'layout' => 'MASONRY'])->assertUnprocessable()->assertJsonValidationErrors(['layout']);
    }

    public function test_who_may(): void
    {
        $auditor = TenantFixture::user($this->delta, 'AUDITOR', 'auditor@delta.test');
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test');
        $this->as($auditor)->getJson(self::E.'/cc-boards')->assertOk();
        $this->as($auditor)->getJson('/api/v1/command-center/options')->assertOk();
        $this->as($auditor)->postJson(self::E.'/cc-compositions', ['title' => 'X'])->assertForbidden();
        $this->as($manager)->postJson(self::E.'/cc-drill-paths', ['metric' => 'Line efficiency'])->assertCreated();
        $this->as($keeper)->getJson(self::E.'/cc-drill-paths')->assertForbidden();
        $this->as($keeper)->getJson('/api/v1/command-center/options')->assertForbidden();

        $other = TenantFixture::company('Other Ltd', 'OTH');
        TenantFixture::actingAsTenant($other);
        $stranger = TenantFixture::user($other, 'COMPANY_OWNER', 'owner@other.test');
        $this->assertSame([], $this->as($stranger, $other)->getJson(self::E.'/cc-drill-paths')->assertOk()->json('data'));
        $this->as($stranger, $other)->postJson(self::E.'/cc-boards', ['name' => 'X', 'department_id' => $this->sewing])->assertUnprocessable()->assertJsonValidationErrors(['department_id']);
        $this->as($stranger, $other)->postJson(self::E.'/cc-compositions', ['title' => 'X', 'author_id' => $this->owner->id])->assertUnprocessable()->assertJsonValidationErrors(['author_id']);
    }

    private function as(?User $user = null, ?Company $company = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, ($company ?? $this->delta)->id, 'Test token');

        return $this->withToken($plain);
    }
}
