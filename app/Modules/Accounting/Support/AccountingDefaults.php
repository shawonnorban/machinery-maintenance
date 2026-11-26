<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Support;

use App\Modules\Accounting\Models\AccAccount;
use App\Modules\Accounting\Models\AccFiscalYear;
use App\Modules\Accounting\Models\AccPaymentMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Finance & Ledger lists a company starts with, as Brickland ships them
 * (AnnotechERP 23/01): nine payment modes, the 128-node chart of accounts,
 * its three account type classifications and two fiscal years. Made the
 * first time a list is opened, and only into an empty list; insertOrIgnore
 * because a page opens several requests at once.
 */
final class AccountingDefaults
{
    /** [name, short, voucher types, remarks] */
    public const PAYMENT_MODES = [
        ['Account Transfer', 'AT', ['BPV', 'BRV', 'CV'], 'Account Transfer'], ['BBLC', 'BBLC', ['BV'], 'BBLC NO'], ['BILL', 'BILL', ['BV'], 'BILL'],
        ['Cash', 'Cash', [], 'Cash'], ['Cheque', 'CHQ', ['BPV', 'BRV', 'CV'], 'Cheque'], ['DD/Payorder', 'DD/Pay', ['BPV', 'BRV', 'CV'], 'DD'],
        ['LC', 'LC', [], 'LC NO'], ['N/A', 'N/A', [], 'N/A'], ['Others', 'Others', ['BPV', 'BRV', 'CV', 'JV'], 'Others'],
    ];

