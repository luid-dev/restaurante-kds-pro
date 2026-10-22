import type { ReactNode } from "react"

import { StatusDot } from "@/components/shared/status-dot"
import { cn } from "@/lib/utils"
import type { StatusTone } from "@/types/realtime"

export interface StatusLegendItem {
  readonly code: string
  readonly label: string
  readonly description: string
  readonly tone: StatusTone
  readonly sample?: ReactNode
}

const BORDER_CLASSES: Record<StatusTone, string> = {
  neutral: "border-l-status-available",
  success: "border-l-status-occupied",
  warning: "border-l-status-prebill",
  danger: "border-l-status-delayed bg-status-delayed/5",
  muted: "border-l-border",
}

interface StatusLegendProps {
  readonly title: string
  readonly items: readonly StatusLegendItem[]
}

/**
 * Leyenda de estados técnicos: borde lateral + punto + código, el mismo
 * lenguaje visual que usarán las mesas y las comandas.
 */
export function StatusLegend({ title, items }: StatusLegendProps) {
  return (
    <div>
      <h3 className="text-xs font-medium text-muted-foreground">{title}</h3>
      <ul className="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
        {items.map((item) => (
          <li
            key={item.code}
            className={cn("flex items-start gap-3 rounded-md border border-l-2 bg-background px-3 py-2.5", BORDER_CLASSES[item.tone])}
          >
            <StatusDot tone={item.tone} className="mt-1.5" />
            <div className="min-w-0 flex-1">
              <div className="flex items-baseline justify-between gap-2">
                <span className="text-sm font-medium">{item.label}</span>
                <code className="font-mono text-[11px] tabular-nums text-muted-foreground">{item.code}</code>
              </div>
              <p className="mt-0.5 text-xs text-muted-foreground">{item.description}</p>
              {item.sample === undefined ? null : <div className="mt-2">{item.sample}</div>}
            </div>
          </li>
        ))}
      </ul>
    </div>
  )
}
