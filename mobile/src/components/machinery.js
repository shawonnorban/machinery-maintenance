/**
 * Machinery's pieces on the phone (AnnotechERP 37, phase 4): the web's own
 * status words and colours, a photo taken with the camera, a row of photos
 * with "add", a picker for long lists (technicians, failure codes) and the
 * work order's checklist.
 */
import { useEffect, useRef, useState } from "react";
import { ActivityIndicator, Image, Linking, Modal, Pressable, ScrollView, View } from "react-native";
import { CameraView, useCameraPermissions } from "expo-camera";
import { File } from "expo-file-system";
import { Camera, Check, CircleSlash, ImagePlus, RotateCcw, Search, ShieldAlert, X } from "lucide-react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { Alert, Badge, Button, Card, Chip, Input, Text } from "./ui";
import { Option, Sheet } from "./tally";
import { useLoad } from "./manage";
import { t } from "../lib/i18n";
import { apiUrl } from "../lib/server";
import { useSession } from "../lib/session";
import { radius, space, touch } from "../theme/tokens";
import { useColors } from "../theme/theme";

export const bt = (key, params) => t("breakdown", key, params);
export const wt = (key, params) => t("work_order", key, params);
export const at = (key, params) => t("asset", key, params);
export const mt = (key, params) => t("mobile", `mach.${key}`, params);

const BREAKDOWN_TONE = { REPORTED: "danger", ACKNOWLEDGED: "warning", ASSIGNED: "warning", IN_REPAIR: "info", ON_HOLD: "warning", REPAIRED: "success", PRODUCTION_RESUMED: "success", VERIFIED: "success", PENDING_APPROVAL: "brand" };
const WORK_ORDER_TONE = { PENDING_APPROVAL: "brand", SCHEDULED: "info", ASSIGNED: "warning", IN_PROGRESS: "info", ON_HOLD: "warning", COMPLETED: "success", VERIFIED: "success", REJECTED: "danger" };
const ASSET_TONE = { RUNNING: "success", UNDER_MAINTENANCE: "warning", BREAKDOWN: "danger", UNDER_REPAIR: "warning", COMMISSIONED: "info", INSTALLED: "info" };
export const PRIORITY_TONE = { CRITICAL: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };

export const lower = (v) => String(v ?? "").toLowerCase();

export function BreakdownStatus({ status }) {
  return <Badge tone={BREAKDOWN_TONE[status] ?? "neutral"}>{bt(`status_${lower(status)}`)}</Badge>;
}

export function WorkOrderStatus({ status }) {
  return <Badge tone={WORK_ORDER_TONE[status] ?? "neutral"}>{wt(`status_${lower(status)}`)}</Badge>;
}

export function AssetStatus({ status }) {
  return <Badge tone={ASSET_TONE[status] ?? "neutral"}>{at(`status_${lower(status)}`)}</Badge>;
}

export function Priority({ priority }) {
  return priority ? <Badge tone={PRIORITY_TONE[priority] ?? "neutral"}>{bt(`priority_${lower(priority)}`)}</Badge> : null;
}

/**
 * The camera, full screen: one photo, looked at, then kept or taken again.
 * Taken at a phone-screen size, not the sensor's twelve megapixels — a report
 * on factory Wi-Fi must not wait on a photo nobody needs that large.
 */
