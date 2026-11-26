import { PageStatus } from "@/components/layout/page-status";

/** An address that matches no route at all. */
export default function NotFound() {
  return <PageStatus kind="not-found" />;
}
