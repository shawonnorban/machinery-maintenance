import { notFound } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { TvDisplay } from "@/components/productiontracking/tv-display";

export const metadata = { title: "Production TV" };

/**
 * A factory-floor TV (Production Tracking → TV Dashboard Show, AnnotechERP
 * 30/04): opened by its display link alone — no ERP sign-in — and read-only.
 * It re-reads itself every refresh interval the screen was set to.
 */
export default async function TvPage({ params }) {
  const { token } = await params;
  if (!/^[A-Za-z0-9]{40}$/.test(token)) notFound();
  let data;
  try {
    data = await apiFetch(`/public/production-tracking/tv/${token}`, { token: null });
  } catch {
    notFound();
  }
  return <TvDisplay data={data} />;
}
