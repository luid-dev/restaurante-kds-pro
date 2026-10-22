import { LoaderCircle, Minus, Plus, Send, Trash2 } from "lucide-react"

import { Amount } from "@/components/shared/amount"
import { ElapsedTimer } from "@/components/shared/elapsed-timer"
import { Button } from "@/components/ui/button"
import { Kbd } from "@/components/ui/kbd"
import { ScrollArea } from "@/components/ui/scroll-area"
import { OrderLines } from "@/features/orders/order-lines"
import { OrderTotals } from "@/features/orders/order-totals"
import { draftCents, usesPackaging, type DraftLine } from "@/features/pos-waiter/draft"
import { fromCents, lineCents } from "@/lib/money"
import type { Order, OrderItem } from "@/types/operations"

interface OrderTicketPanelProps {
  readonly order: Order
  readonly draft: readonly DraftLine[]
  readonly sending: boolean
  readonly onQuantity: (key: string, quantity: number) => void
  readonly onRemove: (key: string) => void
  readonly onSend: () => void
  readonly onVoid?: (item: OrderItem) => void
}

/** Comanda de la mesa: lo ya enviado (con estado de cocina) y la ronda nueva por enviar. */
export function OrderTicketPanel({ order, draft, sending, onQuantity, onRemove, onSend, onVoid }: OrderTicketPanelProps) {
  const location = order.table?.code ?? (order.order_type === "DELIVERY" ? "Delivery" : "Para llevar")
  const pending = fromCents(draftCents(draft, order.order_type))

  return (
    <aside aria-label="Comanda" className="flex min-h-0 flex-col rounded-md border">
      <header className="flex items-start justify-between gap-3 border-b px-4 py-3">
        <div>
          <p className="font-mono text-lg font-semibold tabular-nums">{location}</p>
          <p className="text-xs text-muted-foreground">
            <span className="font-mono tabular-nums">{order.order_number}</span> · {order.waiter?.name ?? "-"} ·{" "}
            <span className="font-mono tabular-nums">{order.diners_count}</span> pax
            {order.customer_name === null ? null : ` · ${order.customer_name}`}
          </p>
        </div>
        <ElapsedTimer since={order.opened_at} className="text-sm text-muted-foreground" />
      </header>

      <ScrollArea className="min-h-0 flex-1">
        <div className="flex flex-col gap-3 px-3 py-3">
          <OrderLines items={order.items ?? []} {...(onVoid === undefined ? {} : { onVoid })} />

          <section aria-label="Ronda por enviar" className="rounded-md border border-dashed">
            <h3 className="border-b border-dashed px-3 py-2 text-xs font-medium text-muted-foreground">
              Ronda nueva (sin enviar) <span className="font-mono tabular-nums">{draft.length}</span>
            </h3>
            {draft.length === 0 ? (
              <p className="px-3 py-4 text-center text-xs text-muted-foreground">Toque un plato de la carta para agregarlo.</p>
            ) : (
              <ul className="divide-y divide-dashed">
                {draft.map((line) => (
                  <li key={line.key} className="flex items-start gap-2 px-3 py-2">
                    <div className="flex items-center gap-1">
                      <Button size="icon-xs" variant="outline" onClick={() => onQuantity(line.key, line.quantity - 1)} aria-label="Restar" disabled={line.quantity <= 1}>
                        <Minus aria-hidden="true" />
                      </Button>
                      <span className="w-6 text-center font-mono text-sm tabular-nums">{line.quantity}</span>
                      <Button size="icon-xs" variant="outline" onClick={() => onQuantity(line.key, line.quantity + 1)} aria-label="Sumar">
                        <Plus aria-hidden="true" />
                      </Button>
                    </div>
                    <div className="min-w-0 flex-1 text-sm">
                      <p className="leading-snug">{line.menuItem.name}</p>
                      {line.modifiers.length === 0 ? null : <p className="font-mono text-[11px] text-muted-foreground">[{line.modifiers.join("] [")}]</p>}
                      {line.notes === null ? null : <p className="text-xs text-muted-foreground">Nota: {line.notes}</p>}
                    </div>
                    <Amount
                      value={fromCents(
                        lineCents(line.menuItem.sale_price, `${String(line.quantity)}.00`) +
                          (usesPackaging(line, order.order_type) ? lineCents(line.menuItem.packaging_surcharge, `${String(line.quantity)}.00`) : 0),
                      )}
                      className="text-sm"
                    />
                    <Button size="icon-xs" variant="ghost" onClick={() => onRemove(line.key)} aria-label={`Quitar ${line.menuItem.name}`}>
                      <Trash2 aria-hidden="true" />
                    </Button>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </div>
      </ScrollArea>

      <footer className="flex flex-col gap-3 border-t px-4 py-3">
        <OrderTotals order={order} />
        {draft.length === 0 ? null : (
          <div className="flex items-center justify-between text-sm">
            <span className="text-muted-foreground">Ronda por enviar (estimado)</span>
            <Amount value={pending} className="font-semibold" />
          </div>
        )}
        <Button size="lg" className="h-12 w-full justify-between" disabled={draft.length === 0 || sending} onClick={onSend}>
          <span className="flex items-center gap-2">
            {sending ? <LoaderCircle className="animate-spin" aria-hidden="true" /> : <Send aria-hidden="true" />}
            Enviar comanda a cocina
          </span>
          <Kbd className="bg-primary-foreground/15 text-primary-foreground">F4</Kbd>
        </Button>
      </footer>
    </aside>
  )
}
