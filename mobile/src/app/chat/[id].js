/**
 * One open chat, as the web's ChatConversation (WhatsApp's): the header
 * (photo, name, online / last seen / typing… / a group's people; search and
 * the chat's menu — info, search, pin, mute, archive, clear, exit), the
 * pinned message bar, the messages on the wallpaper with day pills and the
 * "N unread messages" line (older ones load on scrolling up; a quote, a pin
 * or a search hit is jumped to and lit), the typing bubble, the jump-to-
 * latest button, and the composer — emoji, attach (camera, photos,
 * documents, with a caption), reply and edit bars, @people and #records.
 * Live through the socket; the read ticks follow.
 */
import { useEffect, useMemo, useRef, useState } from "react";
import { ActivityIndicator, FlatList, Image, KeyboardAvoidingView, Pressable, TextInput, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { SafeAreaView } from "react-native-safe-area-context";
import { Camera, ChevronDown, FileText, Image as ImageIcon, MoreVertical, Paperclip, Pencil, Pin, Search, SendHorizontal, Smile, X } from "lucide-react-native";
import { Popup, PopupChoice, Text } from "../../components/ui";
import { PhotoCapture } from "../../components/machinery";
import { ChatHeader, HeaderButton } from "../../components/chat/header";
import { Bubble } from "../../components/chat/bubble";
import { ChatSearchBox, ChatAvatar, EmojiPicker, FileBadge, Flash, ImageViewer, NeedsUpdate, RECORD_ICONS, WA, Wallpaper, copyText, ct, fileUrl, pickDocuments, pickPhotos, saveFile, useFlash } from "../../components/chat/parts";
import {
  MAX_FILE,
  NAME_TONES,
  chatSetting,
  clearChat,
  closeChat,
  dayKey,
  dayLabel,
  deleteMessage,
  editMessage,
  exitGroup,
  fileSize,
  forwardMessage,
  jumpTo,
  lastSeen,
  loadOlder,
  loadNewer,
  mentionPeople,
  messageInfo,
  openChat,
  peopleOf,
  pinMessage,
  reactTo,
  send,
  sendTyping,
  startDirect,
  triggerAt,
  useChatLive,
  useRecordSearch,
} from "../../lib/chat";
import { currentLocale } from "../../lib/i18n";
import { useSession } from "../../lib/session";
import { fonts, space } from "../../theme/tokens";

export default function Conversation() {
  const { id, jump } = useLocalSearchParams();
  const chatId = String(id);
  const s = useChatLive();
  const { bearer } = useSession();
  const locale = currentLocale();
  const me = s.me;
  const chat = (s.chats ?? []).find((c) => c.id === chatId) ?? null;
  const detail = s.details[chatId];
  const thread = s.threads[chatId];
  const people = useMemo(() => peopleOf(s), [s]);
  const typingNames = Object.values(s.typing[chatId] ?? {}).map((x) => x.name);
  const list = useRef(null);
  const input = useRef(null);
  const [text, setText] = useState("");
  const [sel, setSel] = useState({ start: 0, end: 0 });
  // The caret put after a picked mention (the field's own selection otherwise).
  const [force, setForce] = useState(null);
  const [reply, setReply] = useState(null);
  const [editing, setEditing] = useState(null);
  const [emoji, setEmoji] = useState(false);
  const [attachMenu, setAttachMenu] = useState(false);
  const [camera, setCamera] = useState(false);
  const [files, setFiles] = useState(null);
  const [menu, setMenu] = useState(false);
  const [forward, setForward] = useState(null);
  const [info, setInfo] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [confirm, setConfirm] = useState(null);
  const [viewer, setViewer] = useState(null);
  const [atBottom, setAtBottom] = useState(true);
  const [highlight, setHighlight] = useState(null);
  const [picked, setPicked] = useState([]);
  const [recordType, setRecordType] = useState(null);
  const [dismissed, setDismissed] = useState(null);
  const [flash, setFlash] = useFlash();
  const firstOpen = useRef(true);
  const canWrite = chat ? !chat.left : true;

  // Opened: its messages (to the unread line), its details; a search hit's message jumped to.
  useEffect(() => {
    firstOpen.current = true;
    openChat(chatId);
    if (jump) jumpTo(chatId, String(jump));
    return () => closeChat(chatId);
  }, [chatId, jump]);

  const messages = thread?.messages ?? [];
  const firstUnread = thread?.unread_from ? messages.find((m) => m.id === thread.unread_from) : null;
  const unreadCount = firstUnread ? messages.filter((m) => m.id >= firstUnread.id && m.user_id !== me?.id && m.type !== "SYSTEM").length : 0;

  // Newest first for the inverted list; a day pill and the unread line where they fall.
  const rows = useMemo(() => {
    const out = [];
    for (let i = messages.length - 1; i >= 0; i--) {
      const m = messages[i];
      const prev = messages[i - 1];
      const newDay = !prev || dayKey(prev.created_at) !== dayKey(m.created_at);
      const first = newDay || !prev || prev.user_id !== m.user_id || prev.type === "SYSTEM" || new Date(m.created_at) - new Date(prev.created_at) > 5 * 60000 || m.id === firstUnread?.id;
      out.push({ kind: "msg", key: m.id, m, first });
      if (m.id === firstUnread?.id) out.push({ kind: "unread", key: `u-${m.id}` });
      if (newDay) out.push({ kind: "day", key: `d-${m.id}`, at: m.created_at });
    }
    return out;
  }, [messages, firstUnread?.id]);

  // First open with an unread line: start there.
  useEffect(() => {
    if (!thread?.loaded || !firstOpen.current || !rows.length) return;
    firstOpen.current = false;
    const at = rows.findIndex((r) => r.kind === "unread");
    if (at > 4) setTimeout(() => list.current?.scrollToIndex({ index: at, viewPosition: 0.5, animated: false }), 60);
  }, [thread?.loaded, rows]);

  // A jump (quote, pin, search hit): scroll to it and light it for a moment.
  useEffect(() => {
    if (!s.jump) return undefined;
    const at = rows.findIndex((r) => r.kind === "msg" && r.m.id === s.jump.id);
    if (at < 0) return undefined;
    setTimeout(() => list.current?.scrollToIndex({ index: at, viewPosition: 0.5, animated: true }), 60);
    setHighlight(s.jump.id);
    const off = setTimeout(() => setHighlight(null), 1800);
    return () => clearTimeout(off);
  }, [s.jump, rows]);

  // A new message of mine while I am up the list: back to the bottom.
  const lastId = messages[messages.length - 1]?.id;
  useEffect(() => {
    if (!lastId) return;
    if (messages[messages.length - 1]?.user_id === me?.id) list.current?.scrollToOffset({ offset: 0, animated: true });
  }, [lastId]);

  const subtitle = !chat
    ? ""
    : typingNames.length
      ? chat.is_group
        ? ct("typing_name", { name: typingNames.join(", ") })
        : ct("typing")
      : chat.is_group
        ? (detail?.members ?? []).map((p) => (p.id === me?.id ? ct("you") : p.name.split(" ")[0])).join(", ") || ct("group_hint")
        : lastSeen(detail?.other ?? chat.other, ct, locale);
  const pinned = detail?.pinned_messages?.[0];

  /* ── Mentions ── */
  const caret = sel.end;
  const found = canWrite ? triggerAt(text, caret) : null;
  const trigger = found && found.start !== dismissed ? found : null;
  const mentionList = trigger?.kind === "@" && chat && me ? mentionPeople(chat, detail, me, people, trigger.query) : [];
  const records = useRecordSearch(trigger?.kind === "#", recordType, trigger?.kind === "#" ? trigger.query : "");
  const pickItem = (it) => {
    if (!trigger) return;
    const token = trigger.kind === "@" ? `@${it.name}` : it.token;
    const next = `${text.slice(0, trigger.start)}${token} ${text.slice(trigger.end)}`;
    const at = trigger.start + token.length + 1;
    setText(next);
    setSel({ start: at, end: at });
    setForce({ start: at, end: at });
    setPicked((all) => [...all, trigger.kind === "@" ? { user: { id: it.id, name: it.name } } : { ref: { type: it.type, ref: it.ref, token: it.token } }]);
  };
  const mentionsOf = (body, extra = null) => {
    const users = new Map();
    const refs = new Map();
    for (const u of [...(extra?.users ?? []), ...picked.filter((x) => x.user).map((x) => x.user)]) if (body.includes(`@${u.name}`)) users.set(u.id, { id: u.id, name: u.name });
    for (const r of [...(extra?.refs ?? []), ...picked.filter((x) => x.ref).map((x) => x.ref)]) if (body.includes(r.token)) refs.set(`${r.type}:${r.ref}`, { type: r.type, ref: r.ref, token: r.token });
    return users.size || refs.size ? { ...(users.size ? { users: [...users.values()] } : {}), ...(refs.size ? { refs: [...refs.values()] } : {}) } : null;
  };

  /* ── Doing ── */
  const typed = (v) => {
    setText(v);
    if (v) sendTyping(chatId);
  };

  const sendNow = async () => {
    const body = text.trim();
    if (editing) {
      if (body) editMessage(editing, body, mentionsOf(body, editing.meta?.mentions)).catch((e) => setFlash(e.message));
      setEditing(null);
      setText("");
      setPicked([]);
      return;
    }
    if (!body) return;
    const r = reply;
    const mentions = mentionsOf(body);
    setText("");
    setPicked([]);
    setReply(null);
    setEmoji(false);
    send(chatId, { body, reply: r, mentions }).catch((e) => setFlash(`${ct("send_failed")} — ${e.message}`));
  };

  const pick = (list) => {
    const ok = list.filter((f) => !f.size || f.size <= MAX_FILE);
    if (ok.length < list.length) setFlash(ct("file_too_big"));
    if (ok.length) setFiles(ok.slice(0, 10));
  };
  const attach = async (kind) => {
    setAttachMenu(false);
    if (kind === "camera") return setCamera(true);
    try {
      pick(kind === "photo" ? await pickPhotos() : await pickDocuments());
    } catch (e) {
      setFlash(e instanceof NeedsUpdate ? ct("needs_update") : e.message);
    }
    return null;
  };

  const download = async (m) => {
    try {
      const name = await saveFile(fileUrl(m.id, true), m.file?.name ?? "file", m.file?.mime, bearer());
      if (name) setFlash(ct("saved", { name }));
    } catch (e) {
      setFlash(e.message);
    }
  };

  const onAction = async (kind, m, extra) => {
    try {
      if (kind === "reply") {
        setReply(m);
        setEditing(null);
        input.current?.focus();
      } else if (kind === "edit") {
        setEditing(m);
        setReply(null);
        setText(m.body ?? "");
        input.current?.focus();
      } else if (kind === "mention-user") {
        const row = await startDirect(extra);
        router.replace({ pathname: "/chat/[id]", params: { id: row.id } });
      } else if (kind === "copy") {
        setFlash((await copyText(m.body ?? "")) === "copied" ? ct("copied") : null);
      } else if (kind === "react") await reactTo(m, extra);
      else if (kind === "pin") await pinMessage(m);
      else if (kind === "forward") setForward(m);
      else if (kind === "info") {
        setInfo({ data: null });
        setInfo({ data: await messageInfo(m) });
      } else if (kind === "delete") setDeleting(m);
      else if (kind === "download") await download(m);
    } catch (e) {
      setFlash(e.message);
    }
  };

  const chatMenu = async (kind) => {
    setMenu(false);
    if (!chat) return;
    try {
      if (kind === "info") router.push({ pathname: "/chat/info", params: { id: chatId } });
      else if (kind === "search") router.push({ pathname: "/chat/find", params: { id: chatId } });
      else if (kind === "pin") await chatSetting(chat, "pinned");
      else if (kind === "mute") await chatSetting(chat, "muted");
      else if (kind === "archive") {
        await chatSetting(chat, "archived");
        if (!chat.archived) router.back();
      } else setConfirm(kind);
    } catch (e) {
      setFlash(e.message);
    }
  };

  const confirmed = async () => {
    const kind = confirm;
    setConfirm(null);
    try {
      if (kind === "clear") await clearChat(chat);
      else if (kind === "exit") await exitGroup(chat);
    } catch (e) {
      setFlash(e.message);
    }
  };

  const renderRow = ({ item }) => {
    if (item.kind === "day") {
      return (
        <View style={{ alignItems: "center", marginVertical: 8 }}>
          <View style={{ backgroundColor: "#ffffff", borderRadius: 8, paddingHorizontal: 12, paddingVertical: 5, elevation: 1 }}>
            <Text style={{ fontSize: 12, color: WA.icon, textTransform: "uppercase" }}>{dayLabel(item.at, ct, locale)}</Text>
          </View>
        </View>
      );
    }
    if (item.kind === "unread") {
      return (
        <View style={{ alignItems: "center", marginVertical: 8, paddingVertical: 4, backgroundColor: "rgba(255,255,255,0.4)" }}>
          <View style={{ backgroundColor: "#ffffff", borderRadius: 14, paddingHorizontal: 16, paddingVertical: 4 }}>
            <Text style={{ fontSize: 12.5, color: WA.icon }}>{ct("unread_line", { n: unreadCount })}</Text>
          </View>
        </View>
      );
    }
    return <Bubble m={item.m} mine={item.m.user_id === me?.id} group={chat?.is_group} first={item.first} people={people} meId={me?.id} highlight={highlight === item.m.id} onAction={onAction} onJump={(mid) => jumpTo(chatId, mid)} onOpenImage={(m) => setViewer({ id: m.id, name: m.file?.name, mime: m.file?.mime })} />;
  };

  return (
    <SafeAreaView edges={["bottom"]} style={{ flex: 1, backgroundColor: WA.panel }}>
      <ChatHeader
        chat={chat}
        title={chat?.name ?? ct("title")}
        subtitle={subtitle}
        typing={typingNames.length > 0}
        onBack={() => (router.canGoBack() ? router.back() : router.replace("/chat"))}
        onTitle={() => router.push({ pathname: "/chat/info", params: { id: chatId } })}
        right={
          <>
            <HeaderButton icon={Search} label={ct("search_messages")} onPress={() => router.push({ pathname: "/chat/find", params: { id: chatId } })} />
            <HeaderButton icon={MoreVertical} label={ct("menu")} onPress={() => setMenu(true)} />
          </>
        }
      />
      <KeyboardAvoidingView behavior="padding" style={{ flex: 1 }}>
        {pinned ? (
          <Pressable onPress={() => jumpTo(chatId, pinned.id)} style={{ flexDirection: "row", alignItems: "center", gap: 12, paddingHorizontal: 16, paddingVertical: 8, backgroundColor: "#ffffff", borderBottomWidth: 1, borderBottomColor: WA.line }}>
            <Pin size={16} color={WA.icon} style={{ transform: [{ rotate: "45deg" }] }} />
            <View style={{ flex: 1, minWidth: 0 }}>
              <Text style={{ fontSize: 12, color: WA.greenDark }}>{`${ct("pinned")}${detail.pinned_messages.length > 1 ? ` · ${detail.pinned_messages.length}` : ""}`}</Text>
              <Text style={{ fontSize: 13.5, color: WA.text }} numberOfLines={1}>
                {pinned.body || (pinned.file?.image ? `📷 ${ct("photo_msg")}` : `📄 ${pinned.file?.name ?? ""}`)}
              </Text>
            </View>
          </Pressable>
        ) : null}
        {s.socket === "unavailable" || s.socket === "failed" ? (
          <View style={{ paddingHorizontal: 16, paddingVertical: 6, backgroundColor: "#fff8e1" }}>
            <Text style={{ fontSize: 12.5, color: "#8a6d00" }}>{ct("offline")}</Text>
          </View>
        ) : null}

        <View style={{ flex: 1 }}>
          <Wallpaper />
          {!thread?.loaded ? (
            <ActivityIndicator color={WA.tick} style={{ marginTop: space[10] }} />
          ) : (
            <FlatList
              ref={list}
              inverted
              data={rows}
              keyExtractor={(r) => r.key}
              renderItem={renderRow}
              contentContainerStyle={{ paddingVertical: 10 }}
              onEndReached={() => loadOlder(chatId)}
              onEndReachedThreshold={0.4}
              onScroll={(e) => {
                const bottom = e.nativeEvent.contentOffset.y < 80;
                if (bottom !== atBottom) setAtBottom(bottom);
                if (bottom && thread?.has_newer) loadNewer(chatId);
              }}
              scrollEventThrottle={64}
              onScrollToIndexFailed={(e) => list.current?.scrollToOffset({ offset: e.averageItemLength * e.index, animated: false })}
              ListHeaderComponent={
                typingNames.length ? (
                  <View style={{ flexDirection: "row", paddingHorizontal: "3%", marginTop: 8 }}>
                    <View style={{ flexDirection: "row", gap: 4, backgroundColor: "#ffffff", borderRadius: 8, borderTopLeftRadius: 0, paddingHorizontal: 12, paddingVertical: 11 }}>
                      {[0, 1, 2].map((i) => (
                        <View key={i} style={{ width: 7, height: 7, borderRadius: 4, backgroundColor: WA.tick, opacity: 0.5 + i * 0.2 }} />
                      ))}
                    </View>
                  </View>
                ) : null
              }
              ListFooterComponent={thread?.loading && thread?.has_older ? <ActivityIndicator color={WA.tick} style={{ marginVertical: 10 }} /> : null}
              keyboardShouldPersistTaps="handled"
            />
          )}
          {!atBottom ? (
            <Pressable accessibilityLabel={ct("jump_latest")} onPress={() => (thread?.has_newer ? jumpTo(chatId, null) : list.current?.scrollToOffset({ offset: 0, animated: true }))} style={{ position: "absolute", right: 16, bottom: 16, width: 44, height: 44, borderRadius: 22, backgroundColor: "#ffffff", alignItems: "center", justifyContent: "center", elevation: 4 }}>
              <ChevronDown size={24} color={WA.icon} />
            </Pressable>
          ) : null}
          <Flash msg={flash} />
        </View>

        {/* The mention picker over the composer: people for @, records (with kinds) for #. */}
        {trigger ? (
          <View style={{ maxHeight: 260, backgroundColor: "#ffffff", borderTopWidth: 1, borderTopColor: WA.line }}>
            <View style={{ flexDirection: "row", alignItems: "center", flexWrap: "wrap", gap: 6, paddingHorizontal: 12, paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: WA.line }}>
              <Text style={{ fontSize: 12.5, color: WA.greenDark, fontFamily: fonts.medium }}>{trigger.kind === "@" ? ct("mention_people") : ct("records_title")}</Text>
              {trigger.kind === "#" && records.types?.length
                ? ["all", ...records.types].map((k) => (
                    <Pressable key={k} onPress={() => setRecordType(k)} style={{ borderRadius: 12, paddingHorizontal: 10, paddingVertical: 2, backgroundColor: (recordType ?? records.type ?? "all") === k ? "#e7fce3" : WA.panel }}>
                      <Text style={{ fontSize: 12, color: (recordType ?? records.type ?? "all") === k ? WA.greenDark : WA.icon }}>{ct(`rec.${k}`)}</Text>
                    </Pressable>
                  ))
                : null}
              <Pressable onPress={() => setDismissed(trigger.start)} hitSlop={8} style={{ marginLeft: "auto" }}>
                <X size={16} color={WA.icon} />
              </Pressable>
            </View>
            <FlatList
              keyboardShouldPersistTaps="always"
              data={trigger.kind === "@" ? mentionList : records.results}
              keyExtractor={(it, i) => (trigger.kind === "@" ? it.id : `${it.type}:${it.ref}:${i}`)}
              ListEmptyComponent={<Text style={{ padding: 12, fontSize: 13, color: WA.sub }}>{trigger.kind === "#" && records.loading ? ct("searching") : trigger.kind === "@" ? ct("no_people") : ct("no_records")}</Text>}
              renderItem={({ item: it }) => {
                const Icon = RECORD_ICONS[it.type];
                return (
                  <Pressable onPress={() => pickItem(it)} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: 12, paddingHorizontal: 12, paddingVertical: 8, backgroundColor: pressed ? WA.panel : "#ffffff" })}>
                    {trigger.kind === "@" ? (
                      <ChatAvatar person={it} size={32} />
                    ) : (
                      <View style={{ width: 32, height: 32, borderRadius: 16, backgroundColor: "#e7fce3", alignItems: "center", justifyContent: "center" }}>{Icon ? <Icon size={16} color={WA.greenDark} /> : null}</View>
                    )}
                    <View style={{ flex: 1, minWidth: 0 }}>
                      <Text style={{ fontSize: 14, color: WA.text }} numberOfLines={1}>
                        {trigger.kind === "@" ? it.name : `${it.title}  ·  ${ct(`rec.${it.type}`)}`}
                      </Text>
                      <Text style={{ fontSize: 12, color: WA.sub }} numberOfLines={1}>
                        {trigger.kind === "@" ? (it.about ?? "") : it.sub}
                      </Text>
                    </View>
                  </Pressable>
                );
              }}
            />
          </View>
        ) : null}

        {canWrite ? (
          <View style={{ backgroundColor: WA.panel }}>
            {reply || editing ? (
              <View style={{ flexDirection: "row", alignItems: "center", gap: 8, paddingHorizontal: 12, paddingTop: 8 }}>
                <View style={{ flex: 1, minWidth: 0, borderLeftWidth: 4, borderLeftColor: editing ? WA.green : NAME_TONES[(people[reply?.user_id]?.tone ?? 5) % NAME_TONES.length], borderRadius: 8, backgroundColor: "rgba(255,255,255,0.85)", paddingHorizontal: 10, paddingVertical: 6 }}>
                  <View style={{ flexDirection: "row", alignItems: "center", gap: 4 }}>
                    {editing ? <Pencil size={13} color={WA.green} /> : null}
                    <Text style={{ fontSize: 12.5, fontFamily: fonts.semibold, color: editing ? WA.green : NAME_TONES[(people[reply?.user_id]?.tone ?? 5) % NAME_TONES.length] }} numberOfLines={1}>
                      {editing ? ct("editing") : reply.user_id === me?.id ? ct("you") : (people[reply.user_id]?.name ?? reply.user_name)}
                    </Text>
                  </View>
                  <Text style={{ fontSize: 13, color: WA.sub }} numberOfLines={1}>
                    {(editing ?? reply).body || (reply?.file?.image ? `📷 ${ct("photo_msg")}` : `📄 ${reply?.file?.name ?? ""}`)}
                  </Text>
                </View>
                <Pressable accessibilityLabel={ct("cancel")} onPress={() => { setReply(null); if (editing) { setEditing(null); setText(""); } }} hitSlop={8} style={{ padding: 4 }}>
                  <X size={20} color={WA.icon} />
                </Pressable>
              </View>
            ) : null}
            <View style={{ flexDirection: "row", alignItems: "flex-end", gap: 2, paddingHorizontal: 6, paddingVertical: 6 }}>
              <Pressable accessibilityLabel={ct("emoji")} onPress={() => setEmoji((x) => !x)} style={{ padding: 9 }}>
                <Smile size={24} color={emoji ? WA.green : WA.icon} />
              </Pressable>
              {!editing ? (
                <Pressable accessibilityLabel={ct("attach")} onPress={() => setAttachMenu(true)} style={{ padding: 9 }}>
                  <Paperclip size={22} color={WA.icon} style={{ transform: [{ rotate: "-45deg" }] }} />
                </Pressable>
              ) : null}
              <TextInput
                ref={input}
                value={text}
                onChangeText={typed}
                onSelectionChange={(e) => {
                  setSel(e.nativeEvent.selection);
                  if (force) setForce(null);
                }}
                selection={force ?? undefined}
                onFocus={() => setEmoji(false)}
                multiline
                placeholder={ct("composer")}
                placeholderTextColor={WA.tick}
                style={{ flex: 1, maxHeight: 128, minHeight: 42, paddingHorizontal: 12, paddingTop: 10, paddingBottom: 10, borderRadius: 10, backgroundColor: "#ffffff", color: WA.text, fontFamily: fonts.regular, fontSize: 15 }}
              />
              <Pressable accessibilityLabel={ct("send")} onPress={sendNow} disabled={!text.trim()} style={{ padding: 9 }}>
                <SendHorizontal size={24} color={text.trim() ? WA.icon : "rgba(134,150,160,0.6)"} />
              </Pressable>
            </View>
            {emoji ? <EmojiPicker height={240} onPick={(e) => setText((x) => x + e)} /> : null}
          </View>
        ) : (
          <View style={{ padding: 16, backgroundColor: WA.panel }}>
            <Text style={{ textAlign: "center", fontSize: 14, color: WA.sub }}>{ct("left_note")}</Text>
          </View>
        )}
      </KeyboardAvoidingView>

      {/* Attach: camera, photos, documents. */}
      <Popup open={attachMenu} onClose={() => setAttachMenu(false)} title={ct("attach")} icon={Paperclip} width={380} closeLabel={ct("cancel")}>
        <PopupChoice icon={Camera} tone="#e8710a" label={ct("camera")} onPress={() => attach("camera")} />
        <PopupChoice icon={ImageIcon} tone="#007bfc" label={ct("photo")} onPress={() => attach("photo")} />
        <PopupChoice icon={FileText} tone="#7f66ff" label={ct("document")} onPress={() => attach("document")} />
      </Popup>
      <PhotoCapture
        open={camera}
        onClose={() => setCamera(false)}
        onPhoto={(shot) => {
          setCamera(false);
          pick([{ uri: shot.uri, name: `photo-${Date.now()}.jpg`, mime: "image/jpeg", size: 0 }]);
        }}
      />
      {files ? (
        <AttachPreview
          files={files}
          onClose={() => setFiles(null)}
          onSend={(caption) => {
            const r = reply;
            files.forEach((f, i) => send(chatId, { body: i === 0 ? caption : "", file: f, reply: i === 0 ? r : null }).catch((e) => setFlash(`${ct("send_failed")} — ${e.message}`)));
            setFiles(null);
            setReply(null);
          }}
        />
      ) : null}

      {/* The chat's menu. */}
      <Popup open={menu} onClose={() => setMenu(false)} title={chat?.name} width={380} closeLabel={ct("cancel")}>
        <PopupChoice label={chat?.is_group ? ct("group_info") : ct("contact_info")} onPress={() => chatMenu("info")} />
        <PopupChoice label={ct("search_messages")} onPress={() => chatMenu("search")} />
        <PopupChoice label={chat?.pinned ? ct("unpin_chat") : ct("pin_chat")} onPress={() => chatMenu("pin")} />
        <PopupChoice label={chat?.muted ? ct("unmute") : ct("mute")} onPress={() => chatMenu("mute")} />
        <PopupChoice label={chat?.archived ? ct("unarchive") : ct("archive")} onPress={() => chatMenu("archive")} />
        <PopupChoice label={ct("clear")} onPress={() => chatMenu("clear")} />
        {chat?.is_group && !chat.left ? <PopupChoice label={ct("exit_group")} onPress={() => chatMenu("exit")} /> : null}
      </Popup>

      {forward ? <ForwardPopup chats={s.chats ?? []} onClose={() => setForward(null)} onSend={async (ids) => {
        const m = forward;
        setForward(null);
        try {
          const d = await forwardMessage(m, ids);
          setFlash(ct("forwarded_ok", { n: d.chat_ids.length }));
        } catch (e) {
          setFlash(e.message);
        }
      }} /> : null}

      <Popup open={Boolean(info)} onClose={() => setInfo(null)} title={ct("info")} width={400} closeLabel={ct("cancel")}>
        {!info?.data ? <Text style={{ color: WA.sub }}>{ct("loading")}</Text> : (
          <>
            <InfoBlock title={ct("read_by")} people={info.data.read} mark="✓✓" color={WA.read} />
            <InfoBlock title={ct("delivered_to")} people={info.data.delivered} mark="✓✓" color={WA.tick} />
            <InfoBlock title={ct("waiting")} people={info.data.waiting} mark="✓" color={WA.tick} />
          </>
        )}
      </Popup>

      <Popup open={Boolean(deleting)} onClose={() => setDeleting(null)} title={ct("delete_title")} width={380} closeLabel={ct("cancel")}>
        {deleting?.user_id === me?.id ? <PopupChoice label={ct("delete_all")} onPress={() => { const m = deleting; setDeleting(null); deleteMessage(m, "everyone").catch((e) => setFlash(e.message)); }} /> : null}
        <PopupChoice label={ct("delete_me")} onPress={() => { const m = deleting; setDeleting(null); deleteMessage(m, "me").catch((e) => setFlash(e.message)); }} />
        <PopupChoice label={ct("cancel")} onPress={() => setDeleting(null)} />
      </Popup>

      <Popup
        open={Boolean(confirm)}
        onClose={() => setConfirm(null)}
        title={confirm === "exit" ? ct("exit_group") : ct("clear")}
        hint={confirm === "exit" ? ct("exit_confirm", { name: chat?.name ?? "" }) : ct("clear_confirm")}
        width={400}
        closeLabel={ct("cancel")}
        footer={
          <>
            <Pressable onPress={() => setConfirm(null)} style={{ flex: 1, alignItems: "center", paddingVertical: 10, borderRadius: 10, borderWidth: 1, borderColor: WA.line, backgroundColor: "#fff" }}>
              <Text style={{ color: WA.icon }}>{ct("cancel")}</Text>
            </Pressable>
            <Pressable onPress={confirmed} style={{ flex: 1, alignItems: "center", paddingVertical: 10, borderRadius: 10, backgroundColor: WA.danger }}>
              <Text style={{ color: "#fff", fontFamily: fonts.semibold }}>{confirm === "exit" ? ct("exit_group") : ct("clear")}</Text>
            </Pressable>
          </>
        }
      />

      <ImageViewer item={viewer} onClose={() => setViewer(null)} onDownload={(it) => download({ id: it.id, file: { name: it.name, mime: it.mime } })} />
    </SafeAreaView>
  );
}

