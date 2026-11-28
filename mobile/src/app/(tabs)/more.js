/**
 * More (the bottom bar's last tab; redesigned 2026-11-27): who is signed in
 * (a tap opens Account — employee information, language, theme), the
 * shortcuts — Chat and Notifications with what waits unread, Account, About
 * — every module that is not a tab, each only with the permission its API
 * checks, and the way out.
 */
import { Alert as Ask, Pressable, View } from "react-native";
import { router } from "expo-router";
import { Bell, ChevronRight, CircleUserRound, FileClock, Info, LogOut, Menu, MessageCircle } from "lucide-react-native";
import { Avatar, Badge, Button, FeatureTile, Screen, Text } from "../../components/ui";
import { IconDisc, PageHero, SectionCard, useUnreadCount } from "../../components/hero";
import { TilesCard } from "../../components/home-premium";
import { useAccess } from "../../components/manage";
import { useChatUnread } from "../../lib/chat";
import { machineFeatures, orderFeatures, tallyFeatures } from "../../lib/features";
import { t } from "../../lib/i18n";
import { useMachineAccess } from "../../lib/machines";
import { useSection } from "../../lib/section";
import { qcOf, useSession } from "../../lib/session";
import { accents, fonts, space } from "../../theme/tokens";
import { shadow, useColors } from "../../theme/theme";

const m = (key, params) => t("mobile", key, params);

export default function More() {
  const { session, signOut } = useSession();
  const colors = useColors();
  const access = useAccess();
  const section = useSection(access);
  const machines = useMachineAccess();
  const pin = session.kind === "PIN";
  const qc = qcOf(session);
  const name = pin ? qc.qc.name : session.me.user?.name;
  const sub = pin ? [qc.qc.employee_id, qc.qc.designation].filter(Boolean).join(" · ") : [session.me.user?.designation, session.me.company?.name].filter(Boolean).join(" · ");
  const perms = pin ? [] : (session.permissions ?? []);
  const chats = useChatUnread(!pin);
  const [notes] = useUnreadCount();

  // The shortcuts (Chat and Notifications need an email sign-in).
  const shortcuts = [
    ...(pin ? [] : [{ key: "chat", label: m("chat.title"), icon: MessageCircle, image: "chat", color: accents.green, href: "/chat", badge: chats }]),
    ...(pin ? [] : [{ key: "notes", label: m("act.title"), icon: Bell, image: "notification", color: accents.orange, href: "/notifications", badge: notes }]),
    { key: "account", label: m("acct.title"), icon: CircleUserRound, image: "account", color: accents.blue, href: "/account" },
    { key: "about", label: m("about.title"), icon: Info, image: "about", color: accents.purple, href: "/about" },
  ];

  // The modules that are not tabs.
  const groups = [
    {
      title: m("ord.title"),
      items: [
        ...(perms.includes("ordersummary.view") || perms.includes("devsample.view") ? [{ key: "orders", label: m("home.q_orders"), icon: FileClock, image: "order", tone: "brand", href: { pathname: "/order" } }] : []),
        ...orderFeatures(perms).filter((f) => f.key === "budgets").map((f) => ({ ...f, tone: "purple" })),
      ],
    },
    { title: m("mach.title"), items: machineFeatures(machines).map((f) => ({ ...f, tone: { breakdowns: "warning", report: "danger", work: "purple" }[f.key] ?? "brand" })) },
    { title: m("mgr.title"), items: tallyFeatures({ session, section }).filter((f) => f.key !== "dashboard" && f.key !== "tally").map((f) => ({ ...f, tone: "info" })) },
  ].filter((g) => g.items.length);

  const askSignOut = () =>
    Ask.alert(m("home.sign_out_ask"), undefined, [
      { text: m("cancel"), style: "cancel" },
      { text: m("home.sign_out"), style: "destructive", onPress: signOut },
    ]);

  return (
    <Screen tab header={<PageHero title={m("nav.more")} icon={Menu} onBack={() => router.navigate("/")} />}>
        {/* Who is signed in: a tap opens Account. */}
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={m("acct.title")}
          onPress={() => router.push("/account")}
          style={({ pressed }) => [{ flexDirection: "row", alignItems: "center", gap: space[3], padding: space[4], borderRadius: 16, borderWidth: 1, borderColor: colors.border, backgroundColor: pressed ? colors.surfaceMuted : colors.surface }, shadow(colors)]}
        >
          <Avatar name={name} size={56} />
          <View style={{ flex: 1, minWidth: 0, gap: 3 }}>
            <Text variant="cardTitle" style={{ fontSize: 16 }} numberOfLines={1}>
              {name}
            </Text>
            {sub ? (
              <Text style={{ fontSize: 12 }} tone="foregroundMuted" numberOfLines={1}>
                {sub}
              </Text>
            ) : null}
            <Badge tone="brand">{pin ? m("more.by_pin") : m("more.by_email")}</Badge>
          </View>
          <View style={{ alignItems: "center", gap: 2 }}>
            <ChevronRight size={20} color={colors.foregroundSubtle} />
          </View>
        </Pressable>

        <TilesCard>
          {shortcuts.map((s) => (
            <FeatureTile square key={s.key} icon={s.icon} image={s.image} label={s.label} color={s.color} badge={s.badge || undefined} onPress={() => router.push(s.href)} />
          ))}
        </TilesCard>

        {groups.map((g) => (
          <SectionCard key={g.title} title={g.title} bodyStyle={{ gap: 0, paddingHorizontal: 0 }}>
            {g.items.map((item, i) => (
              <Pressable
                key={item.key}
                accessibilityRole="button"
                onPress={() => router.push(item.href)}
                style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: space[3], minHeight: 50, paddingHorizontal: space[4], borderTopWidth: i ? 1 : 0, borderTopColor: colors.divider ?? colors.border, backgroundColor: pressed ? colors.surfaceMuted : "transparent" })}
              >
                <IconDisc icon={item.icon} image={item.image} tone={item.tone} size={34} />
                <Text style={{ flex: 1, fontFamily: fonts.medium, fontSize: 13 }}>{item.label}</Text>
                <ChevronRight size={18} color={colors.foregroundSubtle} />
              </Pressable>
            ))}
          </SectionCard>
        ))}

        {pin ? null : (
          <Button variant="danger" icon={LogOut} onPress={askSignOut}>
            {m("home.sign_out")}
          </Button>
        )}
    </Screen>
  );
}
