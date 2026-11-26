import { PageStatus } from "@/components/layout/page-status";

/** Inside the app (sidebar kept): a screen that called notFound(), or an address under a screen that leads nowhere. */
export default function NotFound() {
  return <PageStatus kind="not-found" />;
}