function InfoBlock({ title, people, mark, color }) {
  if (!people?.length) return null;
  return (
    <View style={{ marginBottom: 8 }}>
      <Text style={{ fontSize: 13, color: WA.greenDark, marginBottom: 4 }}>
        <Text style={{ color }}>{mark}</Text>
        {`  ${title}`}
      </Text>
      {people.map((p) => (
        <View key={p.id} style={{ flexDirection: "row", alignItems: "center", gap: 10, paddingVertical: 5 }}>
          <ChatAvatar person={p} size={34} />
          <Text style={{ fontSize: 14.5, color: WA.text }}>{p.name}</Text>
        </View>
      ))}
    </View>
  );
}

/** What is about to be sent: the picked photos / files, one shown large, a caption, send. */
function AttachPreview({ files, onClose, onSend }) {
  const [caption, setCaption] = useState("");
  const [index, setIndex] = useState(0);
  const f = files[Math.min(index, files.length - 1)];
  const image = (f.mime ?? "").startsWith("image/");

  return (
    <Popup open onClose={onClose} title={f.name} width={520} scroll={false} closeLabel={ct("cancel")}
      footer={
        <View style={{ flex: 1, flexDirection: "row", alignItems: "center", gap: 10 }}>
          <TextInput value={caption} onChangeText={setCaption} placeholder={ct("caption")} placeholderTextColor={WA.tick} style={{ flex: 1, height: 42, borderRadius: 10, backgroundColor: "#fff", paddingHorizontal: 12, fontSize: 14, color: WA.text }} />
          <Pressable accessibilityLabel={ct("send")} onPress={() => onSend(caption)} style={{ width: 46, height: 46, borderRadius: 23, backgroundColor: WA.green, alignItems: "center", justifyContent: "center" }}>
            <SendHorizontal size={20} color="#fff" />
          </Pressable>
        </View>
      }
    >
      <View style={{ alignItems: "center", justifyContent: "center", minHeight: 220, backgroundColor: "#e9edef", borderRadius: 10, padding: 10 }}>
        {image ? (
          <Image source={{ uri: f.uri }} style={{ width: "100%", height: 260 }} resizeMode="contain" />
        ) : (
          <View style={{ alignItems: "center", gap: 10 }}>
            <FileBadge name={f.name} big />
            <Text style={{ fontSize: 14, color: WA.text }} numberOfLines={1}>{f.name}</Text>
            <Text style={{ fontSize: 12.5, color: WA.sub }}>{fileSize(f.size)}</Text>
          </View>
        )}
      </View>
      {files.length > 1 ? (
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8, justifyContent: "center" }}>
          {files.map((x, i) => (
            <Pressable key={i} onPress={() => setIndex(i)} style={{ width: 54, height: 54, borderRadius: 6, borderWidth: 2, borderColor: i === index ? WA.green : "transparent", overflow: "hidden", alignItems: "center", justifyContent: "center", backgroundColor: "#fff" }}>
              {(x.mime ?? "").startsWith("image/") ? <Image source={{ uri: x.uri }} style={{ width: "100%", height: "100%" }} /> : <FileBadge name={x.name} />}
            </Pressable>
          ))}
        </View>
      ) : null}
    </Popup>
  );
}

