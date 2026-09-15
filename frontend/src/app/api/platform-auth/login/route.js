import { NextResponse } from "next/server";
import { platformApiFetch } from "@/lib/platform-api-server";
import { setPlatformSessionToken } from "@/lib/platform-session";
import { ApiError } from "@/lib/api-error";

/**
 * BFF login for the platform console — mirrors `app/api/auth/login/route.js`
 * exactly, against `PlatformAuthApiController::login` instead of the
 * tenant's own `/auth/login`.
 */
export async function POST(request) {
  const { email, password } = await request.json();

  try {
    const data = await platformApiFetch("/auth/login", {
      method: "POST",
      token: null,
      body: JSON.stringify({ email, password, device_name: "Annotech RMG Platform Console" }),
    });

    await setPlatformSessionToken(data.access_token, data.expires_at);

    return NextResponse.json({ user: data.user });
  } catch (error) {
    if (error instanceof ApiError) {
      return NextResponse.json({ message: error.message, code: error.code }, { status: error.status });
    }

    throw error;
  }
}
