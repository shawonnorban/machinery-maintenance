<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Models\LookupTable;
use App\Modules\Identity\Models\LookupValue;
use Illuminate\Database\Seeder;

/**
 * The shared (system) lookup tables of Lookup Table Master (AnnotechERP
 * 02/01), from Brickland's own list. Repeatable: an existing table or value
 * keeps its row; only missing ones are added. Units of measure are not here —
 * they are master data of their own (Settings → UOM).
 */
class LookupSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string, 2: array<string, array{0: string, 1: string}>}> */
    private const TABLES = [
        'SHIFT' => ['Shift', 'Working shifts of the floor.', [
            'A' => ['Shift A', 'শিফট এ'], 'B' => ['Shift B', 'শিফট বি'], 'C' => ['Shift C', 'শিফট সি'], 'GENERAL' => ['General', 'জেনারেল'],
        ]],
        'PAYMENT_TERM' => ['Payment Term', 'How a buyer or a supplier is paid.', [
            'ADVANCE' => ['Advance', 'অগ্রিম'], 'LC_AT_SIGHT' => ['LC at sight', 'এলসি অ্যাট সাইট'], 'LC_DEFERRED' => ['Deferred LC', 'ডেফার্ড এলসি'],
            'TT' => ['Telegraphic transfer (TT)', 'টিটি'], 'CAD' => ['Cash against documents', 'ক্যাশ অ্যাগেইনস্ট ডকুমেন্টস'],
        ]],
        'SHIPMENT_MODE' => ['Shipment Mode', 'How goods travel.', [
            'SEA' => ['Sea', 'সমুদ্র'], 'AIR' => ['Air', 'আকাশ'], 'ROAD' => ['Road', 'সড়ক'], 'SEA_AIR' => ['Sea-Air', 'সমুদ্র-আকাশ'],
        ]],
        // What User Requisition Mapping maps (AnnotechERP 02/02).
        'REQUISITION_TYPE' => ['Requisition Type', 'The kinds of requisition a user may raise or approve.', [
            'PURCHASE' => ['Purchase Requisition', 'ক্রয় রিকুইজিশন'], 'STORE' => ['Store Requisition', 'স্টোর রিকুইজিশন'],
            'FLOOR' => ['Floor Requisition', 'ফ্লোর রিকুইজিশন'], 'YARN' => ['Yarn Requisition', 'সুতা রিকুইজিশন'],
            'FABRIC' => ['Fabric Requisition', 'কাপড় রিকুইজিশন'], 'ACCESSORIES' => ['Accessories Requisition', 'এক্সেসরিজ রিকুইজিশন'],
            'GENERAL' => ['General Requisition', 'সাধারণ রিকুইজিশন'],
        ]],
        // What User Production Category Mapping maps.
        'PRODUCTION_CATEGORY' => ['Production Category', 'The production floors a user may plan for or report on.', [
            'KNITTING' => ['Knitting', 'নিটিং'], 'DYEING' => ['Dyeing', 'ডাইং'], 'AOP' => ['All Over Print', 'অল ওভার প্রিন্ট'],
            'CUTTING' => ['Cutting', 'কাটিং'], 'PRINTING' => ['Printing', 'প্রিন্টিং'], 'EMBROIDERY' => ['Embroidery', 'এমব্রয়ডারি'],
            'SEWING' => ['Sewing', 'সেলাই'], 'WASHING' => ['Washing', 'ওয়াশিং'], 'FINISHING' => ['Finishing', 'ফিনিশিং'],
        ]],
        // Brickland's business rules (User Rule Mapping, Rule Violation Log).
        'BUSINESS_RULE' => ['Business Rule', 'Checks the system raises a violation for; a mapped user may override one.', [
            'BR-001' => ['Purchase order above requisition value', 'রিকুইজিশনের মূল্যের বেশি পারচেজ অর্ডার'],
            'BR-002' => ['Issue beyond available stock', 'মজুদের বেশি ইস্যু'],
            'BR-003' => ['Order confirmed without costing', 'কস্টিং ছাড়া অর্ডার কনফার্ম'],
            'BR-004' => ['Shade approved without lab dip', 'ল্যাব ডিপ ছাড়া শেড অনুমোদন'],
            'BR-005' => ['Overtime above statutory cap', 'আইনি সীমার বেশি ওভারটাইম'],
            'BR-006' => ['Voucher posted without narration', 'বিবরণ ছাড়া ভাউচার পোস্ট'],
            'BR-007' => ['Inspection points above buyer limit', 'বায়ারের সীমার বেশি ইন্সপেকশন পয়েন্ট'],
            'BR-008' => ['Shipment booked after L/C expiry', 'এলসির মেয়াদ শেষে শিপমেন্ট বুকিং'],
        ]],
        'FABRIC_TYPE' => ['Fabric Type', 'The broad family of a fabric.', [
            'KNIT' => ['Knit', 'নিট'], 'WOVEN' => ['Woven', 'ওভেন'], 'DENIM' => ['Denim', 'ডেনিম'], 'SWEATER' => ['Sweater', 'সোয়েটার'],
            'NON_WOVEN' => ['Non-woven', 'নন-ওভেন'], 'LACE' => ['Lace', 'লেস'],
        ]],
        // Brickland's defect types (Fabric Defect Mapping, the four-point inspection sheet).
        'FABRIC_DEFECT' => ['Fabric Defect Type', 'The defects a fabric inspection counts, each buyer with its own penalty points.', [
            'FD-01' => ['Air Spray Spot', 'এয়ার স্প্রে স্পট'], 'FD-02' => ['Back Sewing', 'ব্যাক সেলাই'], 'FD-03' => ['Barre', 'বারে'],
            'FD-04' => ['Chemical Spot', 'কেমিক্যাল স্পট'], 'FD-05' => ['Cita Tara', 'সিটা তারা'], 'FD-06' => ['Color Spot', 'কালার স্পট'],
            'FD-07' => ['Crease Mark', 'ভাঁজের দাগ'], 'FD-08' => ['Cutting Defect', 'কাটিং ত্রুটি'], 'FD-09' => ['Dia Mark', 'ডায়া মার্ক'],
            'FD-10' => ['Dye Pin Hole', 'ডাই পিন হোল'], 'FD-11' => ['Flying Dust / Contra', 'উড়ন্ত ধুলা / কনট্রা'], 'FD-12' => ['Hole / Crack', 'ফুটো / ফাটল'],
            'FD-13' => ['Import Fabric Fault', 'আমদানি ফ্যাব্রিকের ত্রুটি'], 'FD-14' => ['Line Star', 'লাইন স্টার'], 'FD-15' => ['Loop', 'লুপ'],
            'FD-16' => ['Lycra Drop', 'লাইক্রা ড্রপ'], 'FD-17' => ['Lycra Out', 'লাইক্রা আউট'], 'FD-18' => ['Missed Print', 'বাদ পড়া প্রিন্ট'],
            'FD-19' => ['Missing Yarn', 'সুতা বাদ'], 'FD-20' => ['Needle Breakage', 'সুই ভাঙা'], 'FD-21' => ['Needle Drop', 'নিডল ড্রপ'],
            'FD-22' => ['Needle Line', 'নিডল লাইন'], 'FD-23' => ['Needle Mark', 'নিডল মার্ক'], 'FD-24' => ['Oil Mark / Line', 'তেলের দাগ / লাইন'],
            'FD-25' => ['Oil Spot', 'তেলের স্পট'], 'FD-26' => ['Oil Stain', 'তেলের ছোপ'], 'FD-27' => ['Over Print', 'ওভার প্রিন্ট'],
            'FD-28' => ['Patta', 'পাট্টা'], 'FD-29' => ['Print Defect', 'প্রিন্ট ত্রুটি'], 'FD-30' => ['Print Repeat Mark', 'প্রিন্ট রিপিট মার্ক'],
            'FD-31' => ['Set Off', 'সেট অফ'], 'FD-32' => ['Shade Variation', 'শেডের তারতম্য'], 'FD-33' => ['Sinker Mark', 'সিঙ্কার মার্ক'],
            'FD-34' => ['Slub', 'স্লাব'], 'FD-35' => ['Softener Spot', 'সফটনার স্পট'], 'FD-36' => ['Stars', 'স্টার'],
            'FD-37' => ['Thick and Thin', 'মোটা-পাতলা'], 'FD-38' => ['Wheel Free', 'হুইল ফ্রি'], 'FD-39' => ['Yarn Contra', 'সুতার কনট্রা'],
        ]],
        // Brickland's fabric booking categories (grey fabric transfer rules, Buyer-Wise Fabric Transaction Rights).
        'FABRIC_BOOKING_CATEGORY' => ['Fabric Booking Category', 'The booking a fabric was knitted against; grey fabric moves between them only where a buyer has a rule.', [
            'FP01' => ['Sample Booking', 'স্যাম্পল বুকিং'], 'FP02' => ['Bulk Booking', 'বাল্ক বুকিং'], 'FP03' => ['Development Booking', 'ডেভেলপমেন্ট বুকিং'],
            'FP04' => ['Sub-Contract Booking', 'সাব-কন্ট্রাক্ট বুকিং'], 'FP05' => ['Special Order Booking', 'স্পেশাল অর্ডার বুকিং'], 'FP06' => ['Short-Bulk', 'শর্ট-বাল্ক'],
            'FP07' => ['Yarn Test', 'সুতা টেস্ট'], 'FP08' => ['Projection Booking', 'প্রজেকশন বুকিং'], 'FP09' => ['Additional Booking', 'অতিরিক্ত বুকিং'],
            'FP10' => ['General Fabric Booking', 'সাধারণ ফ্যাব্রিক বুকিং'], 'FP11' => ['Buffer Booking', 'বাফার বুকিং'], 'FP12' => ['Short-Sample', 'শর্ট-স্যাম্পল'],
        ]],
        // Norban's Base Module lists the Dev Sample Status, Order Summary and Knit
        // Woven screens pick from (AnnotechERP 31–34). Brands are the company's own.
        'BRAND' => ['Brand', 'The buyer brands a style or sample is made for.', []],
        'SEASON' => ['Season', 'The buyer season a style or sample belongs to.', [
            'SS26' => ['SS-26', 'এসএস-২৬'], 'AW26' => ['AW-26', 'এডব্লিউ-২৬'], 'SS27' => ['SS-27', 'এসএস-২৭'], 'AW27' => ['AW-27', 'এডব্লিউ-২৭'],
        ]],
        'GARMENT_DEPARTMENT' => ['Garment Department', 'Who a garment is made for (Mens, Women, Boys …).', [
            'MENS' => ['Mens', 'পুরুষ'], 'WOMEN' => ['Women', 'মহিলা'], 'LADIES' => ['Ladies', 'লেডিস'], 'BOYS' => ['Boys', 'ছেলে'],
            'GIRLS' => ['Girls', 'বালিকা'], 'KIDS' => ['Kids', 'শিশু'], 'BABY' => ['Baby', 'বেবি'], 'TODDLER_BOYS' => ['Toddler Boys', 'টডলার ছেলে'],
            'TODDLER_GIRLS' => ['Toddler Girls', 'টডলার বালিকা'], 'UNISEX' => ['Unisex', 'ইউনিসেক্স'],
        ]],
    ];

    /**
     * Order Summary's dropdown lists (AnnotechERP 32), Norban's Base Module
     * masters with the values its sheet uses. Plain names: the code is made
     * from the name and the Bangla name is left to the company.
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private const ORDER_SUMMARY = [
        'OS_CLASS' => ['Order Summary – Class', []],
        'OS_ORDER_TYPE' => ['Order Summary – Order Type', ['Confirmed PO', 'BLOCK', 'BLOCK NOS', 'Buying File', 'Repeat', 'Projection', 'Cancelled', 'Cancelled + Liability']],
        'OS_SHIPMENT_MODE' => ['Order Summary – Shipment Mode', ['FOB SEA', 'FOB AIR', 'CNF SEA', 'CIF SEA', 'DDP SEA', 'DDP AIR', 'SEA-AIR']],
        'OS_SHIPOUT_CONDITION' => ['Order Summary – Shipout Condition', ['On Time', 'Early', 'Delay', 'Partial Shipment', 'Short Shipment']],
        'OS_SHIPMENT_STATUS' => ['Order Summary – Shipment Status', ['Shipment Pending', 'Partial Ship Out', 'Ship Out', 'Cancelled']],
        'OS_PAYMENT_STATUS' => ['Order Summary – Payment Rcvd Status', ['Pending', 'Partially Received', 'Received']],
        'OS_EMBELLISHMENT' => ['Order Summary – Embellishment', ['N / A', 'PRINT', 'EMBROIDERY', 'PRINT + EMBROIDERY', 'APPLIQUE', 'HEAT TRANSFER']],
        'OS_ZIPPER' => ['Order Summary – Zipper', ['N / A', 'ZIPPER - Booking Pending', 'ZIPPER - Booking Done', 'ZIPPER - INHOUSE']],
        'OS_BUTTON' => ['Order Summary – Button', ['N / A', 'BUTTON - Booking Pending', 'BUTTON - Booking Done', 'BUTTON - INHOUSE']],
        'OS_WOVEN_TRIM' => ['Order Summary – Woven Trim', ['N / A', 'WOVEN TRIM - Pending Approval', 'WOVEN TRIM - Approved', 'WOVEN TRIM - INHOUSE']],
        'OS_IMPORT_ITEM' => ['Order Summary – Import Item', ['N / A', 'Imported Fabric', 'Imported Trims']],
        'OS_FABRIC_CATEGORY' => ['Order Summary – Fabric Category', ['Knit', 'Woven', 'Denim', 'Sweater']],
        'OS_GSM' => ['Order Summary – GSM', ['120', '140', '160', '180', '200', '220', '240', '260', '280', '300', '320', '350', '380', '400']],
        'OS_DYE_TYPE' => ['Order Summary – Dye Type', ['SOLID - Single Dye', 'SOLID - Double Dye', 'Y/D - Auto Stripe', 'Y/D - Feeder Stripe', 'Multiple Type']],
        'OS_FINISH_TYPE' => ['Order Summary – Fabric Finish Type', ['Regular', 'Brushed Inside', 'Peach & Brushed', 'Peach Finish', 'Enzyme Wash']],
        'OS_AOP' => ['Order Summary – AOP', ['N / A', 'AOP', 'Discharge AOP', 'Pigment AOP']],
        'OS_WASH_INFO' => ['Order Summary – Wash Info At Garmenting', ['N / A', 'Garment Wash', 'Enzyme Wash', 'Pigment Dye', 'Acid Wash']],
        'OS_SUSTAINABILITY' => ['Order Summary – Sustainability', ['Conventional', 'BCI', 'BCI + Recycle', 'BIO GOTS', 'Bio + Recycle', 'OCS', 'GRS']],
        'OS_PRICE_STATUS' => ['Order Summary – Price Status', ['CONFIRMED', 'NOT CONFIRMED', 'NEGOTIATING', 'OFFER PENDING']],
        'OS_MERCHANDISER' => ['Order Summary – Merchandiser', []],
        'OS_SHORT_ORDER_STATUS' => ['Order Summary – Short Order Status', ['Processing', 'Done', 'Not Applicable']],
        'OS_YARN_STATUS' => ['Order Summary – Yarn Status', ['Booking Pending', 'Booking Done', 'Shipment Pending', 'Ship Out', 'Inhouse', 'Yarn Booking Pending', 'Yarn Inhouse Pending', 'Yarn Allocation Done']],
        'OS_FABRIC_STATUS' => ['Order Summary – Fabric Status', ['Fabric Booking PENDING', 'Fabric Booking DONE', 'Fabric Delivery Running', 'Fabric Delivery Done']],
        'OS_TRIM_STATUS' => ['Order Summary – Trims Status', ['PENDING Trims Booking', 'Trims Booking DONE', 'PARTIAL Trims Inhouse', 'ALL Trims Inhouse', 'Trims Detail Pending from Buyer', 'Other Alert!!']],
        'OS_BASE_HANGER_STATUS' => ['Order Summary – Base Hanger Status', self::SAMPLE_STATUSES],
        'OS_PROTO_STATUS' => ['Order Summary – 1st/Proto Status', self::SAMPLE_STATUSES],
        'OS_SOFF_STATUS' => ['Order Summary – Print/Embr. S/Off Status', self::SAMPLE_STATUSES],
        'OS_FIT_STATUS' => ['Order Summary – Black SS/Fit Status', self::SAMPLE_STATUSES],
        'OS_TEST_SAMPLE_STATUS' => ['Order Summary – Test Sample Status', self::SAMPLE_STATUSES],
        'OS_GSS_PP_STATUS' => ['Order Summary – GSS/PP Status', self::SAMPLE_STATUSES],
        'OS_FFP_STATUS' => ['Order Summary – FFP Status', self::SAMPLE_STATUSES],
        'OS_BULK_HANGER_STATUS' => ['Order Summary – Bulk Hanger Status', self::SAMPLE_STATUSES],
        'OS_PROD_FILE_STATUS' => ['Order Summary – Prod. File Status', ['Prod. File Pending', 'Prod. File Given']],
        'OS_GARMENT_PROD_STATUS' => ['Order Summary – Garment Production Status', ['Not Started', 'Cutting Running', 'Sewing Running', 'Sewing Complete', 'Finishing Running', 'Finishing Complete', 'Ship Out', 'Full Completed', 'Sewing ShortFALL', 'ShortFALL', 'Other Alert!!']],
    ];

    private const SAMPLE_STATUSES = ['NOT Submitted', 'SUBMITTED', 'COMMENTS PENDING', 'APPROVED', 'REJECTED', 'RESUBMITTED', 'N / A'];

    public function run(): void
    {
        $tables = self::TABLES;
        foreach (self::ORDER_SUMMARY as $code => [$name, $names]) {
            $values = [];
            foreach ($names as $n) {
                $values[substr(trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($n)), '_'), 0, 48) ?: 'NA'] = [$n, null];
            }
            $tables[$code] = [$name, 'Order Summary dropdown list (Norban Base Module).', $values];
        }
        foreach ($tables as $code => [$name, $description, $values]) {
            $table = LookupTable::query()->firstOrCreate(
                ['company_id' => null, 'code' => $code],
                ['name' => $name, 'description' => $description, 'is_system' => true, 'active' => true],
            );

            $order = 0;
            foreach ($values as $valueCode => [$label, $labelBn]) {
                $order += 10;
                LookupValue::query()->firstOrCreate(
                    ['lookup_table_id' => $table->id, 'code' => $valueCode],
                    ['company_id' => null, 'name' => $label, 'name_bn' => $labelBn, 'sort_order' => $order, 'active' => true],
                );
            }
        }
    }
}