/** Forward a message: pick chats (search), send. */
function ForwardPopup({ chats, onClose, onSend }) {
  const [q, setQ] = useState("");
  const [picked, setPicked] = useState([]);
  const shown = chats.filter((c) => !c.left && (!q || (c.name ?? "").toLowerCase().includes(q.toLowerCase())));

  return (
    <Popup open onClose={onClose} title={ct("forward_to")} width={460} closeLabel={ct("cancel")}
      footer={
        <View style={{ flex: 1, flexDirection: "row", alignItems: "center", gap: 10 }}>
          <Text style={{ flex: 1, fontSize: 13, color: WA.sub }} numberOfLines={1}>{chats.filter((c) => picked.includes(c.id)).map((c) => c.name).join(", ") || ct("selected", { n: 0 })}</Text>
          <Pressable disabled={!picked.length} onPress={() => onSend(picked)} style={{ width: 44, height: 44, borderRadius: 22, backgroundColor: WA.green, opacity: picked.length ? 1 : 0.4, alignItems: "center", justifyContent: "center" }}>
            <SendHorizontal size={19} color="#fff" />
          </Pressable>
        </View>
      }
    >
      <ChatSearchBox value={q} onChange={setQ} placeholder={ct("search")} />
      {shown.map((c) => (
        <PopupChoice key={c.id} label={c.name} on={picked.includes(c.id)} onPress={() => setPicked((all) => (all.includes(c.id) ? all.filter((x) => x !== c.id) : [...all, c.id].slice(0, 20)))} />
      ))}
    </Popup>
  );
}
