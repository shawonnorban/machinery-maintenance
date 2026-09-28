"use server";

import { revalidatePath } from "next/cache";
import { platformApiFetch } from "@/lib/platform-api-server";

async function markAllRead() {
  await platformApiFetch("/notifications/read-all", { method: "POST" });
  revalidatePath("/platform/notifications");
  return { status: "success" };
}

export { markAllRead };
