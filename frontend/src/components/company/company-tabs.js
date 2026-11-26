/**
 * Company Management's tabs, in Brickland's order (AnnotechERP 01). `type` is
 * the master-data registry key a grid tab is backed by; `kind` names the tabs
 * that have their own screen instead (the profile form, the factory and
 * document grids over their own APIs, the machine register).
 */
const COMPANY_TABS = [
  { key: "company-profile", type: null, kind: "profile" },
  { key: "factory", type: null, kind: "factory" },
  { key: "building", type: "buildings" },
  { key: "floor", type: "floors" },
  { key: "department", type: "departments" },
  { key: "section", type: "sections" },
  { key: "line", type: "production-lines" },
  { key: "workstation", type: "workstations" },
  { key: "machine-master", type: null, kind: "machines" },
  { key: "machine-allocation", type: null, kind: "allocation" },
  { key: "designation", type: "designations" },
  { key: "company-documents", type: null, kind: "documents" },
];

function findCompanyTab(key) {
  return COMPANY_TABS.find((tab) => tab.key === key) ?? null;
}

export { COMPANY_TABS, findCompanyTab };
