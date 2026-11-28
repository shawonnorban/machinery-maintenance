/**
 * Chat's small pieces on the phone, as the web draws them (WhatsApp's look):
 * the avatar (the person's HR photo or the group's, else initials on their
 * tone, an online dot), the ticks, the wallpaper, the emoji picker, a
 * message's text with its @people and #records and links, the record cards,
 * a menu in the app's popup, the photo viewer — and the phone's own doings:
 * take or pick a photo, pick a document, copy, save a file.
 */
import { memo, useEffect, useState } from "react";
import { Image, Linking, Modal, Pressable, ScrollView, Share, TextInput, View, useWindowDimensions } from "react-native";
import { SvgXml } from "react-native-svg";
import Storage from "expo-sqlite/kv-store";
import { requireOptionalNativeModule } from "expo-modules-core";
import { Check, CheckCheck, ClipboardList, Clock, Download, ExternalLink, FileSpreadsheet, FlaskConical, Package, Search, Ship, Users, X } from "lucide-react-native";
import { Text } from "../ui";
import { TONES, fileKind, useCards } from "../../lib/chat";
import { apiUrl, currentServer } from "../../lib/server";
import { useSession } from "../../lib/session";
import { t } from "../../lib/i18n";
import { fonts, space } from "../../theme/tokens";
import { useColors } from "../../theme/theme";
import { EMOJI_GROUPS } from "./emoji-data";
import { WALLPAPER_BG, WALLPAPER_XML } from "./wallpaper";

export const ct = (key, params) => t("chat", key, params);
/** The web chat's colours (WhatsApp's). */
export const WA = { green: "#00a884", greenDark: "#008069", mine: "#d9fdd3", mineQuote: "#d1f4cc", panel: "#f0f2f5", line: "#e9edef", text: "#111b21", sub: "#667781", icon: "#54656f", tick: "#8696a0", read: "#53bdeb", link: "#027eb5", danger: "#ea0038", badge: "#25d366", unread: "#1fa855" };

/** An address on the server with the person's token, for an Image. */
export function useAuthSource(path) {
  const { bearer } = useSession();
  if (!path) return null;
  const server = currentServer();
  return { uri: `${apiUrl(server)}${path}`, headers: { Authorization: `Bearer ${bearer()}` } };
}

export const fileUrl = (messageId, download = false) => `/chat/messages/${messageId}/file${download ? "?download=1" : ""}`;

/** A person's or a group's picture: the photo when there is one, else initials on their tone; the online dot. */
export const ChatAvatar = memo(function ChatAvatar({ person = null, chat = null, size = 40, online = false }) {
  const group = chat?.is_group;
  const who = person ?? (group ? null : chat?.other) ?? null;
  const tone = TONES[((who?.tone ?? chat?.tone) ?? 0) % TONES.length];
  const photoPath = group ? (chat?.image ? `/chat/chats/${chat.id}/image${chat.image_v ? `?v=${chat.image_v}` : ""}` : null) : who?.photo ? `/chat/avatars/${who.id}` : null;
  const src = useAuthSource(photoPath);
  const [failed, setFailed] = useState(false);
  const initials = who?.initials ?? chat?.initials ?? "?";

  return (
    <View style={{ width: size, height: size }}>
      {src && !failed ? (
        <Image source={src} onError={() => setFailed(true)} style={{ width: size, height: size, borderRadius: size / 2, backgroundColor: "#dfe5e7" }} />
      ) : (
        <View style={{ width: size, height: size, borderRadius: size / 2, backgroundColor: group && !chat?.tone ? "#dfe5e7" : tone, alignItems: "center", justifyContent: "center" }}>
          {group ? <Users size={size * 0.48} color="#ffffff" /> : <Text style={{ color: "#ffffff", fontFamily: fonts.semibold, fontSize: size * 0.38, lineHeight: size * 0.5 }}>{initials}</Text>}
        </View>
      )}
      {online ? <View style={{ position: "absolute", right: 0, bottom: 0, width: Math.max(10, size * 0.24), height: Math.max(10, size * 0.24), borderRadius: size, backgroundColor: WA.badge, borderWidth: 2, borderColor: "#ffffff" }} /> : null}
    </View>
  );
});

