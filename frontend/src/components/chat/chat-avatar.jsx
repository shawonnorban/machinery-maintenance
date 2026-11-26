"use client";

import { useState } from "react";
import { Users } from "lucide-react";
import { cn } from "@/lib/utils";
import { TONES, avatarUrl, chatImageUrl } from "./chat-lib";

/**
 * A round avatar as WhatsApp draws one: the person's HR photo (or a group's
 * own), else their initials on their own colour; a group without a photo
 * shows the group mark. A green dot for someone online.
 */
function ChatAvatar({ person = null, chat = null, size = 40, online = false, className }) {
  const [broken, setBroken] = useState(false);
  const isGroup = chat?.is_group;
  const src = broken ? null : isGroup ? (chat.image ? chatImageUrl(chat.id, chat.image_v) : null) : person?.photo ? avatarUrl(person.id) : chat?.image && chat.other ? avatarUrl(chat.other.id) : null;
  const tone = TONES[(isGroup ? chat.tone : (person?.tone ?? chat?.tone ?? 0)) % TONES.length];
  const initials = isGroup ? null : (person?.initials ?? chat?.initials ?? "?");

  return (
    <span className={cn("relative inline-flex shrink-0", className)} style={{ width: size, height: size }}>
      {src ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={src} alt="" className="size-full rounded-full object-cover" onError={() => setBroken(true)} />
      ) : (
        <span className="flex size-full items-center justify-center rounded-full font-semibold text-white select-none" style={{ background: isGroup ? "#9eb3c2" : tone, fontSize: Math.max(11, Math.round(size * 0.38)) }}>
          {isGroup ? <Users style={{ width: size * 0.5, height: size * 0.5 }} /> : initials}
        </span>
      )}
      {online ? <span className="absolute right-0 bottom-0 rounded-full border-2 border-white bg-[#25d366]" style={{ width: Math.max(9, size * 0.26), height: Math.max(9, size * 0.26) }} /> : null}
    </span>
  );
}

export { ChatAvatar };
