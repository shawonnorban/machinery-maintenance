import Link from "next/link";
import { CircleCheck, ClipboardList, Cpu, List, UserPlus } from "lucide-react";
import { cn } from "@/lib/utils";
import { buttonVariants } from "@/components/ui/button";
import { apiFetch } from "@/lib/api-server";
import { getT } from "@/lib/i18n-server";
import { QuickRegistration } from "@/components/hrpayroll/employee/quick-registration";
import { EmployeeList } from "@/components/hrpayroll/employee/employee-list";
import { EmployeeAssignment } from "@/components/hrpayroll/employee/employee-assignment";
import { EmployeeProfile } from "@/components/hrpayroll/employee/employee-profile";
import { IdCard } from "@/components/hrpayroll/employee/id-card";
import { Biometric } from "@/components/hrpayroll/employee/biometric";
import { AttendanceCorrection, BiometricSync, DailyAttendance, MissingPunch, MonthlySummary, ShiftRoster } from "@/components/hrpayroll/attendance";
import { LeaveApplication, OtApproval, OtCorrection, OtEligibility, OtRequest } from "@/components/hrpayroll/leave-overtime";
import { DailySalary, FestivalBonus, MonthlySalary, OtSalary, PayrollAdjustment } from "@/components/hrpayroll/salary";
import { Increment, Promotion, Rejoin, Transfer } from "@/components/hrpayroll/movement";
import { Lefty, Separation, Settlement } from "@/components/hrpayroll/separation";
import { GenerateLetter, LetterHistory, LetterMaster, LetterReports } from "@/components/hrpayroll/letters";
import { HrDashboard, HrReports } from "@/components/hrpayroll/reports";
import * as actions from "./actions";

/*
 * HR & Payroll → EMPLOYEE (AnnotechERP 24/03): Quick Registration (a new
 * employee, or `?edit=EMP-000001`), Employee List and Employee Assignment
 * (`?employee=EMP-000001` opens on that employee). Each carries the button
 * Brickland puts in its header.
 */

const safe = (path) => apiFetch(path).catch(() => null);

async function headerLink(href, label, Icon, primary) {
  return (
    <Link
      href={href}
      className={cn(
        buttonVariants({ variant: primary ? "primary" : "outline" }),
      )}
    >
      <Icon className="size-4" aria-hidden />
      {label}
    </Link>
  );
}

async function quickRegistration({ searchParams = {} }) {
  const t = await getT("hrpayroll");
  const [options, initial] = await Promise.all([
    apiFetch("/hr-payroll/employees/options"),
    searchParams.edit
      ? safe(`/hr-payroll/employees/${encodeURIComponent(searchParams.edit)}`)
      : null,
  ]);
  return {
    actions: await headerLink(
      "/hr-payroll/employee-register",
      t("emp.employee_list"),
      List,
      false,
    ),
    body: async () => (
      <QuickRegistration
        key={initial?.code ?? "new"}
        options={options}
        initial={initial}
        act={actions.employeeAction}
      />
    ),
  };
}

async function employeeList({ permissions = [] }) {
  const t = await getT("hrpayroll");
  const canManage = permissions.includes("hrpayroll.employee.manage");
  const [data, options] = await Promise.all([
    apiFetch("/hr-payroll/employees"),
    apiFetch("/hr-payroll/employees/options"),
  ]);
  return {
    actions: canManage
      ? await headerLink(
          "/hr-payroll/employee-quick-registration",
          t("emp.quick_registration"),
          UserPlus,
          true,
        )
      : null,
    body: async () => (
      <EmployeeList
        data={data}
        options={options}
        canManage={canManage}
        act={actions.employeeAction}
      />
    ),
  };
}

async function employeeAssignment({ searchParams = {} }) {
  const t = await getT("hrpayroll");
  const [data, options, preselect] = await Promise.all([
    apiFetch(
      searchParams.employee
        ? "/hr-payroll/employees"
        : "/hr-payroll/employees?assignment=PENDING",
    ),
    apiFetch("/hr-payroll/employees/options"),
    searchParams.employee
      ? safe(
          `/hr-payroll/employees/${encodeURIComponent(searchParams.employee)}`,
        )
      : null,
  ]);
  const list =
    searchParams.employee || data.counts.pending
      ? data
      : await apiFetch("/hr-payroll/employees");
  return {
    actions: await headerLink(
      "/hr-payroll/employee-quick-registration",
      t("emp.quick_registration"),
      UserPlus,
      false,
    ),
    body: async () => (
      <EmployeeAssignment
        data={list}
        options={options}
        preselect={preselect}
        act={actions.employeeAction}
      />
    ),
  };
}

