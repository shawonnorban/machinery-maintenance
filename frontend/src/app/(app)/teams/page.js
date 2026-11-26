import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { NewTeamButton, TeamsTable } from "@/components/teams/teams-table";
import { getT } from "@/lib/i18n-server";
import { createTeam, updateTeam, toggleTeam, deleteTeam } from "./actions";

/** Mirrors `TeamController::index` — who a job goes to when it doesn't go to one person. */
export default async function TeamsPage() {
  const [teams, formOptions, t] = await Promise.all([
    apiFetch("/teams?per_page=100", { includeMeta: true }),
    apiFetch("/teams/form-options"),
    getT("team"),
  ]);

  return (
    <>
      <ScreenHeader
        url="/teams"
        title={t("teams")}
        description={t("page_description")}
        actions={<NewTeamButton factories={formOptions.factories} action={createTeam} />}
      />

      <TeamsTable
        teams={teams.data}
        factories={formOptions.factories}
        actions={{ createTeam, updateTeam, toggleTeam, deleteTeam }}
      />
    </>
  );
}
