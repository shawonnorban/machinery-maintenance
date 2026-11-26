<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Database\Seeders;

use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;

/**
 * Merchandise Desk → Master Data sample rows (AnnotechERP 06/01) for a knit
 * exporter: countries, buyer offices and buyers, colours, sizes, garment
 * parts, services, fibre blends, yarn counts, test labs, buyer trims and
 * nominated suppliers, loss allowances, process rates and T&A templates.
 * Fabric types, fibers, item types, work natures, cost heads, rate lines,
 * sample types and T&A tasks come from the module's own defaults.
 */
final class MerchandiseMasterSeed extends MasterSeed
{
    public function seed(ResourceSeeder $s): void
    {
        $this->places($s);
        $this->buyers($s);
        $this->product($s);
        $this->fabric($s);
        $this->trims($s);
        $this->rates($s);
        $this->tna($s);
    }

    private function places(ResourceSeeder $s): void
    {
        $s->seed('origin-countries', array_map(fn (array $c) => ['code' => $c[0], 'name' => $c[1], 'name_bn' => $c[2], 'region' => $c[3]], [
            ['050', 'Bangladesh', 'বাংলাদেশ', 'ASIAN'], ['156', 'China', 'চীন', 'ASIAN'], ['356', 'India', 'ভারত', 'ASIAN'], ['704', 'Vietnam', 'ভিয়েতনাম', 'ASIAN'],
            ['158', 'Taiwan', 'তাইওয়ান', 'ASIAN'], ['410', 'South Korea', 'দক্ষিণ কোরিয়া', 'ASIAN'], ['392', 'Japan', 'জাপান', 'ASIAN'], ['792', 'Turkey', 'তুরস্ক', 'EU'],
            ['276', 'Germany', 'জার্মানি', 'EU'], ['616', 'Poland', 'পোল্যান্ড', 'EU'], ['752', 'Sweden', 'সুইডেন', 'EU'], ['724', 'Spain', 'স্পেন', 'EU'],
            ['250', 'France', 'ফ্রান্স', 'EU'], ['380', 'Italy', 'ইতালি', 'EU'], ['528', 'Netherlands', 'নেদারল্যান্ডস', 'EU'], ['826', 'United Kingdom', 'যুক্তরাজ্য', 'EU'],
            ['840', 'United States', 'যুক্তরাষ্ট্র', 'AMERICAS'], ['124', 'Canada', 'কানাডা', 'AMERICAS'], ['036', 'Australia', 'অস্ট্রেলিয়া', 'OCEANIA'], ['784', 'United Arab Emirates', 'সংযুক্ত আরব আমিরাত', 'MIDDLE_EAST'],
        ]));

        $s->seed('country-configs', array_map(fn (array $c) => ['code' => $c[0], 'name' => $c[1], 'region' => $c[2], 'currency' => $c[3], 'dial_code' => $c[4], 'is_ship_to' => $c[5]], [
            ['BD', 'Bangladesh', 'ASIAN', 'BDT', '+880', false], ['PL', 'Poland', 'EU', 'PLN', '+48', true], ['DE', 'Germany', 'EU', 'EUR', '+49', true],
            ['SE', 'Sweden', 'EU', 'SEK', '+46', true], ['ES', 'Spain', 'EU', 'EUR', '+34', true], ['FR', 'France', 'EU', 'EUR', '+33', true],
            ['GB', 'United Kingdom', 'EU', 'GBP', '+44', true], ['NL', 'Netherlands', 'EU', 'EUR', '+31', true], ['IT', 'Italy', 'EU', 'EUR', '+39', true],
            ['US', 'United States', 'AMERICAS', 'USD', '+1', true], ['CA', 'Canada', 'AMERICAS', 'CAD', '+1', true], ['AU', 'Australia', 'OCEANIA', 'AUD', '+61', true],
            ['JP', 'Japan', 'ASIAN', 'JPY', '+81', true], ['HK', 'Hong Kong', 'ASIAN', 'HKD', '+852', false], ['AE', 'United Arab Emirates', 'MIDDLE_EAST', 'AED', '+971', true],
        ]));
    }

