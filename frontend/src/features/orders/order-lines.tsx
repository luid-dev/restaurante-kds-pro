import { Ban } from "lucide-react"

import { Amount } from "@/components/shared/amount"
import { Badge } from "@/components/ui/badge"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { formatQuantity } from "@/lib/money"
import { cn } from "@/lib/utils"
import { ITEM_STATUS_LABELS, type ItemStatus, type OrderItem } from "@/types/operations"

const STATUS_CLASSES: Record<ItemStatus, string> = {
  PENDING: "border-border text-muted-foreground",
  PREPARING: "border-status-prebill/50 text-status-prebill",
  READY: "border-status-occupied/50 text-status-occupied",
  SERVED: "border-border text-muted-foreground",
  VOIDED: "border-status-delayed/40 text-status-delayed",
}

export function ItemStatusBadge({ status }: { readonly status: ItemStatus }) {
  return (
    <Badge variant="outline" className={cn("rounded-sm px-1.5 font-normal", STATUS_CLASSES[status])}>
      {ITEM_STATUS_LABELS[status]}
    </Badge>
  )
}

interface OrderLinesProps {
  readonly items: readonly OrderItem[]
  readonly onVoid?: (item: OrderItem) => void
}

/** Platos comandados con estado de cocina; los anulados quedan tachados (auditoría visible). */
export function OrderLines({ items, onVoid }: OrderLinesProps) {
  if (items.length === 0) {
    return <p className="px-1 py-6 text-center text-sm text-muted-foreground">Aún no se comandaron platos.</p>
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="w-12 text-right">Cant.</TableHead>
          <TableHead>Plato</TableHead>
          <TableHead className="w-32">Estado</TableHead>
          <TableHead className="w-24 text-right">Importe</TableHead>
          {onVoid === undefined ? null : <TableHead className="w-10" />}
        </TableRow>
      </TableHeader>
      <TableBody>
        {items.map((item) => {
          const voided = item.status === "VOIDED"

          return (
            <TableRow key={item.id} className={cn(voided && "text-muted-foreground")}>
              <TableCell className="text-right font-mono tabular-nums">{formatQuantity(item.quantity)}</TableCell>
              <TableCell className="whitespace-normal">
                <span className={cn(voided && "line-through")}>{item.menu_item.name}</span>
                {item.modifiers.length === 0 ? null : <span className="block text-xs text-muted-foreground">[{item.modifiers.join("] [")}]</span>}
                {item.notes === null ? null : <span className="block text-xs text-muted-foreground">Nota: {item.notes}</span>}
                {item.void_reason === null ? null : <span className="block text-xs text-status-delayed">Motivo: {item.void_reason}</span>}
              </TableCell>
              <TableCell>
                <ItemStatusBadge status={item.status} />
              </TableCell>
              <TableCell className={cn("text-right", voided && "line-through")}>
                <Amount value={item.line_total} />
              </TableCell>
              {onVoid === undefined ? null : (
                <TableCell>
                  {voided || item.paid_quantity !== "0.00" ? null : (
                    <button
                      type="button"
                      onClick={() => onVoid(item)}
                      className="inline-flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-status-delayed"
                      aria-label={`Anular ${item.menu_item.name}`}
                    >
                      <Ban className="size-4" strokeWidth={1.5} aria-hidden="true" />
                    </button>
                  )}
                </TableCell>
              )}
            </TableRow>
          )
        })}
      </TableBody>
    </Table>
  )
}
