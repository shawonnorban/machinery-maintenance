"use client";

import { useEffect } from "react";
import { usePathname, useRouter } from "next/navigation";
import { PortalNotice } from "@/components/materialprocurement/supplier-portal";

/*
 * A supplier's portal login (AnnotechERP 08/07) works only in the Supplier
 * Portal: it lands on the Supplier Dashboard, and any other screen shows
 * Brickland's "This screen is not available" in place of the page.
 */

const HOME = "/material-procurement/supplier-dashboard";
const ALLOWED = [HOME, "/material-procurement/available-requirements", "/material-procurement/quotation-history", "/account", "/notifications"];

function PortalGuard({ children }) {
  const pathname = usePathname();
  const router = useRouter();
  const home = pathname === "/";
  const allowed = ALLOWED.some((path) => pathname === path || pathname.startsWith(`${path}/`));

  useEffect(() => {
    if (home) router.replace(HOME);
  }, [home, router]);

  if (home) return null;
  return allowed ? children : <PortalNotice reason="suppliers_only" />;
}

export { PortalGuard };