    private function buyers(ResourceSeeder $s): void
    {
        $country = fn (string $code) => fn (ResourceSeeder $s) => $s->id('country-configs', ['code' => $code]);
        $offices = [
            ['LPP S.A.', 'HEAD_OFFICE', 'ul. Łąkowa 39/44, 80-769 Gdańsk, Poland', 'PL', 'Marta Kowalska', 'sourcing@lpp.example', '+48 58 769 6900'],
            ['LPP Bangladesh Liaison Office', 'LIAISON', 'House 12, Road 113, Gulshan 2, Dhaka 1212', 'BD', 'Shahriar Kabir', 'dhaka@lpp.example', '+880 2 5505 1180'],
            ['H&M Production Office Dhaka', 'LIAISON', 'Plot 19, Road 2, Block A, Banani, Dhaka 1213', 'BD', 'Anna Lindqvist', 'dhaka.production@hm.example', '+880 2 5505 2200'],
            ['H & M Hennes & Mauritz AB', 'HEAD_OFFICE', 'Mäster Samuelsgatan 46A, 106 38 Stockholm, Sweden', 'SE', 'Erik Johansson', 'buying@hm.example', '+46 8 796 5500'],
            ['Inditex Dhaka Sourcing Office', 'LIAISON', 'Level 9, Navana Tower, Gulshan 1, Dhaka 1212', 'BD', 'Lucía Martín', 'dhaka@inditex.example', '+880 2 5505 3300'],
            ['Primark Stores Ltd', 'HEAD_OFFICE', 'Weston Centre, 10 Grosvenor Street, London W1K 4QY, United Kingdom', 'GB', 'James Walker', 'sourcing@primark.example', '+44 20 7399 6500'],
            ['C&A Sourcing Bangladesh', 'LIAISON', 'House 58, Road 7, Block H, Banani, Dhaka 1213', 'BD', 'Thomas Becker', 'bd.sourcing@canda.example', '+880 2 5505 4400'],
            ['Kiabi Europe SAS', 'HEAD_OFFICE', '100 Rue du Calvaire, 59510 Hem, France', 'FR', 'Claire Dubois', 'achats@kiabi.example', '+33 3 2066 4000'],
            ['Takko Fashion GmbH', 'HEAD_OFFICE', 'Alfred-Krupp-Straße 21, 48291 Telgte, Germany', 'DE', 'Jonas Fischer', 'einkauf@takko.example', '+49 2504 9230'],
            ['Li & Fung Bangladesh Ltd', 'BUYING_HOUSE', 'Concord I-K Tower, Gulshan 2, Dhaka 1212', 'BD', 'Kevin Chan', 'dhaka@lifung.example', '+880 2 5505 5500'],
            ['Texpro Sourcing Agent', 'AGENT', 'House 31, Road 16, Sector 4, Uttara, Dhaka 1230', 'BD', 'Mahmudul Hasan', 'info@texpro.example', '+880 1711 223344'],
        ];
        $s->seed('buyer-offices', array_map(fn (array $o) => fn (ResourceSeeder $s) => [
            'name' => $o[0], 'office_type' => $o[1], 'address' => $o[2], 'country_id' => $country($o[3])($s), 'contact_person' => $o[4], 'email' => $o[5], 'phone' => $o[6],
        ], $offices), ['name']);

        $office = fn (ResourceSeeder $s, string $name) => $s->id('buyer-offices', ['name' => $name]);
        $origin = fn (ResourceSeeder $s, string $code) => $s->id('origin-countries', ['code' => $code]);
        $buyers = [
            ['LPP', 'LPP Spolka Akcyjna', 'LPP', 'ul. Łąkowa 39/44, 80-769 Gdańsk, Poland', '616', ['LPP S.A.', 'LPP Bangladesh Liaison Office'], 'Marta Kowalska', 10],
            ['HM', 'H & M Hennes & Mauritz AB', 'H&M', 'Mäster Samuelsgatan 46A, 106 38 Stockholm, Sweden', '752', ['H & M Hennes & Mauritz AB', 'H&M Production Office Dhaka'], 'Anna Lindqvist', 20],
            ['ITX', 'Industria de Diseño Textil S.A.', 'INDITEX', 'Avenida de la Diputación, 15143 Arteixo, A Coruña, Spain', '724', ['Inditex Dhaka Sourcing Office'], 'Lucía Martín', 30],
            ['PRM', 'Primark Stores Ltd', 'PRIMARK', 'Weston Centre, 10 Grosvenor Street, London W1K 4QY, United Kingdom', '826', ['Primark Stores Ltd', 'Li & Fung Bangladesh Ltd'], 'James Walker', 40],
            ['CNA', 'C&A Mode GmbH & Co. KG', 'C&A', 'Wanheimer Straße 70, 40468 Düsseldorf, Germany', '276', ['C&A Sourcing Bangladesh'], 'Thomas Becker', 50],
            ['KIA', 'Kiabi Europe SAS', 'KIABI', '100 Rue du Calvaire, 59510 Hem, France', '250', ['Kiabi Europe SAS', 'Texpro Sourcing Agent'], 'Claire Dubois', 60],
            ['TKO', 'Takko Fashion GmbH', 'TAKKO', 'Alfred-Krupp-Straße 21, 48291 Telgte, Germany', '276', ['Takko Fashion GmbH'], 'Jonas Fischer', 70],
        ];
        $s->seed('buyers', array_map(fn (array $b) => fn (ResourceSeeder $s) => [
            'code' => $b[0], 'name' => $b[1], 'short_name' => $b[2], 'pi_address' => $b[3], 'country_id' => $origin($s, $b[4]),
            'office_ids' => array_map(fn (string $n) => $office($s, $n), $b[5]), 'contact_person' => $b[6], 'display_order' => $b[7], 'registered_from' => '2024-01-01',
        ], $buyers));

        $s->seed('test-institutes', [
            ['name' => 'SGS Bangladesh Ltd', 'address' => 'Plot 61, Block B, Kemal Ataturk Avenue, Banani, Dhaka 1213'],
            ['name' => 'Intertek Testing Services Bangladesh Ltd', 'address' => 'Phoenix Tower, 407 Tejgaon I/A, Dhaka 1208'],
            ['name' => 'Bureau Veritas CPS (Bangladesh) Ltd', 'address' => 'Plot 1, Road 4, Sector 9, Uttara, Dhaka 1230'],
            ['name' => 'TÜV SÜD Bangladesh (Pvt) Ltd', 'address' => 'House 20, Road 17, Banani, Dhaka 1213'],
            ['name' => 'Hohenstein Institute Bangladesh', 'address' => 'Gulshan Avenue, Gulshan 1, Dhaka 1212'],
        ], ['name']);
    }

