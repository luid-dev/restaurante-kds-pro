import type { ConnectionStatus } from "laravel-echo"

/** Estado del canal WebSocket (Reverb). "disabled" = sin VITE_REVERB_APP_KEY. */
export type RealtimeStatus = ConnectionStatus | "disabled"

export type StatusTone = "neutral" | "success" | "warning" | "danger" | "muted"