export function PhotoCapture({ open, onClose, onPhoto, base64 = false }) {
  const colors = useColors();
  const [permission, requestPermission] = useCameraPermissions();
  const [size, setSize] = useState(undefined);
  const [shot, setShot] = useState(null);
  const [busy, setBusy] = useState(false);
  const camera = useRef(null);

  useEffect(() => {
    if (!open) {
      setShot(null);
      setBusy(false);
    }
  }, [open]);

  const ready = async () => {
    try {
      const sizes = (await camera.current?.getAvailablePictureSizesAsync()) ?? [];
      const fits = sizes
        .map((s) => ({ s, w: Math.max(...s.split("x").map(Number)) }))
        .filter((s) => s.w > 0 && s.w <= 1600)
        .sort((a, b) => b.w - a.w);
      if (fits[0]) setSize(fits[0].s);
    } catch {
      // The camera's own default size is still a photo.
    }
  };

  const take = async () => {
    if (busy || !camera.current) return;
    setBusy(true);
    try {
      const photo = await camera.current.takePictureAsync({ quality: 0.6, base64, exif: false });
      setShot(photo);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal visible={open} animationType="slide" onRequestClose={onClose} statusBarTranslucent>
      <SafeAreaView style={{ flex: 1, backgroundColor: "#000000" }}>
        <View style={{ flexDirection: "row", alignItems: "center", padding: space[2] }}>
          <Pressable accessibilityRole="button" accessibilityLabel={t("mobile", "cancel")} onPress={onClose} style={{ width: touch, height: touch, alignItems: "center", justifyContent: "center" }}>
            <X size={26} color="#ffffff" />
          </Pressable>
          <Text variant="sectionTitle" style={{ color: "#ffffff", flex: 1 }}>
            {mt("photo_title")}
          </Text>
        </View>
        <View style={{ flex: 1 }}>
          {shot ? (
            <Image source={{ uri: shot.uri }} style={{ flex: 1 }} resizeMode="contain" />
          ) : permission?.granted ? (
            <CameraView ref={camera} style={{ flex: 1 }} facing="back" pictureSize={size} onCameraReady={ready} />
          ) : permission ? (
            <View style={{ flex: 1, alignItems: "center", justifyContent: "center", padding: space[6], gap: space[4] }}>
              <Camera size={40} color="#ffffff" />
              <Text style={{ color: "#ffffff", textAlign: "center" }}>{mt("camera_allow")}</Text>
              {permission.canAskAgain ? <Button onPress={requestPermission}>{t("mobile", "tally.allow")}</Button> : <Button onPress={() => Linking.openSettings()}>{t("mobile", "tally.phone_settings")}</Button>}
            </View>
          ) : (
            <ActivityIndicator color="#ffffff" style={{ flex: 1 }} />
          )}
        </View>
        <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "center", gap: space[4], padding: space[5] }}>
          {shot ? (
            <>
              <Button variant="outline" icon={RotateCcw} onPress={() => setShot(null)} style={{ flex: 1 }}>
                {mt("retake")}
              </Button>
              <Button icon={Check} onPress={() => onPhoto(shot)} style={{ flex: 1 }}>
                {mt("use_photo")}
              </Button>
            </>
          ) : permission?.granted ? (
            <Pressable accessibilityRole="button" accessibilityLabel={mt("take_photo")} onPress={take} style={{ width: 76, height: 76, borderRadius: radius.full, borderWidth: 5, borderColor: "#ffffff", alignItems: "center", justifyContent: "center" }}>
              {busy ? <ActivityIndicator color="#ffffff" /> : <View style={{ width: 56, height: 56, borderRadius: radius.full, backgroundColor: "#ffffff" }} />}
            </Pressable>
          ) : null}
        </View>
      </SafeAreaView>
    </Modal>
  );
}

const photoCache = new Map();

/**
 * A photo the server keeps: read by the app itself with the signed-in
 * person's token (the download answers with a short-lived signed link, which
 * the Image component would not follow with the token's header), kept for
 * the session as a data link, and shown.
 */
function ServerImage({ id, style, resizeMode = "cover" }) {
  const colors = useColors();
  const { bearer } = useSession();
  const [uri, setUri] = useState(photoCache.get(id) ?? null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    if (photoCache.has(id)) return undefined;
    let live = true;
    fetch(`${apiUrl()}/files/${id}/download`, { headers: { Authorization: `Bearer ${bearer()}` } })
      .then((r) => (r.ok ? r.blob() : Promise.reject(new Error(String(r.status)))))
      .then(
        (blob) =>
          new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
          }),
      )
      .then((data) => {
        photoCache.set(id, data);
        if (live) setUri(data);
      })
      .catch(() => live && setFailed(true));

    return () => {
      live = false;
    };
  }, [id]);

  if (!uri) {
    return <View style={[style, { backgroundColor: colors.surfaceMuted, alignItems: "center", justifyContent: "center" }]}>{failed ? <CircleSlash size={22} color={colors.foregroundSubtle} /> : <ActivityIndicator color={colors.brand} />}</View>;
  }

  return <Image source={{ uri }} style={style} resizeMode={resizeMode} />;
}

