"use client";

import { startTransition, useActionState, useEffect, useState } from "react";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const CHANNELS = ["email", "sms", "whatsapp"];

/** Mirrors `notification::notifications.preferences.blade.php`'s table — one row per event, one column per channel. In-app is always on and not switchable: it's part of the audit trail, not a preference. */
function PreferencesForm({ preferences, action }) {
  const t = useT("notification");
  const tc = useT("common");
  const [state, dispatch, pending] = useActionState(action, null);
  const [values, setValues] = useState(preferences);
  const toastManager = useToastManager();

  function eventLabel(event) {
    const value = t(`event_${event}`);
    return value === `event_${event}` ? event : value;
  }

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("preferences_saved"), type: "success" });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function toggle(event, channel) {
    setValues((prev) => ({
      ...prev,
      [event]: { ...prev[event], [channel]: !prev[event][channel] },
    }));
  }

  function handleSubmit(formEvent) {
    formEvent.preventDefault();
    const formData = new FormData();
    for (const [event, channels] of Object.entries(values)) {
      for (const channel of CHANNELS) {
        formData.set(`preferences[${event}][${channel}]`, channels[channel] ? "1" : "0");
      }
    }
    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      <div className="overflow-x-auto rounded-sm border border-border">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border text-left text-xs text-foreground-muted">
              <th className="px-4 py-3">{t("event_label")}</th>
              <th className="px-4 py-3 text-center">{t("channel_in_app")}</th>
              <th className="px-4 py-3 text-center">{t("channel_email")}</th>
              <th className="px-4 py-3 text-center">{t("channel_sms")}</th>
              <th className="px-4 py-3 text-center">{t("channel_whatsapp")}</th>
            </tr>
          </thead>
          <tbody>
            {Object.keys(values).map((event) => (
              <tr key={event} className="border-b border-border last:border-0">
                <td className="px-4 py-3">{eventLabel(event)}</td>
                <td className="px-4 py-3 text-center">
                  <Checkbox checked disabled aria-label={t("channel_in_app")} />
                </td>
                {CHANNELS.map((channel) => (
                  <td key={channel} className="px-4 py-3 text-center">
                    <Checkbox
                      checked={Boolean(values[event][channel])}
                      onCheckedChange={() => toggle(event, channel)}
                      aria-label={channel}
                    />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <p className="text-xs text-foreground-muted">{t("in_app_always_on")}</p>

      <Alert variant="info" title={t("not_yet_delivered")}>
        {t("not_yet_delivered_hint")}
      </Alert>

      {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

      <div>
        <Button type="submit" loading={pending}>
          {tc("save")}
        </Button>
      </div>
    </form>
  );
}

export { PreferencesForm };
