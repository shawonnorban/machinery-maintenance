import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { PreferencesForm } from "@/components/notifications/preferences-form";
import { savePreferences } from "../actions";

/** Mirrors `NotificationController::preferences` (SRS 27). */
export default async function NotificationPreferencesPage() {
  const preferences = await apiFetch("/notification-preferences");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Notifications", href: "/notifications" }, { label: "Preferences" }]}
        title="Preferences"
        description="Which channel each kind of event reaches you on."
      />

      <Card>
        <CardBody>
          <PreferencesForm preferences={preferences} action={savePreferences} />
        </CardBody>
      </Card>
    </>
  );
}
