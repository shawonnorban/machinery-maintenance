<?php

declare(strict_types=1);

use App\Modules\HrPayroll\Http\Controllers\Api\HrAttendanceApiController;
use App\Modules\HrPayroll\Http\Controllers\Api\HrEmployeeApiController;
use App\Modules\HrPayroll\Http\Controllers\Api\HrEmployeeProfileApiController;
use App\Modules\HrPayroll\Http\Controllers\Api\HrFestivalBonusApiController;
use App\Modules\HrPayroll\Http\Controllers\Api\HrIdCardApiController;
use App\Modules\HrPayroll\Http\Controllers\Api\HrLeaveApiController;
use App\Modules\HrPayroll\Http\Controllers\Api\HrLetterApiController;
use App\Modules\HrPayroll\Http\Controllers\Api\HrMovementApiController;
use App\Modules\HrPayroll\Http\Controllers\Api\HrOvertimeApiController;
use App\Modules\HrPayroll\Http\Controllers\Api\HrPayrollAdjustmentApiController;
use App\Modules\HrPayroll\Http\Controllers\Api\HrPayrollApiController;
use App\Modules\HrPayroll\Http\Controllers\Api\HrReportApiController;
use App\Modules\HrPayroll\Http\Controllers\Api\HrRosterApiController;
use App\Modules\HrPayroll\Http\Controllers\Api\HrSalaryApiController;
use App\Modules\HrPayroll\Http\Controllers\Api\HrSeparationApiController;
use Illuminate\Support\Facades\Route;

