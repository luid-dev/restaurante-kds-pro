import Echo from "laravel-echo"
import Pusher from "pusher-js"
import type { ChannelAuthorizationHandler } from "pusher-js"
import { z } from "zod"

import { http, toApiError } from "@/api/http"
import { env } from "@/lib/env"
import type { RealtimeStatus } from "@/types/realtime"

const channelAuthSchema = z.object({
  auth: z.string(),
  channel_data: z.string().optional(),
  shared_secret: z.string().optional(),
})

/**
 * Autoriza canales privados contra /api/broadcasting/auth reutilizando el
 * cliente HTTP (cookie de sesión Sanctum + X-XSRF-TOKEN).
 */
const authorizeChannel: ChannelAuthorizationHandler = (params, callback) => {
  http
    .post<unknown>("/broadcasting/auth", { socket_id: params.socketId, channel_name: params.channelName }, { baseURL: "/api" })
    .then((response) => {
      const auth = channelAuthSchema.safeParse(response.data)

      if (!auth.success) {
        callback(new Error("Respuesta de autorización de canal inválida."), null)

        return
      }

      const { channel_data, shared_secret } = auth.data
      callback(null, {
        auth: auth.data.auth,
        ...(channel_data === undefined ? {} : { channel_data }),
        ...(shared_secret === undefined ? {} : { shared_secret }),
      })
    })
    .catch((error: unknown) => {
      callback(toApiError(error), null)
    })
}

function createEcho(): Echo<"reverb"> | null {
  const config = env.realtime

  if (config === null) {
    return null
  }

  return new Echo({
    broadcaster: "reverb",
    Pusher,
    key: config.key,
    wsHost: config.host,
    wsPort: config.port,
    wssPort: config.port,
    forceTLS: config.forceTLS,
    // Un solo transporte según el esquema: sin reintentos inútiles por wss contra un servidor ws.
    enabledTransports: config.forceTLS ? ["wss"] : ["ws"],
    channelAuthorization: { customHandler: authorizeChannel },
  })
}

/** Una sola conexión WebSocket por pestaña (se crea al cargar el módulo). */
export const echo = createEcho()

export const realtimeEndpoint = env.realtime === null ? null : `${env.realtime.host}:${String(env.realtime.port)}`

export function getRealtimeStatus(): RealtimeStatus {
  return echo === null ? "disabled" : echo.connectionStatus()
}

export function subscribeRealtimeStatus(onChange: () => void): () => void {
  return echo === null ? () => undefined : echo.connector.onConnectionChange(onChange)
}

export function getSocketId(): string | undefined {
  return echo?.socketId()
}
