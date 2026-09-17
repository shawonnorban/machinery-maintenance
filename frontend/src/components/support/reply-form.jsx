"use client";

import { useActionState, useEffect, useRef } from "react";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/** Mirrors `tickets/show.blade.php`'s own reply box at the bottom of the thread. */
function ReplyForm({ action }) {
  const t = useT("support");
  const [state, dispatch, pending] = useActionState(action, null);
  const formRef = useRef(null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      formRef.current?.reset();
      toastManager.add({ title: t("reply_sent_toast"), type: "success" });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <form ref={formRef} action={dispatch} className="flex flex-col gap-3">
      {state?.status === "error" ? <Alert variant="danger">{state.message}</Alert> : null}
      <Textarea name="body" rows={4} maxLength={5000} placeholder={t("reply_placeholder")} required />
      <div className="flex justify-end">
        <Button type="submit" loading={pending}>
          {t("send_reply")}
        </Button>
      </div>
    </form>
  );
}

export { ReplyForm };
