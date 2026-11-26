import { PageStatus } from "@/components/layout/page-status";

/** Inside the app (sidebar kept): a screen the caller's role does not include (forbidden()). */
export default function Forbidden() {
  return <PageStatus kind="forbidden" />;
}
