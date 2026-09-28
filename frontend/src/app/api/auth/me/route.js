import { NextResponse } from "next/server";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/**
 * The one way a Client Component learns who is signed in: the bearer token
 * lives only in the httpOnly cookie, so a client component can't call
 * Laravel's `/auth/me` itself — it calls this proxy, which does.
 */
export async function GET() {
  try {
    const data = await apiFetch("/auth/me");
    return NextResponse.json(data);
  } catch (error) {
    if (error instanceof ApiError) {
      return NextResponse.json({ message: error.message, code: error.code }, { status: error.status });
    }

    throw error;
  }
}
