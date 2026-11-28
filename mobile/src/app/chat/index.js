/**
 * Chat, as the web's left pane (WhatsApp's): new chat and new group, the
 * search — the chats, the company's people, and the messages themselves —
 * All / Unread / Groups, the archived chats, and the rows: photo with the
 * online dot, name, the last message with my ticks or "typing…", the time,
 * the unread count (grey when muted), @ when I was mentioned, pinned and
 * muted marks. A long press opens the chat's own pin / mute / archive.
 * Live through the socket.
 */
import { useMemo, useState } from "react";
import { FlatList, Pressable, View } from "react-native";
import { router } from "expo-router";
import { SafeAreaView } from "react-native-safe-area-context";
import { Archive, BellOff, MessageSquarePlus, Pin, Users } from "lucide-react-native";
import { Popup, PopupChoice, Text } from "../../components/ui";
import { ChatHeader, HeaderButton } from "../../components/chat/header";
import { ChatAvatar, ChatSearchBox, Ticks, WA, ct } from "../../components/chat/parts";
import { BudgetEmptyState } from "../../components/budget-list";
import { chatSetting, listTime, loadChats, peopleOf, previewText, startDirect, useChatLive, useMessageSearch } from "../../lib/chat";
import { currentLocale } from "../../lib/i18n";
import { fonts, space } from "../../theme/tokens";