    /** [code, name, parent code, type, postable] in Brickland's order */
    public const ACCOUNTS = [
        ['1', 'ASSETS', null, 'ASSET', false],
        ['101', 'NON CURRENT ASSETS', '1', 'ASSET', false],
        ['10101', 'Property, Plant and Equipment', '101', 'ASSET', false],
        ['1010101', 'Land & Land Development', '10101', 'ASSET', true],
        ['1010102', 'Building & Construction', '10101', 'ASSET', true],
        ['1010103', 'Factory Equipment', '10101', 'ASSET', true],
        ['1010104', 'Electric Installation', '10101', 'ASSET', true],
        ['1010105', 'Furniture & Fixture', '10101', 'ASSET', true],
        ['10102', 'Capital Work In Progress', '101', 'ASSET', false],
        ['1010201', 'CWIP - Building', '10102', 'ASSET', true],
        ['102', 'CURRENT ASSETS', '1', 'ASSET', false],
        ['10201', 'Inventories', '102', 'ASSET', false],
        ['1020101', 'Raw Materials - Yarn', '10201', 'ASSET', true],
        ['1020102', 'Raw Materials - Fabric', '10201', 'ASSET', true],
        ['1020103', 'Accessories Stock', '10201', 'ASSET', true],
        ['1020104', 'Work In Process', '10201', 'ASSET', true],
        ['1020105', 'Finished Goods', '10201', 'ASSET', true],
        ['10202', 'Accounts Receivable', '102', 'ASSET', false],
        ['1020201', 'Accounts Receivable - Export', '10202', 'ASSET', true],
        ['1020202', 'Accounts Receivable - Local', '10202', 'ASSET', true],
        ['10203', 'Advance, Deposit & Prepayments', '102', 'ASSET', false],
        ['1020301', 'Advance Against Salary', '10203', 'ASSET', true],
        ['1020302', 'Advance Income Tax', '10203', 'ASSET', true],
        ['1020303', 'Security Deposit - Utility', '10203', 'ASSET', true],
        ['10204', 'Cash & Cash Equivalents', '102', 'ASSET', false],
        ['1020401', 'Cash In Hand', '10204', 'ASSET', true],
        ['1020402', 'Cash At Bank - Current A/C', '10204', 'ASSET', true],
        ['1020403', 'Cash At Bank - FC A/C', '10204', 'ASSET', true],
        ['1100', 'Inventory — Yarn', null, 'ASSET', true],
        ['1110', 'Inventory — Dyes & Chemicals', null, 'ASSET', true],
        ['1120', 'Inventory — Trims & Accessories', null, 'ASSET', true],
        ['1130', 'Inventory — Finished Fabric', null, 'ASSET', true],
        ['1200', 'Accounts Receivable', null, 'ASSET', true],
        ['1300', 'Bank — Operating Account', null, 'ASSET', true],
        ['2', 'LIABILITIES', null, 'LIABILITY', false],
        ['201', 'NON CURRENT LIABILITIES', '2', 'LIABILITY', false],
        ['20101', 'Long Term Loan', '201', 'LIABILITY', false],
        ['2010101', 'Term Loan', '20101', 'LIABILITY', true],
        ['20102', 'Loan From Director', '201', 'LIABILITY', false],
        ['2010201', 'Director Loan', '20102', 'LIABILITY', true],
        ['20103', 'Accumulated Depreciation', '201', 'LIABILITY', false],
        ['2010301', 'Acc. Dep. on Building & Construction', '20103', 'LIABILITY', true],
        ['2010302', 'Acc. Dep. on Factory Equipment', '20103', 'LIABILITY', true],
        ['2010303', 'Acc. Dep. on Furniture & Fixture', '20103', 'LIABILITY', true],
        ['202', 'CURRENT LIABILITIES', '2', 'LIABILITY', false],
        ['20201', 'Short Term Loan', '202', 'LIABILITY', false],
        ['2020101', 'PC Loan - Packing Credit', '20201', 'LIABILITY', true],
        ['2020102', 'Loan against Bills Receivable', '20201', 'LIABILITY', true],
        ['2020103', 'Advance Received from Buyer', '20201', 'LIABILITY', true],
        ['20202', 'Accounts Payable - LC', '202', 'LIABILITY', false],
        ['2020201', 'Accounts Payable - Accessories L/C', '20202', 'LIABILITY', true],
        ['2020202', 'Accounts Payable - Yarn L/C', '20202', 'LIABILITY', true],
        ['2020203', 'Accounts Payable - Fabric L/C', '20202', 'LIABILITY', true],
        ['20203', 'Liabilities for Expenses', '202', 'LIABILITY', false],
        ['2020301', 'Salary & Wages Payable', '20203', 'LIABILITY', true],
        ['2020302', 'Utility Bill Payable', '20203', 'LIABILITY', true],
        ['2020303', 'Audit Fee Payable', '20203', 'LIABILITY', true],
        ['2100', 'Accounts Payable', null, 'LIABILITY', true],
        ['2200', 'Accrued Wages', null, 'LIABILITY', true],
        ['3', 'EQUITY', null, 'EQUITY', false],
        ['301', 'CAPITAL ACCOUNT', '3', 'EQUITY', false],
        ['30101', 'Owners Equity', '301', 'EQUITY', false],
        ['3010101', 'Paid Up Capital', '30101', 'EQUITY', true],
        ['3010102', 'Retained Earnings', '30101', 'EQUITY', true],
        ['3010103', 'Profit / Loss', '30101', 'EQUITY', true],
        ['3100', 'Share Capital', null, 'EQUITY', true],
        ['4', 'INCOME', null, 'REVENUE', false],
        ['401', 'Operating Revenue', '4', 'REVENUE', false],
        ['40101', 'Export Revenue', '401', 'REVENUE', false],
        ['4010101', 'Export Sales', '40101', 'REVENUE', true],
        ['4010102', 'Sales Discount', '40101', 'REVENUE', true],
        ['4010103', 'Export Local', '40101', 'REVENUE', true],
        ['40102', 'Subcontract Revenue', '401', 'REVENUE', false],
        ['4010201', 'Revenue From Subcontract - Sewing', '40102', 'REVENUE', true],
        ['4010202', 'Revenue From Subcontract - Printing', '40102', 'REVENUE', true],
        ['4010203', 'Revenue From Subcontract - Dyeing', '40102', 'REVENUE', true],
        ['402', 'Non Operating Revenue', '4', 'REVENUE', false],
        ['40201', 'Interest Income', '402', 'REVENUE', false],
        ['4020101', 'Interest Revenue on FDR', '40201', 'REVENUE', true],
        ['4020102', 'Interest Income on Saving Deposit', '40201', 'REVENUE', true],
        ['40202', 'Others Income', '402', 'REVENUE', false],
        ['4020201', 'Commission Received', '40202', 'REVENUE', true],
        ['4020202', 'Insurance Claim', '40202', 'REVENUE', true],
        ['4020203', 'Wastage Sales', '40202', 'REVENUE', true],
        ['4100', 'Export Sales', null, 'REVENUE', true],
        ['5', 'EXPENDITURE', null, 'EXPENSE', false],
        ['501', 'Financial Expenses', '5', 'EXPENSE', false],
        ['50101', 'Bank Charges & Interest', '501', 'EXPENSE', false],
        ['5010101', 'Interest on Term Loan', '50101', 'EXPENSE', true],
        ['5010102', 'Interest on PC', '50101', 'EXPENSE', true],
        ['5010103', 'Bank Charge - TT', '50101', 'EXPENSE', true],
        ['5010104', 'Foreign Currency Loss / Gain', '50101', 'EXPENSE', true],
        ['502', 'Direct Expenses', '5', 'EXPENSE', false],
        ['50201', 'Production Overhead', '502', 'EXPENSE', false],
        ['5020101', 'Overtime - PO', '50201', 'EXPENSE', true],
        ['5020102', 'Depreciation - PO', '50201', 'EXPENSE', true],
        ['5020103', 'Electricity Expenses - Factory', '50201', 'EXPENSE', true],
        ['5020104', 'Factory Maintenance', '50201', 'EXPENSE', true],
        ['5020105', 'Medical Expenses - PO', '50201', 'EXPENSE', true],
        ['50202', 'Processing Expenses', '502', 'EXPENSE', false],
        ['5020201', 'Dyeing Finishing Expenses', '50202', 'EXPENSE', true],
        ['5020202', 'Embroidery Expenses', '50202', 'EXPENSE', true],
        ['5020203', 'Printing Expenses', '50202', 'EXPENSE', true],
        ['5020204', 'Washing Charge', '50202', 'EXPENSE', true],
        ['50203', 'Direct Labour', '502', 'EXPENSE', false],
        ['5020301', 'Wages & Allowances - DL', '50203', 'EXPENSE', true],
        ['5020302', 'Overtime - DL', '50203', 'EXPENSE', true],
        ['5020303', 'Festival Bonus - DL', '50203', 'EXPENSE', true],
        ['503', 'Operating Overhead', '5', 'EXPENSE', false],
        ['50301', 'Administrative Overhead', '503', 'EXPENSE', false],
        ['5030101', 'Electricity Expenses', '50301', 'EXPENSE', true],
        ['5030102', 'Earn Leave Encashment - Admin', '50301', 'EXPENSE', true],
        ['5030103', 'Tax Expenses', '50301', 'EXPENSE', true],
        ['5030104', 'Miscellaneous Expenses', '50301', 'EXPENSE', true],
        ['50302', 'Commercial Overhead', '503', 'EXPENSE', false],
        ['5030201', 'Documentation Expenses', '50302', 'EXPENSE', true],
        ['5030202', 'B/L Expenses', '50302', 'EXPENSE', true],
        ['5030203', 'GSP Expenses', '50302', 'EXPENSE', true],
        ['50303', 'Marketing, Selling & Distribution Overhead', '503', 'EXPENSE', false],
        ['5030301', 'Carriage Outward', '50303', 'EXPENSE', true],
        ['5030302', 'Freight Expenses - Sea (Export)', '50303', 'EXPENSE', true],
        ['5030303', 'Sample Expenses', '50303', 'EXPENSE', true],
        ['5030304', 'Agency Commission', '50303', 'EXPENSE', true],
        ['5100', 'Cost of Goods Sold', null, 'EXPENSE', true],
        ['5200', 'Wages & Salary', null, 'EXPENSE', true],
        ['5300', 'Gas & Electricity', null, 'EXPENSE', true],
        ['5400', 'Freight & C&F', null, 'EXPENSE', true],
        ['5500', 'Bank Charges', null, 'EXPENSE', true],
    ];

