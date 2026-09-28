/**
 * Mirrors `App\Modules\Asset\Models\Asset::TRANSITIONS` exactly (Data
 * Dictionary 3.3) — used only to decide which options the status-change
 * dropdown *offers*. Hiding an invalid option is a courtesy to the user,
 * never the security boundary (UI-DESIGN-SYSTEM.md §4 rule 4): the API
 * re-checks this same table itself and is what actually enforces it, so a
 * client sending a status this map wouldn't have offered still gets a
 * correct 422/409, not a security hole.
 */
const ASSET_TRANSITIONS = {
  DRAFT: ["PURCHASED"],
  PURCHASED: ["INSTALLED"],
  INSTALLED: ["COMMISSIONED"],
  COMMISSIONED: ["RUNNING", "IDLE"],
  RUNNING: ["IDLE", "UNDER_MAINTENANCE", "BREAKDOWN", "RETIRED", "LOST"],
  IDLE: ["RUNNING", "UNDER_MAINTENANCE", "BREAKDOWN", "RETIRED", "LOST"],
  UNDER_MAINTENANCE: ["RUNNING", "IDLE", "BREAKDOWN"],
  BREAKDOWN: ["UNDER_REPAIR"],
  UNDER_REPAIR: ["RUNNING", "IDLE", "RETIRED", "SCRAPPED"],
  RETIRED: ["SCRAPPED", "RUNNING"],
  SCRAPPED: [],
  LOST: ["RUNNING", "SCRAPPED"],
};

export { ASSET_TRANSITIONS };