export default function Chats() {
  const s = useChatLive();
  const locale = currentLocale();
  const [query, setQuery] = useState("");
  const [filter, setFilter] = useState("all");
  const [pane, setPane] = useState("list");
  const [rowMenu, setRowMenu] = useState(null);
  const people = useMemo(() => peopleOf(s), [s]);
  const me = s.me;
  const chats = s.chats ?? [];
  const q = query.trim().toLowerCase();
  const found = useMessageSearch(pane === "archived" ? "" : query);
  const archivedCount = chats.filter((c) => c.archived).length;
  const shown = chats.filter((c) => {
    if (pane === "archived") return c.archived;
    if (c.archived && !q) return false;
    if (q) return (c.name ?? "").toLowerCase().includes(q) || (c.last?.body ?? "").toLowerCase().includes(q);
    if (filter === "unread") return c.unread > 0;
    if (filter === "groups") return c.is_group;
    return true;
  });
  const directIds = new Set(chats.filter((c) => !c.is_group && c.other).map((c) => c.other.id));
  const matchedPeople = q ? (s.contacts ?? []).filter((p) => !directIds.has(p.id) && `${p.name} ${p.about ?? ""}`.toLowerCase().includes(q)) : [];

  const open = (id, jump) => router.push({ pathname: "/chat/[id]", params: jump ? { id, jump } : { id } });
  const direct = async (p) => open((await startDirect(p)).id);

  const items = [
    ...(pane === "list" && !q && archivedCount ? [{ kind: "archived", key: "archived" }] : []),
    ...shown.map((c) => ({ kind: "chat", key: c.id, c })),
    ...(matchedPeople.length ? [{ kind: "label", key: "people", label: ct("people") }] : []),
    ...matchedPeople.map((p) => ({ kind: "person", key: `p-${p.id}`, p })),
    ...(found.active ? [{ kind: "label", key: "messages", label: ct("messages") }] : []),
    ...(found.loading ? [{ kind: "note", key: "searching", label: ct("searching") }] : []),
    ...(found.active && !found.loading && !found.results.length ? [{ kind: "note", key: "none", label: ct("no_messages_found") }] : []),
    ...found.results.map((h) => ({ kind: "hit", key: `h-${h.id}`, h })),
  ];

  const rowLine = (c) => {
    const names = Object.values(s.typing[c.id] ?? {}).map((x) => x.name);
    if (names.length) return <Text style={{ fontSize: 14, color: WA.unread }} numberOfLines={1}>{c.is_group ? ct("typing_name", { name: names.join(", ") }) : ct("typing")}</Text>;
    const mine = c.last && c.last.user_id === me?.id && c.last.type !== "SYSTEM" && !c.last.deleted;
    return (
      <View style={{ flexDirection: "row", alignItems: "center", gap: 3, flex: 1, minWidth: 0 }}>
        {mine ? <Ticks status={c.last.status} /> : null}
        <Text style={{ flex: 1, fontSize: 14, color: WA.sub }} numberOfLines={1}>
          {previewText(c.last, c, people, me?.id, ct)}
        </Text>
      </View>
    );
  };

  const render = ({ item }) => {
    if (item.kind === "archived") {
      return (
        <Pressable onPress={() => setPane("archived")} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: 22, paddingHorizontal: 24, paddingVertical: 14, backgroundColor: pressed ? "#f5f6f6" : "#fff" })}>
          <Archive size={20} color={WA.green} />
          <Text style={{ flex: 1, fontSize: 16, color: WA.text }}>{ct("tabs.archived")}</Text>
          <Text style={{ fontSize: 12, color: WA.green }}>{archivedCount}</Text>
        </Pressable>
      );
    }
    if (item.kind === "label") return <Text style={{ paddingHorizontal: 24, paddingTop: 18, paddingBottom: 6, fontSize: 15, color: WA.greenDark }}>{item.label}</Text>;
    if (item.kind === "note") return <Text style={{ paddingHorizontal: 24, paddingVertical: 6, fontSize: 13, color: WA.sub }}>{item.label}</Text>;
    if (item.kind === "person") {
      const p = item.p;
      return (
        <Pressable onPress={() => direct(p)} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: 12, paddingLeft: 12, backgroundColor: pressed ? "#f5f6f6" : "#fff" })}>
          <View style={{ paddingVertical: 10 }}>
            <ChatAvatar person={p} size={49} online={p.online} />
          </View>
          <View style={{ flex: 1, minWidth: 0, alignSelf: "stretch", justifyContent: "center", borderBottomWidth: 1, borderBottomColor: WA.line, paddingRight: 14 }}>
            <Text style={{ fontSize: 16.5, color: WA.text }} numberOfLines={1}>{p.name}</Text>
            <Text style={{ fontSize: 14, color: WA.sub }} numberOfLines={1}>{p.about ?? p.email}</Text>
          </View>
        </Pressable>
      );
    }
    if (item.kind === "hit") {
      const h = item.h;
      const chat = chats.find((c) => c.id === h.chat_id);
      const who = h.user_id === me?.id ? ct("you") : chat?.is_group ? h.user_name : null;
      return (
        <Pressable onPress={() => open(h.chat_id, h.id)} style={({ pressed }) => ({ paddingHorizontal: 20, paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: WA.line, backgroundColor: pressed ? "#f5f6f6" : "#fff" })}>
          <View style={{ flexDirection: "row", gap: 8 }}>
            <Text style={{ flex: 1, fontSize: 16, color: WA.text }} numberOfLines={1}>{chat?.name ?? "—"}</Text>
            <Text style={{ fontSize: 12, color: WA.sub }}>{listTime(h.created_at, ct, locale)}</Text>
          </View>
          <Text style={{ fontSize: 14, color: WA.sub }} numberOfLines={2}>
            {who ? <Text style={{ color: WA.text }}>{`${who}: `}</Text> : null}
            {h.type !== "TEXT" && !h.body ? `${h.type === "IMAGE" ? "📷" : "📄"} ` : ""}
            {h.body || h.file_name || ""}
          </Text>
        </Pressable>
      );
    }
    const c = item.c;
    return (
      <Pressable onPress={() => open(c.id)} onLongPress={() => setRowMenu(c)} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: 12, paddingLeft: 12, backgroundColor: pressed ? "#f5f6f6" : "#fff" })}>
        <View style={{ paddingVertical: 11 }}>
          <ChatAvatar chat={c} size={50} online={!c.is_group && c.other?.online} />
        </View>
        <View style={{ flex: 1, minWidth: 0, alignSelf: "stretch", justifyContent: "center", borderBottomWidth: 1, borderBottomColor: WA.line, paddingRight: 14, paddingVertical: 10 }}>
          <View style={{ flexDirection: "row", alignItems: "baseline", gap: 8 }}>
            <Text style={{ flex: 1, fontSize: 16.5, color: WA.text }} numberOfLines={1}>{c.name}</Text>
            <Text style={{ fontSize: 12, color: c.unread && !c.muted ? WA.unread : WA.sub, fontFamily: c.unread && !c.muted ? fonts.medium : fonts.regular }}>{listTime(c.last?.created_at ?? c.sort_at, ct, locale)}</Text>
          </View>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 6, marginTop: 2 }}>
            {rowLine(c)}
            {c.mentioned && c.unread ? (
              <View style={{ width: 20, height: 20, borderRadius: 10, backgroundColor: WA.badge, alignItems: "center", justifyContent: "center" }}>
                <Text style={{ color: "#fff", fontSize: 12, fontFamily: fonts.bold }}>@</Text>
              </View>
            ) : null}
            {c.muted ? <BellOff size={16} color={WA.tick} /> : null}
            {c.pinned ? <Pin size={16} color={WA.tick} style={{ transform: [{ rotate: "45deg" }] }} /> : null}
            {c.unread ? (
              <View style={{ minWidth: 20, height: 20, paddingHorizontal: 6, borderRadius: 10, backgroundColor: c.muted ? WA.tick : WA.badge, alignItems: "center", justifyContent: "center" }}>
                <Text style={{ color: "#fff", fontSize: 12, fontFamily: fonts.medium }}>{c.unread}</Text>
              </View>
            ) : null}
          </View>
        </View>
      </Pressable>
    );
  };

  return (
    <SafeAreaView edges={["bottom"]} style={{ flex: 1, backgroundColor: "#fff" }}>
      <ChatHeader
        title={pane === "archived" ? ct("tabs.archived") : ct("title")}
        subtitle={me?.name}
        onBack={() => (pane === "archived" ? setPane("list") : router.canGoBack() ? router.back() : router.replace("/"))}
        right={
          pane === "list" ? (
            <>
              <HeaderButton icon={Users} label={ct("new_group")} onPress={() => router.push({ pathname: "/chat/new", params: { group: "1" } })} />
              <HeaderButton icon={MessageSquarePlus} label={ct("new_chat")} onPress={() => router.push("/chat/new")} />
            </>
          ) : null
        }
      />
      <View style={{ paddingHorizontal: 12, paddingTop: 8, paddingBottom: 6, gap: 8, backgroundColor: "#fff" }}>
        <ChatSearchBox value={query} onChange={setQuery} placeholder={ct("search")} />
        {pane === "list" ? (
          <View style={{ flexDirection: "row", gap: 8 }}>
            {["all", "unread", "groups"].map((k) => (
              <Pressable key={k} onPress={() => setFilter(k)} style={{ borderRadius: 16, paddingHorizontal: 12, paddingVertical: 4, backgroundColor: filter === k ? "#e7fce3" : WA.panel }}>
                <Text style={{ fontSize: 14, color: filter === k ? WA.greenDark : WA.icon }}>
                  {`${ct(`tabs.${k}`)}${k === "unread" && chats.some((c) => c.unread) ? ` ${chats.filter((c) => c.unread).length}` : ""}`}
                </Text>
              </Pressable>
            ))}
          </View>
        ) : null}
      </View>
      {s.chats === null ? (
        s.error ? <BudgetEmptyState kind="error" message={s.error} onRetry={loadChats} /> : <Text style={{ padding: space[6], color: WA.sub, textAlign: "center" }}>{ct("loading")}</Text>
      ) : (
        <FlatList
          data={items}
          keyExtractor={(r) => r.key}
          renderItem={render}
          keyboardShouldPersistTaps="handled"
          ListEmptyComponent={
            <View style={{ alignItems: "center", gap: 16, paddingHorizontal: 32, paddingVertical: 48 }}>
              <Text style={{ fontSize: 14, color: WA.sub, textAlign: "center" }}>{q ? ct("no_match") : ct("empty_list")}</Text>
              {!q ? (
                <Pressable onPress={() => router.push("/chat/new")} style={{ flexDirection: "row", alignItems: "center", gap: 6, borderRadius: 10, paddingHorizontal: 14, paddingVertical: 8, backgroundColor: WA.green }}>
                  <MessageSquarePlus size={16} color="#fff" />
                  <Text style={{ color: "#fff", fontFamily: fonts.semibold }}>{ct("new_chat")}</Text>
                </Pressable>
              ) : null}
            </View>
          }
        />
      )}

      {/* A chat's own settings, on a long press. */}
      <Popup open={Boolean(rowMenu)} onClose={() => setRowMenu(null)} title={rowMenu?.name} width={360} closeLabel={ct("cancel")}>
        {rowMenu ? (
          <>
            <PopupChoice label={rowMenu.pinned ? ct("unpin_chat") : ct("pin_chat")} onPress={() => { chatSetting(rowMenu, "pinned"); setRowMenu(null); }} />
            <PopupChoice label={rowMenu.muted ? ct("unmute") : ct("mute")} onPress={() => { chatSetting(rowMenu, "muted"); setRowMenu(null); }} />
            <PopupChoice label={rowMenu.archived ? ct("unarchive") : ct("archive")} onPress={() => { chatSetting(rowMenu, "archived"); setRowMenu(null); }} />
          </>
        ) : null}
      </Popup>
    </SafeAreaView>
  );
}