    private function product(ResourceSeeder $s): void
    {
        $group = fn (ResourceSeeder $s, string $name) => $s->id('dye-colour-groups', ['name' => $name]);
        $colours = [
            ['SOLID', 'White', '11-0601 TPX', 'White'], ['SOLID', 'Black', '19-4008 TPX', 'Black/Royal Blue'], ['SOLID', 'Navy Blue', '19-4024 TPX', 'Dark'],
            ['SOLID', 'Royal Blue', '19-3952 TPX', 'Black/Royal Blue'], ['SOLID', 'Red', '18-1663 TPX', 'Medium'], ['SOLID', 'Sky Blue', '14-4318 TPX', 'Light'],
            ['SOLID', 'Bottle Green', '19-5420 TPX', 'Dark'], ['SOLID', 'Maroon', '19-1725 TPX', 'Dark'], ['SOLID', 'Olive', '18-0430 TPX', 'Medium'],
            ['SOLID', 'Lemon Yellow', '13-0858 TPX', 'Light'], ['SOLID', 'Orange', '16-1364 TPX', 'Medium'], ['SOLID', 'Baby Pink', '13-2010 TPX', 'Light'],
            ['SOLID', 'Beige', '13-1008 TPX', 'Light'], ['SOLID', 'Burgundy', '19-1617 TPX', 'Extra Dark'], ['SOLID', 'Turquoise', '16-5123 TPX', 'Medium'],
            ['MELANGE', 'Grey Melange', null, 'Mellange/YD'], ['MELANGE', 'Charcoal Melange', null, 'Mellange/YD'], ['MELANGE', 'Ecru Melange', null, 'Mellange/YD'],
            ['YARN_DYED', 'Navy/White Stripe', null, 'Mellange/YD'], ['AOP', 'Floral AOP', null, 'Medium'],
        ];
        $s->seed('colours', array_map(fn (array $c) => fn (ResourceSeeder $s) => ['colour_type' => $c[0], 'name' => $c[1], 'pantone_no' => $c[2], 'colour_group_id' => $group($s, $c[3])], $colours), ['name']);

        $sizes = [];
        foreach (['XS' => 5, 'S' => 10, 'M' => 20, 'L' => 30, 'XL' => 40, 'XXL' => 50, '3XL' => 60] as $name => $order) {
            $sizes[] = ['size_group' => 'LETTER', 'code' => $name, 'name' => $name, 'display_order' => $order];
        }
        foreach ([28, 30, 32, 34, 36, 38, 40] as $i => $waist) {
            $sizes[] = ['size_group' => 'NUMERIC', 'code' => (string) $waist, 'name' => (string) $waist, 'display_order' => 100 + $i];
        }
        foreach (['2-3Y', '4-5Y', '6-7Y', '8-9Y', '10-11Y', '12-13Y'] as $i => $age) {
            $sizes[] = ['size_group' => 'AGE', 'code' => $age, 'name' => $age, 'display_order' => 200 + $i];
        }
        $s->seed('sizes', $sizes, ['name']);

        $s->seed('garment-parts', array_map(fn (array $p) => ['code' => $p[0], 'name' => $p[1], 'fabric_group' => $p[2], 'is_body' => $p[3], 'is_circular' => $p[4]], [
            ['BDY', 'Body', 'BODY', true, true], ['SLV', 'Sleeve', 'BODY', false, true], ['CLR', 'Collar', 'COLLAR', false, false], ['CUF', 'Cuff', 'RIB', false, true],
            ['NRB', 'Neck Rib', 'RIB', false, true], ['HOD', 'Hood', 'BODY', false, false], ['PKT', 'Pocket', 'BODY', false, false], ['WBD', 'Waistband', 'RIB', false, true],
            ['PLK', 'Placket', 'BODY', false, false], ['LIN', 'Hood Lining', 'LINING', false, false],
        ]));

        $services = [
            ['AOP', 'AOP-REA', 'Reactive AOP'], ['AOP', 'AOP-PIG', 'Pigment AOP'], ['AOP', 'AOP-DIS', 'Discharge AOP'],
            ['YARN_DYEING', 'YD-COT', 'Cotton Yarn Dyeing'], ['YARN_DYEING', 'YD-SPC', 'Space Dyeing'],
            ['PRINT', 'PRT-PIG', 'Pigment Print'], ['PRINT', 'PRT-RUB', 'Rubber Print'], ['PRINT', 'PRT-PLS', 'Plastisol Print'], ['PRINT', 'PRT-FOIL', 'Foil Print'], ['PRINT', 'PRT-HD', 'High Density Print'],
            ['EMBROIDERY', 'EMB-FLAT', 'Flat Embroidery'], ['EMBROIDERY', 'EMB-APP', 'Applique'], ['EMBROIDERY', 'EMB-3D', '3D Puff Embroidery'], ['EMBROIDERY', 'EMB-SEQ', 'Sequin Embroidery'],
            ['GMT_WASH', 'WSH-ENZ', 'Enzyme Wash'], ['GMT_WASH', 'WSH-SIL', 'Silicone Wash'], ['GMT_WASH', 'WSH-GDY', 'Garment Dye'], ['GMT_WASH', 'WSH-ACD', 'Acid Wash'],
        ];
        $s->seed('service-types', array_map(fn (array $v, int $i) => ['service_for' => $v[0], 'code' => $v[1], 'name' => $v[2], 'sort_order' => ($i + 1) * 10], $services, array_keys($services)));

        $s->ensure('sample-types');
    }