/** Employee Profile: Records and New / Edit employee, switching in place (`?edit=EMP-000001` opens on one). */
async function employeeProfile({ searchParams = {}, permissions = [] }) {
  const canManage = permissions.includes("hrpayroll.employee.manage");
  const [rows, options, extra, initial] = await Promise.all([
    apiFetch("/hr-payroll/profiles"),
    apiFetch("/hr-payroll/employees/options"),
    apiFetch("/hr-payroll/profiles/options"),
    searchParams.edit && canManage
      ? safe(`/hr-payroll/profiles/${encodeURIComponent(searchParams.edit)}`)
      : null,
  ]);
  return {
    body: async () => (
      <EmployeeProfile
        rows={rows}
        options={options}
        extra={extra}
        initial={initial}
        canManage={canManage}
        act={actions.employeeAction}
      />
    ),
  };
}

/** Biometric / Fingerprint: the terminals and every employee's fingerprint status; Device Setup leads to the device admin. */
async function biometric({ permissions = [] }) {
  const t = await getT("hrpayroll");
  const [overview, initial, unmapped] = await Promise.all([
    apiFetch("/hr-payroll/biometric/overview"),
    apiFetch("/hr-payroll/biometric/employees"),
    apiFetch("/hr-payroll/biometric/unmapped"),
  ]);
  return {
    actions: permissions.includes("settings.device.view") || permissions.includes("settings.device.manage")
      ? await headerLink("/system-settings/attendance-devices", t("emp.bio.device_setup"), Cpu, false)
      : null,
    body: async () => (
      <Biometric
        overview={overview}
        initial={initial}
        unmapped={unmapped}
        canManage={permissions.includes("hrpayroll.biometric.manage")}
        act={actions.employeeAction}
      />
    ),
  };
}

/** ID Card: the print queue (active employees' cards first) and Add to print queue, switching in place. */
async function idCard({ permissions = [] }) {
  const [rows, options] = await Promise.all([
    apiFetch("/hr-payroll/id-cards?status=ACTIVE"),
    apiFetch("/hr-payroll/id-cards/options"),
  ]);
  return {
    body: async () => (
      <IdCard
        rows={rows}
        options={options}
        canManage={permissions.includes("hrpayroll.employee.manage")}
        act={actions.employeeAction}
      />
    ),
  };
}

/*
 * Attendance & Shift (24/04): the six screens, each drawing its own state;
 * the days are worked out from the punches as they are looked at.
 */
const can = (permissions, p) => permissions.includes(p);

async function dailyAttendance() {
  const [initial, options] = await Promise.all([apiFetch("/hr-payroll/attendance/daily?range=today"), apiFetch("/hr-payroll/attendance/options")]);
  return { body: async () => <DailyAttendance initial={initial} options={options} act={actions.employeeAction} /> };
}

async function biometricSync({ permissions = [] }) {
  const t = await getT("hrpayroll");
  const [initial, options] = await Promise.all([apiFetch("/hr-payroll/attendance/sync"), apiFetch("/hr-payroll/attendance/options")]);
  return {
    actions: can(permissions, "settings.device.view") || can(permissions, "settings.device.manage") ? await headerLink("/system-settings/attendance-devices", t("emp.bio.device_setup"), Cpu, false) : null,
    body: async () => <BiometricSync initial={initial} options={options} canManage={can(permissions, "hrpayroll.attendance.manage")} act={actions.employeeAction} />,
  };
}

async function missingPunch({ permissions = [] }) {
  const [initial, options] = await Promise.all([apiFetch("/hr-payroll/attendance/missing"), apiFetch("/hr-payroll/attendance/options")]);
  return {
    body: async () => (
      <MissingPunch initial={initial} options={options} canManage={can(permissions, "hrpayroll.attendance.manage")} canApprove={can(permissions, "hrpayroll.attendance.approve")} act={actions.employeeAction} />
    ),
  };
}

async function attendanceCorrection({ permissions = [] }) {
  const options = await apiFetch("/hr-payroll/attendance/options");
  return { body: async () => <AttendanceCorrection options={options} canManage={can(permissions, "hrpayroll.attendance.manage")} act={actions.employeeAction} /> };
}

