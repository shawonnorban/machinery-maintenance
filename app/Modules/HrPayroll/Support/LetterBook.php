<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Support;

use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * HR letters (AnnotechERP 24/09):
 *
 * - **Types:** the sixteen kinds Brickland starts with, an Appointment Letter
 *   template ready in Bangla (the rest are written on Letter Master).
 * - **An employee's letter details:** number, card, names, address,
 *   posting, grade, type, joining date, company gross, Gross B, actual
 *   salary and the gazette wage of their grade (Basic, House Rent %,
 *   Medical, Conveyance, Food, Other) — from the template's gazette, else
 *   their own. The letter is merged with these and they are kept on it.
 * - **Reference numbers:** the template's format with {YYYY} (the letter's
 *   year) and {SEQ} (the next number of that type that year, three digits).
 */
final class LetterBook
{
    /** [code, name] in Brickland's order. */
    public const TYPES = [
        ['APPOINTMENT', 'Appointment Letter'], ['REJOIN', 'Rejoin Letter'], ['PROMOTION', 'Promotion Letter'], ['TRANSFER', 'Transfer Letter'],
        ['INCREMENT', 'Increment Letter'], ['LEAVE', 'Leave Letter'], ['WARNING', 'Warning Letter'], ['SHOW_CAUSE', 'Show Cause Letter'],
        ['EXPERIENCE', 'Experience Letter'], ['RELEASE', 'Release Letter'], ['TERMINATION', 'Termination Letter'], ['CONFIRMATION', 'Confirmation Letter'],
        ['SALARY_REVISION', 'Salary Revision Letter'], ['DEPARTMENT_CHANGE', 'Department Change Letter'], ['DESIGNATION_CHANGE', 'Designation Change Letter'], ['CUSTOM', 'Custom Letter'],
    ];

    public function __construct(private readonly string $companyId) {}