/*
 * HR & Payroll (AnnotechERP 24). The master lists are screen-kit resources
 * under /erp/{resource}; the type tree, allowance and deduction entry and
 * upload, the wage gazette form, payroll settings, pay period actions and the
 * fiscal year register live here; so do the employees (24/03) — Quick
 * Registration, Employee List and Employee Assignment.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/hr-payroll/lookups', [HrPayrollApiController::class, 'lookups'])->name('hr-payroll.lookups');
    Route::get('/hr-payroll/type-tree', [HrPayrollApiController::class, 'typeTree'])->name('hr-payroll.type-tree');
    Route::get('/hr-payroll/type-tree/{type}', [HrPayrollApiController::class, 'typeChildren'])->name('hr-payroll.type-tree.children');
    Route::put('/hr-payroll/type-tree/{type}', [HrPayrollApiController::class, 'saveTypeChildren'])->name('hr-payroll.type-tree.save');
    Route::post('/hr-payroll/holidays/{holiday}/calendar', [HrPayrollApiController::class, 'holidayToCalendar'])->name('hr-payroll.holidays.calendar');
    Route::post('/hr-payroll/entries/{kind}', [HrPayrollApiController::class, 'entries'])->whereIn('kind', ['allowances', 'deductions'])->name('hr-payroll.entries');
    Route::post('/hr-payroll/uploads', [HrPayrollApiController::class, 'parseUpload'])->name('hr-payroll.uploads');
    Route::get('/hr-payroll/gazettes/{gazette}', [HrPayrollApiController::class, 'gazette'])->name('hr-payroll.gazettes.show');
    Route::post('/hr-payroll/gazettes', [HrPayrollApiController::class, 'saveGazette'])->name('hr-payroll.gazettes.store');
    Route::put('/hr-payroll/gazettes/{gazette}', [HrPayrollApiController::class, 'saveGazette'])->name('hr-payroll.gazettes.update');
    Route::delete('/hr-payroll/gazettes/{gazette}', [HrPayrollApiController::class, 'deleteGazette'])->name('hr-payroll.gazettes.destroy');
    Route::get('/hr-payroll/settings', [HrPayrollApiController::class, 'settings'])->name('hr-payroll.settings');
    Route::put('/hr-payroll/settings', [HrPayrollApiController::class, 'saveSettings'])->name('hr-payroll.settings.save');
    Route::get('/hr-payroll/period-companies', [HrPayrollApiController::class, 'periodCompanies'])->name('hr-payroll.period-companies');
    Route::get('/hr-payroll/period-companies/{company}', [HrPayrollApiController::class, 'periodsOf'])->name('hr-payroll.periods');
    Route::put('/hr-payroll/period-companies/{company}', [HrPayrollApiController::class, 'savePeriodsOf'])->name('hr-payroll.periods.save');
    Route::get('/hr-payroll/fiscal-registers', [HrPayrollApiController::class, 'fiscalRegisters'])->name('hr-payroll.fiscal-registers');
    Route::get('/hr-payroll/fiscal-registers/{register}', [HrPayrollApiController::class, 'fiscalYearsOf'])->name('hr-payroll.fiscal-years');
    Route::put('/hr-payroll/fiscal-registers/{register}', [HrPayrollApiController::class, 'saveFiscalYearsOf'])->name('hr-payroll.fiscal-years.save');

    Route::get('/hr-payroll/employees/options', [HrEmployeeApiController::class, 'options'])->name('hr-payroll.employees.options');
    Route::post('/hr-payroll/employees/assign', [HrEmployeeApiController::class, 'assign'])->name('hr-payroll.employees.assign');
    Route::get('/hr-payroll/employees', [HrEmployeeApiController::class, 'index'])->name('hr-payroll.employees.index');
    Route::post('/hr-payroll/employees', [HrEmployeeApiController::class, 'store'])->name('hr-payroll.employees.store');
    Route::get('/hr-payroll/employees/{employee}', [HrEmployeeApiController::class, 'show'])->name('hr-payroll.employees.show');
    Route::put('/hr-payroll/employees/{employee}', [HrEmployeeApiController::class, 'update'])->name('hr-payroll.employees.update');
    Route::get('/hr-payroll/employees/{employee}/history', [HrEmployeeApiController::class, 'history'])->name('hr-payroll.employees.history');

    // Employee Profile (24/03): the full record in seven tabs, written in one save; its photograph.
    Route::get('/hr-payroll/profiles/options', [HrEmployeeProfileApiController::class, 'profileOptions'])->name('hr-payroll.profiles.options');
    Route::get('/hr-payroll/profiles', [HrEmployeeProfileApiController::class, 'profiles'])->name('hr-payroll.profiles.index');
    Route::post('/hr-payroll/profiles', [HrEmployeeProfileApiController::class, 'storeProfile'])->name('hr-payroll.profiles.store');
    Route::get('/hr-payroll/profiles/{employee}', [HrEmployeeProfileApiController::class, 'profile'])->name('hr-payroll.profiles.show');
    Route::put('/hr-payroll/profiles/{employee}', [HrEmployeeProfileApiController::class, 'updateProfile'])->name('hr-payroll.profiles.update');
    Route::delete('/hr-payroll/profiles/{employee}', [HrEmployeeProfileApiController::class, 'destroyProfile'])->name('hr-payroll.profiles.delete');
    Route::post('/hr-payroll/profiles/{employee}/photo', [HrEmployeeProfileApiController::class, 'photo'])->name('hr-payroll.profiles.photo');
    Route::delete('/hr-payroll/profiles/{employee}/photo', [HrEmployeeProfileApiController::class, 'removePhoto'])->name('hr-payroll.profiles.photo.delete');

    // Attendance & Shift (24/04): daily attendance worked out from the punches, its corrections, the month summary, the shift roster.
    Route::get('/hr-payroll/attendance/options', [HrAttendanceApiController::class, 'options'])->name('hr-payroll.attendance.options');
    Route::get('/hr-payroll/attendance/daily', [HrAttendanceApiController::class, 'daily'])->name('hr-payroll.attendance.daily');
    Route::get('/hr-payroll/attendance/daily/{format}', [HrAttendanceApiController::class, 'dailyExport'])->name('hr-payroll.attendance.daily.export');
    Route::get('/hr-payroll/attendance/sync', [HrAttendanceApiController::class, 'sync'])->name('hr-payroll.attendance.sync');
    Route::post('/hr-payroll/attendance/sync', [HrAttendanceApiController::class, 'syncNow'])->name('hr-payroll.attendance.sync.now');
    Route::post('/hr-payroll/attendance/import', [HrAttendanceApiController::class, 'import'])->name('hr-payroll.attendance.import');
    Route::get('/hr-payroll/attendance/missing', [HrAttendanceApiController::class, 'missing'])->name('hr-payroll.attendance.missing');
    Route::post('/hr-payroll/attendance/corrections', [HrAttendanceApiController::class, 'requestCorrection'])->name('hr-payroll.attendance.corrections.store');
    Route::post('/hr-payroll/attendance/corrections/{id}/decide', [HrAttendanceApiController::class, 'decideCorrection'])->name('hr-payroll.attendance.corrections.decide');
    Route::get('/hr-payroll/attendance/month', [HrAttendanceApiController::class, 'month'])->name('hr-payroll.attendance.month');
    Route::post('/hr-payroll/attendance/day', [HrAttendanceApiController::class, 'saveDay'])->name('hr-payroll.attendance.day.save');
    Route::post('/hr-payroll/attendance/day/delete', [HrAttendanceApiController::class, 'deleteDay'])->name('hr-payroll.attendance.day.delete');
    Route::get('/hr-payroll/attendance/summary', [HrAttendanceApiController::class, 'summary'])->name('hr-payroll.attendance.summary');
    Route::get('/hr-payroll/attendance/summary/{format}', [HrAttendanceApiController::class, 'summaryExport'])->name('hr-payroll.attendance.summary.export');
    Route::get('/hr-payroll/roster/options', [HrRosterApiController::class, 'options'])->name('hr-payroll.roster.options');
    Route::get('/hr-payroll/roster', [HrRosterApiController::class, 'index'])->name('hr-payroll.roster.index');
    Route::post('/hr-payroll/roster', [HrRosterApiController::class, 'assign'])->name('hr-payroll.roster.assign');
    Route::delete('/hr-payroll/roster/{id}', [HrRosterApiController::class, 'destroy'])->name('hr-payroll.roster.delete');

    // Leave & Overtime (24/05): leave applications and balances; OT eligibility, correction, requests and approval.
    Route::get('/hr-payroll/leave/options', [HrLeaveApiController::class, 'options'])->name('hr-payroll.leave.options');
    Route::get('/hr-payroll/leave/balances', [HrLeaveApiController::class, 'balances'])->name('hr-payroll.leave.balances');
    Route::get('/hr-payroll/leave', [HrLeaveApiController::class, 'index'])->name('hr-payroll.leave.index');
    Route::post('/hr-payroll/leave', [HrLeaveApiController::class, 'store'])->name('hr-payroll.leave.store');
    Route::delete('/hr-payroll/leave/{ref}', [HrLeaveApiController::class, 'destroy'])->name('hr-payroll.leave.delete');
    Route::get('/hr-payroll/ot/options', [HrOvertimeApiController::class, 'options'])->name('hr-payroll.ot.options');
    Route::get('/hr-payroll/ot/eligibility', [HrOvertimeApiController::class, 'eligibility'])->name('hr-payroll.ot.eligibility');
    Route::post('/hr-payroll/ot/limits', [HrOvertimeApiController::class, 'saveLimits'])->name('hr-payroll.ot.limits');
    Route::post('/hr-payroll/ot/grade', [HrOvertimeApiController::class, 'saveGrade'])->name('hr-payroll.ot.grade');
    Route::post('/hr-payroll/ot/employee', [HrOvertimeApiController::class, 'saveEmployee'])->name('hr-payroll.ot.employee');
    Route::get('/hr-payroll/ot/correction', [HrOvertimeApiController::class, 'correction'])->name('hr-payroll.ot.correction');
    Route::post('/hr-payroll/ot/correction', [HrOvertimeApiController::class, 'saveCorrection'])->name('hr-payroll.ot.correction.save');
    Route::post('/hr-payroll/ot/correction/reset', [HrOvertimeApiController::class, 'resetCorrection'])->name('hr-payroll.ot.correction.reset');
    Route::get('/hr-payroll/ot/correction/history', [HrOvertimeApiController::class, 'correctionHistory'])->name('hr-payroll.ot.correction.history');
    Route::get('/hr-payroll/ot/requests', [HrOvertimeApiController::class, 'requests'])->name('hr-payroll.ot.requests');
    Route::get('/hr-payroll/ot/line-employees', [HrOvertimeApiController::class, 'lineEmployees'])->name('hr-payroll.ot.line-employees');
    Route::post('/hr-payroll/ot/requests', [HrOvertimeApiController::class, 'storeRequest'])->name('hr-payroll.ot.requests.store');
    Route::put('/hr-payroll/ot/requests/{no}', [HrOvertimeApiController::class, 'updateRequest'])->name('hr-payroll.ot.requests.update');
    Route::get('/hr-payroll/ot/approvals', [HrOvertimeApiController::class, 'approvals'])->name('hr-payroll.ot.approvals');
    Route::post('/hr-payroll/ot/requests/{no}/decide', [HrOvertimeApiController::class, 'decide'])->name('hr-payroll.ot.requests.decide');

    // Salary Processing (24/06): daily salary, OT salary, festival bonus, the monthly run and payroll adjustments.
    Route::get('/hr-payroll/salary/options', [HrSalaryApiController::class, 'options'])->name('hr-payroll.salary.options');
    Route::get('/hr-payroll/salary/daily', [HrSalaryApiController::class, 'daily'])->name('hr-payroll.salary.daily');
    Route::post('/hr-payroll/salary/daily/calculate', [HrSalaryApiController::class, 'calculateDaily'])->name('hr-payroll.salary.daily.calculate');
    Route::post('/hr-payroll/salary/daily/move', [HrSalaryApiController::class, 'moveDaily'])->name('hr-payroll.salary.daily.move');
    Route::get('/hr-payroll/salary/ot', [HrSalaryApiController::class, 'otSalary'])->name('hr-payroll.salary.ot');
    Route::post('/hr-payroll/salary/ot/recalculate', [HrSalaryApiController::class, 'recalculateOt'])->name('hr-payroll.salary.ot.recalculate');
    Route::get('/hr-payroll/salary/monthly', [HrSalaryApiController::class, 'monthly'])->name('hr-payroll.salary.monthly');
    Route::post('/hr-payroll/salary/monthly/calculate', [HrSalaryApiController::class, 'calculateMonthly'])->name('hr-payroll.salary.monthly.calculate');
    Route::post('/hr-payroll/salary/monthly/move', [HrSalaryApiController::class, 'moveMonthly'])->name('hr-payroll.salary.monthly.move');
    Route::get('/hr-payroll/salary/bonus', [HrFestivalBonusApiController::class, 'show'])->name('hr-payroll.salary.bonus');
    Route::post('/hr-payroll/salary/bonus/calculate', [HrFestivalBonusApiController::class, 'calculate'])->name('hr-payroll.salary.bonus.calculate');
    Route::post('/hr-payroll/salary/bonus/move', [HrFestivalBonusApiController::class, 'move'])->name('hr-payroll.salary.bonus.move');
    Route::get('/hr-payroll/salary/adjustments', [HrPayrollAdjustmentApiController::class, 'index'])->name('hr-payroll.salary.adjustments');
    Route::post('/hr-payroll/salary/adjustments', [HrPayrollAdjustmentApiController::class, 'store'])->name('hr-payroll.salary.adjustments.store');
    Route::post('/hr-payroll/salary/adjustments/{ref}/decide', [HrPayrollAdjustmentApiController::class, 'decide'])->name('hr-payroll.salary.adjustments.decide');

    // Employee Movement (24/07): transfers, promotions, increments and re-joins.
    Route::get('/hr-payroll/movement/options', [HrMovementApiController::class, 'options'])->name('hr-payroll.movement.options');
    Route::get('/hr-payroll/movement/employees', [HrMovementApiController::class, 'search'])->name('hr-payroll.movement.employees');
    Route::get('/hr-payroll/transfers', [HrMovementApiController::class, 'transfers'])->name('hr-payroll.transfers.index');
    Route::post('/hr-payroll/transfers', [HrMovementApiController::class, 'storeTransfer'])->name('hr-payroll.transfers.store');
    Route::put('/hr-payroll/transfers/{ref}', [HrMovementApiController::class, 'updateTransfer'])->name('hr-payroll.transfers.update');
    Route::delete('/hr-payroll/transfers/{ref}', [HrMovementApiController::class, 'destroyTransfer'])->name('hr-payroll.transfers.delete');
    Route::post('/hr-payroll/transfers/{ref}/finalize', [HrMovementApiController::class, 'finalize'])->name('hr-payroll.transfers.finalize');
    Route::get('/hr-payroll/promotions', [HrMovementApiController::class, 'promotions'])->name('hr-payroll.promotions.index');
    Route::post('/hr-payroll/promotions', [HrMovementApiController::class, 'storePromotion'])->name('hr-payroll.promotions.store');
    Route::get('/hr-payroll/increments', [HrMovementApiController::class, 'incrementsIndex'])->name('hr-payroll.increments.index');
    Route::post('/hr-payroll/increments', [HrMovementApiController::class, 'storeIncrement'])->name('hr-payroll.increments.store');
    Route::post('/hr-payroll/{kind}/{ref}/decide', [HrMovementApiController::class, 'decide'])->whereIn('kind', ['promotions', 'increments'])->name('hr-payroll.movement.decide');
    Route::delete('/hr-payroll/{kind}/{ref}', [HrMovementApiController::class, 'withdraw'])->whereIn('kind', ['promotions', 'increments'])->name('hr-payroll.movement.withdraw');
    Route::get('/hr-payroll/rejoins', [HrMovementApiController::class, 'rejoins'])->name('hr-payroll.rejoins.index');
    Route::post('/hr-payroll/rejoins', [HrMovementApiController::class, 'storeRejoin'])->name('hr-payroll.rejoins.store');
    Route::put('/hr-payroll/rejoins/{ref}', [HrMovementApiController::class, 'updateRejoin'])->name('hr-payroll.rejoins.update');
    Route::delete('/hr-payroll/rejoins/{ref}', [HrMovementApiController::class, 'destroyRejoin'])->name('hr-payroll.rejoins.delete');
    Route::post('/hr-payroll/rejoins/{ref}/doc', [HrMovementApiController::class, 'rejoinDoc'])->name('hr-payroll.rejoins.doc');

    // Separation (24/08): resignation / release, lefty cases and the final settlement.
    Route::get('/hr-payroll/separation/employees', [HrSeparationApiController::class, 'search'])->name('hr-payroll.separation.employees');
    Route::get('/hr-payroll/separations', [HrSeparationApiController::class, 'separations'])->name('hr-payroll.separations.index');
    Route::post('/hr-payroll/separations', [HrSeparationApiController::class, 'storeSeparation'])->name('hr-payroll.separations.store');
    Route::post('/hr-payroll/separations/{ref}/decide', [HrSeparationApiController::class, 'decideSeparation'])->name('hr-payroll.separations.decide');
    Route::post('/hr-payroll/separations/{ref}/reopen', [HrSeparationApiController::class, 'reopenSeparation'])->name('hr-payroll.separations.reopen');
    Route::post('/hr-payroll/separations/{ref}/settlement', [HrSeparationApiController::class, 'settleSeparation'])->name('hr-payroll.separations.settle');
    Route::delete('/hr-payroll/separations/{ref}', [HrSeparationApiController::class, 'withdrawSeparation'])->name('hr-payroll.separations.withdraw');
    Route::get('/hr-payroll/lefty', [HrSeparationApiController::class, 'lefty'])->name('hr-payroll.lefty.index');
    Route::get('/hr-payroll/lefty/candidates', [HrSeparationApiController::class, 'leftyCandidates'])->name('hr-payroll.lefty.candidates');
    Route::post('/hr-payroll/lefty', [HrSeparationApiController::class, 'reportLefty'])->name('hr-payroll.lefty.store');
    Route::post('/hr-payroll/lefty/{ref}/move', [HrSeparationApiController::class, 'moveLefty'])->name('hr-payroll.lefty.move');
    Route::get('/hr-payroll/settlements', [HrSeparationApiController::class, 'settlements'])->name('hr-payroll.settlements.index');
    Route::get('/hr-payroll/settlements/preview', [HrSeparationApiController::class, 'preview'])->name('hr-payroll.settlements.preview');
    Route::post('/hr-payroll/settlements', [HrSeparationApiController::class, 'storeSettlement'])->name('hr-payroll.settlements.store');
    Route::put('/hr-payroll/settlements/{ref}', [HrSeparationApiController::class, 'updateSettlement'])->name('hr-payroll.settlements.update');
    Route::delete('/hr-payroll/settlements/{ref}', [HrSeparationApiController::class, 'destroySettlement'])->name('hr-payroll.settlements.delete');
    Route::post('/hr-payroll/settlements/{ref}/move', [HrSeparationApiController::class, 'moveSettlement'])->name('hr-payroll.settlements.move');

    // Letter Management (24/09): letter types and template versions, generating, printing and the register.
    Route::get('/hr-payroll/letters/master', [HrLetterApiController::class, 'master'])->name('hr-payroll.letters.master');
    Route::post('/hr-payroll/letters/types', [HrLetterApiController::class, 'addType'])->name('hr-payroll.letters.types.store');
    Route::post('/hr-payroll/letters/types/{code}/toggle', [HrLetterApiController::class, 'toggleType'])->name('hr-payroll.letters.types.toggle');
    Route::post('/hr-payroll/letters/templates', [HrLetterApiController::class, 'saveTemplate'])->name('hr-payroll.letters.templates.save');
    Route::post('/hr-payroll/letters/templates/{code}/{version}/status', [HrLetterApiController::class, 'templateStatus'])->whereNumber('version')->name('hr-payroll.letters.templates.status');
    Route::delete('/hr-payroll/letters/templates/{code}/{version}', [HrLetterApiController::class, 'deleteTemplate'])->whereNumber('version')->name('hr-payroll.letters.templates.delete');
    Route::get('/hr-payroll/letters/generate', [HrLetterApiController::class, 'generateOptions'])->name('hr-payroll.letters.generate.options');
    Route::get('/hr-payroll/letters/employee', [HrLetterApiController::class, 'employee'])->name('hr-payroll.letters.employee');
    Route::post('/hr-payroll/letters/employees', [HrLetterApiController::class, 'employees'])->name('hr-payroll.letters.employees');
    Route::post('/hr-payroll/letters/print', [HrLetterApiController::class, 'printable'])->name('hr-payroll.letters.print');
    Route::post('/hr-payroll/letters/printed', [HrLetterApiController::class, 'printed'])->name('hr-payroll.letters.printed');
    Route::get('/hr-payroll/letters', [HrLetterApiController::class, 'history'])->name('hr-payroll.letters.index');
    Route::post('/hr-payroll/letters', [HrLetterApiController::class, 'generate'])->name('hr-payroll.letters.generate');

    // Dashboard and Reports (24/02).
    Route::get('/hr-payroll/dashboard', [HrReportApiController::class, 'dashboard'])->name('hr-payroll.dashboard');
    Route::get('/hr-payroll/reports', [HrReportApiController::class, 'catalog'])->name('hr-payroll.reports.catalog');
    Route::post('/hr-payroll/reports/{key}', [HrReportApiController::class, 'run'])->name('hr-payroll.reports.run');
    Route::get('/hr-payroll/reports/{key}/xlsx', [HrReportApiController::class, 'excel'])->name('hr-payroll.reports.excel');

    // ID Card (24/03): the print queue; the cards read from the profiles at print time.
    Route::get('/hr-payroll/id-cards/options', [HrIdCardApiController::class, 'cardOptions'])->name('hr-payroll.id-cards.options');
    Route::get('/hr-payroll/id-cards/print', [HrIdCardApiController::class, 'cards'])->name('hr-payroll.id-cards.print');
    Route::get('/hr-payroll/id-cards', [HrIdCardApiController::class, 'queue'])->name('hr-payroll.id-cards.index');
    Route::post('/hr-payroll/id-cards', [HrIdCardApiController::class, 'addOne'])->name('hr-payroll.id-cards.store');
    Route::post('/hr-payroll/id-cards/add', [HrIdCardApiController::class, 'addPicked'])->name('hr-payroll.id-cards.add');
    Route::delete('/hr-payroll/id-cards', [HrIdCardApiController::class, 'clear'])->name('hr-payroll.id-cards.clear');
    Route::put('/hr-payroll/id-cards/{id}', [HrIdCardApiController::class, 'updateCard'])->name('hr-payroll.id-cards.update');
    Route::delete('/hr-payroll/id-cards/{id}', [HrIdCardApiController::class, 'removeCard'])->name('hr-payroll.id-cards.delete');
});
