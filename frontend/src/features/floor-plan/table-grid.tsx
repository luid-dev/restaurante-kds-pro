import { User, Users } from "lucide-react"
import type { CSSProperties } from "react"

import { Amount } from "@/components/shared/amount"
import { ElapsedTimer } from "@/components/shared/elapsed-timer"
import { StatusDot } from "@/components/shared/status-dot"
import { cn } from "@/lib/utils"
import type { RestaurantTable, TableStatus } from "@/types/operations"
import type { StatusTone } from "@/types/realtime"

const STATUS: Record<TableStatus, { label: string; tone: StatusTone; className: string }> = {
  AVAILABLE: { label: "Libre", tone: "neutral", className: "hover:border-foreground/30" },
  OCCUPIED: { label: "Ocupada", tone: "success", className: "border-status-occupied/40 bg-status-occupied/5 hover:border-status-occupied/70" },
  PRE_BILL: { label: "Pre-cuenta", tone: "warning", className: "border-status-prebill/50 bg-status-prebill/5 hover:border-status-prebill/80" },
  RESERVED: { label: "Reservada", tone: "muted", className: "border-dashed hover:border-foreground/30" },
}

function TableCell({ table, onSelect }: { readonly table: RestaurantTable; readonly onSelect: (table: RestaurantTable) => void }) {
  const status = STATUS[table.status]
  const style: CSSProperties = { gridColumnStart: table.grid_x + 1, gridRowStart: table.grid_y + 1 }

  return (
    <button
      type="button"
      style={style}
      onClick={() => onSelect(table)}
      className={cn(
        "flex min-h-32 flex-col justify-between gap-2 rounded-md border bg-background p-3 text-left transition-colors focus-visible:outline-2 focus-visible:outline-ring",
        status.className,
      )}
      aria-label={`Mesa ${table.code}, ${status.label}`}
    >
      <div className="flex items-start justify-between gap-2">
        <span className="font-mono text-base font-semibold tabular-nums">{table.code}</span>
        <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <StatusDot tone={status.tone} />
          {status.label}
        </span>
      </div>

      {table.order === null ? (
        <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <Users className="size-4" strokeWidth={1.5} aria-hidden="true" />
          <span className="font-mono tabular-nums">{table.capacity}</span> sillas
        </span>
      ) : (
        <div className="flex flex-col gap-1 text-xs">
          <div className="flex items-center justify-between gap-2 text-muted-foreground">
            <span className="flex items-center gap-1.5">
              <Users className="size-4" strokeWidth={1.5} aria-hidden="true" />
              <span className="font-mono tabular-nums">
                {table.order.diners_count}/{table.capacity}
              </span>
            </span>
            {table.opened_at === null ? null : <ElapsedTimer since={table.opened_at} />}
          </div>
          <span className="flex items-center gap-1.5 truncate text-muted-foreground">
            <User className="size-4 shrink-0" strokeWidth={1.5} aria-hidden="true" />
            <span className="truncate">{table.order.waiter_name ?? "-"}</span>
          </span>
          <div className="flex items-center justify-between gap-2 border-t pt-1.5">
            <span className="font-mono text-muted-foreground tabular-nums">{table.order.order_number}</span>
            <Amount value={table.order.total_amount} className="text-sm font-semibold" />
          </div>
        </div>
      )}
    </button>
  )
}

/** Plano geométrico: cada mesa ocupa su celda (grid_x, grid_y) definida en el backend. */
export function TableGrid({ tables, onSelect }: { readonly tables: readonly RestaurantTable[]; readonly onSelect: (table: RestaurantTable) => void }) {
  const columns = Math.max(1, ...tables.map((table) => table.grid_x + 1))
  const style: CSSProperties = { gridTemplateColumns: `repeat(${String(columns)}, minmax(11rem, 1fr))` }

  return (
    <div className="overflow-x-auto">
      <div className="grid gap-3" style={style}>
        {tables.map((table) => (
          <TableCell key={table.id} table={table} onSelect={onSelect} />
        ))}
      </div>
    </div>
  )
}
