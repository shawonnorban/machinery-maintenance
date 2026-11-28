/**
 * One message, as the web's ChatMessage (WhatsApp's): green on the right for
 * mine, white on the left for others — a group shows the sender's name in
 * their colour and their avatar on the first of a run — with the tail on the
 * first, the reply quote (a tap goes to it), "Forwarded", the photo or the
 * file, the record cards, the text with its mentions, then "Edited", the
 * time and my ticks; the reactions hang under it. A long press (or the
 * arrow) opens the quick reactions and Reply, Copy, Forward, Pin, Message
 * info, Edit, Download and Delete; the reactions open who reacted.
 */
import { memo, useState } from "react";
import { Image, Pressable, View } from "react-native";
import { Ban, ChevronDown, Copy, Download, Forward, Info, Pencil, Pin, PinOff, Plus, Reply, SmilePlus, Trash2 } from "lucide-react-native";
import { Popup, Text } from "../ui";
import { NAME_TONES, QUICK_REACTIONS, clock, fileSize, systemText } from "../../lib/chat";
import { fonts } from "../../theme/tokens";
import { ChatAvatar, EmojiPicker, FileBadge, RecordCards, RichText, Ticks, WA, ct, fileUrl, useAuthSource } from "./parts";

/** The quoted message inside a reply bubble. */
function Quote({ reply, people, meId, onJump, mine }) {
  if (!reply) return null;
  const who = reply.user_id === meId ? ct("you") : (people[reply.user_id]?.name ?? "—");
  const tone = NAME_TONES[(people[reply.user_id]?.tone ?? 5) % NAME_TONES.length];
  const text = reply.deleted ? ct("deleted") : reply.body || (reply.image ? `📷 ${ct("photo_msg")}` : reply.file_name ? `📄 ${reply.file_name}` : "");

  return (
    <Pressable onPress={() => onJump(reply.id)} style={{ marginBottom: 4, borderLeftWidth: 4, borderLeftColor: tone, borderRadius: 6, paddingHorizontal: 8, paddingVertical: 4, backgroundColor: mine ? WA.mineQuote : "#f5f6f6" }}>
      <Text style={{ fontSize: 12.5, fontFamily: fonts.semibold, color: tone }} numberOfLines={1}>
        {who}
      </Text>
      <Text style={{ fontSize: 13, color: WA.sub }} numberOfLines={2}>
        {text}
      </Text>
    </Pressable>
  );
}

function Photo({ m, onOpen, children }) {
  const remote = useAuthSource(m.local_url ? null : fileUrl(m.id));
  const src = m.local_url ? { uri: m.local_url } : remote;
  const [ratio, setRatio] = useState(4 / 3);

  return (
    <Pressable onPress={() => onOpen(m)} style={{ width: 250, maxWidth: "100%", borderRadius: 6, overflow: "hidden" }}>
      <Image source={src} onLoad={(e) => e.nativeEvent?.source?.width && setRatio(Math.max(0.6, Math.min(1.8, e.nativeEvent.source.width / e.nativeEvent.source.height)))} style={{ width: "100%", aspectRatio: ratio, maxHeight: 330, backgroundColor: "#dfe5e7" }} resizeMode="cover" />
      {children}
    </Pressable>
  );
}

function MenuRow({ icon: Icon, label, onPress, danger }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: 14, paddingHorizontal: 6, paddingVertical: 11, borderRadius: 10, backgroundColor: pressed ? "#f5f6f6" : "transparent" })}>
      <Icon size={19} color={danger ? WA.danger : WA.icon} />
      <Text style={{ fontSize: 14.5, color: danger ? WA.danger : "#3b4a54" }}>{label}</Text>
    </Pressable>
  );
}