/** One's own message's ticks: a clock while sending, ✓ sent, ✓✓ delivered, blue ✓✓ read. */
export function Ticks({ status, color }) {
  if (status === "pending") return <Clock size={13} color={color ?? WA.tick} />;
  if (status === "failed") return <Text style={{ fontSize: 11, fontFamily: fonts.bold, color: "#dc2626" }}>!</Text>;
  if (status === "read") return <CheckCheck size={15} color={WA.read} />;
  if (status === "delivered") return <CheckCheck size={15} color={color ?? WA.tick} />;
  if (status === "sent") return <Check size={15} color={color ?? WA.tick} />;
  return null;
}

/** The chat's wallpaper: the web's doodle tiled over beige, behind the messages. */
export function Wallpaper() {
  const { width, height } = useWindowDimensions();
  const tile = 360;
  const cols = Math.ceil(width / tile);
  const rows = Math.ceil(height / tile);
  const tiles = [];
  for (let r = 0; r < rows; r++) for (let c = 0; c < cols; c++) tiles.push(<SvgXml key={`${r}-${c}`} xml={WALLPAPER_XML} width={tile} height={tile} style={{ position: "absolute", left: c * tile, top: r * tile }} />);

  return (
    <View pointerEvents="none" style={{ position: "absolute", top: 0, left: 0, right: 0, bottom: 0, backgroundColor: WALLPAPER_BG, overflow: "hidden" }}>
      {tiles}
    </View>
  );
}

/* ── Emoji ── */

const RECENT = "chat.emoji.recent";
const readRecent = () => {
  try {
    return JSON.parse(Storage.getItemSync(RECENT) ?? "[]").slice(0, 32);
  } catch {
    return [];
  }
};
export function rememberEmoji(e) {
  try {
    Storage.setItemSync(RECENT, JSON.stringify([e, ...readRecent().filter((x) => x !== e)].slice(0, 32)));
  } catch {
    /* recent only where storage works */
  }
}

/** The emoji picker: category icons on top (the open one underlined in green), the grid below. */
export function EmojiPicker({ onPick, height = 260 }) {
  const [recent] = useState(readRecent);
  const groups = recent.length ? [{ key: "recent", icon: "🕘", list: recent }, ...EMOJI_GROUPS] : EMOJI_GROUPS;
  const [open, setOpen] = useState(groups[0].key);
  const group = groups.find((g) => g.key === open) ?? groups[0];

  return (
    <View style={{ backgroundColor: WA.panel, borderTopWidth: 1, borderTopColor: WA.line }}>
      <View style={{ flexDirection: "row", borderBottomWidth: 1, borderBottomColor: WA.line }}>
        {groups.map((g) => (
          <Pressable key={g.key} accessibilityLabel={ct(`emoji_groups.${g.key}`)} onPress={() => setOpen(g.key)} style={{ flex: 1, alignItems: "center", paddingVertical: 8, borderBottomWidth: 3, borderBottomColor: open === g.key ? WA.green : "transparent", opacity: open === g.key ? 1 : 0.55 }}>
            <Text style={{ fontSize: 19 }}>{g.icon}</Text>
          </Pressable>
        ))}
      </View>
      <Text style={{ fontSize: 12, color: WA.sub, paddingHorizontal: space[3], paddingTop: 6 }}>{ct(`emoji_groups.${group.key}`)}</Text>
      <ScrollView style={{ height }} contentContainerStyle={{ flexDirection: "row", flexWrap: "wrap", paddingHorizontal: 6, paddingBottom: 8 }} keyboardShouldPersistTaps="always">
        {group.list.map((e, i) => (
          <Pressable
            key={`${e}-${i}`}
            onPress={() => {
              rememberEmoji(e);
              onPick(e);
            }}
            style={({ pressed }) => ({ width: "12.5%", aspectRatio: 1, alignItems: "center", justifyContent: "center", borderRadius: 8, backgroundColor: pressed ? "rgba(0,0,0,0.06)" : "transparent" })}
          >
            <Text style={{ fontSize: 26, lineHeight: 32 }}>{e}</Text>
          </Pressable>
        ))}
      </ScrollView>
    </View>
  );
}

