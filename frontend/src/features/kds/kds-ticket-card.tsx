import { Check, ChefHat, Flame, PackageCheck, Printer } from "lucide-react"

import { Button } from "@/components/ui/button"
import { useNow } from "@/hooks/use-now"
import { formatElapsed, secondsSince } from "@/lib/format"
import { formatQuantity } from "@/lib/money"
import { cn } from "@/lib/utils"
import { ITEM_STATUS_LABELS, type ItemStatus, type KdsItem, type KdsTicket } from "@/types/operations"

type Target = Extract<ItemStatus, "PREPARING" | "READY" | "SERVED">

const WARNING_SECONDS = 10 * 60
const DELAYED_SECONDS = 15 * 60

const NEXT: Partial<Record<ItemStatus, Target>> = { PENDING: "PREPARING", PREPARING: "READY" }

interface KdsTicketCardProps {
  readonly ticket: KdsTicket
  readonly busy: boolean
  readonly canUpdate: boolean
  readonly onAdvance: (itemIds: readonly string[], status: Target) => void
  readonly onPrint: (ticket: KdsTicket) => void
}

function ItemRow({ item, disabled, onAdvance }: { readonly item: KdsItem; readonly disabled: boolean; readonly onAdvance: KdsTicketCardProps["onAdvance"] }) {
  const next = NEXT[item.status]

  return (
    <li>
      <button
        type="button"
        disabled={disabled || next === undefined}
        onClick={() => (next === undefined ? undefined : onAdvance([item.id], next))}
        className={cn(
          "flex w-full items-start gap-3 px-3 py-2 text-left transition-colors hover:bg-zinc-900 disabled:cursor-default disabled:hover:bg-transparent",
          item.status === "READY" && "text-zinc-500",
        )}
        aria-label={`${item.name}: ${ITEM_STATUS_LABELS[item.status]}${next === undefined ? "" : `. Marcar ${ITEM_STATUS_LABELS[next]}`}`}
      >
        <span className="w-8 shrink-0 text-right font-mono text-xl leading-6 font-semibold tabular-nums">{formatQuantity(item.quantity)}</span>
        <span className="min-w-0 flex-1">
          <span className={cn("block text-base leading-6 font-medium", item.status === "READY" && "line-through decoration-zinc-600")}>{item.name}</span>
          {item.modifiers.length === 0 ? null : <span className="block font-mono text-xs text-amber-300/90">[{item.modifiers.join("] [")}]</span>}
          {item.notes === null ? null : <span className="block text-xs text-zinc-300">Nota: {item.notes}</span>}
        </span>
        <span
          className={cn(
            "mt-0.5 flex shrink-0 items-center gap-1 rounded-sm border px-1.5 py-0.5 text-[11px]",
            item.status === "PENDING" && "border-zinc-700 text-zinc-400",
            item.status === "PREPARING" && "border-amber-500/50 text-amber-300",
            item.status === "READY" && "border-emerald-500/50 text-emerald-400",
          )}
        >
          {item.status === "PREPARING" ? <Flame className="size-3" strokeWidth={1.5} aria-hidden="true" /> : null}
          {item.status === "READY" ? <Check className="size-3" strokeWidth={1.5} aria-hidden="true" /> : null}
          {ITEM_STATUS_LABELS[item.status]}
        </span>
      </button>
    </li>
  )
}

/** Ticket de una ronda: el borde cambia con la antigüedad (neutral, ámbar a 10 min, rojo a 15 min). */
export function KdsTicketCard({ ticket, busy, canUpdate, onAdvance, onPrint }: KdsTicketCardProps) {
  const now = useNow()
  const elapsed = ticket.sent_at === null ? 0 : secondsSince(ticket.sent_at, now)
  const delayed = elapsed >= DELAYED_SECONDS
  const warning = !delayed && elapsed >= WARNING_SECONDS
  const ids = (statuses: readonly ItemStatus[]) => ticket.items.filter((item) => statuses.includes(item.status)).map((item) => item.id)
  const pending = ids(["PENDING"])
  const notReady = ids(["PENDING", "PREPARING"])
  const allReady = notReady.length === 0
  const disabled = busy || !canUpdate

  return (
    <article
      className={cn(
        "flex flex-col rounded-md border bg-zinc-950",
        delayed ? "border-red-500/60 bg-red-950/20" : warning ? "border-amber-500/60" : "border-zinc-800",
        allReady && "border-emerald-600/60",
      )}
      aria-label={`Comanda ${ticket.order.order_number}`}
    >
      <header className={cn("flex items-start justify-between gap-2 border-b px-3 py-2", delayed ? "border-red-500/40" : warning ? "border-amber-500/40" : "border-zinc-800")}>
        <div className="min-w-0">
          <p className="font-mono text-xl leading-7 font-semibold tabular-nums">
            {ticket.order.table_code ?? (ticket.order.order_type === "DELIVERY" ? "DELIVERY" : "LLEVAR")}
          </p>
          <p className="truncate text-xs text-zinc-400">
            <span className="font-mono tabular-nums">{ticket.order.order_number}</span> · {ticket.order.waiter_name ?? "-"}
            {ticket.order.customer_name === null ? null : ` · ${ticket.order.customer_name}`}
          </p>
        </div>
        <div className="flex items-center gap-1">
          <time
            dateTime={ticket.sent_at ?? undefined}
            className={cn("font-mono text-xl leading-7 font-semibold tabular-nums", delayed ? "text-red-400" : warning ? "text-amber-300" : "text-zinc-100")}
          >
            {formatElapsed(elapsed)}
          </time>
          <Button variant="ghost" size="icon-sm" className="text-zinc-400 hover:bg-zinc-900 hover:text-zinc-100" onClick={() => onPrint(ticket)} aria-label="Imprimir comanda">
            <Printer aria-hidden="true" />
          </Button>
        </div>
      </header>

      <ul className="flex-1 divide-y divide-zinc-900">
        {ticket.items.map((item) => (
          <ItemRow key={item.id} item={item} disabled={disabled} onAdvance={onAdvance} />
        ))}
      </ul>

      <footer className="grid grid-cols-2 gap-px border-t border-zinc-800 bg-zinc-800">
        {allReady ? (
          <button
            type="button"
            disabled={disabled}
            onClick={() => onAdvance(ids(["READY"]), "SERVED")}
            className="col-span-2 flex h-11 items-center justify-center gap-2 bg-emerald-950/60 text-sm font-medium text-emerald-300 hover:bg-emerald-900/60 disabled:opacity-50"
          >
            <PackageCheck className="size-4" strokeWidth={1.5} aria-hidden="true" />
            Despachar al salón
          </button>
        ) : (
          <>
            <button
              type="button"
              disabled={disabled || pending.length === 0}
              onClick={() => onAdvance(pending, "PREPARING")}
              className="flex h-11 items-center justify-center gap-2 bg-zinc-950 text-sm text-zinc-200 hover:bg-zinc-900 disabled:text-zinc-600"
            >
              <ChefHat className="size-4" strokeWidth={1.5} aria-hidden="true" />
              En preparación
            </button>
            <button
              type="button"
              disabled={disabled}
              onClick={() => onAdvance(notReady, "READY")}
              className="flex h-11 items-center justify-center gap-2 bg-zinc-950 text-sm font-medium text-emerald-300 hover:bg-zinc-900 disabled:text-zinc-600"
            >
              <Check className="size-4" strokeWidth={1.5} aria-hidden="true" />
              Listo para servir
            </button>
          </>
        )}
      </footer>
    </article>
  )
}
