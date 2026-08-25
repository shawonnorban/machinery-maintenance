<?php

declare(strict_types=1);

namespace App\Modules\Asset\Database\Seeders;

use App\Modules\Asset\Models\AssetModel;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Asset\Models\Manufacturer;
use Illuminate\Database\Seeder;

/**
 * The machine models this industry actually runs (Seed Catalog).
 *
 * Nothing seeded asset_models anywhere in the codebase before this. The
 * platform taxonomy stopped at manufacturer, so every machine anybody
 * registered had an empty model field and "which model is this" had no answer
 * on screen — on a maintenance system, where the spare part that fits depends
 * on exactly that.
 *
 * Platform-seeded with a null company_id, like the rest of the taxonomy: a
 * Juki DDL-9000C is the same machine in every mill that owns one, and a
 * per-tenant copy would mean the same model spelled four ways across four
 * customers.
 *
 * A tenant may still add their own; those carry their company_id and are
 * untouched by this seeder.
 */
class AssetModelSeeder extends Seeder
{
    /**
     * Manufacturer code, asset type code, then the models.
     *
     * Grouped by who makes them rather than by category, because that is how
     * the list arrives from the trade: a supplier quotes their range, and the
     * range spans categories.
     *
     * @return array<string, array<string, list<string>>>
     */
    public static function catalogue(): array
    {
        return [
            'SEWING' => [
                'JUKI' => [
                    'DDL-9000C', 'DDL-8700', 'LH-3500A', 'MO-6800D', 'MO-6700S',
                    'MO-6714S', 'MF-7900', 'MF-7500', 'LBH-1790AN', 'MEB-3200',
                    'LK-1903BN', 'LK-1900BN', 'MS-1261M', 'MS-261', 'MH-1410',
                    'AP-876', 'APG-870',
                ],
                'BROTHER' => [
                    'S-7300A', 'S-7100A', 'T-8422C', 'T-8722C', 'HE-800B',
                    'BE-438FX', 'KE-430HX', 'DA-9270', 'DA-9280',
                ],
                'PEGASUS' => ['M900', 'MX Series', 'W1500N', 'W3500P', 'M700'],
                'YAMATO' => ['VG Series', 'VT Series', 'CZ Series', 'CM Series'],
                'JACK' => [
                    'A5E', 'A4F', 'A7', 'JK-58750', 'C5S', 'C4', 'E4S', 'W4',
                    'K4S', 'JK-T1790B', 'JK-T1377E', 'JK-T1900BS',
                ],
                'SIRUBA' => ['DL7200', '700QD', '700F', 'C007KP', 'F007K', 'VC008'],
                'HIKARI' => ['H8800'],
                'HIGHLEAD' => ['GC20618'],
                'DURKOPP' => ['581', '745-34B'],
                'SUNSTAR' => ['SPS/E-BR1200'],
                'KANSAI' => ['DFB-1412P', 'FX-4412P', 'DFB-1404P'],
                'KINGTEX' => ['CT Series'],
                'VIBEMAC' => ['2261HP', '2516V4'],
                'SIPAMI' => ['9090 Series'],
                'TREASURE' => ['BS-830', 'BS-850'],
                'UNION_SPECIAL' => ['37500 Series'],
                'MAIER_UNITAS' => ['Class 251'],
                'PFAFF' => ['8303', '8323'],
                'HH_SEALING' => ['AI-001', 'SF-812', 'US-501'],
                'QUEEN_LIGHT' => ['QHP Series'],
                'ARDMEL' => ['HD Seam Sealing'],
                'SONOTRONIC' => ['EcoSonic'],
            ],

            'CUTTING' => [
                'LECTRA' => ['VectorFashion iX6', 'VectorFashion Q80', 'Vector iQ', 'Allys Plotter'],
                'GERBER' => [
                    'Paragon HX', 'Paragon VX', 'XLc7000', 'GerberSpreader XLs',
                    'GERBERplotter MP',
                ],
                'MORGAN_TECNICA' => ['Next 2', 'Ply 1', 'Fox 100', 'Twist 100', 'Air Floating Table'],
                'BULLMER' => ['Premiumcut', 'Procut', 'Compact S'],
                'YIN' => ['HY-HC Series', 'SM-III', 'SM-IV'],
                'KURIS' => ['TexCut 3030', 'TexCut 4040'],
                'SHIMA_SEIKI' => ['P-CAM'],
                'EASTMAN' => [
                    'Blue Streak II 629', 'Brute 627', 'Cardinal 548', 'Cardinal 567',
                    'EC-700N', 'EC-900N', 'CR-200', 'EC-300', 'CD3 Cloth Drill',
                    'Power Track Spreader',
                ],
                'HASHIMA' => [
                    'KS-AUV', 'KAE-900A', 'KAE-700A', 'HPI-2200V', 'HPI-D',
                    'HPR-2000', 'HP-600S', 'HP-900L', 'HP-450MS',
                ],
                'KM_CUTTER' => ['KS-EU', 'KS-AU', 'KR-A', 'BK-900', 'BK-1200', 'DVD-201'],
                'OSHIMA' => ['K9-2100', 'J3-2100', 'OB-900A', 'OP-450G', 'OP-600L', 'ON-600R'],
                'DAYANG' => ['CZD-3'],
                'HOOGS' => ['X Series'],
                'SUPRENA' => ['CR-100'],
                'SIRUBA' => ['SR-500', 'SR-900'],
                'ATOM' => ['SE Series', 'VS Series'],
                'MAIMIN' => ['Inspection Table'],
                'RAMSONS' => ['Fabric Inspection'],
                'USTER' => ['Q-Bar'],
                'MEYER' => ['RPS-M', 'RPS-Standard'],
                'KANNEGIESSER' => ['CC Fusing'],
                'MARTIN_GROUP' => ['Open Top', 'X Series'],
                'RICHPEACE' => ['High-Speed Inkjet Plotter'],
                'SINAJET' => ['POP Series'],
                'KAWAKAMI' => ['Auto Spreader'],
                'SERKON' => ['Master Spreader'],
                'JACK' => ['JK-T3'],
                'AVERY_DENNISON' => ['Monarch Tag Attacher'],
                'SILVER_STAR' => ['Tagging Machine'],
            ],

            'EMBROIDERY' => [
                'TAJIMA' => ['TMEZ-SC', 'TMCR-VF', 'TFMX', 'TCMX', 'Sai Series', 'TMEZ-S1501C'],
                'BARUDAN' => ['KTX', 'KY', 'LEX Series', 'BEDY', 'BEKY', 'KG Series'],
                'ZSK' => ['Racer 1S', 'Racer 2W', 'Racer 4W', 'Challenger', 'Sprint'],
                'SWF' => ['K-Series', 'KX-Series', 'MAN Series'],
                'RICOMA' => ['CHT2', 'FHS', 'MT Series', 'TC-1501', 'SWD-1501'],
                'FEIYA' => ['CT Series', 'GG Series'],
                'PROMAKER' => ['HS High-Speed', 'Chenille Mixed'],
                'MAYA_EMB' => ['High-Speed Intelligent Flat'],
                'BROTHER' => ['PR1055X', 'Entrepreneur Pro X', 'PR680W'],
            ],

            'YARN_PREP' => [
                'TRUTZSCHLER' => [
                    'BO-A', 'MX-I', 'CL-P', 'CL-F', 'TC 19i', 'TC 30i',
                    'TCO 21', 'TD 10', 'TD 10-XX',
                ],
                'RIETER' => [
                    'UNIfloc A 12', 'UNImix B 72', 'UNIclean B 15', 'UNIflex B 60',
                    'C 81', 'OMEGAlap E 36', 'Comber E 90', 'RSB-D 50', 'Autoroving F 40',
                ],
                'LMW' => ['Line Balemat', 'Card LC636', 'LDF3'],
                'MARZOLI' => ['Galileo Line', 'LWN80', 'FT70'],
                'JINGWEI' => ['VC5A'],
                'TOYOTA' => ['FL200'],
                'SAURER' => ['Autoconer X6'],
                'MURATA' => ['Process Coner II QPRO Plus'],
                'SAVIO' => ['Propack', 'Eco PulsarS'],
                'XORELLA' => ['XO Smart', 'XO Select'],
                'WELKER' => ['Condibox'],
            ],

            'KNITTING' => [
                'MAYER_CIE' => ['Relanit 3.2 II', 'OVJA 2.4 EM'],
                'TERROT' => ['I3P 176'],
                'FUKUHARA' => ['V-LEC4BW'],
                'PAILUNG' => ['LFC-A'],
            ],

            'DYEING' => [
                'THIES' => ['iMaster H2O'],
                'FONGS' => ['ALLFIT ECO-6'],
                'SCLAVOS' => ['Venus Nova'],
                'DILMENLER' => ['DL-HTHP-500'],
            ],
        ];
    }