    private function fabric(ResourceSeeder $s): void
    {
        $fiber = fn (ResourceSeeder $s, string $code) => $s->id('fibers', ['code' => $code]);
        $blends = [
            'CTN100' => ['COT' => 100], 'CVC6040' => ['COT' => 60, 'POLY' => 40], 'PC6535' => ['POLY' => 65, 'COT' => 35], 'CE955' => ['COT' => 95, 'ELA' => 5],
            'CP8020' => ['COT' => 80, 'POLY' => 20], 'PE928' => ['POLY' => 92, 'ELA' => 8], 'VE955' => ['VIS' => 95, 'ELA' => 5], 'CM5050' => ['COT' => 50, 'MOD' => 50],
            'PL100' => ['POLY' => 100],
        ];
        $s->seed('fiber-compositions', array_map(fn (string $code) => fn (ResourceSeeder $s) => [
            'code' => $code, 'items' => array_map(fn (string $f, int $pct) => ['fiber_id' => $fiber($s, $f), 'percent' => $pct], array_keys($blends[$code]), $blends[$code]),
        ], array_keys($blends)));

        $type = fn (ResourceSeeder $s, string $code) => $s->id('fabric-types', ['code' => $code]);
        $counts = [
            ['SJ', 'COTTON_100', '24', 120, 140, '30/1', null, null], ['SJ', 'COTTON_100', '24', 141, 160, '26/1', null, null], ['SJ', 'COTTON_100', '24', 161, 180, '24/1', null, null],
            ['LSJ', 'LYCRA_BLEND', '24', 170, 210, '30/1 + 20D', null, null], ['PIQ', 'COTTON_100', '24', 180, 220, '24/1', null, null],
            ['RIB1X1', 'LYCRA_BLEND', '18', 200, 240, '30/1 + 40D', null, null], ['INTLK', 'COTTON_100', '24', 200, 240, '40/1', null, null],
            ['FLEECE', 'CVC_BLEND', '20', 260, 320, '30/1', '75D', '16/1'], ['TERRY', 'COTTON_100', '20', 220, 260, '30/1', null, '20/1'],
        ];
        $s->seed('yarn-count-configs', array_map(fn (array $c) => fn (ResourceSeeder $s) => [
            'fabric_type_id' => $type($s, $c[0]), 'template' => $c[1], 'gauge' => $c[2], 'gsm_min' => $c[3], 'gsm_max' => $c[4], 'count_top' => $c[5], 'count_binding' => $c[6], 'count_back' => $c[7],
        ], $counts), ['fabric_type_id', 'gauge', 'gsm_min']);

        $blend = fn (ResourceSeeder $s, string $code) => $s->id('fiber-compositions', ['code' => $code]);
        $bands = [];
        foreach (['CTN100' => 0, 'CVC6040' => 1, 'CE955' => 2] as $code => $extra) {
            foreach (['WHITE' => 7, 'LIGHT' => 8, 'MEDIUM' => 9, 'DARK' => 10, 'EXTRA_DARK' => 12, 'MELANGE' => 6] as $groupName => $loss) {
                $bands[] = fn (ResourceSeeder $s) => ['fiber_composition_id' => $blend($s, $code), 'color_group' => $groupName, 'weight_low' => 0, 'weight_high' => 200, 'loss_percent' => $loss + $extra];
                $bands[] = fn (ResourceSeeder $s) => ['fiber_composition_id' => $blend($s, $code), 'color_group' => $groupName, 'weight_low' => 200, 'weight_high' => 400, 'loss_percent' => $loss + $extra + 1];
            }
        }
        $s->seed('dyeing-loss-bands', $bands, ['fiber_composition_id', 'color_group', 'weight_low']);

        $s->seed('booking-loss-allowances', array_map(fn (array $b) => ['qty_low' => $b[0], 'qty_high' => $b[1], 'loss_qty' => $b[2], 'add_to_booking' => true, 'effective_from' => '2026-01-01'], [
            [0, 1000, 10], [1000, 5000, 8], [5000, 10000, 6], [10000, 50000, 5], [50000, 999999, 4],
        ]), ['qty_low', 'qty_high']);
        $s->seed('cutting-loss-allowances', array_map(fn (array $b) => ['qty_low' => $b[0], 'qty_high' => $b[1], 'loss_qty' => $b[2], 'add_to_booking' => true, 'effective_from' => '2026-01-01'], [
            [0, 1000, 5], [1000, 5000, 4], [5000, 20000, 3], [20000, 999999, 2.5],
        ]), ['qty_low', 'qty_high']);
    }

