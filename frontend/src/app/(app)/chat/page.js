import { apiFetch } from "@/lib/api-server";
import { ChatApp } from "@/components/chat/chat-app";

/**
 * Chat (user 2026-11-22: Norban's chat module, WhatsApp style) — everyone of
 * the company, no permission of its own: the chats are the caller's by
 * membership. The page fills the space between the topbar and the footer;
 * everything after the first paint happens in the browser, live.
 */
export const dynamic = "force-dynamic";

export default async function ChatPage() {
  const initial = await apiFetch("/chat/chats");

  return <ChatApp initial={initial} />;
}