async function monthlySummary() {
  const options = await apiFetch("/hr-payroll/attendance/options");
  return { body: async () => <MonthlySummary options={options} act={actions.employeeAction} /> };
}

async function shiftRoster({ permissions = [] }) {
  const [initial, attendance, roster] = await Promise.all([apiFetch("/hr-payroll/roster"), apiFetch("/hr-payroll/attendance/options"), apiFetch("/hr-payroll/roster/options")]);
  return { body: async () => <ShiftRoster initial={initial} options={{ ...attendance, ...roster }} canManage={can(permissions, "hrpayroll.attendance.manage")} act={actions.employeeAction} /> };
}

/*
 * Leave & Overtime (24/05): Leave, OT Correction, OT Eligibility, OT Request
 * and OT Approval; OT Request and OT Approval link to each other in the header.
 */
async function leaveApplication({ permissions = [] }) {
  const [initial, options] = await Promise.all([apiFetch("/hr-payroll/leave"), apiFetch("/hr-payroll/leave/options")]);
  return { body: async () => <LeaveApplication initial={initial} options={options} canManage={can(permissions, "hrpayroll.leave.manage")} act={actions.employeeAction} /> };
}

async function otCorrection({ permissions = [] }) {
  const [initial, options] = await Promise.all([apiFetch("/hr-payroll/ot/correction"), apiFetch("/hr-payroll/ot/options")]);
  return { body: async () => <OtCorrection initial={initial} options={options} canManage={can(permissions, "hrpayroll.ot.manage")} act={actions.employeeAction} /> };
}

async function otEligibility({ permissions = [] }) {
  const [initial, options] = await Promise.all([apiFetch("/hr-payroll/ot/eligibility"), apiFetch("/hr-payroll/ot/options")]);
  return { body: async () => <OtEligibility initial={initial} options={options} canManage={can(permissions, "hrpayroll.ot.manage")} act={actions.employeeAction} /> };
}

async function otRequest({ permissions = [] }) {
  const t = await getT("hrpayroll");
  const [initial, options] = await Promise.all([apiFetch("/hr-payroll/ot/requests"), apiFetch("/hr-payroll/ot/options")]);
  return {
    actions: can(permissions, "hrpayroll.ot.approve") || can(permissions, "hrpayroll.ot.view") ? await headerLink("/hr-payroll/ot-approval", t("emp.screens.ot-approval"), CircleCheck, false) : null,
    body: async () => <OtRequest initial={initial} options={options} act={actions.employeeAction} />,
  };
}

async function otApproval({ permissions = [] }) {
  const t = await getT("hrpayroll");
  const initial = await apiFetch("/hr-payroll/ot/approvals");
  return {
    actions: can(permissions, "hrpayroll.ot.manage") || can(permissions, "hrpayroll.ot.view") ? await headerLink("/hr-payroll/ot-request", t("emp.screens.ot-request"), ClipboardList, false) : null,
    body: async () => <OtApproval initial={initial} canApprove={can(permissions, "hrpayroll.ot.approve")} act={actions.employeeAction} />,
  };
}

/*
 * Salary Processing (24/06): Daily Salary, OT Salary, Festival Bonus,
 * Monthly Salary and Payroll Adjustment.
 */
async function dailySalary() {
  const [initial, options] = await Promise.all([apiFetch("/hr-payroll/salary/daily"), apiFetch("/hr-payroll/salary/options")]);
  return { body: async () => <DailySalary initial={initial} options={options} act={actions.employeeAction} /> };
}

async function otSalary() {
  const [initial, options] = await Promise.all([apiFetch("/hr-payroll/salary/ot"), apiFetch("/hr-payroll/salary/options")]);
  return { body: async () => <OtSalary initial={initial} options={options} act={actions.employeeAction} /> };
}

async function festivalBonus() {
  const initial = await apiFetch("/hr-payroll/salary/bonus");
  return { body: async () => <FestivalBonus initial={initial} act={actions.employeeAction} /> };
}

async function monthlySalary() {
  const options = await apiFetch("/hr-payroll/salary/options");
  const initial = options.periods.length ? await apiFetch("/hr-payroll/salary/monthly") : null;
  return { body: async () => <MonthlySalary initial={initial} options={options} act={actions.employeeAction} /> };
}

async function payrollAdjustment() {
  const initial = await apiFetch("/hr-payroll/salary/adjustments");
  return { body: async () => <PayrollAdjustment initial={initial} act={actions.employeeAction} /> };
}