    private function trims(ResourceSeeder $s): void
    {
        $buyer = fn (ResourceSeeder $s, string $code) => $s->id('buyers', ['code' => $code]);
        $items = [
            ['LPP', 'LPP-ML-01', 'Main Label Woven', 'Label', 'Damask, end fold', 'Polyester', 'Black', '50 x 20 mm', 'PCS'],
            ['LPP', 'LPP-CL-01', 'Care Label', 'Label', 'Printed satin, 7 languages', 'Polyester satin', 'White', '30 x 70 mm', 'PCS'],
            ['LPP', 'LPP-HT-01', 'Hang Tag with Price Sticker', 'Tag', '350 GSM art card, matt lamination', 'Paper', 'White', '50 x 90 mm', 'PCS'],
            ['LPP', 'LPP-PB-01', 'Poly Bag with Warning Print', 'Packing', 'LDPE 40 micron, self adhesive', 'LDPE', 'Clear', '30 x 40 cm', 'PCS'],
            ['HM', 'HM-ML-01', 'Main Label Printed', 'Label', 'Heat transfer, tagless', 'Ink', 'Black', '40 x 25 mm', 'PCS'],
            ['HM', 'HM-SZ-01', 'Size Sticker', 'Label', 'Size strip on poly bag', 'Paper', 'White', '20 x 60 mm', 'PCS'],
            ['HM', 'HM-HT-01', 'Hang Tag RFID', 'Tag', 'RFID inlay, 300 GSM', 'Paper', 'White', '45 x 85 mm', 'PCS'],
            ['HM', 'HM-CT-01', 'Export Carton', 'Packing', '5 ply, double wall, 32 ECT', 'Kraft', 'Brown', '60 x 40 x 30 cm', 'PCS'],
            ['PRM', 'PRM-TK-01', 'Price Ticket', 'Tag', 'Barcode ticket with retail price', 'Paper', 'White', '35 x 70 mm', 'PCS'],
            ['PRM', 'PRM-HG-01', 'Hanger', 'Packing', 'Top hanger with size cube', 'Polypropylene', 'Black', '12 inch', 'PCS'],
        ];
        $s->seed('buyer-trim-items', array_map(fn (array $t) => fn (ResourceSeeder $s) => [
            'buyer_id' => $buyer($s, $t[0]), 'item_code' => $t[1], 'item_name' => $t[2], 'category' => $t[3], 'specification' => $t[4], 'material' => $t[5], 'color' => $t[6], 'size' => $t[7], 'uom' => $t[8], 'approved_by' => 'Buyer merchandiser',
        ], $items), ['buyer_id', 'item_code']);

        $supplier = fn (ResourceSeeder $s, string $code) => $s->id('material-suppliers', ['code' => $code]);
        $s->seed('buyer-supplier-configs', array_map(fn (array $c) => fn (ResourceSeeder $s) => [
            'buyer_id' => $buyer($s, $c[0]), 'nominated_supplier_id' => $supplier($s, $c[1]), 'alternative_supplier_id' => $c[2] ? $supplier($s, $c[2]) : null, 'remarks' => $c[3],
        ], [
            ['LPP', 'SUP-10001', 'SUP-10008', 'Labels and tags from the nominated vendor only'],
            ['HM', 'SUP-10001', null, 'RFID hang tags through Avery Dennison'],
            ['PRM', 'SUP-10008', 'SUP-10001', 'Price tickets per Primark ticketing portal'],
            ['ITX', 'SUP-10002', null, 'Zippers YKK only'],
        ]), ['buyer_id']);

        $s->seed('trim-spec-templates', [
            ['code' => 'LBL', 'name' => 'Labels', 'controls' => [
                ['name' => 'Label size', 'control_type' => 'TEXT', 'required' => true],
                ['name' => 'Fold type', 'control_type' => 'SELECT', 'options' => 'Center fold,End fold,Straight cut', 'required' => true],
                ['name' => 'Construction', 'control_type' => 'SELECT', 'options' => 'Woven,Printed,Heat transfer'],
            ]],
            ['code' => 'BTN', 'name' => 'Buttons', 'controls' => [
                ['name' => 'Ligne', 'control_type' => 'NUMBER', 'required' => true],
                ['name' => 'Holes', 'control_type' => 'SELECT', 'options' => '2,4'],
                ['name' => 'Finish', 'control_type' => 'TEXT'],
            ]],
            ['code' => 'ZIP', 'name' => 'Zippers', 'controls' => [
                ['name' => 'Length (inch)', 'control_type' => 'NUMBER', 'required' => true],
                ['name' => 'Teeth', 'control_type' => 'SELECT', 'options' => 'Metal,Plastic,Nylon coil', 'required' => true],
                ['name' => 'Auto lock slider', 'control_type' => 'YESNO'],
            ]],
            ['code' => 'PKG', 'name' => 'Packing', 'controls' => [
                ['name' => 'Ply', 'control_type' => 'NUMBER'],
                ['name' => 'Dimension', 'control_type' => 'TEXT', 'required' => true],
            ]],
        ]);
    }

