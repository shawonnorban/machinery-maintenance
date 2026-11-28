/**
 * New chat and new group, as the web's panes: the company's people (search
 * by name, about or email; "New group" on top) — a tap opens the chat; for a
 * group, pick its people (chips on top), then its name, then create.
 */
import { useState } from "react";
import { FlatList, Pressable, TextInput, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { SafeAreaView } from "react-native-safe-area-context";
import { ArrowRight, Check, Users, X } from "lucide-react-native";
import { Text } from "../../components/ui";
import { ChatHeader } from "../../components/chat/header";
import { ChatAvatar, ChatSearchBox, Flash, WA, ct, useFlash } from "../../components/chat/parts";
import { createGroup, lastSeen, startDirect, useChatLive } from "../../lib/chat";
import { currentLocale } from "../../lib/i18n";
import { fonts } from "../../theme/tokens";

export function PersonRow({ person, onPress, selected = null }) {
  const locale = currentLocale();
  return (
    <Pressable onPress={onPress} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: 14, paddingLeft: 16, backgroundColor: pressed ? "#f5f6f6" : "#fff" })}>
      <View style={{ paddingVertical: 10 }}>
        <ChatAvatar person={person} size={46} online={person.online} />
        {selected ? (
          <View style={{ position: "absolute", right: 0, bottom: 8, width: 18, height: 18, borderRadius: 9, borderWidth: 2, borderColor: "#fff", backgroundColor: WA.green, alignItems: "center", justifyContent: "center" }}>
            <Check size={11} color="#fff" strokeWidth={3} />
          </View>
        ) : null}
      </View>
      <View style={{ flex: 1, minWidth: 0, alignSelf: "stretch", justifyContent: "center", borderBottomWidth: 1, borderBottomColor: WA.line, paddingRight: 14 }}>
        <Text style={{ fontSize: 16, color: WA.text }} numberOfLines={1}>{person.name}</Text>
        <Text style={{ fontSize: 13.5, color: WA.sub }} numberOfLines={1}>{person.about || lastSeen(person, ct, locale) || person.email}</Text>
      </View>
    </Pressable>
  );
}