export const Bubble = memo(function Bubble({ m, mine, group, first, people, meId, highlight, onAction, onJump, onOpenImage }) {
  const [menu, setMenu] = useState(false);
  const [reactions, setReactions] = useState(false);
  const [allEmoji, setAllEmoji] = useState(false);
  const sender = people[m.user_id];
  const tone = NAME_TONES[(sender?.tone ?? 0) % NAME_TONES.length];

  if (m.type === "SYSTEM") {
    return (
      <View style={{ alignItems: "center", marginVertical: 6, paddingHorizontal: "6%" }}>
        <View style={{ backgroundColor: "rgba(255,255,255,0.92)", borderRadius: 8, paddingHorizontal: 12, paddingVertical: 6 }}>
          <Text style={{ fontSize: 12.5, color: WA.icon, textAlign: "center" }}>{systemText(m, people, meId, ct)}</Text>
        </View>
      </View>
    );
  }

  const image = m.file?.image && !m.deleted;
  const canMenu = !m.deleted && m.status !== "pending";
  const act = (kind, extra) => {
    setMenu(false);
    setReactions(false);
    setAllEmoji(false);
    onAction(kind, m, extra);
  };
  const total = (m.reactions ?? []).reduce((a, r) => a + r.count, 0);
  // The react button beside the bubble: on its left for mine, on its right for others'.
  const smile = (
    <Pressable accessibilityLabel={ct("react")} onPress={() => setReactions(true)} hitSlop={6} style={{ alignSelf: "center", marginHorizontal: 4, width: 26, height: 26, borderRadius: 13, backgroundColor: "rgba(0,0,0,0.05)", alignItems: "center", justifyContent: "center" }}>
      <SmilePlus size={14} color={WA.tick} />
    </Pressable>
  );
  const meta = (
    <View style={[{ flexDirection: "row", alignItems: "center", gap: 3, alignSelf: "flex-end" }, image && !m.body ? { position: "absolute", right: 6, bottom: 5, backgroundColor: "rgba(0,0,0,0.35)", borderRadius: 10, paddingHorizontal: 6, paddingVertical: 1 } : { marginTop: 1 }]}>
      {m.edited && !m.deleted ? <Text style={{ fontSize: 10.5, color: image && !m.body ? "#fff" : WA.sub }}>{ct("edited")}</Text> : null}
      <Text style={{ fontSize: 10.5, color: image && !m.body ? "#fff" : WA.sub }}>{clock(m.created_at)}</Text>
      {mine && !m.deleted ? <Ticks status={m.status} color={image && !m.body ? "#fff" : undefined} /> : null}
    </View>
  );

  return (
    <View style={{ flexDirection: "row", justifyContent: mine ? "flex-end" : "flex-start", paddingHorizontal: "3%", marginTop: first ? 10 : 2, marginBottom: m.reactions?.length ? 14 : 0 }}>
      {canMenu && mine ? smile : null}
      {group && !mine ? <View style={{ width: 30, marginRight: 4 }}>{first ? <ChatAvatar person={sender ?? { id: m.user_id, initials: (m.user_name ?? "?").slice(0, 1), tone: 0 }} size={28} /> : null}</View> : null}
      <Pressable
        onLongPress={() => canMenu && setMenu(true)}
        delayLongPress={300}
        style={{
          maxWidth: "82%",
          minWidth: 84,
          borderRadius: 8,
          borderTopRightRadius: first && mine ? 0 : 8,
          borderTopLeftRadius: first && !mine ? 0 : 8,
          backgroundColor: mine ? WA.mine : "#ffffff",
          paddingHorizontal: image ? 3 : 9,
          paddingTop: image ? 3 : 6,
          paddingBottom: image ? 3 : 6,
          borderWidth: highlight ? 2 : 0,
          borderColor: WA.read,
          shadowColor: "#0b141a",
          shadowOffset: { width: 0, height: 1 },
          shadowOpacity: 0.13,
          shadowRadius: 0.5,
          elevation: 1,
        }}
      >
        {first ? <View style={{ position: "absolute", top: 0, [mine ? "right" : "left"]: -7, width: 0, height: 0, borderTopWidth: 12, borderTopColor: mine ? WA.mine : "#ffffff", [mine ? "borderRightWidth" : "borderLeftWidth"]: 8, [mine ? "borderRightColor" : "borderLeftColor"]: "transparent" }} /> : null}
        {group && !mine && first ? (
          <Text style={{ fontSize: 12.5, fontFamily: fonts.semibold, color: tone, paddingHorizontal: image ? 6 : 0, paddingTop: image ? 3 : 0 }} numberOfLines={1}>
            {m.user_name ?? sender?.name}
          </Text>
        ) : null}
        {m.forwarded && !m.deleted ? (
          <View style={{ flexDirection: "row", alignItems: "center", gap: 4, marginBottom: 2, paddingHorizontal: image ? 6 : 0 }}>
            <Forward size={13} color={WA.sub} />
            <Text style={{ fontSize: 12.5, color: WA.sub, fontStyle: "italic" }}>{ct("forwarded")}</Text>
          </View>
        ) : null}
        {!m.deleted ? <Quote reply={m.reply} people={people} meId={meId} onJump={onJump} mine={mine} /> : null}
        {m.deleted ? (
          <View style={{ flexDirection: "row", alignItems: "center", gap: 6, flexWrap: "wrap" }}>
            <Ban size={15} color={WA.tick} />
            <Text style={{ fontSize: 13.5, color: WA.tick, fontStyle: "italic" }}>{mine ? ct("deleted_mine") : ct("deleted")}</Text>
            {meta}
          </View>
        ) : (
          <>
            {image ? (
              <Photo m={m} onOpen={onOpenImage}>
                {!m.body ? meta : null}
              </Photo>
            ) : m.file ? (
              <Pressable onPress={() => onAction("download", m)} style={{ minWidth: 220, flexDirection: "row", alignItems: "center", gap: 10, padding: 9, marginBottom: 3, borderRadius: 6, backgroundColor: mine ? WA.mineQuote : "#f5f6f6" }}>
                <FileBadge name={m.file.name} />
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text style={{ fontSize: 14, color: WA.text }} numberOfLines={1}>
                    {m.file.name}
                  </Text>
                  <Text style={{ fontSize: 12, color: WA.sub }}>{fileSize(m.file.size)}</Text>
                </View>
                <View style={{ width: 32, height: 32, borderRadius: 16, borderWidth: 1, borderColor: "rgba(134,150,160,0.5)", alignItems: "center", justifyContent: "center" }}>
                  <Download size={16} color={WA.tick} />
                </View>
              </Pressable>
            ) : null}
            {m.meta?.mentions?.refs?.length ? <RecordCards refs={m.meta.mentions.refs} mine={mine} /> : null}
            {m.body ? (
              <View style={{ paddingHorizontal: image ? 6 : 0, paddingTop: image ? 4 : 0 }}>
                <RichText text={m.body} mentions={m.meta?.mentions} meId={meId} onUser={(u) => onAction("mention-user", m, u)} style={{ fontSize: 14.5, lineHeight: 20, color: WA.text }} />
                {meta}
              </View>
            ) : !image ? (
              meta
            ) : null}
          </>
        )}
        {canMenu ? (
          <Pressable accessibilityLabel={ct("menu")} onPress={() => setMenu(true)} hitSlop={6} style={{ position: "absolute", top: 1, right: 1, padding: 1, borderRadius: 10, backgroundColor: image ? "rgba(0,0,0,0.3)" : "transparent", opacity: 0.55 }}>
            <ChevronDown size={16} color={image ? "#fff" : WA.tick} />
          </Pressable>
        ) : null}
        {m.reactions?.length ? (
          <Pressable onPress={() => setReactions(true)} style={{ position: "absolute", bottom: -15, [mine ? "right" : "left"]: 8, flexDirection: "row", alignItems: "center", gap: 1, borderRadius: 12, borderWidth: 1, borderColor: WA.line, backgroundColor: "#ffffff", paddingHorizontal: 6, paddingVertical: 1 }}>
            {m.reactions.slice(0, 3).map((r) => (
              <Text key={r.emoji} style={{ fontSize: 13 }}>
                {r.emoji}
              </Text>
            ))}
            {total > 1 ? <Text style={{ fontSize: 11.5, color: WA.sub, marginLeft: 2 }}>{total}</Text> : null}
          </Pressable>
        ) : null}
      </Pressable>
      {canMenu && !mine ? smile : null}

      {/* The message's menu: the quick reactions, then what may be done. */}
      <Popup open={menu} onClose={() => setMenu(false)} closeLabel={ct("cancel")} width={380}>
        <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between", paddingBottom: 8, borderBottomWidth: 1, borderBottomColor: WA.line }}>
          {QUICK_REACTIONS.map((e) => (
            <Pressable key={e} onPress={() => act("react", e)} style={{ padding: 4 }}>
              <Text style={{ fontSize: 26 }}>{e}</Text>
            </Pressable>
          ))}
          <Pressable accessibilityLabel={ct("more_reactions")} onPress={() => { setMenu(false); setAllEmoji(true); }} style={{ width: 34, height: 34, borderRadius: 17, backgroundColor: WA.panel, alignItems: "center", justifyContent: "center" }}>
            <Plus size={18} color={WA.icon} />
          </Pressable>
        </View>
        <MenuRow icon={Reply} label={ct("reply")} onPress={() => act("reply")} />
        {m.body ? <MenuRow icon={Copy} label={ct("copy")} onPress={() => act("copy")} /> : null}
        <MenuRow icon={Forward} label={ct("forward")} onPress={() => act("forward")} />
        <MenuRow icon={m.pinned ? PinOff : Pin} label={m.pinned ? ct("unpin") : ct("pin")} onPress={() => act("pin")} />
        {mine ? <MenuRow icon={Info} label={ct("info")} onPress={() => act("info")} /> : null}
        {mine && (m.type === "TEXT" || m.body) ? <MenuRow icon={Pencil} label={ct("edit")} onPress={() => act("edit")} /> : null}
        {m.file ? <MenuRow icon={Download} label={ct("download")} onPress={() => act("download")} /> : null}
        <MenuRow icon={Trash2} label={ct("delete")} danger onPress={() => act("delete")} />
      </Popup>

      {/* The reactions: the quick ones (mine marked), then who reacted with what. */}
      <Popup open={reactions} onClose={() => setReactions(false)} closeLabel={ct("cancel")} width={380} title={m.reactions?.length ? ct("react") : undefined}>
        <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
          {QUICK_REACTIONS.map((e) => {
            const minePick = m.reactions?.some((r) => r.emoji === e && r.mine);
            return (
              <Pressable key={e} onPress={() => act("react", e)} style={{ padding: 4, borderRadius: 20, backgroundColor: minePick ? WA.line : "transparent" }}>
                <Text style={{ fontSize: 28 }}>{e}</Text>
              </Pressable>
            );
          })}
          <Pressable accessibilityLabel={ct("more_reactions")} onPress={() => { setReactions(false); setAllEmoji(true); }} style={{ width: 36, height: 36, borderRadius: 18, backgroundColor: WA.panel, alignItems: "center", justifyContent: "center" }}>
            <Plus size={19} color={WA.icon} />
          </Pressable>
        </View>
        {(m.reactions ?? []).flatMap((r) =>
          r.users.map((u) => (
            <View key={`${r.emoji}-${u}`} style={{ flexDirection: "row", alignItems: "center", gap: 10, paddingVertical: 5 }}>
              <ChatAvatar person={people[u] ?? { id: u, initials: "?", tone: 0 }} size={28} />
              <Text style={{ flex: 1, fontSize: 14 }} numberOfLines={1}>
                {u === meId ? ct("you") : (people[u]?.name ?? "—")}
              </Text>
              <Text style={{ fontSize: 18 }}>{r.emoji}</Text>
            </View>
          )),
        )}
      </Popup>

      {/* Every emoji, for a reaction not among the quick ones. */}
      <Popup open={allEmoji} onClose={() => setAllEmoji(false)} closeLabel={ct("cancel")} title={ct("more_reactions")} scroll={false}>
        <View style={{ marginHorizontal: -16, marginBottom: -8 }}>
          <EmojiPicker height={300} onPick={(e) => act("react", e)} />
        </View>
      </Popup>
    </View>
  );
});
