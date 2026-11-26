"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { MessageCircle } from "lucide-react";
import { getEcho } from "@/lib/echo";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { CHAT_EVENT, CHAT_STATE, chatApi } from "./chat-lib";
import { pushOn, pushPermission } from "./chat-push";

/**
 * The topbar's chat button (user 2026-11-22: Norban's chat, WhatsApp style):
 * the unread count on a green badge, and the one live subscription of the
 * tab — the person's own `user.{id}` channel — whose chat events it passes
 * on to the chat page (a window event, so the page never opens or closes
 * the channel itself). It also says "I'm here" each minute (online / last
 * seen, delivered ticks), and away from the chat page shows a new message
 * as a toast.
 */
function ChatButton() {
  const t = useT("chat");
  const pathname = usePathname();
  const toast = useToastManager();
  const [unread, setUnread] = useState(0);
  const me = useRef(null);
  const last = useRef(0);
  const onChat = useRef(false);
  const pending = useRef(null);

  // `t` and the toaster are new on every render: kept in a ref, so the one subscription below is made once
  // (with them as dependencies it was made again on each render — a ping and a socket listener each time).
  const words = useRef({ t, toast });
  useEffect(() => {
    onChat.current = pathname?.startsWith("/chat") ?? false;
    words.current = { t, toast };
  });

  const ping = useCallback(async (event = null) => {
    try {
      const d = await chatApi("ping", { method: "POST" });
      me.current = d.me;
      setUnread(d.unread);
      const m = event?.data?.message;
      if (m && d.unread > last.current && !onChat.current && !window.__annotechChatDock?.shows?.(event.chat_id)) {
        const { t: tr, toast: toaster } = words.current;
        const body = m.body || (m.type === "IMAGE" ? `📷 ${tr("photo_msg")}` : m.file?.name ? `📄 ${m.file.name}` : "");
        const group = event.data.chat?.is_group;
        toaster.add({ title: group ? event.data.chat.name : (m.user_name ?? tr("title")), description: group ? `${m.user_name ?? ""}: ${body}` : body, type: "info" });
      }
      last.current = d.unread;
      return d;
    } catch {
      return null;
    }
  }, []);

  useEffect(() => {
    let alive = true;
    let channel = null;
    const tick = setInterval(() => ping(), 60000);
    const onVisible = () => document.visibilityState === "visible" && ping();
    document.addEventListener("visibilitychange", onVisible);
    // The chat page tells its own count at once (it reads chats as they open).
    const onCount = (e) => {
      setUnread(e.detail);
      last.current = e.detail;
    };
    window.addEventListener("annotech:chat-unread", onCount);

    queueMicrotask(() => ping().then((d) => {
      if (!alive || !d?.me) return;
      // A browser that allowed chat notifications keeps its subscription on whoever is signed in now (no prompt here).
      if (pushPermission() === "granted") pushOn({ ask: false }).catch(() => {});
      const echo = getEcho();
      if (!echo) {
        window.dispatchEvent(new CustomEvent(CHAT_STATE, { detail: "unavailable" }));
        return;
      }
      channel = echo.private(`user.${d.me}`);
      channel.listen(".chat.event", (payload) => {
        window.dispatchEvent(new CustomEvent(CHAT_EVENT, { detail: payload }));
        const fromOther = payload.kind === "message" && payload.data?.message?.user_id !== me.current;
        if (fromOther || payload.kind === "read" || payload.kind === "chat") {
          clearTimeout(pending.current);
          pending.current = setTimeout(() => ping(fromOther ? payload : null), fromOther ? 300 : 800);
        }
      });
      const pusher = echo.connector.pusher;
      const tell = (s) => window.dispatchEvent(new CustomEvent(CHAT_STATE, { detail: s.current ?? s }));
      pusher.connection.bind("state_change", tell);
      tell(pusher.connection.state);
    }));

    return () => {
      alive = false;
      clearInterval(tick);
      clearTimeout(pending.current);
      document.removeEventListener("visibilitychange", onVisible);
      window.removeEventListener("annotech:chat-unread", onCount);
      // The channel itself stays for the tab's life (the bell's notifications ride it too); only this listener goes.
      channel?.stopListening(".chat.event");
    };
  }, [ping]);

  return (
    <Link href="/chat" aria-label={t("title")} title={t("title")} className="relative inline-flex size-9 items-center justify-center rounded-full text-foreground-muted transition-colors hover:bg-surface-muted hover:text-foreground">
      <MessageCircle className="size-[18px]" />
      {unread > 0 ? (
        <span className="absolute -top-0.5 -right-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[#25d366] px-1 text-[10px] font-bold text-white">{unread > 99 ? "99+" : unread}</span>
      ) : null}
    </Link>
  );
}

export { ChatButton };
