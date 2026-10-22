import { useQuery } from "@tanstack/react-query"
import { ClipboardList } from "lucide-react"

import { operationQueries } from "@/api/operations"
import { Amount } from "@/components/shared/amount"
import { EmptyPanel } from "@/components/shared/empty-panel"
import { Skeleton } from "@/components/ui/skeleton"

/** Sin comanda en foco: el mozo elige una mesa ocupada o un pedido de mostrador. */
export function OrderPicker({ onPick }: { readonly onPick: (orderId: string) => void }) {
  const plan = useQuery(operationQueries.floorPlan())

  if (plan.isPending) {
    return <Skeleton className="h-40 w-full" />
  }

  const options = [
    ...(plan.data?.zones.flatMap((zone) => zone.tables) ?? []).flatMap((table) =>
      table.order === null ? [] : [{ id: table.order.id, label: table.code, number: table.order.order_number, total: table.order.total_amount }],
    ),
    ...(plan.data?.counter_orders ?? []).map((order) => ({
      id: order.id,
      label: order.order_type === "DELIVERY" ? "Delivery" : "Llevar",
      number: order.order_number,
      total: order.totals.total_amount,
    })),
  ]

  if (options.length === 0) {
    return (
      <EmptyPanel
        icon={ClipboardList}
        title="No hay comandas abiertas"
        description="Abra una mesa desde Salones y Mesas o cree un pedido para llevar para empezar a comandar."
      />
    )
  }

  return (
    <section aria-label="Comandas abiertas" className="rounded-md border">
      <h2 className="border-b px-4 py-2.5 text-sm font-medium">Elija la comanda a trabajar</h2>
      <div className="grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-px bg-border">
        {options.map((option) => (
          <button key={option.id} type="button" onClick={() => onPick(option.id)} className="flex flex-col gap-1 bg-background p-3 text-left hover:bg-accent">
            <span className="font-mono text-base font-semibold tabular-nums">{option.label}</span>
            <span className="font-mono text-xs text-muted-foreground tabular-nums">{option.number}</span>
            <Amount value={option.total} className="text-sm" />
          </button>
        ))}
      </div>
    </section>
  )
}
