<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An employee: the auto-numbered code, the card / proxy number, name and NID;
 * the salary structure with Gross A, Gross B and the actual salary; and the
 * current posting, assigned from Employee Assignment. HR & Payroll →
 * Employee (AnnotechERP 24/03).
 */
class HrEmployee extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['ACTIVE', 'INACTIVE', 'SUSPENDED', 'SEPARATED'];

    /** The posting's places, in the order Employee Assignment asks for them. */
    public const PLACEMENT = ['factory_id', 'building_id', 'floor_id', 'department_id', 'section_id', 'line_id', 'designation_id', 'grade_id', 'shift_id'];

    /** The columns only the Employee Profile fills. */
    public const PROFILE = ['first_name', 'last_name', 'nick_name', 'guardian_name', 'guardian_relation', 'birth_certificate_no', 'nationality', 'child_count', 'home_phone', 'home_phone_ext', 'tin', 'separation_date', 'active',
        'present_post_code', 'permanent_post_code', 'emergency_name', 'emergency_relation', 'emergency_mobile', 'emergency_address', 'passport_no', 'passport_issue_place', 'passport_issue_date', 'passport_expire_date',
        'driving_license_no', 'driving_license_expire', 'photo_file_id', 'name_bn', 'higher_education', 'spouse_name', 'spouse_relation', 'spouse_employee_code', 'stay_with_family', 'festival_ot_eligible'];

    protected $table = 'hr_employees';

    protected $fillable = [
        'company_id', 'employee_code', 'card_no', 'name', 'nid', 'date_of_birth', 'joining_date', 'mobile', 'status',
        'gazette_id', 'employment_type_id', 'gross_a', 'gross_b', 'actual_salary',
        'father_name', 'mother_name', 'email', 'gender', 'religion', 'blood_group', 'marital_status', 'country', 'rocket_no', 'present_address', 'permanent_address',
        ...self::PROFILE, ...self::PLACEMENT, 'created_by',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'joining_date' => 'date', 'separation_date' => 'date', 'passport_issue_date' => 'date', 'passport_expire_date' => 'date', 'driving_license_expire' => 'date', 'active' => 'boolean', 'stay_with_family' => 'boolean', 'festival_ot_eligible' => 'boolean', 'child_count' => 'integer', 'gross_a' => 'decimal:2', 'gross_b' => 'decimal:2', 'actual_salary' => 'decimal:2'];
    }

    /** Assigned once Employee Assignment has given them a factory, department and designation. */
    public function isAssigned(): bool
    {
        return $this->factory_id !== null && $this->department_id !== null && $this->designation_id !== null;
    }
}