    public function run(): void
    {
        $types = AssetType::whereNull('company_id')->pluck('id', 'code');
        $makers = Manufacturer::whereNull('company_id')->pluck('id', 'code');
        $written = 0;

        foreach (self::catalogue() as $typeCode => $byManufacturer) {
            $typeId = $types[$typeCode] ?? null;

            if ($typeId === null) {
                // A type this catalogue names that the taxonomy does not. Said
                // out loud: silently seeding nothing would leave a whole
                // section modelless and look like the data was simply thin.
                $this->command?->warn("Asset models: unknown asset type {$typeCode}, skipped.");

                continue;
            }

            foreach ($byManufacturer as $manufacturerCode => $models) {
                $manufacturerId = $makers[$manufacturerCode] ?? null;

                if ($manufacturerId === null) {
                    $this->command?->warn(
                        "Asset models: unknown manufacturer {$manufacturerCode}, skipped.",
                    );

                    continue;
                }

                foreach ($models as $model) {
                    // The code has to be unique across the platform, and two
                    // makers genuinely ship a "K Series" — so the manufacturer
                    // is part of it rather than the model name alone.
                    AssetModel::updateOrCreate(
                        [
                            'company_id' => null,
                            'code' => $manufacturerCode.'::'.strtoupper(str_replace(' ', '-', $model)),
                        ],
                        [
                            'manufacturer_id' => $manufacturerId,
                            'asset_type_id' => $typeId,
                            'model' => $model,
                            'active' => true,
                        ],
                    );

                    $written++;
                }
            }
        }

        $this->command?->info("Asset models: {$written} platform models.");
    }
}