    private function rates(ResourceSeeder $s): void
    {
        $group = fn (ResourceSeeder $s, string $name) => $s->id('dye-colour-groups', ['name' => $name]);
        $rates = [
            ['KNITTING', 'COTTON_100', null, 'Single Jersey knitting 24/1–30/1', 18, 17, 1],
            ['KNITTING', 'LYCRA_BLEND', null, 'Lycra Single Jersey knitting', 26, 25, 1.5],
            ['KNITTING', 'COTTON_100', null, '1x1 Rib knitting', 22, 21, 1],
            ['KNITTING', 'CVC_BLEND', null, 'Fleece knitting 3-thread', 28, 27, 1.5],
            ['DYEING', null, 'White', 'Reactive dyeing — white', 85, 82, 7],
            ['DYEING', null, 'Light', 'Reactive dyeing — light shade', 110, 106, 8],
            ['DYEING', null, 'Medium', 'Reactive dyeing — medium shade', 130, 126, 9],
            ['DYEING', null, 'Dark', 'Reactive dyeing — dark shade', 150, 145, 10],
            ['DYEING', null, 'Extra Dark', 'Reactive dyeing — extra dark shade', 170, 165, 12],
            ['DYEING_FINISHING', null, null, 'Open compacting', 12, 11, 1],
            ['DYEING_FINISHING', null, null, 'Peach finish', 25, 24, 2],
            ['YARN_DYEING', 'COTTON_100', null, 'Cotton yarn dyeing — medium', 160, 155, 5],
            ['AOP', null, null, 'Reactive all-over print', 180, 175, 4],
        ];
        $s->seed('merch-fab-process-rates', array_map(fn (array $r) => fn (ResourceSeeder $s) => [
            'category' => $r[0], 'fiber_template' => $r[1], 'colour_group_id' => $r[2] ? $group($s, $r[2]) : null, 'description' => $r[3], 'rate' => $r[4], 'prod_rate' => $r[5], 'process_loss' => $r[6], 'currency' => 'BDT', 'per' => 'KG',
        ], $rates), ['description']);
    }

