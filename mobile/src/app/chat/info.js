/**
 * Contact / group info, as the web's InfoPanel: the photo (a group admin
 * changes it: camera or gallery), the name (renamed by an admin), the email
 * or "Group · N participants", the department badge, last seen; a group's
 * description (added / edited by an admin) and its date, a person's about;
 * the media; the pinned messages (a tap goes to it); mute and pin; a group's
 * participants — add, make / dismiss admin, remove — and clear / exit.
 */
import { useMemo, useState } from "react";
import { Image, Pressable, ScrollView, TextInput, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { SafeAreaView } from "react-native-safe-area-context";
import { Bell, BellOff, Camera, Check, ChevronDown, LogOut, Pencil, Pin, Trash2, UserPlus } from "lucide-react-native";
import { Popup, PopupChoice, Text } from "../../components/ui";
import { PhotoCapture } from "../../components/machinery";
import { ChatHeader } from "../../components/chat/header";
import { ChatAvatar, ChatSearchBox, FileBadge, Flash, ImageViewer, NeedsUpdate, WA, ct, fileUrl, pickPhotos, saveFile, useAuthSource, useFlash } from "../../components/chat/parts";
import { addMembers, chatSetting, clearChat, describeGroup, exitGroup, groupPhoto, jumpTo, lastSeen, peopleOf, removeMember, renameGroup, setRole, shortDate, useChatLive } from "../../lib/chat";
import { currentLocale } from "../../lib/i18n";
import { useSession } from "../../lib/session";
import { fonts } from "../../theme/tokens";
import { PersonRow } from "./new";

function Section({ children, style }) {
  return <View style={[{ marginTop: 10, backgroundColor: "#fff", paddingVertical: 8, elevation: 1 }, style]}>{children}</View>;
}

function Row({ icon: Icon, label, onPress, danger }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: 22, paddingHorizontal: 24, paddingVertical: 13, backgroundColor: pressed ? "#f5f6f6" : "#fff" })}>
      <Icon size={20} color={danger ? WA.danger : WA.icon} />
      <Text style={{ fontSize: 15, color: danger ? WA.danger : WA.text }}>{label}</Text>
    </Pressable>
  );
}

function MediaTile({ f, onOpen, onSave }) {
  const src = useAuthSource(f.image ? fileUrl(f.id) : null);
  return f.image ? (
    <Pressable onPress={() => onOpen(f)} style={{ width: "31.5%", aspectRatio: 1, borderRadius: 4, overflow: "hidden", backgroundColor: "#dfe5e7" }}>
      <Image source={src} style={{ width: "100%", height: "100%" }} />
    </Pressable>
  ) : (
    <Pressable onPress={() => onSave(f)} style={{ width: "31.5%", aspectRatio: 1, borderRadius: 4, backgroundColor: WA.panel, alignItems: "center", justifyContent: "center", gap: 4, padding: 4 }}>
      <FileBadge name={f.name} />
      <Text style={{ fontSize: 10.5, color: WA.icon, textAlign: "center" }} numberOfLines={2}>{f.name}</Text>
    </Pressable>
  );
}

