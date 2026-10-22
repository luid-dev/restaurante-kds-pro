import { useQuery } from "@tanstack/react-query"
import { MonitorSmartphone } from "lucide-react"
import type { ReactNode } from "react"

import { systemQueries } from "@/api/system"
import { LiveClock } from "@/components/layout/live-clock"
import { StatusDot } from "@/components/shared/status-dot"
import { useRealtimeConnection } from "@/hooks/use-realtime-connection"
import { useTerminalStore } from "@/stores/terminal-store"
import type { RealtimeStatus, StatusTone } from "@/types/realtime"

const REALTIME_LABELS: Record<RealtimeStatus, { label: string; tone: StatusTone }> = {
  connected: { label: "Conectado", tone: "success" },
  connecting: { label: "Conectando", tone: "warning" },
  reconnecting: { label: "Reconectando", tone: "warning" },
  disconnected: { label: "Sin conexión", tone: "danger" },
  failed: { label: "Conexión fallida", tone: "danger" },
  disabled: { label: "No configurado", tone: "muted" },
}

function Row({ label, children }: { readonly label: string; readonly children: ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-4 py-2.5">
      <dt className="text-sm text-muted-foreground">{label}</dt>
      <dd className="flex items-center gap-2 text-sm">{children}</dd>
    </div>
  )
}

/** Estado técnico de la terminal antes de ingresar: empresa, hora y conectividad. */
export function TerminalOverview() {
  const companyRuc = useTerminalStore((state) => state.companyRuc)
  const health = useQuery(systemQueries.health())
  const realtime = REALTIME_LABELS[useRealtimeConnection().status]

  return (
    <div className="flex h-full flex-col gap-6">
      <div>
        <p className="flex items-center gap-2 text-sm text-muted-foreground">
          <MonitorSmartphone className="size-4" strokeWidth={1.5} aria-hidden="true" />
          Terminal de restaurante
        </p>
        <h1 className="mt-3 text-xl font-semibold tracking-tight">Acceso al sistema</h1>
        <p className="mt-1 max-w-sm text-sm text-muted-foreground">
          Mozos y cocina ingresan con su PIN de 6 dígitos. Gerencia y caja también pueden usar su correo y contraseña.
        </p>
      </div>

      <dl className="divide-y border-y">
        <Row label="Empresa vinculada">
          {companyRuc === null ? (
            <span className="text-muted-foreground">Sin vincular</span>
          ) : (
            <span className="font-mono tabular-nums">{companyRuc}</span>
          )}
        </Row>
        <Row label="Hora de Lima">
          <LiveClock />
        </Row>
        <Row label="Servidor">
          <StatusDot tone={health.isSuccess ? "success" : health.isError ? "danger" : "warning"} />
          {health.isSuccess ? (
            <>
              <span>En línea</span>
              <span className="font-mono tabular-nums text-muted-foreground">{health.data.latencyMs} ms</span>
            </>
          ) : (
            <span>{health.isError ? "Sin respuesta" : "Verificando"}</span>
          )}
        </Row>
        <Row label="Tiempo real">
          <StatusDot tone={realtime.tone} />
          <span>{realtime.label}</span>
        </Row>
      </dl>

      <p className="mt-auto text-xs text-muted-foreground">
        Por seguridad, una sesión iniciada con PIN se cierra tras 30 minutos sin actividad en la terminal.
      </p>
    </div>
  )
}