    private function tna(ResourceSeeder $s): void
    {
        $s->ensure('tna-tasks');
        $approver = $this->userId('Rahim Uddin');
        $templates = [
            ['T-BAS-NEW', 'NEW', 'BASIC', 45, 60, 55, 3, 2, 'Basic knit tops, new style'],
            ['T-BAS-RPT', 'REPEAT', 'BASIC', 30, 45, 40, 2, 2, 'Basic knit tops, repeat order'],
            ['T-SC-NEW', 'NEW', 'SEMI_CRITICAL', 60, 75, 70, 4, 3, 'Polo and fleece, new style'],
            ['T-CR-NEW', 'NEW', 'CRITICAL', 75, 100, 90, 5, 3, 'Printed / embroidered critical styles'],
            ['T-DEV', 'DEVELOPMENT', 'BASIC', 20, 35, 30, 2, 1, 'Development and proto samples'],
        ];
        $s->seed('tna-templates', array_map(fn (array $t) => [
            'code' => $t[0], 'scope' => 'GENERAL', 'order_type' => $t[1], 'gmt_type' => $t[2], 'lead_from' => $t[3], 'lead_to' => $t[4], 'std_days' => $t[5],
            'before_swing' => $t[6], 'after_swing' => $t[7], 'description' => $t[8], 'approved_by' => $approver,
        ], $templates));
    }
}