/* ── Mentions and records ── */

export const RECORD_ICONS = { os: Package, pb: FileSpreadsheet, req: ClipboardList, ds: FlaskConical, es: Ship };
const escape = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

/** A message's text: @people in blue, #records as chips, links that open. */
export function RichText({ text, mentions, meId, onUser, style }) {
  const users = mentions?.users ?? [];
  const refs = mentions?.refs ?? [];
  const cards = useCards(refs.map((r) => `${r.type}:${r.ref}`));
  const tokens = [...users.map((u) => ({ token: `@${u.name}`, user: u })), ...refs.map((r) => ({ token: r.token, ref: r }))].sort((a, b) => b.token.length - a.token.length);
  const pattern = new RegExp(`(https?:\\/\\/[^\\s]+${tokens.map((x) => `|${escape(x.token)}`).join("")})`, "g");
  const parts = String(text).split(pattern);

  return (
    <Text style={style}>
      {parts.map((p, i) => {
        if (!p) return null;
        if (/^https?:\/\//.test(p)) {
          return (
            <Text key={i} style={{ color: WA.link }} onPress={() => Linking.openURL(p)}>
              {p}
            </Text>
          );
        }
        const hit = tokens.find((x) => x.token === p);
        if (hit?.user) {
          return (
            <Text key={i} style={{ color: WA.link, fontFamily: fonts.medium }} onPress={() => hit.user.id !== meId && onUser?.(hit.user)}>
              {p}
            </Text>
          );
        }
        if (hit?.ref) {
          const card = cards[`${hit.ref.type}:${hit.ref.ref}`];
          return (
            <Text key={i} style={{ color: card ? WA.link : WA.icon, backgroundColor: card ? "rgba(2,126,181,0.1)" : "rgba(0,0,0,0.05)", fontFamily: fonts.medium }} onPress={card?.url ? () => Linking.openURL(card.url) : undefined}>
              {p}
            </Text>
          );
        }
        return p;
      })}
    </Text>
  );
}

/** Under the text: a card per mentioned record the reader may see (buyer, style, qty …, status, Open). */
export function RecordCards({ refs, mine }) {
  const list = (refs ?? []).slice(0, 3);
  const cards = useCards(list.map((r) => `${r.type}:${r.ref}`));
  const shown = list.map((r) => cards[`${r.type}:${r.ref}`]).filter(Boolean);
  if (!shown.length) return null;

  return (
    <View style={{ gap: 4, marginBottom: 4 }}>
      {shown.map((c) => {
        const Icon = RECORD_ICONS[c.type] ?? Package;
        return (
          <Pressable key={`${c.type}:${c.ref}`} onPress={() => c.url && Linking.openURL(c.url)} style={{ minWidth: 200, borderLeftWidth: 4, borderLeftColor: WA.green, borderRadius: 6, paddingHorizontal: 10, paddingVertical: 6, backgroundColor: mine ? WA.mineQuote : "#f5f6f6" }}>
            <View style={{ flexDirection: "row", alignItems: "center", gap: 5, flexWrap: "wrap" }}>
              <Icon size={13} color={WA.greenDark} />
              <Text style={{ fontSize: 12.5, fontFamily: fonts.semibold, color: WA.greenDark }}>{`${c.title} · ${String(c.token).replace(/^#/, "")}`}</Text>
              {c.status ? <Text style={{ marginLeft: "auto", fontSize: 11, color: WA.icon, backgroundColor: "rgba(255,255,255,0.8)", paddingHorizontal: 6, borderRadius: 8 }}>{c.status}</Text> : null}
            </View>
            <Text style={{ fontSize: 12, lineHeight: 17, color: "#3b4a54", marginTop: 2 }}>
              {(c.fields ?? []).map((f, i) => (
                <Text key={f.label}>
                  <Text style={{ color: WA.sub }}>{`${f.label}: `}</Text>
                  {`${f.value}${i < c.fields.length - 1 ? "   " : ""}`}
                </Text>
              ))}
            </Text>
            <View style={{ flexDirection: "row", alignItems: "center", gap: 4, marginTop: 2 }}>
              <ExternalLink size={11} color={WA.link} />
              <Text style={{ fontSize: 11.5, color: WA.link }}>{ct("open_record")}</Text>
            </View>
          </Pressable>
        );
      })}
    </View>
  );
}

