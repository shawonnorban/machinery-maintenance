<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OrderSize;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\Size;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Master Data → Style & Order Upload (AnnotechERP 06/01): a buyer's delimited
 * spreadsheet is verified row by row against the register — buyer, account,
 * country, colour and size held, numbers and dates sound, nothing twice and
 * nothing already on the register — and only a batch with no rejected row is
 * uploaded: each Order# of a style one confirmed order (the style made when
 * new), its rows the colour × size breakdown; numbered UPL-00001.
 */
class MerchandiseOrderUploadTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'Buyer A/C Group,Buyer A/C,Buyer,Style,Job#,Sub Style,Order#,Confirm DT,Ship DT,Ship Country,Item,GMT Type,GMT Color,Size,Qty,Rate,Total,Production Date';

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
    }

    public function test_a_buyer_spreadsheet_is_verified_against_the_register_and_uploaded_as_one_batch(): void
    {
        $this->register();
        $this->as()->postJson('/api/v1/merchandise/order-uploads/verify', [])->assertUnprocessable()->assertJsonValidationErrors(['file' => 'Attach the spreadsheet first.']);
        $this->as()->post('/api/v1/merchandise/order-uploads/verify', ['file' => $this->csv("Buyer,Style\nLPP,X")], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'The header row lacks: Job#, Order#, Ship DT, GMT Color, Size, Qty.']);

        // Five rows: an unknown colour, a total that is not qty × rate, the same line twice, an unknown buyer.
        $bad = $this->csv(implode("\n", [
            self::HEADER,
            'LPP Group,LPP Office,LPP,637PK,529,,PO-4401,01/10/2026,30/11/2026,Poland,Hoodie,Knit,Navy,M,1200,2.50,3000,15/11/2026',
            'LPP Group,LPP Office,LPP,637PK,529,,PO-4401,01/10/2026,30/11/2026,Poland,Hoodie,Knit,Teal,L,800,2.50,2000,15/11/2026',
            'LPP Group,LPP Office,LPP,637PK,529,,PO-4401,01/10/2026,30/11/2026,Poland,Hoodie,Knit,Navy,L,800,2.50,1900,15/11/2026',
            'LPP Group,LPP Office,LPP,637PK,529,,PO-4401,01/10/2026,30/11/2026,Poland,Hoodie,Knit,Navy,M,100,2.50,250,15/11/2026',
            ',,ZARA,Z-01,9,,PO-1,2026-10-01,2026-11-30,,Tee,Knit,Navy,M,10,1,10,',
        ]));
        $staged = $this->as()->post('/api/v1/merchandise/order-uploads/verify', ['file' => $bad], ['Accept' => 'application/json'])->assertOk()->json('data');
        $this->assertSame([5, 1, 4], [count($staged['rows']), $staged['accepted'], $staged['rejected']]);
        $this->assertSame([
            [2, 'OK', []],
            [3, 'REJECTED', ['GMT Color "Teal" is not in the Colour Directory.']],
            [4, 'REJECTED', ['Total 1900 is not Qty 800 × Rate 2.50.']],
            [5, 'REJECTED', ['The same order, colour and size is already on row 2.']],
            [6, 'REJECTED', ['Buyer "ZARA" is not on the Buyer Account Master.']],
        ], array_map(fn ($r) => [$r['row_no'], $r['status'], $r['reasons']], $staged['rows']));
        $this->assertTrue($staged['rows'][0]['new_style']);
        $this->assertEquals(['2026-10-01', '2026-11-30', 3000], [$staged['rows'][0]['confirm_date'], $staged['rows'][0]['ship_date'], $staged['rows'][0]['total']]);
        // One rejected row blocks the whole batch.
        $this->as()->post('/api/v1/merchandise/order-uploads', ['file' => $bad], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => '4 rows are rejected — the batch is not uploaded until they are fixed.']);
        $this->assertSame(0, Order::query()->count());

        // Fixed (tab-separated this time): two orders of one new style, three lines.
        $good = $this->csv(implode("\n", array_map(fn (string $l) => str_replace(',', "\t", $l), [
            self::HEADER,
            'LPP Group,LPP Office,LPP,637PK,529,,PO-4401,01/10/2026,30/11/2026,Poland,Hoodie,Knit,Navy,M,1200,2.50,3000,15/11/2026',
            'LPP Group,LPP Office,LPP,637PK,529,,PO-4401,01/10/2026,30/11/2026,Poland,Hoodie,Knit,NV-01,L,800,2.50,2000,15/11/2026',
            'LPP Group,LPP Office,LPP,637PK,530,,PO-4402,01-Oct-2026,15-Dec-2026,,Hoodie,Knit,Navy,M,500,2.60,,',
        ])), 'lpp-ss27.tsv');
        $done = $this->as()->post('/api/v1/merchandise/order-uploads', ['file' => $good], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertSame(['UPL-00001', 3, 1, 2], [$done['batch_no'], $done['rows'], $done['styles'], $done['orders']]);

        $style = Style::query()->where('style_ref', '637PK')->firstOrFail();
        $this->assertSame(['RUNNING', 'Hoodie'], [$style->status, $style->description]);
        $orders = Order::query()->orderBy('po_no')->get();
        $this->assertSame([['PO-4401', '529', 2000, 2.5, 'CONFIRMED', '2026-11-30'], ['PO-4402', '530', 500, 2.6, 'CONFIRMED', '2026-12-15']],
            $orders->map(fn (Order $o) => [$o->po_no, $o->job_no, $o->total_qty, $o->unit_price, $o->status, $o->ship_date->toDateString()])->all());
        $this->assertSame([['Navy', 'M', 1200], ['Navy', 'L', 800]], OrderSize::query()->where('order_id', $orders[0]->id)->orderBy('sort_order')->get()->map(fn ($s) => [$s->colour, $s->size, $s->qty])->all());

        $records = $this->as()->getJson('/api/v1/merchandise/order-uploads')->assertOk()->json('data');
        $this->assertCount(3, $records);
        $this->assertSame(['UPL-00001', 'LPP Office', 'LPP', '637PK', 'PO-4401', $orders[0]->order_no, 'Navy', 'M', 1200], array_values(array_intersect_key($records[0], array_flip(['batch_no', 'buyer_ac', 'buyer', 'style', 'po_no', 'order_no', 'colour', 'size', 'qty']))));

        // The same file again: every order is already on the register.
        $again = $this->as()->post('/api/v1/merchandise/order-uploads/verify', ['file' => $good], ['Accept' => 'application/json'])->assertOk()->json('data');
        $this->assertSame(3, $again['rejected']);
        $this->assertSame('Order# PO-4401 of style 637PK is already on the register.', $again['rows'][0]['reasons'][0]);

        $storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($storeManager)->post('/api/v1/merchandise/order-uploads/verify', ['file' => $good], ['Accept' => 'application/json'])->assertForbidden();
    }

    /** The buyer LPP with its account, Poland, Navy and sizes M and L. */
    private function register(): void
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        OriginCountry::query()->create(['company_id' => $this->delta->id, 'code' => 'PL', 'name' => 'Poland', 'active' => true]);
        $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])->assertCreated();
        Colour::query()->create(['code' => 'NV-01', 'name' => 'Navy', 'colour_type' => 'SOLID', 'active' => true]);
        Size::query()->create(['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M', 'active' => true]);
        Size::query()->create(['size_group' => 'LETTER', 'code' => 'L', 'name' => 'L', 'active' => true]);
    }

    private function csv(string $content, string $name = 'lpp-ss27.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
