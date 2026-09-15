import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { TeamsTable } from "@/components/teams/teams-table";
import { createTeam, updateTeam, toggleTeam, deleteTeam } from "./actions";

/** Mirrors `TeamController::index` — who a job goes to when it doesn't go to one person. */
export default async function TeamsPage() {
  const [teams, formOptions] = await Promise.all([
    apiFetch("/teams?per_page=100", { includeMeta: true }),
    apiFetch("/teams/form-options"),
  ]);

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Settings" }, { label: "Teams" }]} title="Teams" description="Maintenance teams a job can be handed to." />

      <TeamsTable
        teams={teams.data}
        factories={formOptions.factories}
        actions={{ createTeam, updateTeam, toggleTeam, deleteTeam }}
      />
    </>
  );
}
