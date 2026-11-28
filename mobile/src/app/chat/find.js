/**
 * Search the messages of one chat, as the web's drawer: 2+ letters, asked
 * 300 ms after the typing stops; each hit with its time, sender and the
 * matching words marked; a tap goes back to the chat on that message.
 */
import { useState } from "react";
import { FlatList, Pressable, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { SafeAreaView } from "react-native-safe-area-context";
import { Text } from "../../components/ui";
import { ChatHeader } from "../../components/chat/header";
import { ChatSearchBox, WA, ct } from "../../components/chat/parts";
import { jumpTo, listTime, useChatLive, useMessageSearch } from "../../lib/chat";
import { currentLocale } from "../../lib/i18n";
import { fonts } from "../../theme/tokens";

function Highlight({ text, query }) {
  const term = query.trim();
  if (!text || term.length < 2) return text ?? "";
  const at = text.toLowerCase().indexOf(term.toLowerCase());
  if (at < 0) return text;
  const from = Math.max(0, at - 30);
  return (
    <Text>
      {from > 0 ? "…" : ""}
      {text.slice(from, at)}
      <Text style={{ color: WA.green, fontFamily: fonts.semibold }}>{text.slice(at, at + term.length)}</Text>
      {text.slice(at + term.length)}
    </Text>
  );
}

export default function FindInChat() {
  const { id } = useLocalSearchParams();
  const chatId = String(id);
  const s = useChatLive();
  const locale = currentLocale();
  const chat = (s.chats ?? []).find((c) => c.id === chatId);
  const [query, setQuery] = useState("");
  const { active, loading, results } = useMessageSearch(query, chatId);

  return (
    <SafeAreaView edges={["bottom"]} style={{ flex: 1, backgroundColor: "#fff" }}>
      <ChatHeader title={ct("search_messages")} subtitle={chat?.name} onBack={() => router.back()} />
      <View style={{ padding: 12 }}>
        <ChatSearchBox value={query} onChange={setQuery} placeholder={ct("search_in_chat")} autoFocus />
      </View>
      <FlatList
        data={results}
        keyExtractor={(h) => h.id}
        keyboardShouldPersistTaps="handled"
        ListEmptyComponent={<Text style={{ padding: 32, textAlign: "center", fontSize: 14, color: WA.sub }}>{!active ? ct("search_hint", { name: chat?.name ?? "" }) : loading ? ct("searching") : ct("no_messages_found")}</Text>}
        renderItem={({ item: h }) => {
          const who = h.user_id === s.me?.id ? ct("you") : chat?.is_group ? h.user_name : null;
          return (
            <Pressable
              onPress={() => {
                jumpTo(chatId, h.id);
                router.back();
              }}
              style={({ pressed }) => ({ paddingHorizontal: 20, paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: WA.line, backgroundColor: pressed ? "#f5f6f6" : "#fff" })}
            >
              <Text style={{ fontSize: 12.5, color: WA.sub }}>{listTime(h.created_at, ct, locale)}</Text>
              <Text style={{ fontSize: 14, color: WA.sub }} numberOfLines={2}>
                {who ? <Text style={{ color: WA.text }}>{`${who}: `}</Text> : null}
                {h.type !== "TEXT" && !h.body ? `${h.type === "IMAGE" ? "📷" : "📄"} ` : ""}
                <Highlight text={h.body || h.file_name || ""} query={query} />
              </Text>
            </Pressable>
          );
        }}
      />
    </SafeAreaView>
  );
}
