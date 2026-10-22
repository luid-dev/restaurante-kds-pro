import { useSyncExternalStore } from "react"

import { getRealtimeStatus, getSocketId, realtimeEndpoint, subscribeRealtimeStatus } from "@/api/echo"
import type { RealtimeStatus } from "@/types/realtime"

export interface RealtimeConnection {
  readonly status: RealtimeStatus
  readonly endpoint: string | null
  readonly socketId: string | undefined
}

/**
 * Estado en vivo de la conexión WebSocket con Reverb. Se suscribe directamente
 * al conector de Echo: solo re-renderiza cuando cambia el estado.
 */
export function useRealtimeConnection(): RealtimeConnection {
  const status = useSyncExternalStore(subscribeRealtimeStatus, getRealtimeStatus, getRealtimeStatus)

  return {
    status,
    endpoint: realtimeEndpoint,
    socketId: status === "connected" ? getSocketId() : undefined,
  }
}
