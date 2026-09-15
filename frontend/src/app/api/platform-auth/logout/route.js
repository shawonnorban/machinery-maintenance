import { NextResponse } from "next/server";
import { platformApiFetch } from "@/lib/platform-api-server";
import { getPlatformSessionToken, clearPlatformSessionToken } from "@/lib/platform-session";

export async function POST() {
  const token = await getPlatformSessionToken();

  if (token) {
    await platformApiFetch("/auth/logout", { method: "POST" }).catch(() => null);
  }

  await clearPlatformSessionToken();

  return NextResponse.json({ success: true });
}