/*
 * Employee Movement (24/07): Transfer, Promotion, Increment and Re-Joining.
 */
async function employeeTransfer() {
  const [initial, options] = await Promise.all([apiFetch("/hr-payroll/transfers"), apiFetch("/hr-payroll/movement/options")]);
  return { body: async () => <Transfer initial={initial} options={options} act={actions.employeeAction} /> };
}

async function employeePromotion() {
  const initial = await apiFetch("/hr-payroll/promotions");
  return { body: async () => <Promotion initial={initial} act={actions.employeeAction} /> };
}

async function employeeIncrement() {
  const initial = await apiFetch("/hr-payroll/increments");
  return { body: async () => <Increment initial={initial} act={actions.employeeAction} /> };
}

async function employeeRejoin() {
  const initial = await apiFetch("/hr-payroll/rejoins");
  return { body: async () => <Rejoin initial={initial} act={actions.employeeAction} /> };
}

/*
 * Separation (24/08): Resignation / Release, Final Settlement and Lefty Management.
 */
async function employeeSeparation() {
  const initial = await apiFetch("/hr-payroll/separations");
  return { body: async () => <Separation initial={initial} act={actions.employeeAction} /> };
}

async function finalSettlement() {
  const initial = await apiFetch("/hr-payroll/settlements");
  return { body: async () => <Settlement initial={initial} act={actions.employeeAction} /> };
}

async function leftyManagement() {
  const initial = await apiFetch("/hr-payroll/lefty");
  return { body: async () => <Lefty initial={initial} act={actions.employeeAction} /> };
}

/*
 * Letter Management (24/09): Letter Master, Generate Letter, Letter History and Letter Reports.
 */
async function letterMaster() {
  const initial = await apiFetch("/hr-payroll/letters/master");
  return { body: async () => <LetterMaster initial={initial} act={actions.employeeAction} /> };
}

async function generateLetter() {
  const initial = await apiFetch("/hr-payroll/letters/generate");
  return { body: async () => <GenerateLetter initial={initial} act={actions.employeeAction} /> };
}

async function letterHistory() {
  const initial = await apiFetch("/hr-payroll/letters");
  return { body: async () => <LetterHistory initial={initial} act={actions.employeeAction} /> };
}

async function letterReports() {
  const initial = await apiFetch("/hr-payroll/letters");
  return { body: async () => <LetterReports initial={initial} act={actions.employeeAction} /> };
}

/*
 * Dashboard and Reports (24/02).
 */
async function hrDashboard() {
  const initial = await apiFetch("/hr-payroll/dashboard");
  return { body: async () => <HrDashboard initial={initial} act={actions.employeeAction} /> };
}

async function hrReports({ searchParams }) {
  const initial = await apiFetch("/hr-payroll/reports");
  return { body: async () => <HrReports initial={initial} act={actions.employeeAction} start={searchParams?.report} /> };
}

const EMPLOYEE_SCREENS = {
  "hr-dashboard": hrDashboard,
  "hr-reports": hrReports,
  "employee-quick-registration": quickRegistration,
  "employee-register": employeeList,
  "employee-profile": employeeProfile,
  "employee-assignment": employeeAssignment,
  "biometric-fingerprint": biometric,
  "id-card-print": idCard,
  "daily-attendance": dailyAttendance,
  "biometric-sync": biometricSync,
  "missing-punch": missingPunch,
  "attendance-correction": attendanceCorrection,
  "monthly-attendance-summary": monthlySummary,
  "shift-roster": shiftRoster,
  "leave-application": leaveApplication,
  "ot-correction": otCorrection,
  "ot-eligibility": otEligibility,
  "ot-request": otRequest,
  "ot-approval": otApproval,
  "daily-salary": dailySalary,
  "ot-salary": otSalary,
  "festival-bonus": festivalBonus,
  "monthly-salary": monthlySalary,
  "payroll-adjustment": payrollAdjustment,
  "employee-transfer": employeeTransfer,
  "employee-promotion": employeePromotion,
  "employee-increment": employeeIncrement,
  "employee-re-join": employeeRejoin,
  "employee-separation": employeeSeparation,
  "final-settlement": finalSettlement,
  "lefty-management": leftyManagement,
  "letter-master": letterMaster,
  "generate-letter": generateLetter,
  "letter-history": letterHistory,
  "letter-reports": letterReports,
};

export { EMPLOYEE_SCREENS };
