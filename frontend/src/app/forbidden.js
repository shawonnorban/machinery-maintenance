import { PageStatus } from "@/components/layout/page-status";

/** A forbidden() outside the app shell. */
export default function Forbidden() {
  return <PageStatus kind="forbidden" />;
}