export default function ChatInfo() {
  const { id } = useLocalSearchParams();
  const chatId = String(id);
  const s = useChatLive();
  const { bearer } = useSession();
  const locale = currentLocale();
  const chat = (s.chats ?? []).find((c) => c.id === chatId);
  const detail = s.details[chatId];
  const me = s.me;
  const people = useMemo(() => peopleOf(s), [s]);
  const [renaming, setRenaming] = useState(false);
  const [name, setName] = useState(chat?.name ?? "");
  const [describing, setDescribing] = useState(false);
  const [description, setDescription] = useState(detail?.description ?? "");
  const [adding, setAdding] = useState(false);
  const [member, setMember] = useState(null);
  const [photoMenu, setPhotoMenu] = useState(false);
  const [camera, setCamera] = useState(false);
  const [confirm, setConfirm] = useState(null);
  const [viewer, setViewer] = useState(null);
  const [flash, setFlash] = useFlash();
  if (!chat) return null;
  const admin = chat.is_group && ["OWNER", "ADMIN"].includes(chat.role) && !chat.left;
  const other = detail?.other ?? chat.other;
  const memberIds = new Set((detail?.members ?? []).map((m) => m.id));
  const run = (p) => p.catch((e) => setFlash(e.message));
  const save = async (f) => {
    try {
      const n = await saveFile(fileUrl(f.id, true), f.name, f.mime, bearer());
      if (n) setFlash(ct("saved", { name: n }));
    } catch (e) {
      setFlash(e.message);
    }
  };
  const newPhoto = async (kind) => {
    setPhotoMenu(false);
    if (kind === "camera") return setCamera(true);
    try {
      const [p] = await pickPhotos();
      if (p) run(groupPhoto(chatId, p));
    } catch (e) {
      setFlash(e instanceof NeedsUpdate ? ct("needs_update") : e.message);
    }
    return null;
  };

  return (
    <SafeAreaView edges={["bottom"]} style={{ flex: 1, backgroundColor: WA.panel }}>
      <ChatHeader title={chat.is_group ? ct("group_info") : ct("contact_info")} onBack={() => router.back()} />
      <ScrollView contentContainerStyle={{ paddingBottom: 24 }}>
        <View style={{ alignItems: "center", backgroundColor: "#fff", paddingHorizontal: 24, paddingTop: 24, paddingBottom: 18, elevation: 1 }}>
          <Pressable disabled={!admin} onPress={() => setPhotoMenu(true)}>
            <ChatAvatar chat={chat} person={chat.is_group ? null : other} size={140} />
            {admin ? (
              <View style={{ position: "absolute", right: 4, bottom: 4, width: 38, height: 38, borderRadius: 19, backgroundColor: WA.green, alignItems: "center", justifyContent: "center", borderWidth: 2, borderColor: "#fff" }}>
                <Camera size={18} color="#fff" />
              </View>
            ) : null}
          </Pressable>
          {renaming ? (
            <View style={{ marginTop: 16, flexDirection: "row", alignItems: "center", gap: 8, alignSelf: "stretch", borderBottomWidth: 2, borderBottomColor: WA.green }}>
              <TextInput autoFocus value={name} onChangeText={setName} maxLength={120} style={{ flex: 1, paddingVertical: 6, fontSize: 18, color: WA.text, fontFamily: fonts.regular }} />
              <Pressable accessibilityLabel={ct("save")} onPress={() => { if (name.trim()) run(renameGroup(chatId, name.trim())); setRenaming(false); }} hitSlop={8}>
                <Check size={20} color={WA.icon} />
              </Pressable>
            </View>
          ) : (
            <View style={{ marginTop: 14, flexDirection: "row", alignItems: "center", gap: 8 }}>
              <Text style={{ fontSize: 22, color: WA.text, textAlign: "center" }}>{chat.name}</Text>
              {admin ? (
                <Pressable accessibilityLabel={ct("rename")} onPress={() => { setName(chat.name ?? ""); setRenaming(true); }} hitSlop={8}>
                  <Pencil size={16} color={WA.icon} />
                </Pressable>
              ) : null}
            </View>
          )}
          <Text style={{ marginTop: 4, fontSize: 15, color: WA.sub }}>{chat.is_group ? `${ct("group")} · ${ct("participants", { n: detail?.members?.length ?? chat.member_count })}` : (other?.email ?? "")}</Text>
          {chat.department ? <Text style={{ marginTop: 8, fontSize: 12.5, color: WA.greenDark, backgroundColor: "#e7fce3", borderRadius: 12, paddingHorizontal: 12, paddingVertical: 4, textAlign: "center" }}>{ct("dept_badge")}</Text> : null}
          {!chat.is_group && other ? <Text style={{ marginTop: 2, fontSize: 13.5, color: WA.sub }}>{lastSeen(other, ct, locale)}</Text> : null}
        </View>

        {chat.is_group ? (
          <Section style={{ paddingHorizontal: 24, paddingVertical: 14 }}>
            {describing ? (
              <View style={{ flexDirection: "row", alignItems: "flex-start", gap: 8, borderBottomWidth: 2, borderBottomColor: WA.green }}>
                <TextInput autoFocus multiline value={description} onChangeText={setDescription} maxLength={500} style={{ flex: 1, minHeight: 60, fontSize: 14.5, color: WA.text, fontFamily: fonts.regular, textAlignVertical: "top" }} />
                <Pressable accessibilityLabel={ct("save")} onPress={() => { run(describeGroup(chatId, description)); setDescribing(false); }} hitSlop={8}>
                  <Check size={20} color={WA.icon} />
                </Pressable>
              </View>
            ) : detail?.description ? (
              <View style={{ flexDirection: "row", alignItems: "flex-start", gap: 8 }}>
                <Text style={{ flex: 1, fontSize: 14.5, color: WA.text }}>{detail.description}</Text>
                {admin ? (
                  <Pressable accessibilityLabel={ct("description")} onPress={() => { setDescription(detail.description ?? ""); setDescribing(true); }} hitSlop={8}>
                    <Pencil size={16} color={WA.icon} />
                  </Pressable>
                ) : null}
              </View>
            ) : admin ? (
              <Pressable onPress={() => setDescribing(true)}>
                <Text style={{ fontSize: 14.5, color: WA.greenDark }}>{ct("add_description")}</Text>
              </Pressable>
            ) : null}
            {detail?.created_at ? <Text style={{ marginTop: 6, fontSize: 13, color: WA.sub }}>{ct("created_on", { date: shortDate(detail.created_at, locale) })}</Text> : null}
          </Section>
        ) : other?.about ? (
          <Section style={{ paddingHorizontal: 24, paddingVertical: 14 }}>
            <Text style={{ fontSize: 14, color: WA.sub }}>{ct("about")}</Text>
            <Text style={{ marginTop: 4, fontSize: 15, color: WA.text }}>{other.about}</Text>
          </Section>
        ) : null}

        <Section style={{ paddingHorizontal: 24, paddingVertical: 14 }}>
          <Text style={{ marginBottom: 10, fontSize: 14, color: WA.sub }}>{ct("media")}</Text>
          {detail?.media?.length ? (
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
              {detail.media.slice(0, 12).map((f) => (
                <MediaTile key={f.id} f={f} onOpen={(x) => setViewer({ id: x.id, name: x.name, mime: x.mime })} onSave={save} />
              ))}
            </View>
          ) : (
            <Text style={{ fontSize: 13.5, color: WA.tick }}>{ct("no_media")}</Text>
          )}
        </Section>

        {detail?.pinned_messages?.length ? (
          <Section>
            {detail.pinned_messages.map((m) => (
              <Pressable key={m.id} onPress={() => { jumpTo(chatId, m.id); router.back(); }} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: 12, paddingHorizontal: 24, paddingVertical: 11, backgroundColor: pressed ? "#f5f6f6" : "#fff" })}>
                <Pin size={16} color={WA.icon} style={{ transform: [{ rotate: "45deg" }] }} />
                <Text style={{ flex: 1, fontSize: 14.5, color: WA.text }} numberOfLines={1}>{m.body || m.file?.name}</Text>
              </Pressable>
            ))}
          </Section>
        ) : null}

        <Section>
          <Row icon={chat.muted ? BellOff : Bell} label={chat.muted ? ct("unmute") : ct("mute")} onPress={() => run(chatSetting(chat, "muted"))} />
          <Row icon={Pin} label={chat.pinned ? ct("unpin_chat") : ct("pin_chat")} onPress={() => run(chatSetting(chat, "pinned"))} />
        </Section>

        {chat.is_group ? (
          <Section>
            <Text style={{ paddingHorizontal: 24, paddingVertical: 8, fontSize: 14, color: WA.sub }}>{ct("participants", { n: detail?.members?.length ?? chat.member_count })}</Text>
            {admin ? (
              <Pressable onPress={() => setAdding(true)} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: 16, paddingHorizontal: 18, paddingVertical: 10, backgroundColor: pressed ? "#f5f6f6" : "#fff" })}>
                <View style={{ width: 46, height: 46, borderRadius: 23, backgroundColor: WA.green, alignItems: "center", justifyContent: "center" }}>
                  <UserPlus size={20} color="#fff" />
                </View>
                <Text style={{ fontSize: 16, color: WA.text }}>{ct("add_participants")}</Text>
              </Pressable>
            ) : null}
            {(detail?.members ?? []).map((p) => (
              <Pressable key={p.id} disabled={!admin || p.id === me?.id || p.role === "OWNER"} onPress={() => setMember(p)} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: 16, paddingLeft: 18, backgroundColor: pressed ? "#f5f6f6" : "#fff" })}>
                <View style={{ paddingVertical: 10 }}>
                  <ChatAvatar person={p} size={46} online={p.online && p.id !== me?.id} />
                </View>
                <View style={{ flex: 1, minWidth: 0, flexDirection: "row", alignItems: "center", gap: 8, alignSelf: "stretch", borderBottomWidth: 1, borderBottomColor: WA.line, paddingRight: 14 }}>
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <Text style={{ fontSize: 16, color: WA.text }} numberOfLines={1}>{p.id === me?.id ? ct("you") : p.name}</Text>
                    <Text style={{ fontSize: 13.5, color: WA.sub }} numberOfLines={1}>{p.about ?? ""}</Text>
                  </View>
                  {p.role !== "MEMBER" ? <Text style={{ fontSize: 11.5, color: WA.greenDark, backgroundColor: "#e7fce3", borderRadius: 4, paddingHorizontal: 6, paddingVertical: 2 }}>{p.role === "OWNER" ? ct("owner") : ct("admin")}</Text> : null}
                  {admin && p.id !== me?.id && p.role !== "OWNER" ? <ChevronDown size={18} color={WA.tick} /> : null}
                </View>
              </Pressable>
            ))}
          </Section>
        ) : null}

        <Section>
          <Row icon={Trash2} label={ct("clear")} danger onPress={() => setConfirm("clear")} />
          {chat.is_group && !chat.left ? <Row icon={LogOut} label={ct("exit_group")} danger onPress={() => setConfirm("exit")} /> : null}
        </Section>
      </ScrollView>

      <Popup open={Boolean(member)} onClose={() => setMember(null)} title={member?.name} width={360} closeLabel={ct("cancel")}>
        {member ? (
          <>
            <PopupChoice label={member.role === "ADMIN" ? ct("dismiss_admin") : ct("make_admin")} onPress={() => { run(setRole(chatId, member.id, member.role === "ADMIN" ? "MEMBER" : "ADMIN")); setMember(null); }} />
            <PopupChoice label={ct("remove")} onPress={() => { run(removeMember(chatId, member.id)); setMember(null); }} />
          </>
        ) : null}
      </Popup>

      <Popup open={photoMenu} onClose={() => setPhotoMenu(false)} title={ct("change_photo")} icon={Camera} width={360} closeLabel={ct("cancel")}>
        <PopupChoice label={ct("camera")} onPress={() => newPhoto("camera")} />
        <PopupChoice label={ct("photo")} onPress={() => newPhoto("photo")} />
      </Popup>
      <PhotoCapture open={camera} onClose={() => setCamera(false)} onPhoto={(shot) => { setCamera(false); run(groupPhoto(chatId, { uri: shot.uri, name: `group-${Date.now()}.jpg`, mime: "image/jpeg" })); }} />

      {adding ? <PeoplePicker contacts={(s.contacts ?? []).filter((p) => !memberIds.has(p.id))} onClose={() => setAdding(false)} onDone={(ids) => { run(addMembers(chatId, ids)); setAdding(false); }} /> : null}

      <Popup
        open={Boolean(confirm)}
        onClose={() => setConfirm(null)}
        title={confirm === "exit" ? ct("exit_group") : ct("clear")}
        hint={confirm === "exit" ? ct("exit_confirm", { name: chat.name ?? "" }) : ct("clear_confirm")}
        width={400}
        closeLabel={ct("cancel")}
        footer={
          <>
            <Pressable onPress={() => setConfirm(null)} style={{ flex: 1, alignItems: "center", paddingVertical: 10, borderRadius: 10, borderWidth: 1, borderColor: WA.line, backgroundColor: "#fff" }}>
              <Text style={{ color: WA.icon }}>{ct("cancel")}</Text>
            </Pressable>
            <Pressable onPress={() => { const k = confirm; setConfirm(null); run(k === "exit" ? exitGroup(chat) : clearChat(chat)); }} style={{ flex: 1, alignItems: "center", paddingVertical: 10, borderRadius: 10, backgroundColor: WA.danger }}>
              <Text style={{ color: "#fff", fontFamily: fonts.semibold }}>{confirm === "exit" ? ct("exit_group") : ct("clear")}</Text>
            </Pressable>
          </>
        }
      />
      <ImageViewer item={viewer} onClose={() => setViewer(null)} onDownload={(it) => save({ id: it.id, name: it.name, mime: it.mime })} />
      <Flash msg={flash} />
    </SafeAreaView>
  );
}

