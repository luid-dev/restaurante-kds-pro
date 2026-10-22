import { queryOptions } from "@tanstack/react-query"
import type { AxiosResponse } from "axios"

import { ApiError, ensureCsrfCookie, http, unwrapWithMeta } from "@/api/http"
import { sessionMetaSchema, sessionUserSchema, type Credentials, type PinLogin, type Session } from "@/types/auth"

/**
 * Autenticación por sesión Sanctum (cookie HttpOnly + CSRF): el SPA nunca
 * recibe ni guarda tokens. La sesión vigente se lee siempre desde /me.
 */

export const SESSION_QUERY_KEY = ["session", "me"] as const

function toSession(response: AxiosResponse<unknown>): Session {
  const { data, meta } = unwrapWithMeta(response, sessionUserSchema, sessionMetaSchema)

  return { user: data, authMethod: meta.auth_method, pinIdleTimeoutMinutes: meta.pin_idle_timeout_minutes }
}

async function fetchSession(signal: AbortSignal): Promise<Session | null> {
  try {
    return toSession(await http.get<unknown>("/me", { signal }))
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      return null
    }

    throw error
  }
}

export const sessionQueries = {
  current: () =>
    queryOptions({
      queryKey: SESSION_QUERY_KEY,
      queryFn: ({ signal }) => fetchSession(signal),
      staleTime: Number.POSITIVE_INFINITY,
      retry: false,
    }),
}

export async function loginWithCredentials(credentials: Credentials): Promise<Session> {
  await ensureCsrfCookie()

  return toSession(await http.post<unknown>("/auth/login", credentials))
}

export async function loginWithPin(payload: PinLogin): Promise<Session> {
  await ensureCsrfCookie()

  return toSession(await http.post<unknown>("/auth/pin-login", payload))
}

export async function logout(): Promise<void> {
  await http.post("/auth/logout")
}
