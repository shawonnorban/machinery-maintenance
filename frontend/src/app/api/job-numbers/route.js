import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * The Job No dialog (26NCL00001) of each sheet: `?module=os|pb|ds|es`
 * (Order Summary, Pre-Budget, Dev Sample Status, Export Statement) and
 * `factory=NCL` passed on to that module's `jobNumbers()` — no factory gives
 * every factory at once — and the answer handed back as it is.
 */
const PATHS = {
  os: "/order-summary/job-numbers",
  pb: "/pre-budget/job-numbers",
  ds: "/dev-sample/job-numbers",
  es: "/export-statement/job-numbers",
};

export async function GET(request) {
  const token = await getSessionToken();
  if (!token) return Response.json({ message: "Unauthenticated." }, { status: 401 });

  const params = new URL(request.url).searchParams;
  const path = PATHS[params.get("module") ?? ""];
  if (!path) return Response.json({ message: "Not found." }, { status: 404 });
  const factory = params.get("factory");
  const response = await fetch(`${BASE_URL}${path}${factory ? `?factory=${encodeURIComponent(factory)}` : ""}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    cache: "no-store",
  });

  return new Response(await response.text(), { status: response.status, headers: { "Content-Type": "application/json" } });
}
