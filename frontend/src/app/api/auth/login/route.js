import { NextResponse } from "next/server";
import { apiFetch } from "@/lib/api-server";
import { setSessionToken } from "@/lib/session";
import { ApiError } from "@/lib/api-error";

/**
 * BFF login (docs/12-Stack-Migration-Implementation-Plan.md Phase C §5):
 * calls the Laravel API's bearer-token login server-side, then keeps the
 * token in an httpOnly cookie instead of returning it to the browser. The
 * browser only ever learns who it logged in as, never the credential.
 */
export async function POST(request) {
  const { email, password, company_id: companyId } = await request.json();

  try {
    const data = await apiFetch("/auth/login", {
      method: "POST",
      token: null,
      body: JSON.stringify({
        email,
        password,
        company_id: companyId ?? undefined,
        device_name: "Annotech RMG Web",
      }),
    });

    await setSessionToken(data.access_token, data.expires_at);

    return NextResponse.json({ user: data.user, company_id: data.company_id });
  } catch (error) {
    if (error instanceof ApiError) {
      return NextResponse.json({ message: error.message, code: error.code }, { status: error.status });
    }

    throw error;
  }
}
