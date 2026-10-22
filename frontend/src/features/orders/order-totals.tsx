import { Amount } from "@/components/shared/amount"
import { cn } from "@/lib/utils"
import type { Order } from "@/types/operations"

function Row({ label, value, strong = false }: { readonly label: string; readonly value: string; readonly strong?: boolean }) {
  return (
    <div className={cn("flex items-center justify-between gap-4 py-0.5 text-sm", strong && "text-base font-semibold")}>
      <dt className={cn(!strong && "text-muted-foreground")}>{label}</dt>
      <dd>
        <Amount value={value} />
      </dd>
    </div>
  )
}

/** Totales calculados por el backend (BCMath): la UI nunca recalcula el IGV. */
export function OrderTotals({ order, className }: { readonly order: Order; readonly className?: string }) {
  const { totals } = order

  return (
    <dl className={cn("flex flex-col", className)}>
      <Row label="Consumo" value={totals.subtotal} />
      {totals.packaging_total === "0.00" ? null : <Row label="Empaque para llevar" value={totals.packaging_total} />}
      <Row label="IGV incluido" value={totals.tax_amount} />
      {totals.service_charge === "0.00" ? null : <Row label="Recargo al consumo" value={totals.service_charge} />}
      <Row label="Total" value={totals.total_amount} strong />
      {totals.paid_amount === "0.00" ? null : (
        <>
          <Row label="Pagado" value={totals.paid_amount} />
          <Row label="Saldo pendiente" value={totals.balance} strong />
        </>
      )}
    </dl>
  )
}
