import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { PreferencesForm } from "@/components/notifications/preferences-form";
import { getT } from "@/lib/i18n-server";
import { savePreferences } from "../actions";

/** Mirrors `NotificationController::preferences` (SRS 27). */
export default async function NotificationPreferencesPage() {
  const [preferences, t] = await Promise.all([
    apiFetch("/notification-preferences"),
    getT("notification"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("notifications"), href: "/notifications" }, { label: t("preferences_short") }]}
        title={t("preferences_short")}
        description={t("preferences_page_description")}
      />

      <Card>
        <CardBody>
          <PreferencesForm preferences={preferences} action={savePreferences} />
        </CardBody>
      </Card>
    </>
  );
}