/* ── The photo viewer ── */

export function ImageViewer({ item, onClose, onDownload }) {
  const src = useAuthSource(item ? fileUrl(item.id) : null);
  return (
    <Modal visible={Boolean(item)} transparent animationType="fade" onRequestClose={onClose} statusBarTranslucent>
      <View style={{ flex: 1, backgroundColor: "rgba(0,0,0,0.94)" }}>
        <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], paddingTop: 44, paddingHorizontal: space[3], paddingBottom: space[2] }}>
          <Text style={{ flex: 1, color: "#ffffff", fontSize: 14 }} numberOfLines={1}>
            {item?.name ?? ""}
          </Text>
          {onDownload ? (
            <Pressable accessibilityLabel={ct("download")} onPress={() => onDownload(item)} hitSlop={10} style={{ padding: 8 }}>
              <Download size={22} color="#ffffff" />
            </Pressable>
          ) : null}
          <Pressable accessibilityLabel={ct("cancel")} onPress={onClose} hitSlop={10} style={{ padding: 8 }}>
            <X size={24} color="#ffffff" />
          </Pressable>
        </View>
        {src ? <Image source={src} resizeMode="contain" style={{ flex: 1, marginBottom: 40 }} /> : null}
      </View>
    </Modal>
  );
}

/* ── The phone's own doings ── */

/**
 * A native module this app version may not carry yet (added for chat
 * 2026-11-27; it arrives with the next build). Asked for by its native name
 * first — the package is loaded only when the phone has it, since loading a
 * package whose native half is missing is reported as a crash.
 */
function optional(nativeName, load) {
  if (!requireOptionalNativeModule(nativeName)) return null;
  try {
    return load();
  } catch {
    return null;
  }
}
export class NeedsUpdate extends Error {}

/** Copy text (expo-clipboard); without it in this build, the phone's share sheet. */
export async function copyText(text) {
  const Clipboard = optional("ExpoClipboard", () => require("expo-clipboard"));
  if (Clipboard?.setStringAsync) {
    try {
      await Clipboard.setStringAsync(text);
      return "copied";
    } catch {
      /* the share sheet instead */
    }
  }
  await Share.share({ message: text });
  return "shared";
}

/** Photos from the gallery (expo-image-picker). */
export async function pickPhotos() {
  const Picker = optional("ExponentImagePicker", () => require("expo-image-picker"));
  if (!Picker?.launchImageLibraryAsync) throw new NeedsUpdate();
  let result;
  try {
    result = await Picker.launchImageLibraryAsync({ mediaTypes: ["images"], allowsMultipleSelection: true, selectionLimit: 10, quality: 0.7 });
  } catch {
    throw new NeedsUpdate();
  }
  if (result.canceled) return [];
  return result.assets.map((a, i) => ({ uri: a.uri, name: a.fileName ?? `photo-${Date.now()}-${i}.jpg`, mime: a.mimeType ?? "image/jpeg", size: a.fileSize ?? 0 }));
}