/**
 * A photo to send as a file. Expo's fetch (the app's global one) sends a
 * FormData part only as bytes — React Native's `{ uri, name, type }` is
 * refused before it leaves the phone — so the photo goes as the file itself.
 */
export const photoFile = (shot) => new File(shot.uri);

/**
 * A record's photos (`/breakdowns/{id}/attachments`, `/work-orders/{id}/attachments`):
 * the row of them, one opened full screen, and "add" for whoever may.
 */
export function Photos({ path, canAdd }) {
  const colors = useColors();
  const { call } = useSession();
  const load = useLoad(path, []);
  const [camera, setCamera] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [view, setView] = useState(null);
  const files = load.data ?? [];

  const upload = async (shot) => {
    setCamera(false);
    setBusy(true);
    setError(null);
    try {
      const form = new FormData();
      form.append("file", photoFile(shot));
      await call(path, { method: "POST", body: form, deadline: 60000 });
      await load.reload();
    } catch (caught) {
      setError(caught.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Card>
      <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
        <Text variant="cardTitle">{`${mt("photos")} (${files.length})`}</Text>
        {canAdd ? (
          <Button variant="outline" icon={ImagePlus} loading={busy} onPress={() => setCamera(true)} style={{ minHeight: 40, paddingHorizontal: space[3] }}>
            {mt("add_photo")}
          </Button>
        ) : null}
      </View>
      {error ? <Alert>{error}</Alert> : null}
      {load.error && !load.data ? <Alert tone="warning">{load.error}</Alert> : null}
      {files.length === 0 && load.data ? (
        <Text variant="small" tone="foregroundMuted">
          {mt("no_photos")}
        </Text>
      ) : null}
      {files.length ? (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: space[2] }}>
          {files.map((f) =>
            f.is_image ? (
              <Pressable key={f.id} accessibilityRole="imagebutton" accessibilityLabel={f.original_name} onPress={() => setView(f)}>
                <ServerImage id={f.id} style={{ width: 96, height: 96, borderRadius: radius.md, backgroundColor: colors.surfaceMuted }} />
              </Pressable>
            ) : (
              <View key={f.id} style={{ width: 96, height: 96, borderRadius: radius.md, backgroundColor: colors.surfaceMuted, padding: space[2], justifyContent: "center" }}>
                <Text variant="small" numberOfLines={3}>
                  {f.original_name}
                </Text>
              </View>
            ),
          )}
        </ScrollView>
      ) : null}
      <PhotoCapture open={camera} onClose={() => setCamera(false)} onPhoto={upload} />
      <Modal visible={Boolean(view)} transparent animationType="fade" onRequestClose={() => setView(null)} statusBarTranslucent>
        <Pressable accessibilityLabel={t("mobile", "cancel")} onPress={() => setView(null)} style={{ flex: 1, backgroundColor: "#000000", justifyContent: "center" }}>
          {view ? <ServerImage id={view.id} style={{ width: "100%", height: "80%" }} resizeMode="contain" /> : null}
        </Pressable>
      </Modal>
    </Card>
  );
}

/** One of a long list — technicians, failure codes, root causes — found by typing. */
export function PickSheet({ open, onClose, title, items, onPick, selected }) {
  const [find, setFind] = useState("");
  const shown = items.filter((i) => !find.trim() || `${i.name} ${i.sub ?? ""}`.toLowerCase().includes(find.trim().toLowerCase()));

  useEffect(() => {
    if (!open) setFind("");
  }, [open]);

  return (
    <Sheet open={open} onClose={onClose} title={title}>
      {items.length > 6 ? <Input icon={Search} value={find} onChangeText={setFind} placeholder={mt("find")} autoCorrect={false} /> : null}
      {shown.map((i) => (
        <Option
          key={i.id}
          title={i.name}
          sub={i.sub}
          on={i.id === selected}
          onPress={() => {
            onPick(i);
            onClose();
          }}
        />
      ))}
      {shown.length === 0 ? <Text tone="foregroundMuted">{mt("nothing_found")}</Text> : null}
    </Sheet>
  );
}

