import type { ReactNode } from "react"

import { Amount } from "@/components/shared/amount"
import { StatusDot } from "@/components/shared/status-dot"
import type { FloorPlan } from "@/types/operations"
import type { StatusTone } from "@/types/realtime"

function Metric({ label, tone, children }: { readonly label: string; readonly tone?: StatusTone; readonly children: ReactNode }) {
  return (
    <div className="flex min-w-0 flex-col gap-0.5 border-r px-4 py-2.5 last:border-r-0">
      <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
        {tone === undefined ? null : <StatusDot tone={tone} />}
        {label}
      </span>
      <span className="font-mono text-lg font-semibold tabular-nums">{children}</span>
    </div>
  )
}

/** Barra de estado del salón: contadores en vivo y venta en curso. */
export function FloorSummaryBar({ summary }: { readonly summary: FloorPlan["summary"] }) {
  return (
    <section aria-label="Resumen del salón" className="grid grid-cols-2 rounded-md border sm:grid-cols-5">
      <Metric label="Libres" tone="neutral">
        {summary.available}
      </Metric>
      <Metric label="Ocupadas" tone="success">
        {summary.occupied}
      </Metric>
      <Metric label="En pre-cuenta" tone="warning">
        {summary.pre_bill}
      </Metric>
      <Metric label="Venta en curso">
        <Amount value={summary.open_sales_total} />
      </Metric>
      <Metric label="Saldo por cobrar">
        <Amount value={summary.pending_balance} />
      </Metric>
    </section>
  )
}
