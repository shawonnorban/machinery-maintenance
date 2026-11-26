<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Database\Seeders;

use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;

/** Material Procurement → Master Data sample rows (AnnotechERP 08/01): suppliers, fabric items and trim items, in the UOM master's codes. */
final class MaterialProcurementMasterSeed extends MasterSeed
{
    public function seed(ResourceSeeder $s): void
    {
        $s->seed('material-suppliers', [
            ['code' => 'SUP-10001', 'name' => 'Avery Dennison (Bangladesh) Ltd', 'short_name' => 'AVERY', 'supplier_type' => 'LOCAL_EPZ', 'goods_service' => 'Labels, tags and RFID', 'vat_reg_no' => '000123456-0101', 'tin_no' => '284512369741', 'address' => 'Plot 21-22, Dhaka EPZ, Savar, Dhaka'],
            ['code' => 'SUP-10002', 'name' => 'YKK Bangladesh Pte Ltd', 'short_name' => 'YKK', 'supplier_type' => 'LOCAL_EPZ', 'goods_service' => 'Zippers and snaps', 'vat_reg_no' => '000123457-0101', 'tin_no' => '284512369742', 'address' => 'Plot 17, Dhaka EPZ, Savar, Dhaka'],
            ['code' => 'SUP-10003', 'name' => 'Coats Bangladesh Ltd', 'short_name' => 'COATS', 'supplier_type' => 'LOCAL_NON_EPZ', 'goods_service' => 'Sewing thread', 'vat_reg_no' => '000123458-0101', 'tin_no' => '284512369743', 'address' => 'Kashimpur, Gazipur'],
            ['code' => 'SUP-10004', 'name' => 'Square Textiles Ltd', 'short_name' => 'SQUARE', 'supplier_type' => 'LOCAL_NON_EPZ', 'goods_service' => 'Cotton yarn', 'vat_reg_no' => '000123459-0101', 'tin_no' => '284512369744', 'address' => 'Saradaganj, Kashimpur, Gazipur'],
            ['code' => 'SUP-10005', 'name' => 'Nitol Packaging Industries Ltd', 'short_name' => 'NITOL', 'supplier_type' => 'LOCAL_NON_EPZ', 'goods_service' => 'Export cartons', 'vat_reg_no' => '000123460-0101', 'tin_no' => '284512369745', 'address' => 'Tongi I/A, Gazipur'],
            ['code' => 'SUP-10007', 'name' => 'Shaoxing Huatai Textile Co Ltd', 'short_name' => 'HUATAI', 'supplier_type' => 'FOREIGN', 'goods_service' => 'Woven fabric', 'address' => 'Keqiao District, Shaoxing, Zhejiang, China'],
            ['code' => 'SUP-10008', 'name' => 'SML Bangladesh Ltd', 'short_name' => 'SML', 'supplier_type' => 'LOCAL_EPZ', 'goods_service' => 'Woven labels and hang tags', 'vat_reg_no' => '000123461-0101', 'tin_no' => '284512369746', 'address' => 'Adamjee EPZ, Narayanganj'],
            ['code' => 'SUP-10009', 'name' => 'Akij Poly Packaging Ltd', 'short_name' => 'AKIJ', 'supplier_type' => 'LOCAL_NON_EPZ', 'goods_service' => 'Poly bags', 'vat_reg_no' => '000123462-0101', 'tin_no' => '284512369747', 'address' => 'Dhamrai, Dhaka'],
        ]);

        $s->seed('material-fabrics', [
            ['item_code' => 'FB-SJ-160', 'name' => 'Single Jersey 160 GSM', 'category' => 'Knit', 'sub_category' => 'Single Jersey', 'gsm' => '160', 'width' => '72" open', 'composition' => '100% Cotton', 'uom' => 'KG'],
            ['item_code' => 'FB-SJL-190', 'name' => 'Lycra Single Jersey 190 GSM', 'category' => 'Knit', 'sub_category' => 'Single Jersey', 'gsm' => '190', 'width' => '68" open', 'composition' => '95% Cotton 5% Elastane', 'uom' => 'KG'],
            ['item_code' => 'FB-PIQ-220', 'name' => 'Pique 220 GSM', 'category' => 'Knit', 'sub_category' => 'Pique', 'gsm' => '220', 'width' => '70" open', 'composition' => '100% Cotton', 'uom' => 'KG'],
            ['item_code' => 'FB-RIB-220', 'name' => '1x1 Rib 220 GSM', 'category' => 'Knit', 'sub_category' => 'Rib', 'gsm' => '220', 'width' => '36" tube', 'composition' => '95% Cotton 5% Elastane', 'uom' => 'KG'],
            ['item_code' => 'FB-FLC-300', 'name' => '3-Thread Fleece 300 GSM', 'category' => 'Knit', 'sub_category' => 'Fleece', 'gsm' => '300', 'width' => '74" open', 'composition' => '80% Cotton 20% Polyester', 'uom' => 'KG'],
            ['item_code' => 'FB-TER-260', 'name' => 'French Terry 260 GSM', 'category' => 'Knit', 'sub_category' => 'Terry', 'gsm' => '260', 'width' => '72" open', 'composition' => '100% Cotton', 'uom' => 'KG'],
            ['item_code' => 'FB-TWL-280', 'name' => 'Cotton Twill 280 GSM', 'category' => 'Woven', 'sub_category' => 'Twill', 'gsm' => '280', 'width' => '58"', 'composition' => '98% Cotton 2% Elastane', 'uom' => 'YDS'],
            ['item_code' => 'FB-DNM-12', 'name' => 'Denim 12 oz', 'category' => 'Woven', 'sub_category' => 'Denim', 'gsm' => '407', 'width' => '60"', 'composition' => '100% Cotton', 'colour' => 'Indigo', 'uom' => 'YDS'],
        ], ['item_code']);

        $s->seed('material-trims', [
            ['item_code' => 'TR-LBL-CARE', 'name' => 'Care Label Printed Satin', 'category' => 'Label', 'size' => '30 x 60 mm', 'uom' => 'PCS'],
            ['item_code' => 'TR-LBL-SIZE', 'name' => 'Size Label Woven', 'category' => 'Label', 'size' => '15 x 15 mm', 'uom' => 'PCS'],
            ['item_code' => 'TR-TAG-HANG', 'name' => 'Hang Tag 350 GSM Art Card', 'category' => 'Tag', 'size' => '50 x 90 mm', 'uom' => 'PCS'],
            ['item_code' => 'TR-ZIP-NY5', 'name' => 'Nylon Coil Zipper #5', 'category' => 'Zipper', 'size' => '7 inch', 'uom' => 'PCS'],
            ['item_code' => 'TR-DRW-FLAT', 'name' => 'Flat Drawcord 10 mm', 'category' => 'Cord', 'size' => '130 cm', 'uom' => 'PCS'],
            ['item_code' => 'TR-ELS-25', 'name' => 'Knitted Elastic 25 mm', 'category' => 'Elastic', 'size' => '25 mm', 'uom' => 'MTR'],
            ['item_code' => 'TR-INT-FUS', 'name' => 'Fusible Interlining 40 GSM', 'category' => 'Interlining', 'uom' => 'MTR'],
            ['item_code' => 'TR-CTN-5PLY', 'name' => 'Export Carton 5 Ply', 'category' => 'Packing', 'size' => '60 x 40 x 30 cm', 'uom' => 'PCS'],
            ['item_code' => 'TR-TPE-GUM', 'name' => 'Gum Tape 2 inch', 'category' => 'Packing', 'uom' => 'ROLL'],
            ['item_code' => 'TR-SNP-12', 'name' => 'Snap Button 12L Antique Brass', 'category' => 'Button', 'size' => '12L', 'uom' => 'SET'],
        ], ['item_code']);
    }
}
