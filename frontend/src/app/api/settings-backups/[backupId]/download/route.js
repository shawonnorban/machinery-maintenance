import { getSessionToken } from "@/lib/session";
import { BASE_URL } from "@/lib/api-server";

/**
 * Settings → Backup & Restore (AnnotechERP 03/04): the backup zip, streamed
 * through from `SystemMaintenanceApiController::downloadBackup()` with the
 * session's token — the file has no public address of its own.
 */
export async function GET(request, { params }) {
  const { backupId } = await params;
  const token = await getSessionToken();

  const response = await fetch(`${BASE_URL}/settings/backups/${encodeURIComponent(backupId)}/download`, {
    headers: { ...(token ? { Authorization: `Bearer ${token}` } : {}) },
  });

  if (!response.ok) {
    return new Response(null, { status: response.status });
  }

  return new Response(response.body, {
    headers: {
      "Content-Type": response.headers.get("Content-Type") ?? "application/zip",
      "Content-Disposition": response.headers.get("Content-Disposition") ?? "attachment",
    },
  });
}