/** A field that opens a PickSheet: its label, and the choice or a prompt. */
export function PickField({ label, value, placeholder, onPress }) {
  const colors = useColors();

  return (
    <View style={{ gap: space[1.5] }}>
      <Text variant="label">{label}</Text>
      <Pressable accessibilityRole="button" onPress={onPress} style={{ minHeight: touch, borderRadius: radius.md, borderWidth: 1, borderColor: colors.borderStrong, backgroundColor: colors.surface, paddingHorizontal: space[3], justifyContent: "center" }}>
        <Text tone={value ? "foreground" : "foregroundSubtle"} numberOfLines={2}>
          {value ?? placeholder}
        </Text>
      </Pressable>
    </View>
  );
}

const RESULT_TONE = { PASS: "success", FAIL: "danger", NA: "neutral" };

/**
 * A work order's checklist: every item with its answer; while the work
 * order is in progress, whoever works it answers an item in a sheet —
 * pass / fail / not applicable, the reading, the text or the choice, a note,
 * and a photo (some items need one on a fail).
 */
export function Checklist({ workOrderId, canRecord }) {
  const colors = useColors();
  const { call } = useSession();
  const load = useLoad(`/work-orders/${workOrderId}/checklist`, []);
  const [item, setItem] = useState(null);
  const items = load.data?.items ?? [];
  const progress = load.data?.progress;

  if (load.data && items.length === 0) return null;

  return (
    <Card>
      <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: space[2] }}>
        <Text variant="cardTitle">{wt("checklist")}</Text>
        {progress ? <Badge tone={progress.required_remaining ? "warning" : "success"}>{`${progress.answered}/${progress.total}`}</Badge> : null}
      </View>
      {load.error && !load.data ? <Alert tone="warning">{load.error}</Alert> : null}
      {progress?.required_remaining ? (
        <Text variant="small" tone="warning">
          {wt("checklist_required_remaining", { count: progress.required_remaining })}
        </Text>
      ) : null}
      {canRecord ? null : load.data ? (
        <Text variant="small" tone="foregroundMuted">
          {wt("checklist_needs_in_progress")}
        </Text>
      ) : null}
      {items.map((i) => (
        <Pressable
          key={i.id}
          accessibilityRole={canRecord ? "button" : undefined}
          disabled={!canRecord}
          onPress={() => setItem(i)}
          style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: space[3], minHeight: 52, paddingVertical: space[2], borderTopWidth: 1, borderTopColor: colors.border, opacity: pressed ? 0.75 : 1 })}
        >
          <View style={{ flex: 1, gap: 2 }}>
            <Text variant="label">
              {`${i.sequence}. ${i.label}`}
              {i.required ? <Text tone="danger"> *</Text> : null}
            </Text>
            {i.result?.numeric_value !== null && i.result?.numeric_value !== undefined ? (
              <Text variant="small" tone="foregroundMuted">{`${Number(i.result.numeric_value)} ${i.unit ?? ""}`}</Text>
            ) : null}
            {i.result?.text_value ? (
              <Text variant="small" tone="foregroundMuted">
                {i.result.text_value}
              </Text>
            ) : null}
            {i.result?.observation ? (
              <Text variant="small" tone="foregroundMuted">
                {i.result.observation}
              </Text>
            ) : null}
          </View>
          {i.is_safety_item ? <ShieldAlert size={18} color={colors.warning} /> : null}
          {i.result ? <Badge tone={RESULT_TONE[i.result.result]}>{wt(`result_${lower(i.result.result)}`)}</Badge> : canRecord ? <Badge tone="brand">{wt("record")}</Badge> : <CircleSlash size={18} color={colors.foregroundSubtle} />}
        </Pressable>
      ))}
      <ChecklistSheet
        item={item}
        onClose={() => setItem(null)}
        onSave={async (form) => {
          await call(`/work-orders/${workOrderId}/checklist/results`, { method: "POST", body: form, deadline: 60000 });
          setItem(null);
          await load.reload();
        }}
      />
    </Card>
  );
}