    /** [account type, layer, account code] */
    public const CLASSES = [['CASH', 'LEDGER', '1020401'], ['BANK', 'LEDGER', '1020402'], ['BANK', 'LEDGER', '1020403']];

    /** [name, fy start, fy end, calendar start, calendar end] */
    public const FISCAL_YEARS = [['FY2026-27', '2026-07-01', '2027-06-30', '2026-01-01', '2026-12-31'], ['FY2027-28', '2027-07-01', '2028-06-30', '2027-01-01', '2027-12-31']];

    public static function paymentModes(string $companyId): void
    {
        if (AccPaymentMode::query()->exists()) {
            return;
        }
        $now = now();
        DB::table('acc_payment_modes')->insertOrIgnore(array_map(fn (array $m) => [
            'id' => (string) Str::ulid(), 'company_id' => $companyId, 'name' => $m[0], 'short_name' => $m[1], 'voucher_types' => json_encode($m[2]), 'remarks' => $m[3], 'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], self::PAYMENT_MODES));
    }

    public static function accounts(string $companyId): void
    {
        if (AccAccount::query()->exists()) {
            return;
        }
        $now = now();
        $ids = [];
        $rows = [];
        foreach (self::ACCOUNTS as $i => [$code, $name, , $type, $postable]) {
            $ids[$code] = (string) Str::ulid();
            $rows[] = ['id' => $ids[$code], 'company_id' => $companyId, 'code' => $code, 'name' => $name, 'parent_id' => null, 'type' => $type, 'postable' => $postable, 'display_order' => $i + 1, 'active' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('acc_accounts')->insertOrIgnore($rows);
        $stored = DB::table('acc_accounts')->where('company_id', $companyId)->pluck('id', 'code');
        foreach (self::ACCOUNTS as [$code, , $parent]) {
            if ($parent) {
                DB::table('acc_accounts')->where('company_id', $companyId)->where('code', $code)->whereNull('parent_id')->update(['parent_id' => $stored[$parent] ?? null]);
            }
        }
        DB::table('acc_account_classes')->insertOrIgnore(array_values(array_filter(array_map(fn (array $c) => isset($stored[$c[2]]) ? [
            'id' => (string) Str::ulid(), 'company_id' => $companyId, 'account_type' => $c[0], 'layer' => $c[1], 'account_id' => $stored[$c[2]], 'created_at' => $now, 'updated_at' => $now,
        ] : null, self::CLASSES))));
    }

    public static function fiscalYears(string $companyId): void
    {
        if (AccFiscalYear::query()->exists()) {
            return;
        }
        $now = now();
        DB::table('acc_fiscal_years')->insertOrIgnore(array_map(fn (array $y) => [
            'id' => (string) Str::ulid(), 'company_id' => $companyId, 'name' => $y[0], 'fy_start' => $y[1], 'fy_end' => $y[2], 'cal_start' => $y[3], 'cal_end' => $y[4], 'closed' => false, 'created_at' => $now, 'updated_at' => $now,
        ], self::FISCAL_YEARS));
    }
}