/** Pick people (adding to a group). */
function PeoplePicker({ contacts, onClose, onDone }) {
  const [q, setQ] = useState("");
  const [picked, setPicked] = useState([]);
  const shown = contacts.filter((p) => !q || `${p.name} ${p.about ?? ""}`.toLowerCase().includes(q.toLowerCase()));

  return (
    <Popup open onClose={onClose} title={ct("add_participants")} icon={UserPlus} width={460} closeLabel={ct("cancel")}
      footer={
        <View style={{ flex: 1, flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
          <Text style={{ fontSize: 13.5, color: WA.sub }}>{ct("selected", { n: picked.length })}</Text>
          <Pressable disabled={!picked.length} onPress={() => onDone(picked)} style={{ width: 44, height: 44, borderRadius: 22, backgroundColor: WA.green, opacity: picked.length ? 1 : 0.4, alignItems: "center", justifyContent: "center" }}>
            <Check size={20} color="#fff" />
          </Pressable>
        </View>
      }
    >
      <ChatSearchBox value={q} onChange={setQ} placeholder={ct("search_people")} />
      <View style={{ marginHorizontal: -16 }}>
        {shown.map((p) => (
          <PersonRow key={p.id} person={p} selected={picked.includes(p.id)} onPress={() => setPicked((all) => (all.includes(p.id) ? all.filter((x) => x !== p.id) : [...all, p.id]))} />
        ))}
      </View>
      {!shown.length ? <Text style={{ fontSize: 14, color: WA.sub }}>{ct("no_people")}</Text> : null}
    </Popup>
  );
}