function ChecklistSheet({ item, onClose, onSave }) {
  const [result, setResult] = useState(null);
  const [reading, setReading] = useState("");
  const [text, setText] = useState("");
  const [note, setNote] = useState("");
  const [photo, setPhoto] = useState(null);
  const [camera, setCamera] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    setResult(item?.result?.result ?? null);
    setReading(item?.result?.numeric_value === null || item?.result?.numeric_value === undefined ? "" : String(Number(item.result.numeric_value)));
    setText(item?.result?.text_value ?? "");
    setNote(item?.result?.observation ?? "");
    setPhoto(null);
    setError(null);
  }, [item]);

  if (!item) return null;
  const numeric = item.input_type === "NUMERIC";
  const choices = item.input_type === "CHOICE" ? (Array.isArray(item.options) ? item.options : (item.options?.choices ?? [])) : [];

  const save = async () => {
    setBusy(true);
    setError(null);
    try {
      const form = new FormData();
      form.append("checklist_item_id", item.id);
      form.append("result", result);
      if (numeric && reading.trim() !== "") form.append("numeric_value", reading.trim());
      if ((item.input_type === "TEXT" || item.input_type === "CHOICE") && text.trim() !== "") form.append("text_value", text.trim());
      if (note.trim() !== "") form.append("observation", note.trim());
      if (photo) form.append("photo", photoFile(photo));
      await onSave(form);
    } catch (caught) {
      setError(caught.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Sheet
      open
      onClose={onClose}
      title={item.label}
      hint={[item.unit, item.required ? wt("checklist_item") + " *" : null].filter(Boolean).join(" · ") || undefined}
      footer={
        <>
          <Button variant="outline" onPress={onClose} style={{ flex: 1 }}>
            {t("mobile", "cancel")}
          </Button>
          <Button loading={busy} disabled={!result} onPress={save} style={{ flex: 1 }}>
            {wt("record")}
          </Button>
        </>
      }
    >
      {error ? <Alert>{error}</Alert> : null}
      <View style={{ flexDirection: "row", gap: space[2] }}>
        {["PASS", "FAIL", ...(item.required ? [] : ["NA"])].map((r) => (
          <Option key={r} title={wt(`result_${lower(r)}`)} on={result === r} tone={r === "PASS" ? "success" : r === "FAIL" ? "danger" : "brand"} onPress={() => setResult(r)} style={{ flex: 1, alignItems: "center" }} />
        ))}
      </View>
      {numeric && result !== "NA" ? <Input label={`${wt("reading")}${item.unit ? ` (${item.unit})` : ""}`} value={reading} onChangeText={setReading} keyboardType="decimal-pad" /> : null}
      {item.input_type === "TEXT" && result !== "NA" ? <Input label={wt("answer")} value={text} onChangeText={setText} multiline /> : null}
      {choices.length && result !== "NA" ? (
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: space[2] }}>
          {choices.map((c) => (
            <Chip key={c} on={text === c} onPress={() => setText(c)}>
              {c}
            </Chip>
          ))}
        </View>
      ) : null}
      <Input label={wt("observation")} value={note} onChangeText={setNote} placeholder={result === "FAIL" ? wt("observation_if_fail_placeholder") : undefined} multiline />
      <Button variant="outline" icon={photo ? Check : Camera} onPress={() => setCamera(true)}>
        {photo ? mt("photo_taken") : wt("photo")}
      </Button>
      <PhotoCapture
        open={camera}
        onClose={() => setCamera(false)}
        onPhoto={(shot) => {
          setPhoto(shot);
          setCamera(false);
        }}
      />
    </Sheet>
  );
}

/**
 * A list read a page at a time (the API's paginated lists): the rows so far,
 * the total, and "more". Read again from the first page when `path` changes.
 */
export function usePages(path) {
  const { call } = useSession();
  const [state, setState] = useState({ rows: null, page: 0, last: 0, total: 0 });
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);
  const asked = useRef(0);

  const read = async (page, keep) => {
    if (!path) return;
    const turn = ++asked.current;
    setLoading(true);
    try {
      const joint = path.includes("?") ? "&" : "?";
      const answer = await call(`${path}${joint}page=${page}&per_page=20`, { whole: true });
      if (turn !== asked.current) return;
      setState({ rows: [...(keep ?? []), ...(answer.data ?? [])], page: answer.meta?.current_page ?? page, last: answer.meta?.last_page ?? page, total: answer.meta?.total ?? 0 });
      setError(null);
    } catch (caught) {
      if (turn === asked.current) setError(caught.message);
    } finally {
      if (turn === asked.current) setLoading(false);
    }
  };

  useEffect(() => {
    read(1, null);
  }, [path]);

  return { ...state, error, loading, reload: () => read(1, null), more: state.page < state.last ? () => read(state.page + 1, state.rows) : null };
}

