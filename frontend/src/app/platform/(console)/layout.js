import { redirect } from "next/navigation";
import { Building2, LifeBuoy, Ticket, Wallet, Bell, UserPlus } from "lucide-react";
import { platformApiFetch } from "@/lib/platform-api-server";
import { ApiError } from "@/lib/api-error";
import { PlatformShell } from "@/components/layout/platform-shell";

export default async function PlatformConsoleLayout({ children }) {
  let me, notifications, openTickets, activeGrants, overdueInvoices;

  try {
    [me, notifications, openTickets, activeGrants, overdueInvoices] = await Promise.all([
      platformApiFetch("/auth/me"),
      // ALL, not UNREAD: the bell preview shows the 5 most recent regardless
      // of read state, same as the tenant app's own bell — `meta.unread_count`
      // for the badge is computed server-side either way.
      platformApiFetch("/notifications?filter=ALL&per_page=5", { includeMeta: true }),
      platformApiFetch("/tickets?status=OPEN&per_page=1", { includeMeta: true }),
      platformApiFetch("/support-grants?active=true"),
      platformApiFetch("/finance/invoices/overdue?per_page=1", { includeMeta: true }),
    ]);
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      redirect("/platform/login");
    }

    throw error;
  }

  // The ERP sidebar's shape: sections in brand capitals, each entry with its count.
  const navGroups = [
    {
      label: "Customers",
      items: [
        { label: "Customers", href: "/platform", icon: <Building2 />, tone: "brand", match: ["/platform/tenants"] },
        { label: "New customer", href: "/platform/tenants/new", icon: <UserPlus />, tone: "success" },
      ],
    },
    {
      label: "Service",
      items: [
        { label: "Tickets", href: "/platform/tickets", icon: <Ticket />, tone: "warning", badge: openTickets.meta.total },
        { label: "Support access", href: "/platform/support", icon: <LifeBuoy />, tone: "info", badge: activeGrants.length },
      ],
    },
    {
      label: "Business",
      items: [{ label: "Finance", href: "/platform/finance", icon: <Wallet />, tone: "success", badge: overdueInvoices.meta.total, badgeTone: "danger" }],
    },
    {
      label: "System",
      items: [{ label: "Notifications", href: "/platform/notifications", icon: <Bell />, tone: "brand", badge: notifications.meta.unread_count }],
    },
  ];

  return (
    <PlatformShell
      navGroups={navGroups}
      userName={me.name}
      userEmail={me.email}
      unreadNotifications={notifications.meta.unread_count}
      recentNotifications={notifications.data}
    >
      {children}
    </PlatformShell>
  );
}
