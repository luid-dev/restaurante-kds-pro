import { useQuery } from "@tanstack/react-query"
import { Radio, Server } from "lucide-react"
import type { ReactNode } from "react"

import { systemQueries } from "@/api/system"
import { StatusDot } from "@/components/shared/status-dot"
import { useRealtimeConnection } from "@/hooks/use-realtime-connection"
import { cn } from "@/lib/utils"
import type { RealtimeStatus, StatusTone } from "@/types/realtime"

const REALTIME_STATES: Record<RealtimeStatus, { label: string; tone: StatusTone }> = {
  connected: { label: "Conectado", tone: "success" },
  connecting: { label: "Conectando", tone: "warning" },
  reconnecting: { label: "Reconectando", tone: "warning" },
  disconnected: { label: "Sin conexión", tone: "danger" },
  failed: { label: "Conexión fallida", tone: "danger" },
  disabled: { label: "No configurado", tone: "muted" },
}

function Segment({ children, className }: { readonly children: ReactNode; readonly className?: string }) {
  return <div className={cn("flex h-full items-center gap-1.5 border-r px-3 last:border-r-0", className)}>{children}</div>
}

function RealtimeSegment() {
  const { status, endpoint, socketId } = useRealtimeConnection()
  const state = REALTIME_STATES[status]

  return (
    <>
      <Segment>
        <Radio className="size-3.5 text-muted-foreground" strokeWidth={1.5} aria-hidden="true" />
        <span className="text-muted-foreground">Tiempo real</span>
        <StatusDot tone={state.tone} pulse={status === "connecting" || status === "reconnecting"} />
        <output aria-live="polite" className={cn(state.tone === "danger" && "text-status-delayed")}>
          {state.label}
        </output>
      </Segment>
      {endpoint === null ? null : (
        <Segment className="hidden md:flex">
          <span className="text-muted-foreground">Reverb</span>
          <span className="font-mono tabular-nums">{endpoint}</span>
        </Segment>
      )}
      {socketId === undefined ? null : (
        <Segment className="hidden lg:flex">
          <span className="text-muted-foreground">Socket</span>
          <span className="font-mono tabular-nums">{socketId}</span>
        </Segment>
      )}
    </>
  )
}

function ApiSegment() {
  const health = useQuery(systemQueries.health())

  const tone: StatusTone = health.isSuccess ? "success" : health.isError ? "danger" : "warning"
  const label = health.isSuccess ? `API ${health.data.api_version}` : health.isError ? "API sin respuesta" : "API verificando"

  return (
    <Segment>
      <Server className="size-3.5 text-muted-foreground" strokeWidth={1.5} aria-hidden="true" />
      <StatusDot tone={tone} />
      <span>{label}</span>
      {health.isSuccess ? (
        <span className="font-mono tabular-nums text-muted-foreground">{health.data.latencyMs} ms</span>
      ) : null}
      {health.isError && health.error.traceId !== null ? (
        <span className="hidden font-mono text-muted-foreground xl:inline" title="trace_id del backend">
          {health.error.traceId}
        </span>
      ) : null}
    </Segment>
  )
}

/** Barra de estado inferior: conexión WebSocket en vivo y disponibilidad de la API. */
export function StatusBar() {
  return (
    <footer
      aria-label="Estado de la terminal"
      className="flex h-7 shrink-0 items-stretch border-t bg-sidebar text-xs text-sidebar-foreground"
    >
      <RealtimeSegment />
      <ApiSegment />
      <div className="ml-auto flex items-center px-3 font-mono tabular-nums text-muted-foreground">v0.1.0</div>
    </footer>
  )
}
