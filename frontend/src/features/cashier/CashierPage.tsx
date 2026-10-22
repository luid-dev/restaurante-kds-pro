import { useQuery } from "@tanstack/react-query"
import { Receipt } from "lucide-react"

import { operationQueries } from "@/api/operations"
import { findNavigationItem } from "@/components/layout/navigation"
import { Amount } from "@/components/shared/amount"
import { EmptyPanel } from "@/components/shared/empty-panel"
import { ModuleHeader } from "@/components/shared/module-header"
import { StatusDot } from "@/components/shared/status-dot"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { CashSessionPanel } from "@/features/cashier/cash-session-panel"
import { CashierCheckoutModal } from "@/features/cashier/CashierCheckoutModal"
import { useOrderFocusStore } from "@/stores/order-focus-store"
import type { OrderStatus } from "@/types/operations"

interface ChargeableRow {
  readonly orderId: string
  readonly location: string
  readonly orderNumber: string
  readonly status: OrderStatus
  readonly waiter: string | null
  readonly total: string
}

/** Caja: turno abierto y cuentas pendientes de cobro (mesas y mostrador). */
export default function CashierPage() {
  const item = findNavigationItem("cashier")
  const cash = useQuery(operationQueries.cash())
  const plan = useQuery(operationQueries.floorPlan())
  const focusedOrderId = useOrderFocusStore((state) => state.cashierOrderId)
  const focusOrder = useOrderFocusStore((state) => state.focusCashierOrder)

  const rows: ChargeableRow[] = [
    ...(plan.data?.zones.flatMap((zone) => zone.tables) ?? []).flatMap((table) =>
      table.order === null
        ? []
        : [{ orderId: table.order.id, location: table.code, orderNumber: table.order.order_number, status: table.order.status, waiter: table.order.waiter_name, total: table.order.total_amount }],
    ),
    ...(plan.data?.counter_orders ?? []).map((order) => ({
      orderId: order.id,
      location: order.order_type === "DELIVERY" ? "Delivery" : "Llevar",
      orderNumber: order.order_number,
      status: order.status,
      waiter: order.waiter?.name ?? null,
      total: order.totals.balance,
    })),
  ]
  const hasSession = cash.data?.session !== null && cash.data?.session !== undefined

  return (
    <>
      <ModuleHeader item={item} />
      <div className="flex flex-col gap-4 p-4 md:p-6">
        {cash.isPending ? <Skeleton className="h-12 w-full" /> : cash.isError ? <p className="text-sm text-status-delayed">{cash.error.message}</p> : <CashSessionPanel context={cash.data} />}

        {plan.isPending ? (
          <Skeleton className="h-48 w-full" />
        ) : rows.length === 0 ? (
          <EmptyPanel icon={Receipt} title="Sin cuentas por cobrar" description="Las mesas ocupadas y los pedidos para llevar aparecerán aquí listos para el cobro." />
        ) : (
          <section aria-label="Cuentas por cobrar" className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Mesa</TableHead>
                  <TableHead>Comanda</TableHead>
                  <TableHead>Estado</TableHead>
                  <TableHead>Mozo</TableHead>
                  <TableHead className="text-right">Total</TableHead>
                  <TableHead className="w-28" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((row) => (
                  <TableRow key={row.orderId}>
                    <TableCell className="font-mono font-semibold tabular-nums">{row.location}</TableCell>
                    <TableCell className="font-mono tabular-nums">{row.orderNumber}</TableCell>
                    <TableCell>
                      <span className="flex items-center gap-1.5 text-sm">
                        <StatusDot tone={row.status === "PRE_BILLED" ? "warning" : "success"} />
                        {row.status === "PRE_BILLED" ? "En pre-cuenta" : "Abierta"}
                      </span>
                    </TableCell>
                    <TableCell className="text-muted-foreground">{row.waiter ?? "-"}</TableCell>
                    <TableCell className="text-right">
                      <Amount value={row.total} />
                    </TableCell>
                    <TableCell className="text-right">
                      <Button size="sm" disabled={!hasSession || row.total === "0.00"} onClick={() => focusOrder(row.orderId)}>
                        Cobrar
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </section>
        )}
      </div>

      {focusedOrderId === null || cash.data === undefined || !hasSession ? null : (
        <CashierCheckoutModal key={focusedOrderId} orderId={focusedOrderId} context={cash.data} onClose={() => focusOrder(null)} />
      )}
    </>
  )
}
