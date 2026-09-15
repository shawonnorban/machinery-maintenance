/**
 * Mirrors the Laravel API's one error envelope (app/Shared/Http/Api/ApiResponse.php):
 * `{ success: false, message, code, errors?, meta }`. Thrown by `apiFetch` so
 * every caller can branch on `error.code` the same way the API spec intends,
 * instead of re-parsing a Response object at each call site.
 */
class ApiError extends Error {
  constructor(status, body) {
    super(body?.message ?? "Request failed");
    this.name = "ApiError";
    this.status = status;
    this.code = body?.code ?? "UNKNOWN_ERROR";
    this.errors = body?.errors ?? {};
  }
}

export { ApiError };