/** Documents (expo-document-picker). */
export async function pickDocuments() {
  const Picker = optional("ExpoDocumentPicker", () => require("expo-document-picker"));
  if (!Picker?.getDocumentAsync) throw new NeedsUpdate();
  let result;
  try {
    result = await Picker.getDocumentAsync({ multiple: true, copyToCacheDirectory: true });
  } catch {
    throw new NeedsUpdate();
  }
  if (result.canceled) return [];
  return result.assets.map((a) => ({ uri: a.uri, name: a.name, mime: a.mimeType ?? "application/octet-stream", size: a.size ?? 0 }));
}

/**
 * Save a chat file: fetched with the person's token into the app's cache,
 * then written into a folder the person picks (Android's own "save to"
 * chooser — Downloads, Documents …). Returns the file's name, or null when
 * the person cancelled.
 */
export async function saveFile(path, name, mime, token) {
  const FS = require("expo-file-system/legacy");
  const local = `${FS.cacheDirectory}${encodeURIComponent(name)}`;
  const done = await FS.downloadAsync(`${apiUrl(currentServer())}${path}`, local, { headers: { Authorization: `Bearer ${token}` } });
  if (done.status >= 400) throw new Error(ct("send_failed"));
  const SAF = FS.StorageAccessFramework;
  const perm = await SAF.requestDirectoryPermissionsAsync();
  if (!perm.granted) return null;
  const target = await SAF.createFileAsync(perm.directoryUri, name, mime || "application/octet-stream");
  const data = await FS.readAsStringAsync(local, { encoding: FS.EncodingType.Base64 });
  await FS.writeAsStringAsync(target, data, { encoding: FS.EncodingType.Base64 });
  return name;
}

/** A small file badge: its kind's colour and label (PDF, XLSX …). */
export function FileBadge({ name, big = false }) {
  const k = fileKind(name);
  return (
    <View style={{ width: big ? 48 : 32, height: big ? 60 : 40, borderRadius: 3, backgroundColor: k.color, alignItems: "center", justifyContent: "center" }}>
      <Text style={{ color: "#ffffff", fontSize: big ? 12 : 9, fontFamily: fonts.bold }}>{k.label}</Text>
    </View>
  );
}

/** The search field of the chat screens (the web's grey rounded box). */
export function ChatSearchBox({ value, onChange, placeholder, autoFocus = false }) {
  const colors = useColors();
  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: space[3], height: 38, paddingHorizontal: space[3], borderRadius: 10, backgroundColor: WA.panel }}>
      <Search size={16} color={WA.icon} />
      <TextInput value={value} onChangeText={onChange} placeholder={placeholder} placeholderTextColor={WA.sub} autoFocus={autoFocus} style={{ flex: 1, color: colors.foreground, fontFamily: fonts.regular, fontSize: 14, paddingVertical: 0 }} />
      {value ? (
        <Pressable onPress={() => onChange("")} hitSlop={10}>
          <X size={16} color={WA.icon} />
        </Pressable>
      ) : null}
    </View>
  );
}

/** Keeps a value for a moment (a toast line under the header). */
export function useFlash() {
  const [msg, setMsg] = useState(null);
  useEffect(() => {
    if (!msg) return undefined;
    const id = setTimeout(() => setMsg(null), 2600);
    return () => clearTimeout(id);
  }, [msg]);
  return [msg, setMsg];
}

/** The toast itself: a dark pill near the top of the chat. */
export function Flash({ msg }) {
  if (!msg) return null;
  return (
    <View pointerEvents="none" style={{ position: "absolute", top: 12, left: 0, right: 0, alignItems: "center", zIndex: 50 }}>
      <View style={{ maxWidth: "86%", paddingHorizontal: 14, paddingVertical: 8, borderRadius: 18, backgroundColor: "rgba(17,27,33,0.88)" }}>
        <Text style={{ color: "#ffffff", fontSize: 13 }}>{msg}</Text>
      </View>
    </View>
  );
}