    /** The default types and the Appointment Letter's first template, once per company. */
    public function ensure(): void
    {
        if (DB::table('hr_letter_types')->where('company_id', $this->companyId)->exists()) {
            return;
        }
        $now = now();
        foreach (self::TYPES as $i => [$code, $name]) {
            DB::table('hr_letter_types')->insert(['id' => self::ulid(), 'company_id' => $this->companyId, 'code' => $code, 'name' => $name, 'active' => true, 'sort_order' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now]);
        }
        $type = DB::table('hr_letter_types')->where('company_id', $this->companyId)->where('code', 'APPOINTMENT')->value('id');
        $code = DB::table('companies')->where('id', $this->companyId)->value('code') ?: 'HR';
        $gazette = DB::table('hr_gazettes')->where('company_id', $this->companyId)->where('name', 'RMG Minimum Wage 2023 (Non-EPZ)')->value('id');
        DB::table('hr_letter_templates')->insert([
            'id' => self::ulid(), 'company_id' => $this->companyId, 'letter_type_id' => $type, 'version' => 1,
            'title' => 'নিয়োগপত্র', 'subject' => 'নিয়োগপত্র', 'reference_format' => "{$code}/HR/APP/{YYYY}/{SEQ}",
            'salutation' => 'জনাব/জনাবা **{{EmployeeName}}**,',
            'opening_message' => 'আপনার আবেদন, যোগ্যতা ও অভিজ্ঞতায় সন্তুষ্ট হয়ে কর্তৃপক্ষ আপনাকে **{{CompanyName}}**-এর **{{Department}}** বিভাগে **{{Designation}}** পদে নিম্নলিখিত শর্তাবলীতে নিয়োগ প্রদান করছে:',
            'clauses' => json_encode([
                'আপনার যোগদানের তারিখ **{{JoiningDate}}**। যোগদানের তারিখ থেকে **{{ProbationPeriod}}** আপনি শিক্ষানবিশ হিসেবে কর্মরত থাকবেন; শিক্ষানবিশকাল সন্তোষজনকভাবে সম্পন্ন হলে আপনার চাকরি স্থায়ী করা হবে।',
                'আপনার মাসিক মোট বেতন **{{CompanyGrossSalary}}/- টাকা ({{CompanyGrossSalaryInWords}})**, যা **{{GazetteName}}** অনুযায়ী কোম্পানির বেতন কাঠামো মোতাবেক প্রদান করা হবে। কোম্পানির নিয়ম অনুযায়ী আপনি বছরে ২ (দুই)টি উৎসব ভাতা পাবেন।',
                'আপনার আইডি নং **{{EmployeeNumber}}** এবং কার্ড নং **{{CardNumber}}**।',
                'দৈনিক স্বাভাবিক কর্মঘণ্টা ৮ (আট) ঘণ্টা। প্রযোজ্য ক্ষেত্রে কর্তৃপক্ষের প্রয়োজনে অতিরিক্ত সময় (ওভারটাইম) কাজ করতে হবে এবং বাংলাদেশ শ্রম আইন অনুযায়ী ওভারটাইম ভাতা প্রদান করা হবে।',
                'বাংলাদেশ শ্রম আইন ২০০৬ (সংশোধিত) ও কোম্পানির চাকরি বিধিমালা অনুযায়ী আপনি ছুটি ও অন্যান্য সুযোগ-সুবিধা ভোগ করবেন।',
                'আপনার কর্মস্থল **{{CompanyName}}**-এর কারখানা প্রাঙ্গণ। কর্তৃপক্ষ প্রয়োজনে আপনাকে কোম্পানির যেকোনো বিভাগ বা শাখায় বদলি করতে পারবে।',
                'বাংলাদেশ শ্রম আইন ২০০৬ এর ধারা ২৬ ও ২৭ অনুযায়ী নোটিশ প্রদান সাপেক্ষে যেকোনো পক্ষ এই চাকরির অবসান ঘটাতে পারবে।',
                'প্রযোজ্য ক্ষেত্রে আপনার বেতনের উপর আরোপিত আয়কর আপনাকে বহন করতে হবে।',
                'আপনাকে কোম্পানির সকল নিয়ম-কানুন, শৃঙ্খলা ও কর্তৃপক্ষের নির্দেশ মেনে চলতে হবে।',
                'উপরোক্ত শর্তাবলী মেনে নিয়ে **{{JoiningDate}}** তারিখে আপনার যোগদান এই নিয়োগপত্র গ্রহণ হিসেবে গণ্য হবে।',
            ], JSON_UNESCAPED_UNICODE),
            'closing_message' => "আমরা আপনার কর্মজীবনের সাফল্য কামনা করি।\nধন্যবাদান্তে,",
            'signature_title' => 'ব্যবস্থাপনা পরিচালক', 'signature_company' => '{{CompanyName}}',
            'footer_note' => "আমি এই নিয়োগপত্রের সকল শর্তাবলী পড়ে/শুনে ও বুঝে স্বেচ্ছায় গ্রহণ করলাম।\nশ্রমিক/কর্মচারীর স্বাক্ষর: ____________________     তারিখ: ____________",
            'show_salary_tables' => true, 'salary_tables_after' => 2, 'gazette_id' => $gazette, 'effective_from' => CarbonImmutable::now('Asia/Dhaka')->toDateString(),
            'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    /** Employees with what a letter needs, by name. */
    public function people()
    {
        return DB::table('hr_employees as e')->leftJoin('departments as dp', 'dp.id', '=', 'e.department_id')->leftJoin('sections as sc', 'sc.id', '=', 'e.section_id')
            ->leftJoin('floors as fl', 'fl.id', '=', 'e.floor_id')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')->leftJoin('grades as g', 'g.id', '=', 'e.grade_id')
            ->leftJoin('hr_employment_types as t', 't.id', '=', 'e.employment_type_id')->leftJoin('factories as fa', 'fa.id', '=', 'e.factory_id')->leftJoin('hr_gazettes as gz', 'gz.id', '=', 'e.gazette_id')
            ->where('e.company_id', $this->companyId)->orderBy('e.employee_code')
            ->select(['e.*', 'dp.name as department', 'sc.name as section', 'fl.name as floor', 'd.name as designation', 'g.name as grade_name', 'g.code as grade_code',
                't.name as type_name', 'fa.name as factory', 'gz.name as gazette_name', 'gz.year as gazette_year']);
    }

    /**
     * Each employee's letter details; the wage from the given gazette (the template's), else their own.
     *
     * @param  Collection<int, object>  $people
     * @return list<array<string, mixed>>
     */
    public function details(Collection $people, ?string $gazetteId = null): array
    {
        $gazette = $gazetteId ? DB::table('hr_gazettes')->where('id', $gazetteId)->first(['id', 'name', 'year', 'number']) : null;
        $wages = DB::table('hr_gazette_grade_wages')->where('company_id', $this->companyId)->where('active', true)->get()->keyBy(fn ($w) => "{$w->gazette_id}|{$w->employment_type_id}|{$w->grade_id}");

        return $people->map(function ($e) use ($gazette, $wages) {
            $gz = $gazette ?? (object) ['id' => $e->gazette_id, 'name' => $e->gazette_name, 'year' => $e->gazette_year, 'number' => null];
            $w = $wages["{$gz->id}|{$e->employment_type_id}|{$e->grade_id}"] ?? null;
            $grade = $e->grade_name ?: $e->grade_code;

            return [
                'id' => $e->employee_code, 'employeeNumber' => $e->employee_code, 'cardNumber' => $e->card_no, 'name' => $e->name, 'bn' => ['name' => $e->name_bn],
                'fatherName' => $e->father_name, 'motherName' => $e->mother_name, 'address' => $e->present_address ?: $e->permanent_address,
                'designation' => $e->designation, 'department' => $e->department, 'section' => $e->section, 'floor' => $e->floor, 'grade' => $grade, 'employmentType' => $e->type_name,
                'email' => $e->email, 'factory' => $e->factory, 'status' => $e->status, 'joiningDate' => $e->joining_date ? substr((string) $e->joining_date, 0, 10) : null,
                'companyGross' => $e->gross_a === null ? null : (float) $e->gross_a, 'actualSalary' => $e->actual_salary === null ? null : (float) $e->actual_salary, 'grossB' => $e->gross_b === null ? null : (float) $e->gross_b,
                'gazetteName' => $gz->name, 'gazetteYear' => $gz->year ? (string) $gz->year : null,
                'wage' => $w ? ['gazetteName' => $gz->name, 'gazetteYear' => $gz->year ? (string) $gz->year : null, 'category' => $e->type_name, 'grade' => $grade, 'basic' => (float) $w->basic,
                    'houseRentPercent' => (float) $w->house_rent_pct, 'medical' => (float) $w->medical, 'conveyance' => (float) $w->conveyance, 'food' => (float) $w->food, 'other' => (float) ($w->other ?? 0)] : null,
            ];
        })->values()->all();
    }

    /** The next reference of a type for a letter date. */
    public function nextRef(object $type, string $format, string $date, int $offset = 0): string
    {
        $year = substr($date, 0, 4);
        $n = DB::table('hr_letters')->where('company_id', $this->companyId)->where('letter_type_id', $type->id)->whereYear('letter_date', (int) $year)->count() + 1 + $offset;
        $format = trim($format) !== '' ? $format : strtoupper(Str::limit($type->code, 4, '')).'/{YYYY}/{SEQ}';

        return str_replace(['{YYYY}', '{SEQ}'], [$year, str_pad((string) $n, 3, '0', STR_PAD_LEFT)], $format);
    }

    /** A template as the screens use it. @return array<string, mixed> */
    public static function template(object $t, ?string $gazette = null, int $letters = 0, ?string $by = null): array
    {
        return [
            'id' => "{$t->letter_type_id}:{$t->version}", 'version' => (int) $t->version, 'title' => $t->title, 'subject' => $t->subject, 'referenceFormat' => $t->reference_format,
            'salutation' => (string) $t->salutation, 'openingMessage' => (string) $t->opening_message, 'clauses' => json_decode((string) $t->clauses, true) ?: [],
            'closingMessage' => (string) $t->closing_message, 'signatureTitle' => (string) $t->signature_title, 'signatureCompany' => (string) $t->signature_company, 'footerNote' => (string) $t->footer_note,
            'showSalaryTables' => (bool) $t->show_salary_tables, 'salaryTablesAfter' => (int) $t->salary_tables_after, 'gazetteId' => $t->gazette_id, 'gazette' => $gazette,
            'effectiveFrom' => $t->effective_from ? substr((string) $t->effective_from, 0, 10) : null, 'status' => $t->status, 'letterCount' => $letters,
            'updatedBy' => $by, 'updatedAt' => (string) $t->updated_at,
        ];
    }

    /** The company's letterhead: name, contacts and addresses, logo. @return array<string, mixed> */
    public function letterhead(): array
    {
        $c = Company::query()->find($this->companyId);
        $factory = DB::table('factories')->where('company_id', $this->companyId)->whereNotNull('address')->orderBy('name')->value('address');

        return [
            'company' => $c?->name ?? '', 'phone' => $c?->phone, 'email' => $c?->email, 'website' => $c?->website, 'address' => $factory ?: $c?->address,
            'corporateAddress' => $c?->address, 'logo' => $c?->logoUrl(),
        ];
    }

    public static function ulid(): string
    {
        return strtolower((string) Str::ulid());
    }
}