export default function NewChat() {
  const { group } = useLocalSearchParams();
  const s = useChatLive();
  const [step, setStep] = useState(group ? "people" : "chat");
  const [q, setQ] = useState("");
  const [picked, setPicked] = useState([]);
  const [name, setName] = useState("");
  const [busy, setBusy] = useState(false);
  const [flash, setFlash] = useFlash();
  const contacts = s.contacts;
  const shown = (contacts ?? []).filter((p) => !q || `${p.name} ${p.about ?? ""} ${p.email ?? ""}`.toLowerCase().includes(q.toLowerCase()));
  const toggle = (p) => setPicked((all) => (all.some((x) => x.id === p.id) ? all.filter((x) => x.id !== p.id) : [...all, p]));

  const direct = async (p) => {
    try {
      const row = await startDirect(p);
      router.replace({ pathname: "/chat/[id]", params: { id: row.id } });
    } catch (e) {
      setFlash(e.message);
    }
  };
  const create = async () => {
    setBusy(true);
    try {
      const row = await createGroup(name.trim(), picked.map((p) => p.id));
      router.replace({ pathname: "/chat/[id]", params: { id: row.id } });
    } catch (e) {
      setFlash(e.message);
    } finally {
      setBusy(false);
    }
  };

  if (step === "name") {
    return (
      <SafeAreaView edges={["bottom"]} style={{ flex: 1, backgroundColor: WA.panel }}>
        <ChatHeader title={ct("new_group")} onBack={() => setStep("people")} />
        <View style={{ alignItems: "center", gap: 22, backgroundColor: "#fff", paddingHorizontal: 28, paddingVertical: 26 }}>
          <View style={{ width: 120, height: 120, borderRadius: 60, backgroundColor: "#dfe5e7", alignItems: "center", justifyContent: "center" }}>
            <Users size={60} color="#9eb3c2" />
          </View>
          <TextInput autoFocus value={name} onChangeText={setName} maxLength={120} placeholder={ct("group_name")} placeholderTextColor={WA.tick} style={{ alignSelf: "stretch", borderBottomWidth: 2, borderBottomColor: WA.green, paddingVertical: 8, fontSize: 16, color: WA.text, fontFamily: fonts.regular }} />
          <Text style={{ alignSelf: "flex-start", fontSize: 13, color: WA.sub }}>{`${ct("selected", { n: picked.length })}: ${picked.map((p) => p.name).join(", ")}`}</Text>
        </View>
        <View style={{ alignItems: "center", paddingTop: 30 }}>
          {name.trim() ? (
            <Pressable accessibilityLabel={ct("create_group")} disabled={busy} onPress={create} style={{ width: 52, height: 52, borderRadius: 26, backgroundColor: WA.green, opacity: busy ? 0.6 : 1, alignItems: "center", justifyContent: "center", elevation: 3 }}>
              <Check size={24} color="#fff" />
            </Pressable>
          ) : null}
        </View>
        <Flash msg={flash} />
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView edges={["bottom"]} style={{ flex: 1, backgroundColor: "#fff" }}>
      <ChatHeader title={step === "people" ? ct("select_people") : ct("new_chat")} onBack={() => (step === "people" && !group ? setStep("chat") : router.back())} />
      {step === "people" && picked.length ? (
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8, paddingHorizontal: 14, paddingTop: 10 }}>
          {picked.map((p) => (
            <View key={p.id} style={{ flexDirection: "row", alignItems: "center", gap: 6, borderRadius: 16, backgroundColor: WA.line, paddingLeft: 2, paddingRight: 8, paddingVertical: 2 }}>
              <ChatAvatar person={p} size={24} />
              <Text style={{ fontSize: 13.5 }}>{p.name.split(" ")[0]}</Text>
              <Pressable onPress={() => toggle(p)} hitSlop={8}>
                <X size={14} color={WA.icon} />
              </Pressable>
            </View>
          ))}
        </View>
      ) : null}
      <View style={{ padding: 12 }}>
        <ChatSearchBox value={q} onChange={setQ} placeholder={ct("search_people")} />
      </View>
      <FlatList
        data={shown}
        keyExtractor={(p) => p.id}
        keyboardShouldPersistTaps="handled"
        ListHeaderComponent={
          step === "chat" ? (
            <>
              <Pressable onPress={() => setStep("people")} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: 14, paddingHorizontal: 16, paddingVertical: 10, backgroundColor: pressed ? "#f5f6f6" : "#fff" })}>
                <View style={{ width: 46, height: 46, borderRadius: 23, backgroundColor: WA.green, alignItems: "center", justifyContent: "center" }}>
                  <Users size={22} color="#fff" />
                </View>
                <Text style={{ fontSize: 16, color: WA.text }}>{ct("new_group")}</Text>
              </Pressable>
              <Text style={{ paddingHorizontal: 28, paddingTop: 16, paddingBottom: 8, fontSize: 15, color: WA.greenDark }}>{ct("people")}</Text>
            </>
          ) : null
        }
        ListEmptyComponent={<Text style={{ padding: 24, fontSize: 14, color: WA.sub }}>{contacts === null ? ct("loading") : ct("no_people")}</Text>}
        renderItem={({ item }) => <PersonRow person={item} onPress={() => (step === "people" ? toggle(item) : direct(item))} selected={step === "people" ? picked.some((x) => x.id === item.id) : null} />}
      />
      {step === "people" && picked.length ? (
        <View style={{ alignItems: "center", paddingVertical: 14, backgroundColor: WA.panel }}>
          <Pressable accessibilityLabel={ct("next")} onPress={() => setStep("name")} style={{ width: 52, height: 52, borderRadius: 26, backgroundColor: WA.green, alignItems: "center", justifyContent: "center", elevation: 3 }}>
            <ArrowRight size={24} color="#fff" />
          </Pressable>
        </View>
      ) : null}
      <Flash msg={flash} />
    </SafeAreaView>
  );
}
