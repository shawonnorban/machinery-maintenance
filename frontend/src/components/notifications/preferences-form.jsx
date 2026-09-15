"use client";

import { startTransition, useActionState, useEffect, useState } from "react";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { formatStatus } from "@/components/ui/status-badge";

const CHANNELS = ["email", "sms", "whatsapp"];

/** Mirrors `notification::notifications.preferences.blade.php`'s table — one row per event, one column per channel. In-app is always on and not switchable: it's part of the audit trail, not a preference. */
function PreferencesForm({ preferences, action }) {
  const [state, dispatch, pending] = useActionState(action, null);
  const [values, setValues] = useState(preferences);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Preferences saved", type: "success" });
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
              <th className="px-4 py-3">Event</th>
              <th className="px-4 py-3 text-center">In-app</th>
              <th className="px-4 py-3 text-center">Email</th>
              <th className="px-4 py-3 text-center">SMS</th>
              <th className="px-4 py-3 text-center">WhatsApp</th>
            </tr>
          </thead>
          <tbody>
            {Object.keys(values).map((event) => (
              <tr key={event} className="border-b border-border last:border-0">
                <td className="px-4 py-3">{formatStatus(event)}</td>
                <td className="px-4 py-3 text-center">
                  <Checkbox checked disabled aria-label="In-app" />
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

      <p className="text-xs text-foreground-muted">In-app is always on — it&apos;s part of the audit trail, not a preference.</p>

      <Alert variant="info" title="Not yet delivered">
        Email, SMS and WhatsApp delivery are not wired up yet — a preference saved here is honored the moment they are.
      </Alert>

      {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

      <div>
        <Button type="submit" loading={pending}>
          Save
        </Button>
      </div>
    </form>
  );
}

export { PreferencesForm };
